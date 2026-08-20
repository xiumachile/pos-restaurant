import { SyncQueueRepository, type SyncQueueItem } from "../../db/repositories/SyncQueueRepository";
import { syncApi } from "../syncApi";
import { pullEngine } from "./PullEngine";
import { useSyncStore } from "../../store/useSyncStore";
import { useToastStore } from "../../store/useToastStore";

/**
 * SyncEngine: Orquesta la sincronización bidireccional entre
 * SQLite local y PostgreSQL cloud.
 */
export class SyncEngine {
  private isProcessing = false;

  /**
   * Procesa batch de eventos pendientes en sync_queue (solo push).
   * Retorna estadísticas para compatibilidad con tests.
   */
  async processBatch(): Promise<{
    processed: number;
    success: number;
    failed: number;
    skipped: number;
  }> {
    if (this.isProcessing) {
      console.log("[SyncEngine] Ya procesando, saltando batch");
      return { processed: 0, success: 0, failed: 0, skipped: 1 };
    }

    this.isProcessing = true;
    const store = useSyncStore.getState();
    const stats = { processed: 0, success: 0, failed: 0, skipped: 0 };

    try {
      store.setStatus("syncing");
      
      const pendingItems = await SyncQueueRepository.getPending(10);

      if (pendingItems.length === 0) {
        store.setStatus("online");
        this.isProcessing = false;
        return stats;
      }

      store.setProgress({
        phase: "push-processing",
        message: `Subiendo ${pendingItems.length} eventos...`,
        current: 0,
        total: pendingItems.length,
        percentage: 0,
      });

      console.log(`[SyncEngine] Procesando ${pendingItems.length} eventos pendientes`);

      for (let i = 0; i < pendingItems.length; i++) {
        const item = pendingItems[i];
        try {
          store.updateProgress({
            current: i + 1,
            percentage: Math.round(((i + 1) / pendingItems.length) * 100),
            message: `Subiendo ${item.entity_type} (${i + 1}/${pendingItems.length})...`,
          });

          await this.processItem(item);
          stats.processed++;
          stats.success++;
        } catch (error: any) {
          console.error(`[SyncEngine] Error procesando ${item.id}:`, error);
          await this.handleFailure(item, error?.message || "Unknown error");
          stats.processed++;
          stats.failed++;
        }
      }

      store.updateProgress({
        phase: "push-completing",
        message: "Finalizando...",
        percentage: 100,
      });

      store.setStatus(stats.failed > 0 && stats.success === 0 ? "error" : "online");
      store.setProgress(null);
      await store.refreshPendingCount();
    } catch (error: any) {
      console.error("[SyncEngine] Error crítico en batch:", error);
      store.setStatus("error");
      store.setLastError(error?.message || "Error crítico");
      store.setProgress(null);
    } finally {
      this.isProcessing = false;
    }

    return stats;
  }

  /**
   * Procesa un item individual de la cola.
   */
  private async processItem(item: SyncQueueItem): Promise<void> {
    await SyncQueueRepository.markAsSyncing(item.id);

    const payload = this.safeParseJson(item.payload);
    if (!payload) {
      throw new Error("Payload inválido");
    }

    let cloudId: string | null = null;

    switch (item.entity_type) {
      case "order":
        cloudId = await this.processOrder(item, payload);
        break;
      case "payment":
        cloudId = await this.processPayment(item, payload);
        break;
      case "table_status":
        await this.processTableStatus(item, payload);
        break;
      case "cash_session":
        console.warn("[SyncEngine] cash_session sync no implementado aún");
        break;
      default:
        throw new Error(`Entity type no soportado: ${item.entity_type}`);
    }

    await SyncQueueRepository.markAsSynced(item.id, cloudId || undefined);
    console.log(`[SyncEngine] ✓ ${item.entity_type}/${item.action} synced${cloudId ? ` (cloud: ${cloudId})` : ""}`);
  }

