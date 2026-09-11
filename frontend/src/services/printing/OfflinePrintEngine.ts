import { LocalPrintJobRepository } from "../../db/repositories/LocalPrintJobRepository";
import { PrinterConfigRepository, type PrinterType } from "../../db/repositories/PrinterConfigRepository";
import { MockPrinterAdapter, type PrinterAdapter } from "./adapters/MockPrinterAdapter";
import { TauriNetworkPrinterAdapter } from "./adapters/TauriNetworkPrinterAdapter";

/**
 * OfflinePrintEngine: procesa print jobs desde SQLite local.
 * 
 * ARQUITECTURA HÍBRIDA:
 * - Este motor SIEMPRE está activo (incluso en modo offline)
 * - Los jobs se crean localmente al momento del evento (cobro, comanda)
 * - Se imprimen automáticamente cuando el engine detecta pendientes
 * 
 * SELECCIÓN DE ADAPTER (NUEVO en Commit 6d):
 * 
 * Para cada job, el engine consulta PrinterConfigRepository.getDefault()
 * usando el printer_type del job ("receipt", "kitchen", "bar").
 * 
 * SI hay una impresora configurada para ese tipo:
 *   → Usa TauriNetworkPrinterAdapter con la IP/puerto configurados
 *   → Imprime por TCP al puerto 9100 de la impresora térmica
 * 
 * SI NO hay configuración (o falla):
 *   → Usa MockPrinterAdapter (loguea en consola)
 *   → Útil para desarrollo sin hardware
 * 
 * FLUJO:
 * 1. Usuario cobra offline → offlinePaymentService crea local_print_job
 * 2. OfflinePrintEngine detecta job pendiente (polling cada 3s)
 * 3. Consulta PrinterConfigRepository.getDefault(job.printer_type)
 * 4. Elige adapter (TauriNetwork o Mock)
 * 5. markAsPrinting → imprime → markAsCompleted/markAsFailed
 * 6. Si falla: retry automático hasta max_attempts
 * 
 * RECUPERACIÓN:
 * - Jobs en estado "printing" por > 2min se consideran abandonados
 * - Se resetean a "pending" para reintentar
 */
export class OfflinePrintEngine {
  private isRunning = false;
  private pollIntervalMs: number;
  private fallbackAdapter: PrinterAdapter;
  private networkAdapter: TauriNetworkPrinterAdapter;
  private intervalId?: ReturnType<typeof setInterval>;
  private processing = false;

  constructor(options: {
    pollIntervalMs?: number;
    fallbackAdapter?: PrinterAdapter;
    networkAdapter?: TauriNetworkPrinterAdapter;
  } = {}) {
    this.pollIntervalMs = options.pollIntervalMs ?? 3000;
    this.fallbackAdapter = options.fallbackAdapter ?? new MockPrinterAdapter();
    this.networkAdapter = options.networkAdapter ?? new TauriNetworkPrinterAdapter();
  }

  /**
   * Inicia el polling automático.
   */
  start(): void {
    if (this.isRunning) {
      console.log("[OfflinePrintEngine] ⚠️ Ya está corriendo");
      return;
    }

    this.isRunning = true;
    console.log(`[OfflinePrintEngine] 🟢 Iniciado (polling cada ${this.pollIntervalMs}ms)`);

    // Ejecutar inmediatamente + polling
    this.processJobs();
    this.intervalId = setInterval(() => this.processJobs(), this.pollIntervalMs);
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
    console.log("[OfflinePrintEngine] 🔴 Detenido");
  }

  /**
   * Procesa todos los print jobs pendientes.
   * 
   * MÉTODO PÚBLICO para permitir tests determinísticos (await directo)
   * y para invocación manual desde la UI (botón 'Reprocesar cola').
   */
  async processJobs(): Promise<void> {
    if (this.processing) return;
    this.processing = true;

    try {
      // 1. Recuperar jobs abandonados (printing > 2min)
      await LocalPrintJobRepository.recoverAbandonedPrinting();

      // 2. Obtener jobs pendientes
      const pendingJobs = await LocalPrintJobRepository.getPending();
      if (pendingJobs.length === 0) {
        return;
      }

      console.log(`[OfflinePrintEngine] 📋 ${pendingJobs.length} jobs pendientes`);

      // 3. Procesar cada job
      for (const job of pendingJobs) {
        await this.processSingleJob(job);
      }
    } catch (err: any) {
      console.error("[OfflinePrintEngine] ❌ Error procesando jobs:", err?.message);
    } finally {
      this.processing = false;
    }
  }

