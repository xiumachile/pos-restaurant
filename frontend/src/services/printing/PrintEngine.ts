import { printJobsApi, type PrintJob } from '../printJobsApi';
import { MockPrinterAdapter, type PrinterAdapter } from './adapters/MockPrinterAdapter';

/**
 * PrintEngine: orquestador de impresión híbrida.
 * 
 * Hace polling periódico de PrintJobs pendientes en el backend,
 * los reclama, los imprime localmente y marca como completados.
 * 
 * Arquitectura híbrida (Opción C del documento):
 * - Backend Cloud encola jobs (listeners ya lo hacen)
 * - POS Tauri imprime localmente (este engine)
 * - Sin necesidad de Local Branch Agent
 */
export class PrintEngine {
  private isRunning = false;
  private pollIntervalMs: number;
  private clientId: string;
  private adapter: PrinterAdapter;
  private intervalId?: ReturnType<typeof setInterval>;

  constructor(options: {
    pollIntervalMs?: number;
    clientId?: string;
    adapter?: PrinterAdapter;
  } = {}) {
    this.pollIntervalMs = options.pollIntervalMs ?? 5000; // 5s por defecto
    this.clientId = options.clientId ?? `tauri-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
    this.adapter = options.adapter ?? new MockPrinterAdapter();
  }

  /**
   * Inicia el polling automático.
   */
  start(): void {
    if (this.isRunning) {
      console.warn('[PrintEngine] Ya está corriendo');
      return;
    }

    this.isRunning = true;
    console.log(`[PrintEngine] 🚀 Iniciado (clientId: ${this.clientId}, poll: ${this.pollIntervalMs}ms)`);

    // Primera ejecución inmediata
    this.processPendingJobs().catch(err => {
      console.error('[PrintEngine] Error en primera ejecución:', err);
    });

    // Polling periódico
    this.intervalId = setInterval(() => {
      this.processPendingJobs().catch(err => {
        console.error('[PrintEngine] Error en polling:', err);
      });
    }, this.pollIntervalMs);
  }

  /**
   * Detiene el polling.
   */
  stop(): void {
    if (!this.isRunning) return;

    this.isRunning = false;
    if (this.intervalId) {
      clearInterval(this.intervalId);
      this.intervalId = undefined;
    }
    console.log('[PrintEngine] ⏹️  Detenido');
  }

  /**
   * Procesa todos los jobs pendientes (una iteración del polling).
   */
  async processPendingJobs(): Promise<void> {
    try {
      const pendingJobs = await printJobsApi.listPending(20);

      if (pendingJobs.length === 0) {
        return; // Nada que hacer
      }

      console.log(`[PrintEngine] 📋 ${pendingJobs.length} jobs pendientes`);

      // Procesar secuencialmente para evitar race conditions
      for (const job of pendingJobs) {
        await this.processJob(job);
      }
    } catch (error: any) {
      console.error('[PrintEngine] Error procesando jobs:', error.message);
    }
  }

  /**
   * Procesa un job individual: claim → print → complete/fail
   */
  private async processJob(job: PrintJob): Promise<void> {
    try {
      // 1. Reclamar el job
      console.log(`[PrintEngine] 🔒 Claiming job ${job.uuid} (${job.job_type})...`);
      await printJobsApi.claim(job.uuid, this.clientId);

      // 2. Obtener bytes ESC/POS
      const jobWithBytes = await printJobsApi.getWithBytes(job.uuid);
      
      if (!jobWithBytes.escpos_base64) {
        throw new Error('Job no tiene bytes ESC/POS');
      }

      // 3. Decodificar base64 a Uint8Array
      const binaryString = atob(jobWithBytes.escpos_base64);
      const bytes = new Uint8Array(binaryString.length);
      for (let i = 0; i < binaryString.length; i++) {
        bytes[i] = binaryString.charCodeAt(i);
      }

      // 4. Imprimir localmente
      console.log(`[PrintEngine] 🖨️  Imprimiendo ${bytes.length} bytes a ${jobWithBytes.printer_name}...`);
      await this.adapter.print(bytes, jobWithBytes.printer_connection);

      // 5. Marcar como completado
      await printJobsApi.complete(job.uuid);
      console.log(`[PrintEngine] ✅ Job ${job.uuid} completado`);
    } catch (error: any) {
      console.error(`[PrintEngine] ❌ Error en job ${job.uuid}:`, error.message);
      
      // Intentar reportar el fallo (pero no fallar si el reporte falla)
      try {
        await printJobsApi.fail(job.uuid, error.message || 'Error desconocido');
      } catch (reportError: any) {
        console.warn('[PrintEngine] No se pudo reportar fallo:', reportError.message);
      }
    }
  }
}

/**
 * Instancia singleton del PrintEngine.
 */
export const printEngine = new PrintEngine();