  private async processOrder(item: SyncQueueItem, payload: any): Promise<string | null> {
    switch (item.action) {
      case "create": {
        const response = await syncApi.createOrder({
          ...payload,
          idempotency_key: payload.idempotency_key,
        });
        const cloudId = response.uuid || response.id;
        if (cloudId) {
          const { OrderRepository } = await import("../../db/repositories/OrderRepository");
          await OrderRepository.markAsSynced(item.entity_local_uuid, String(cloudId));
        }
        return cloudId ? String(cloudId) : null;
      }
      case "update": {
        const { OrderRepository } = await import("../../db/repositories/OrderRepository");
        const order = await OrderRepository.findByLocalUuid(item.entity_local_uuid);
        if (!order?.cloud_id) {
          throw new Error("Orden sin cloud_id, no se puede actualizar");
        }
        await syncApi.updateOrder(order.cloud_id, payload);
        return order.cloud_id;
      }
      case "delete": {
        const cloudId = payload.cloud_id || item.entity_cloud_id;
        if (!cloudId) {
          throw new Error("No se puede eliminar sin cloud_id");
        }
        await syncApi.deleteOrder(cloudId);
        return cloudId;
      }
      default:
        throw new Error(`Acción no soportada para order: ${item.action}`);
    }
  }

  private async processPayment(item: SyncQueueItem, payload: any): Promise<string | null> {
    if (item.action !== "create") {
      throw new Error(`Acción no soportada para payment: ${item.action}`);
    }

    let orderId = payload.order_id;
    if (!orderId && payload.order_local_uuid) {
      const { OrderRepository } = await import("../../db/repositories/OrderRepository");
      const order = await OrderRepository.findByLocalUuid(payload.order_local_uuid);
      if (!order?.cloud_id) {
        throw new Error("Order padre sin cloud_id, no se puede crear payment");
      }
      orderId = order.cloud_id;
    }

    const response = await syncApi.createPayment({
      ...payload,
      order_id: orderId,
      idempotency_key: payload.idempotency_key,
    });

    const cloudId = response.uuid || response.id;
    if (cloudId) {
      const { PaymentRepository } = await import("../../db/repositories/PaymentRepository");
      await PaymentRepository.markAsSynced(item.entity_local_uuid, String(cloudId));
    }
    return cloudId ? String(cloudId) : null;
  }

  private async processTableStatus(item: SyncQueueItem, payload: any): Promise<void> {
    if (item.action !== "update") {
      throw new Error(`Acción no soportada para table_status: ${item.action}`);
    }
    await syncApi.updateTableStatus(item.entity_local_uuid, payload.status);
  }

  private async handleFailure(item: SyncQueueItem, errorMessage: string): Promise<void> {
    try {
      await SyncQueueRepository.markAsFailed(item.id, errorMessage);
    } catch (e) {
      console.error("[SyncEngine] No se pudo registrar el fallo:", e);
    }
  }

  private safeParseJson(str: string): any {
    try {
      return JSON.parse(str);
    } catch {
      return null;
    }
  }

  /**
   * Dispara sincronización completa: push + pull.
   * Incluye notificaciones toast de progreso.
   */
  async triggerFullSync(): Promise<void> {
    if (this.isProcessing) {
      console.log("[SyncEngine] Ya hay una sincronización en progreso");
      return;
    }

    const store = useSyncStore.getState();
    const toastStore = useToastStore.getState();

    try {
      // Fase 1: Push
      toastStore.addToast("info", "Subiendo cambios locales...");
      const pushStats = await this.processBatch();

      if (pushStats.failed > 0) {
        toastStore.addToast(
          "warning",
          `Push completado con errores: ${pushStats.success} exitosos, ${pushStats.failed} fallidos`
        );
      } else if (pushStats.success > 0) {
        toastStore.addToast("success", `Push completado: ${pushStats.success} eventos subidos`);
      }

      // Fase 2: Pull
      store.setProgress({
        phase: "pull-downloading",
        message: "Descargando catálogo...",
        current: 0,
        total: 4,
        percentage: 0,
      });

      toastStore.addToast("info", "Descargando datos del servidor...");
      const pullStats = await pullEngine.pullAll();

      if (!pullStats.success) {
        throw new Error(pullStats.error || "Error en pull");
      }

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

      const totalItems =
        pullStats.categories +
        pullStats.products +
        pullStats.tables +
        pullStats.paymentMethods;

      toastStore.addToast(
        "success",
        `Sincronización completada: ${totalItems} elementos actualizados`
      );

      store.setLastSyncAt(new Date().toISOString());
      store.setStatus("online");

      setTimeout(() => {
        store.setProgress(null);
      }, 2000);
    } catch (error: any) {
      console.error("[SyncEngine] Error en triggerFullSync:", error);
      store.setStatus("error");
      store.setLastError(error.message);
      toastStore.addToast("error", `Error de sincronización: ${error.message}`);
      store.setProgress(null);
    }
  }
}

export const syncEngine = new SyncEngine();
