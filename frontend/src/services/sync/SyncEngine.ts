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
          
          // Logging detallado para errores 422 (validación)
          if (error?.response?.status === 422) {
            console.error("[SyncEngine] ❌ Error de validación (422):");
            console.error("[SyncEngine] Response data:", error.response.data);
            console.error("[SyncEngine] Request payload:", item.payload);
          }
          
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
        // Leer items desde local_order_items (se agregan después del create)
        const { OrderRepository } = await import("../../db/repositories/OrderRepository");
        const orderItems = await OrderRepository.findItemsByOrderUuid(item.entity_local_uuid);

        // Construir payload con mapeo correcto de campos
        // Backend espera: type (no order_type), table_uuid (no table_id)
        // NO enviar status aquí: backend crea en DRAFT por defecto (necesario para agregar items)
        const orderPayload = {
          type: payload.order_type || payload.type,
          table_uuid: payload.table_id || payload.table_uuid,
          notes: payload.notes || null,
        };

        console.log(`[SyncEngine] 📤 Creando orden (items: ${orderItems.length})...`);
        console.log("[SyncEngine] Payload:", JSON.stringify(orderPayload, null, 2));

        const response = await syncApi.createOrder({
          ...orderPayload,
          idempotency_key: payload.idempotency_key,
        });

        const cloudId = response.uuid || response.id;
        if (!cloudId) {
          throw new Error("Backend no retornó uuid/id de la orden");
        }

        console.log(`[SyncEngine] ✅ Orden creada en cloud: ${cloudId}`);
        await OrderRepository.markAsSynced(item.entity_local_uuid, String(cloudId));

        // Agregar items uno por uno (requiere estado DRAFT)
        let itemsAdded = 0;
        if (orderItems.length > 0) {
          console.log(`[SyncEngine] 📤 Agregando ${orderItems.length} items...`);

          for (const orderItem of orderItems) {
            try {
              await syncApi.addOrderItem(String(cloudId), {
                product_uuid: orderItem.product_id, // Backend espera product_uuid
                quantity: orderItem.quantity,
                notes: orderItem.notes || null,
                idempotency_key: orderItem.local_uuid, // clave estable: dedup en reintentos
              });
              console.log(`[SyncEngine] ✅ Item agregado: ${orderItem.product_name}`);
              itemsAdded++;
            } catch (itemError: any) {
              console.error(`[SyncEngine] ❌ Error agregando item ${orderItem.product_name}:`);
              if (itemError?.response?.data) {
                console.error("[SyncEngine] Item error:", JSON.stringify(itemError.response.data, null, 2));
              }
              // Fallar completamente si un item no se puede agregar
              throw new Error(`No se pudo agregar item ${orderItem.product_name}: ${itemError?.response?.data?.message || itemError?.message}`);
            }
          }
        }

        // IMPORTANTE: Confirmar pedido vía transición de dominio DESPUÉS de agregar items
        // Usa POST /orders/{uuid}/confirm que dispara OrderConfirmed event
        // Esto ejecuta los listeners: OccupyTableOnOrderConfirm (mesa),
        // ticket de cocina, auditoría, etc.
        // Antes usaba updateOrder({status:'confirmed'}) que solo pisaba la
        // columna sin pasar por OrderStateMachine.
        // CRÍTICO: Confirmar pedido vía transición de dominio
        // POST /orders/{uuid}/confirm dispara OrderConfirmed event que ejecuta:
        // - OccupyTableOnOrderConfirm (mesa pasa a occupied)
        // - BroadcastOrderEvents (ticket de cocina)
        // - Auditoría, stock, transición DRAFT → CONFIRMED
        //
        // Si falla por red/timeout: relanzar error → evento queda pendiente → reintento
        // Si falla porque YA estaba confirmado (idempotencia): tratar como éxito
        if (itemsAdded > 0) {
          try {
            await syncApi.confirmOrder(String(cloudId));
            console.log(`[SyncEngine] ✅ Pedido confirmado vía transición de dominio (${itemsAdded} items)`);
          } catch (confirmError: any) {
            const status = confirmError?.response?.status;
            const errorData = confirmError?.response?.data;
            const errorCode = errorData?.error || errorData?.code || errorData?.error_code;
            const errorMessage = errorData?.message || confirmError?.message || '';

            // Idempotencia: si el pedido YA estaba confirmado (ej: reintento previo),
            // el backend retorna 422 con InvalidOrderTransitionException
            // Tratamos como éxito para no bloquear el flujo
            const isAlreadyConfirmed =
              status === 422 &&
              (errorCode === 'invalid_transition' ||
               errorCode === 'invalid_order_transition' ||
               /already.*confirm/i.test(errorMessage) ||
               /cannot.*transition/i.test(errorMessage) ||
               /transición.*inválida/i.test(errorMessage));

            if (isAlreadyConfirmed) {
              console.log(`[SyncEngine] ✅ Pedido ya estaba confirmado (idempotencia)`);
            } else {
              // Falla real (red, timeout, error de servidor, validación, etc.)
              // Relanzar para que processItem() NO marque como synced
              // y el SyncWorker reintente en el próximo ciclo
              console.error(`[SyncEngine] ❌ CRÍTICO: No se pudo confirmar pedido (status: ${status})`);
              console.error('[SyncEngine] El evento quedará pendiente para reintento automático');
              console.error('[SyncEngine] Error:', errorData || confirmError?.message);
              throw new Error(
                `Fallo crítico al confirmar pedido ${cloudId}: ${errorMessage || 'Error desconocido'}`
              );
            }
          }
        }

        return String(cloudId);
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

    // Resolver order_uuid desde order_local_uuid (el backend espera order_uuid, no order_id)
    let orderUuid = payload.order_uuid;
    if (!orderUuid && payload.order_local_uuid) {
      const { OrderRepository } = await import("../../db/repositories/OrderRepository");
      const order = await OrderRepository.findByLocalUuid(payload.order_local_uuid);
      if (!order?.cloud_id) {
        throw new Error("Order padre sin cloud_id, no se puede crear payment");
      }
      orderUuid = order.cloud_id;
    }

    if (!orderUuid) {
      throw new Error("No se pudo resolver order_uuid para el payment");
    }

    if (!payload.payment_method_uuid) {
      throw new Error("payment_method_uuid es requerido pero no está en el payload");
    }

    // Construir payload con el formato que espera el backend (StorePaymentRequest)
    const paymentPayload = {
      order_uuid: orderUuid,
      payment_method_uuid: payload.payment_method_uuid,
      amount: payload.amount,
      tip_amount: payload.tip_amount || 0,
      reference_code: payload.reference_code || null,
      notes: payload.notes || null,
      idempotency_key: payload.idempotency_key,
    };

    console.log("[SyncEngine] 📤 Creando payment:", JSON.stringify(paymentPayload, null, 2));

    const response = await syncApi.createPayment(paymentPayload);
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

      // Si el evento agotó reintentos, reflejar el fallo en el pedido local
      const updated = await SyncQueueRepository.findById(item.id);
      if (updated?.sync_status === "failed" && item.entity_type === "order") {
        const { OrderRepository } = await import("../../db/repositories/OrderRepository");
        await OrderRepository.markSyncError(item.entity_local_uuid, errorMessage);
      }
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
