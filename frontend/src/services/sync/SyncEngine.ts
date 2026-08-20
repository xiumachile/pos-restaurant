import { SyncQueueRepository, type SyncQueueItem } from "../../db/repositories/SyncQueueRepository";
import { OrderRepository } from "../../db/repositories/OrderRepository";
import { PaymentRepository } from "../../db/repositories/PaymentRepository";
import { syncApi } from "../syncApi";
import { pullEngine } from "./PullEngine";
import { useSyncStore } from "../../store/useSyncStore";

/**
 * SyncEngine: Worker que procesa la cola sync_queue local
 * y sincroniza con el servidor mediante endpoints REST.
 *
 * Flujo:
 * 1. Lee eventos pendientes de sync_queue (respetando backoff)
 * 2. Para cada evento, llama al endpoint apropiado según entity_type + action
 * 3. Vincula cloud_id en la entidad local
 * 4. Marca el evento como synced y lo elimina de la cola
 * 5. En caso de error: incrementa attempts y programa backoff exponencial
 */
export class SyncEngine {
  private isProcessing = false;
  private batchSize = 10;

  /**
   * Procesa un lote de eventos de la cola.
   * Retorna resumen de la operación.
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
    const stats = { processed: 0, success: 0, failed: 0, skipped: 0 };

    try {
      const setStatus = useSyncStore.getState().setStatus;
      setStatus("syncing");

      const pendingItems = await SyncQueueRepository.getPending(this.batchSize);

      if (pendingItems.length === 0) {
        setStatus("online");
        return stats;
      }

      console.log(`[SyncEngine] Procesando ${pendingItems.length} eventos pendientes`);

      for (const item of pendingItems) {
        try {
          await this.processItem(item);
          stats.processed++;
          stats.success++;
        } catch (error: any) {
          console.error(`[SyncEngine] Error procesando ${item.entity_type}/${item.action}:`, error);
          await this.handleFailure(item, error?.message || "Unknown error");
          stats.processed++;
          stats.failed++;
        }
      }

      setStatus(stats.failed > 0 && stats.success === 0 ? "error" : "online");
      useSyncStore.getState().setLastSyncAt(new Date().toISOString());
      await useSyncStore.getState().refreshPendingCount();
    } catch (error: any) {
      console.error("[SyncEngine] Error crítico en batch:", error);
      useSyncStore.getState().setStatus("error");
      useSyncStore.getState().setLastError(error?.message || "Error crítico");
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
        // TODO: Implementar cuando tengamos CashSessionRepository
        console.warn("[SyncEngine] cash_session sync no implementado aún");
        break;

      default:
        throw new Error(`Entity type no soportado: ${item.entity_type}`);
    }

    await SyncQueueRepository.markAsSynced(item.id, cloudId || undefined);
    console.log(`[SyncEngine] ✓ ${item.entity_type}/${item.action} synced${cloudId ? ` (cloud: ${cloudId})` : ""}`);
  }

  /**
   * Procesa eventos de tipo order.
   */
  private async processOrder(item: SyncQueueItem, payload: any): Promise<string | null> {
    switch (item.action) {
      case "create": {
        const response = await syncApi.createOrder({
          ...payload,
          idempotency_key: payload.idempotency_key,
        });
        const cloudId = response.uuid || response.id;
        if (cloudId) {
          await OrderRepository.markAsSynced(item.entity_local_uuid, String(cloudId));
        }
        return cloudId ? String(cloudId) : null;
      }

      case "update": {
        // Para updates, necesitamos el cloud_id de la entidad
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

  /**
   * Procesa eventos de tipo payment.
   */
  private async processPayment(item: SyncQueueItem, payload: any): Promise<string | null> {
    if (item.action !== "create") {
      throw new Error(`Acción no soportada para payment: ${item.action}`);
    }

    // Para crear un pago necesitamos el order_id del servidor
    let orderId = payload.order_id;
    if (!orderId && payload.order_local_uuid) {
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
      await PaymentRepository.markAsSynced(item.entity_local_uuid, String(cloudId));
    }
    return cloudId ? String(cloudId) : null;
  }

  /**
   * Procesa cambios de estado de mesa.
   */
  private async processTableStatus(item: SyncQueueItem, payload: any): Promise<void> {
    if (item.action !== "update") {
      throw new Error(`Acción no soportada para table_status: ${item.action}`);
    }
    await syncApi.updateTableStatus(item.entity_local_uuid, payload.status);
  }

  /**
   * Maneja fallos con backoff exponencial.
   */
  private async handleFailure(item: SyncQueueItem, errorMessage: string): Promise<void> {
    try {
      await SyncQueueRepository.markAsFailed(item.id, errorMessage);
    } catch (e) {
      console.error("[SyncEngine] No se pudo registrar el fallo:", e);
    }
  }

  /**
   * Parsea JSON de forma segura.
   */
  private safeParseJson(str: string): any {
    try {
      return JSON.parse(str);
    } catch {
      return null;
    }
  }
  /**
   * Dispara sincronización completa: push (eventos locales) + pull (snapshot cloud).
   */
  async triggerFullSync(): Promise<{
    push: { processed: number; success: number; failed: number };
    pull: {
      categories: number;
      products: number;
      tables: number;
      paymentMethods: number;
    };
  }> {
    console.log("[SyncEngine] Sincronización completa: push + pull");

    // 1. Push eventos locales
    const pushStats = await this.processBatch();

    // 2. Pull snapshot del cloud
    const pullStats = await pullEngine.pullAll();

    return {
      push: pushStats,
      pull: {
        categories: pullStats.categories,
        products: pullStats.products,
        tables: pullStats.tables,
        paymentMethods: pullStats.paymentMethods,
      },
    };
  }
}

// Singleton
export const syncEngine = new SyncEngine();