  /**
   * Procesa un solo print job.
   * 
   * SELECCIÓN DE ADAPTER:
   * 1. Consulta PrinterConfigRepository.getDefault(job.printer_type)
   * 2. Si hay config → TauriNetworkPrinterAdapter con IP/puerto
   * 3. Si no hay config → MockPrinterAdapter (fallback)
   */
  private async processSingleJob(job: any): Promise<void> {
    const jobId = job.local_uuid;
    const jobType = job.job_type;
    const printerType = job.printer_type || "receipt";

    try {
      // Verificar que tiene bytes para imprimir
      if (!job.escpos_base64) {
        console.warn(`[OfflinePrintEngine] ⚠️ Job ${jobId} sin escpos_base64, marcando como failed permanente`);
        await LocalPrintJobRepository.markAsPermanentlyFailed(jobId, "Sin bytes ESC/POS (escpos_base64 vacío)");
        return;
      }

      // Marcar como printing
      await LocalPrintJobRepository.markAsPrinting(jobId);

      // Decodificar base64 → Uint8Array
      const binaryString = atob(job.escpos_base64);
      const bytes = new Uint8Array(binaryString.length);
      for (let i = 0; i < binaryString.length; i++) {
        bytes[i] = binaryString.charCodeAt(i);
      }

      // Seleccionar adapter + connection basado en configuración
      const { adapter, connection } = await this.resolveAdapter(printerType as PrinterType);

      console.log(
        `[OfflinePrintEngine] 🖨️ Imprimiendo job ${jobId} (${jobType}) ` +
        `→ ${connection.type === "network" ? `${connection.ip}:${connection.port}` : "mock"}`
      );

      // Imprimir
      await adapter.print(bytes, connection);

      // Marcar como completado
      await LocalPrintJobRepository.markAsCompleted(jobId);
      console.log(`[OfflinePrintEngine] ✅ Job ${jobId} completado`);

    } catch (err: any) {
      const errorMsg = err?.message || "Error desconocido";
      console.error(`[OfflinePrintEngine] ❌ Job ${jobId} falló:`, errorMsg);
      await LocalPrintJobRepository.markAsFailed(jobId, errorMsg);
    }
  }

  /**
   * Resuelve qué adapter y connection usar para un tipo de impresora.
   * 
   * PRIORIDAD:
   * 1. Si hay config en PrinterConfigRepository → TauriNetworkPrinterAdapter
   * 2. Si no hay config → MockPrinterAdapter (fallback para desarrollo)
   */
  private async resolveAdapter(printerType: PrinterType): Promise<{
    adapter: PrinterAdapter;
    connection: any;
  }> {
    try {
      const config = await PrinterConfigRepository.getDefault(printerType);

      if (config && config.ip) {
        return {
          adapter: this.networkAdapter,
          connection: {
            type: "tcp",
            host: config.ip,
            port: config.port,
          },
        };
      }
    } catch (err: any) {
      // Si falla leer config (ej: tabla no existe aún), usar fallback
      console.warn(`[OfflinePrintEngine] ⚠️ No se pudo leer config de ${printerType}:`, err?.message);
    }

    // Fallback: MockPrinterAdapter
    // Nota: PrinterConnection solo permite 'tcp'|'usb'|'bluetooth'|'serial'
    // Para mock usamos 'serial' como valor dummy (el adapter lo ignora)
    return {
      adapter: this.fallbackAdapter,
      connection: { type: "serial" as const },
    };
  }

  /**
   * Indica si el engine está activo.
   */
  get running(): boolean {
    return this.isRunning;
  }
}

/**
 * Singleton del engine offline.
 * 
 * NOTA: Usa fallback adapter (Mock) por defecto.
 * Cuando hay impresoras configuradas en PrinterConfigRepository,
 * automáticamente usa TauriNetworkPrinterAdapter para cada job.
 */
export const offlinePrintEngine = new OfflinePrintEngine();
