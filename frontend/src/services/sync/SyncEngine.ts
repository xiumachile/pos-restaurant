import { SyncQueueRepository } from "../../db/repositories/SyncQueueRepository";
import { syncApi } from "../syncApi";
import { pullEngine } from "./PullEngine";
import { useSyncStore } from "../../store/useSyncStore";
import { useToastStore } from "../../store/useToastStore";

/**
 * SyncEngine: Orquesta la sincronización bidireccional entre
 * SQLite local y PostgreSQL cloud.
 *
 * Flujo completo:
 * 1. Push: Sube eventos locales (sync_queue) al cloud
 * 2. Pull: Descarga snapshot del cloud (catálogo, mesas, métodos)
 * 3. Actualiza estado local con resultados
 */
export class SyncEngine {
  private isSyncing = false;

  /**
   * Procesa batch de eventos pendientes en sync_queue.
   * Solo push (sube eventos locales al cloud).
   */
  async processBatch(): Promise<{
    processed: number;
    success: number;
    failed: number;
  }> {
    const store = useSyncStore.getState();
    const pendingItems = await SyncQueueRepository.getPending(50);

    if (pendingItems.length === 0) {
      return { processed: 0, success: 0, failed: 0 };
    }

    store.setStatus("syncing");
    store.setProgress({
      phase: "push-processing",
      message: "Subiendo eventos locales...",
      current: 0,
      total: pendingItems.length,
      percentage: 0,
    });

    let processed = 0;
    let success = 0;
    let failed = 0;

    for (const item of pendingItems) {
      try {
        store.updateProgress({
          current: processed + 1,
          percentage: Math.round(((processed + 1) / pendingItems.length) * 100),
          message: `Subiendo ${item.entity_type} (${processed + 1}/${pendingItems.length})...`,
        });

        await this.processQueueItem(item);
        success++;
      } catch (error: any) {
        console.error(`[SyncEngine] Error procesando ${item.id}:`, error);
        await this.handleSyncError(item, error);
        failed++;
      }
      processed++;
    }

    store.updateProgress({
      phase: "push-completing",
      message: "Finalizando subida...",
      percentage: 100,
    });

    await store.refreshPendingCount();
    store.setStatus(failed > 0 ? "error" : "online");
    store.setProgress(null);

    return { processed, success, failed };
  }

  /**
   * Procesa un item individual de sync_queue.
   */
  private async processQueueItem(item: any): Promise<void> {
    await SyncQueueRepository.markAsSyncing(item.id);

    let response: any;
    const payload = JSON.parse(item.payload);

    switch (item.entity_type) {
      case "order":
        if (item.action === "create") {
          response = await syncApi.createOrder(payload);
        } else if (item.action === "update") {
          response = await syncApi.updateOrder(item.entity_uuid, payload);
        }
        break;

      case "payment":
        response = await syncApi.createPayment(payload);
        break;

      case "table_status":
        response = await syncApi.updateTableStatus(item.entity_uuid, payload.status);
        break;

      default:
        throw new Error(`Tipo de entidad no soportado: ${item.entity_type}`);
    }

    // Marcar como sincronizado
    const cloudId = response?.uuid || response?.id;
    await SyncQueueRepository.markAsSynced(item.id, cloudId);
  }

  /**
   * Maneja errores de sincronización con backoff exponencial.
   */
  private async handleSyncError(item: any, error: any): Promise<void> {
    const maxAttempts = 5;
    const attempts = (item.attempts || 0) + 1;

    if (attempts >= maxAttempts) {
      await SyncQueueRepository.markAsFailed(
        item.id,
        `Máximo de intentos alcanzado: ${error.message}`
      );
    } else {
      // Backoff exponencial: 15s, 30s, 60s, 120s, 240s
      const backoffSeconds = Math.pow(2, attempts - 1) * 15;
      const nextRetryAt = new Date(Date.now() + backoffSeconds * 1000).toISOString();
      
      await SyncQueueRepository.markAsFailed(
        item.id,
        `${error.message} (intento ${attempts}/${maxAttempts})`
      );
      
      // Actualizar next_retry_at
      const { localDb } = await import("../../db/localDb");
      await localDb.execute(
        "UPDATE sync_queue SET next_retry_at = ? WHERE id = ?",
        [nextRetryAt, item.id]
      );
    }
  }

  /**
   * Dispara sincronización completa: push + pull.
   * Incluye notificaciones toast de progreso.
   */
  async triggerFullSync(): Promise<void> {
    if (this.isSyncing) {
      console.log("[SyncEngine] Ya hay una sincronización en progreso");
      return;
    }

    this.isSyncing = true;
    const store = useSyncStore.getState();
    const toastStore = useToastStore.getState();

    try {
      // Fase 1: Push
      toastStore.info("Iniciando sincronización...");
      const pushStats = await this.processBatch();

      if (pushStats.failed > 0) {
        toastStore.warning(
          `Push completado con errores: ${pushStats.success} exitosos, ${pushStats.failed} fallidos`
        );
      } else if (pushStats.success > 0) {
        toastStore.success(`Push completado: ${pushStats.success} eventos subidos`);
      }

      // Fase 2: Pull
      store.setProgress({
        phase: "pull-downloading",
        message: "Descargando catálogo...",
        current: 0,
        total: 100,
        percentage: 0,
      });

      toastStore.info("Descargando datos del servidor...");
      const pullStats = await pullEngine.pullAll();

      store.updateProgress({
        phase: "pull-applying",
        message: "Aplicando cambios locales...",
        percentage: 90,
      });

      store.updateProgress({
        phase: "completed",
        message: "Sincronización completada",
        percentage: 100,
      });

      // Notificación final
      const totalItems = 
        pullStats.categories + 
        pullStats.products + 
        pullStats.tables + 
        pullStats.paymentMethods;

      toastStore.success(
        `Sincronización completada: ${totalItems} elementos actualizados`
      );

      store.setLastSyncAt(new Date().toISOString());
      store.setStatus("online");

      // Limpiar progreso después de 2 segundos
      setTimeout(() => {
        store.setProgress(null);
      }, 2000);

    } catch (error: any) {
      console.error("[SyncEngine] Error en triggerFullSync:", error);
      store.setStatus("error");
      store.setLastError(error.message);
      toastStore.error(`Error de sincronización: ${error.message}`);
      store.setProgress(null);
    } finally {
      this.isSyncing = false;
    }
  }
}

export const syncEngine = new SyncEngine();
