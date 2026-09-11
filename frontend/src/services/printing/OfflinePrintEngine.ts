import { LocalPrintJobRepository } from "../../db/repositories/LocalPrintJobRepository";
import { MockPrinterAdapter, type PrinterAdapter } from "./adapters/MockPrinterAdapter";

/**
 * OfflinePrintEngine: procesa print jobs desde SQLite local.
 * 
 * ARQUITECTURA HÍBRIDA:
 * - Este motor SIEMPRE está activo (incluso en modo offline)
 * - Complementa al OnlinePrintEngine (que hace polling del backend)
 * - Los jobs se crean localmente al momento del evento (cobro, comanda)
 * - Se imprimen automáticamente cuando el engine detecta pendientes
 * 
 * FLUJO:
 * 1. Usuario cobra offline → offlinePaymentService crea local_print_job
 * 2. OfflinePrintEngine detecta job pendiente (polling cada 3s)
 * 3. markAsPrinting → imprime → markAsCompleted/markAsFailed
 * 4. Si falla: retry automático hasta max_attempts
 * 
 * RECUPERACIÓN:
 * - Jobs en estado "printing" por > 2min se consideran abandonados
 * - Se resetean a "pending" para reintentar
 * - Esto protege contra crashes/cortes de energía durante impresión
 */
export class OfflinePrintEngine {
  private isRunning = false;
  private pollIntervalMs: number;
  private adapter: PrinterAdapter;
  private intervalId?: ReturnType<typeof setInterval>;
  private processing = false;

  constructor(options: {
    pollIntervalMs?: number;
    adapter?: PrinterAdapter;
  } = {}) {
    this.pollIntervalMs = options.pollIntervalMs ?? 3000; // 3s por defecto
    this.adapter = options.adapter ?? new MockPrinterAdapter();
  }

  /**
   * Inicia el polling automático.
   */
  start(): void {
    if (this.isRunning) {
      console.warn("[OfflinePrintEngine] Ya está corriendo");
      return;
    }

    this.isRunning = true;
    console.log(`[OfflinePrintEngine] 🚀 Iniciado (poll: ${this.pollIntervalMs}ms)`);

    // Primera ejecución inmediata
    this.processPendingJobs().catch(err => {
      console.error("[OfflinePrintEngine] Error en primera ejecución:", err);
    });

    // Polling periódico
    this.intervalId = setInterval(() => {
      this.processPendingJobs().catch(err => {
        console.error("[OfflinePrintEngine] Error en polling:", err);
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
    console.log("[OfflinePrintEngine] ⏹️  Detenido");
  }

  /**
   * Procesa todos los jobs pendientes (una iteración del polling).
   * Usa flag 'processing' para evitar overlaps si un job tarda mucho.
   */
  async processPendingJobs(): Promise<void> {
    if (this.processing) {
      return; // Ya hay un procesamiento en curso
    }

    this.processing = true;
    try {
      const pendingJobs = await LocalPrintJobRepository.getPending(20);

      if (pendingJobs.length === 0) {
        return; // Nada que hacer
      }

      console.log(`[OfflinePrintEngine] 📋 ${pendingJobs.length} jobs locales pendientes`);

      // Procesar secuencialmente para evitar race conditions
      for (const job of pendingJobs) {
        await this.processJob(job.local_uuid);
      }
    } catch (error: any) {
      console.error("[OfflinePrintEngine] Error procesando jobs:", error.message);
    } finally {
      this.processing = false;
    }
  }

  /**
   * Procesa un job individual: claim → print → complete/fail
   */
  private async processJob(localUuid: string): Promise<void> {
    try {
      const job = await LocalPrintJobRepository.findByLocalUuid(localUuid);
      if (!job) {
        console.warn(`[OfflinePrintEngine] Job ${localUuid} no encontrado`);
        return;
      }

      // 1. Marcar como "printing" (claim)
      console.log(`[OfflinePrintEngine] 🔒 Claiming job ${localUuid} (${job.job_type})...`);
      await LocalPrintJobRepository.markAsPrinting(localUuid);

      // 2. Verificar que tenemos bytes ESC/POS
      if (!job.escpos_base64) {
        // Error crítico: sin bytes no tiene sentido reintentar
        const errorMsg = "Job no tiene bytes ESC/POS (escpos_base64 vacío)";
        console.error(`[OfflinePrintEngine] ❌ Error crítico en job ${localUuid}:`, errorMsg);
        await LocalPrintJobRepository.markAsPermanentlyFailed(localUuid, errorMsg);
        return;
      }

      // 3. Decodificar base64 a Uint8Array
      const binaryString = atob(job.escpos_base64);
      const bytes = new Uint8Array(binaryString.length);
      for (let i = 0; i < binaryString.length; i++) {
        bytes[i] = binaryString.charCodeAt(i);
      }

      // 4. Imprimir localmente
      console.log(`[OfflinePrintEngine] 🖨️  Imprimiendo ${bytes.length} bytes a ${job.printer_name || 'mock'}...`);
      
      // MockPrinterAdapter espera PrinterConnection, pero para jobs locales
      // usamos una conexión genérica (el adapter es mock por ahora)
      await this.adapter.print(bytes, { type: "usb" });

      // 5. Marcar como completado
      await LocalPrintJobRepository.markAsCompleted(localUuid);
      console.log(`[OfflinePrintEngine] ✅ Job ${localUuid} completado`);
    } catch (error: any) {
      console.error(`[OfflinePrintEngine] ❌ Error en job ${localUuid}:`, error.message);
      
      // Marcar como fallido (con retry automático si no alcanzó max_attempts)
      try {
        await LocalPrintJobRepository.markAsFailed(
          localUuid,
          error.message || "Error desconocido"
        );
      } catch (failError: any) {
        console.warn("[OfflinePrintEngine] No se pudo marcar como failed:", failError.message);
      }
    }
  }
}

/**
 * Instancia singleton del OfflinePrintEngine.
 */
export const offlinePrintEngine = new OfflinePrintEngine();
