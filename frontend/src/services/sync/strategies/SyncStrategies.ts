import { type SyncQueueItem } from "../../../db/repositories/SyncQueueRepository";
import { localDb } from "../../../db/localDb";
import { SyncQueueRepository } from "../../../db/repositories/SyncQueueRepository";
import { syncApi } from "../../syncApi";
import { useToastStore } from "../../../store/useToastStore";
import { queryClient } from "../../../queryClient";


export class SyncStrategies {
  static async processOrder(item: SyncQueueItem, payload: any): Promise<string | null> {
    switch (item.action) {
      case "create": {
        // Leer items desde local_order_items (se agregan después del create)
        const { OrderRepository } = await import("../../../db/repositories/OrderRepository");
        const orderItems = await OrderRepository.findItemsByOrderUuid(item.entity_local_uuid);

        // Construir payload con mapeo correcto de campos
        // Backend espera: type (no order_type), table_uuid (no table_id)
        // NO enviar status aquí: backend crea en DRAFT por defecto (necesario para agregar items)
        const orderPayload = {
          type: payload.order_type || payload.type,
          table_uuid: payload.table_id || payload.table_uuid,
          notes: payload.notes || null,
          // Campos de cliente (solo para delivery, backend los valida)
          customer_id: payload.customer_id || null,
          customer_name: payload.customer_name || null,
          customer_phone: payload.customer_phone || null,
          delivery_address: payload.delivery_address || null,
          delivery_notes: payload.delivery_notes || null,
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
        
        // ⚠️ FASE 2: NO marcamos como synced todavía.
        // La orden solo se considera sincronizada cuando TODA la cadena
        // de dominio está completa (create + items + confirm).
        // El markAsSynced final lo hace SyncQueueRepository.markAsSynced()
        // al final de processItem(), DESPUÉS de que confirmOrder() sea exitoso.

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

          // ⚡ INVALIDACIÓN INMEDIATA después de agregar items
          // Para que los cambios aparezcan instantáneamente incluso antes de confirmar
          try {
            await queryClient.invalidateQueries({ 
              queryKey: ["orders", "active"],
              refetchType: "all"
            });
            console.log("[SyncEngine] ⚡ Queries invalidadas tras agregar items");
          } catch (e) {
            // No crítico
          }
        }

        // IMPORTANTE: Confirmar pedido vía transición de dominio DESPUÉS de agregar items
        // Usa POST /orders/{uuid}/confirm que dispara OrderConfirmed event
        // Esto ejecuta los listeners: OccupyTableOnOrderConfirm (mesa),
        // ticket de cocina, auditoría, etc.
        // Antes usaba updateOrder({status:'confirmed'}) que solo pisaba la
        // columna sin pasar por OrderStateMachine.
        // Confirmar pedido vía transición de dominio
        // POST /orders/{uuid}/confirm dispara OrderConfirmed event que ejecuta:
        // - OccupyTableOnOrderConfirm (mesa pasa a occupied)
        // - BroadcastOrderEvents (ticket de cocina)
        // - Auditoría, stock, transición DRAFT → CONFIRMED
        //
        // IDEMPOTENCIA GARANTIZADA POR BACKEND (ADR-007 + Nivel 1):
        // - Si pedido ya está confirmado → retorna 200 (idempotencia de dominio)
        // - Si falla por cualquier razón → relanzar error → reintento
        if (itemsAdded > 0) {
          try {
            await syncApi.confirmOrder(String(cloudId));
            console.log(`[SyncEngine] ✅ Pedido confirmado vía transición de dominio (${itemsAdded} items)`);

            // ⚡ REFETCH INMEDIATO DE QUERIES
            // refetchQueries SÍ fuerza fetch inmediato (invalidateQueries no lo hace con staleTime > 0)
            // Esto hace que el pedido aparezca instantáneamente en:
            // - Pedidos Activos (useActiveOrders)
            // - Caja (tablesWithBills)
            // - Dashboard de Caja
            try {
              await queryClient.refetchQueries({ 
                queryKey: ["orders", "active"],
                type: "all"
              });
              await queryClient.refetchQueries({ 
                queryKey: ["cashier", "tables-with-bills"],
                type: "all"
              });
              await queryClient.refetchQueries({ 
                queryKey: ["cashier", "dashboard"],
                type: "all"
              });
              console.log("[SyncEngine] ⚡ Queries refrescadas: pedidos aparecerán instantáneamente");
            } catch (refetchError: any) {
              // No crítico: si falla el refetch, el refetchInterval lo hará en 10s
              console.warn("[SyncEngine] ⚠️ No se pudieron refrescar queries:", refetchError?.message);
            }

            // FASE 5: RECONCILIACIÓN INMEDIATA DE MUTACIÓN DE MESA
            // El backend ya disparó OrderConfirmed → OccupyTableOnOrderConfirm,
            // por lo que la mesa en cloud ya está en estado 'occupied'.
            // Podemos eliminar la mutación local inmediatamente sin esperar PullEngine.
            const tableUuid = orderPayload.table_uuid;
            if (tableUuid) {
              try {
                await localDb.execute(
                  "DELETE FROM table_local_mutations WHERE table_uuid = ? AND pending_status = 'occupied'",
                  [tableUuid]
                );
                console.log(`[SyncEngine] ✅ Mutación de mesa ${tableUuid} reconciliada tras sync exitoso`);
              } catch (mutError: any) {
                // No crítico: si falla eliminar la mutación, PullEngine la reconciliará en el próximo pull
                console.warn(`[SyncEngine] ⚠️  No se pudo eliminar mutación de mesa ${tableUuid}:`, mutError?.message);
              }
            }
          } catch (error: any) {
            // FASE 3: Manejo inteligente de HTTP 422
            // 422 puede ser:
            //   (a) Idempotencia: orden ya estaba CONFIRMED → tratar como éxito
            //   (b) Error real: transición inválida (ej: orden cancelada) → relanzar
            //
            // Para resolver la ambigüedad, consultamos el estado real de la orden
            // en el backend y decidimos según su status.
            if (error?.response?.status === 422) {
              console.warn(`[SyncEngine] ⚠️  HTTP 422 al confirmar ${cloudId}, consultando estado real...`);
              try {
                const orderState = await syncApi.getOrder(String(cloudId));
                const status = orderState?.status;
                
                console.log(`[SyncEngine] 🔍 Estado real de orden ${cloudId}: ${status}`);
                
                // Estados que indican que la confirmación YA OCURRIÓ (idempotencia)
                const confirmedStatuses = ['confirmed', 'preparing', 'ready', 'served', 'paid', 'closed'];
                
                if (confirmedStatuses.includes(status)) {
                  console.log(`[SyncEngine] ✓ Orden ya confirmada (idempotencia 422), continuando`);
                  // NO relanzar: considerar como éxito
                } else if (status === 'draft') {
                  // La orden sigue en DRAFT: confirmación realmente falló
                  console.error(`[SyncEngine] ❌ Orden sigue en DRAFT tras 422, reintentando`);
                  throw new Error(`Orden ${cloudId} sigue en DRAFT tras 422, requiere reintento`);
                } else if (status === 'cancelled') {
                  // Orden fue cancelada (posiblemente por otro proceso)
                  console.error(`[SyncEngine] ❌ Orden ${cloudId} fue cancelada, no se puede confirmar`);
                  throw new Error(`Orden ${cloudId} cancelada, confirmación imposible`);
                } else {
                  // Estado inesperado
                  console.error(`[SyncEngine] ❌ Estado inesperado de orden ${cloudId}: ${status}`);
                  throw new Error(`Estado inesperado de orden ${cloudId}: ${status}`);
                }
              } catch (fetchError: any) {
                // Si falla consultar el estado, relanzar el error original
                console.error(`[SyncEngine] ❌ No se pudo consultar estado de orden ${cloudId}:`, fetchError);
                throw new Error(`Fallo al confirmar ${cloudId} y no se pudo verificar estado`);
              }
            } else {
              // Error no-422: relanzar normalmente (timeout, 500, etc.)
              const errorMessage = error?.response?.data?.message || error?.message || 'Error desconocido';
              console.error(`[SyncEngine] ❌ Error al confirmar pedido ${cloudId}: ${errorMessage}`);
              throw new Error(`Fallo al confirmar pedido ${cloudId}: ${errorMessage}`);
            }
          }
        }

        return String(cloudId);
      }
      case "update": {
        const { OrderRepository } = await import("../../../db/repositories/OrderRepository");
        const order = await OrderRepository.findByLocalUuid(item.entity_local_uuid);
        if (!order?.cloud_id) {
          throw new Error("Orden sin cloud_id, no se puede actualizar");
        }

        // ═══════════════════════════════════════════════════════════
        // FIX CRÍTICO: add_item debe ir a POST /orders/{uuid}/items
        // UpdateOrderRequest solo acepta status/table_uuid/notes/guest_count
        // ═══════════════════════════════════════════════════════════
        if (payload.action === "add_item" && payload.item) {
          console.log(`[SyncEngine] 📦 Agregando item a orden ${order.cloud_id}`);
          await syncApi.addOrderItem(order.cloud_id, {
            product_uuid: payload.item.product_id || payload.item.product_uuid,
            quantity: payload.item.quantity,
            unit_price: payload.item.unit_price ?? payload.item.price,
            notes: payload.item.notes ?? null,
            idempotency_key: payload.item.local_uuid,
          });
          return order.cloud_id;
        }

        if (payload.action === "remove_item" && payload.item_uuid) {
          console.log(`[SyncEngine] 🗑️ Removiendo item ${payload.item_uuid} de orden ${order.cloud_id}`);
          await syncApi.removeOrderItem(order.cloud_id, payload.item_uuid, item.id);
          return order.cloud_id;
        }

        // Update normal de metadata (status, notes, guest_count)
        // P1-010: Usar item.id como Idempotency-Key estable para reintentos
        // P1-OCC: Incluir version para Optimistic Concurrency Control
        const updatePayload = {
          ...payload,
          version: (order as any).version,
        };
        await syncApi.updateOrder(order.cloud_id, updatePayload, item.id);
        return order.cloud_id;
      }
      case "delete": {
        const cloudId = payload.cloud_id || item.entity_cloud_id;
        if (!cloudId) {
          throw new Error("No se puede eliminar sin cloud_id");
        }
        // P1-010: Usar item.id como Idempotency-Key estable para reintentos
        await syncApi.deleteOrder(cloudId, item.id);
        return cloudId;
      }
      default:
        throw new Error(`Acción no soportada para order: ${item.action}`);
    }
  }

  static async processPayment(item: SyncQueueItem, payload: any): Promise<string | null> {
    if (item.action !== "create") {
      throw new Error(`Acción no soportada para payment: ${item.action}`);
    }

    // Resolver order_uuid desde order_local_uuid (el backend espera order_uuid, no order_id)
    let orderUuid = payload.order_uuid;
    if (!orderUuid && payload.order_local_uuid) {
      const { OrderRepository } = await import("../../../db/repositories/OrderRepository");
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

    // ADR-020: Resolver bill_uuid desde bill_local_uuid (si existe)
    let billUuid: string | null = null;
    if (payload.bill_local_uuid) {
      const { BillRepository } = await import("../../../db/repositories/BillRepository");
      const bill = await BillRepository.findByLocalUuid(payload.bill_local_uuid);
      if (!bill?.cloud_id) {
        throw new Error(`Bill sin cloud_id, no se puede crear payment: ${payload.bill_local_uuid}`);
      }
      billUuid = bill.cloud_id;
    }

    // Construir payload con el formato que espera el backend (StorePaymentRequest)
    const paymentPayload = {
      order_uuid: orderUuid,
      payment_method_uuid: payload.payment_method_uuid,
      bill_uuid: billUuid,  // ADR-020: puede ser null si payment directo a order
      amount: payload.sale_amount || (payload.amount - (payload.tip_amount || 0)),  // ADR-011: enviar sale_amount (venta sin propina)
      tip_amount: payload.tip_amount || 0,
      reference_code: payload.reference_code || null,
      notes: payload.notes || null,
      idempotency_key: payload.idempotency_key,
    };

    console.log("[SyncEngine] 📤 Creando payment:", JSON.stringify(paymentPayload, null, 2));

    const response = await syncApi.createPayment(paymentPayload);
    const cloudId = response.uuid || response.id;
    if (cloudId) {
      const { PaymentRepository } = await import("../../../db/repositories/PaymentRepository");
      await PaymentRepository.markAsSynced(item.entity_local_uuid, String(cloudId));
    }
    return cloudId ? String(cloudId) : null;
  }

  static async processBill(item: SyncQueueItem, payload: any): Promise<string | null> {
    if (item.action !== "create") {
      throw new Error(`Acción no soportada para bill: ${item.action}`);
    }

    // 1. Resolver order_uuid desde order_local_uuid
    let orderUuid = payload.order_uuid || payload.order_cloud_id;
    if (!orderUuid && payload.order_local_uuid) {
      const { OrderRepository } = await import("../../../db/repositories/OrderRepository");
      const order = await OrderRepository.findByLocalUuid(payload.order_local_uuid);
      if (!order?.cloud_id) {
        throw new Error(`Order padre sin cloud_id, no se puede crear bill: ${payload.order_local_uuid}`);
      }
      orderUuid = order.cloud_id;
    }

    if (!orderUuid) {
      throw new Error("No se pudo resolver order_uuid para la bill");
    }

    // 2. Mapear campos frontend→backend
    // Frontend (LocalBill): tax_total, discount_total, grand_total, amount_due
    // Backend (Bill):       tax_amount, discount_amount, total
    //
    // Semántica backend: total = grand_total (solo venta, sin propina) (ADR-011 + ADR-018)
    const billPayload = {
      order_uuid: orderUuid,
      bill_number: payload.bill_number,
      type: payload.type || "single",
      subtotal: payload.subtotal,
      tax_amount: payload.tax_total ?? 0,
      discount_amount: payload.discount_total ?? 0,
      tip_amount: payload.tip_amount ?? 0,
      total: payload.grand_total ?? 0,  // Backend espera solo venta (sin propina)
      paid_amount: payload.paid_amount ?? 0,
      remaining_amount: payload.remaining_amount ?? payload.grand_total ?? 0,  // remaining = grand_total cuando no hay pagos
      status: payload.status || "open",
      idempotency_key: payload.idempotency_key,
    };

    console.log("[SyncEngine] 📤 Creando bill:", JSON.stringify(billPayload, null, 2));

    // 3. Llamar al backend
    const response = await syncApi.createBill(billPayload);
    const cloudId = response.uuid || response.id;

    // 4. Actualizar local_bills con cloud_id
    if (cloudId) {
      const { BillRepository } = await import("../../../db/repositories/BillRepository");
      await BillRepository.markAsSynced(item.entity_local_uuid, String(cloudId));
    }

    return cloudId ? String(cloudId) : null;
  }

  static async processTableStatus(item: SyncQueueItem, payload: any): Promise<void> {
    if (item.action !== "update") {
      throw new Error(`Acción no soportada para table_status: ${item.action}`);
    }
    await syncApi.updateTableStatus(item.entity_local_uuid, payload.status);
  }

  static async processCashMovement(item: SyncQueueItem, payload: any): Promise<string | null> {
    if (item.action !== "create") {
      throw new Error(`Acción no soportada para cash_movement: ${item.action}`);
    }

    const movementType = payload.type;
    
    // Solo withdrawal/deposit/adjustment llegan al backend
    const syncableTypes = ["withdrawal", "deposit", "adjustment"];
    if (!syncableTypes.includes(movementType)) {
      console.log(`[SyncEngine] ⏭️  Saltando ${movementType} (se sincroniza por otra vía)`);
      return null;
    }

    // Resolver session_uuid desde cash_session_cloud_id o cash_session_local_uuid
    let sessionUuid = payload.cash_session_cloud_id;
    if (!sessionUuid && payload.cash_session_local_uuid) {
      const { CashSessionRepository } = await import("../../../db/repositories/CashSessionRepository");
      const session = await CashSessionRepository.findByLocalUuid(payload.cash_session_local_uuid);
      if (!session?.cloud_id) {
        throw new Error(
          `Cash session ${payload.cash_session_local_uuid} no está sincronizada. ` +
          `No se puede crear movimiento sin session_uuid en backend.`
        );
      }
      sessionUuid = session.cloud_id;
    }

    if (!sessionUuid) {
      throw new Error("No se pudo resolver session_uuid para el movimiento");
    }

    // Backend espera amount positivo (el signo lo da el type)
    const absoluteAmount = Math.abs(payload.amount);

    const movementPayload = {
      session_uuid: sessionUuid,
      type: movementType as "withdrawal" | "deposit" | "adjustment",
      amount: absoluteAmount,
      reason: payload.reason || `${movementType} offline`,
      notes: payload.notes || null,
      authorizer_uuid: payload.authorized_by || null,
      reference_type: payload.reference_type || null,
      reference_id: payload.reference_local_uuid || null,
      idempotency_key: payload.idempotency_key,
    };

    console.log("[SyncEngine] 📤 Creando movimiento:", JSON.stringify(movementPayload, null, 2));

    const response = await syncApi.createMovement(movementPayload);
    const cloudId = response.uuid || response.id;
    
    if (cloudId) {
      const { CashMovementRepository } = await import("../../../db/repositories/CashMovementRepository");
      await CashMovementRepository.markAsSynced(item.entity_local_uuid, String(cloudId));
    }
    
    return cloudId ? String(cloudId) : null;
  }

  static async handleFailure(item: SyncQueueItem, errorMessage: string): Promise<void> {
    try {
      await SyncQueueRepository.markAsFailed(item.id, errorMessage);

      // Si el evento agotó reintentos, reflejar el fallo en el pedido local
      const updated = await SyncQueueRepository.findById(item.id);
      if (updated?.sync_status === "failed" && item.entity_type === "order") {
        const { OrderRepository } = await import("../../../db/repositories/OrderRepository");
        await OrderRepository.markSyncError(item.entity_local_uuid, errorMessage);
      }
    } catch (e) {
      console.error("[SyncEngine] No se pudo registrar el fallo:", e);
    }
  }

  static async processCashSession(item: SyncQueueItem, payload: any): Promise<string | null> {
    const { CashSessionRepository } = await import("../../../db/repositories/CashSessionRepository");

    switch (item.action) {
      case "create": {
        // Abrir nueva sesión de caja
        if (!payload.opening_amount && payload.opening_amount !== 0) {
          throw new Error("opening_amount es requerido para abrir sesión de caja");
        }

        const idempotencyKey = payload.idempotency_key || item.entity_local_uuid; // Ya es UUIDv4 válido

        console.log(`[SyncEngine] 🔓 Abriendo sesión de caja: ${item.entity_local_uuid}`);

        const response = await syncApi.openCashSession({
          opening_amount: payload.opening_amount,
          notes: payload.notes || null,
          idempotency_key: idempotencyKey,
        });

        const cloudId = response.uuid || response.id;
        if (cloudId) {
          await CashSessionRepository.markAsSynced(item.entity_local_uuid, String(cloudId));
        }
        return cloudId ? String(cloudId) : null;
      }

      case "update": {
        // Cerrar sesión de caja
        // Buscar por local_uuid primero, si no encuentra buscar por cloud_id (retry pattern)
        let session = await CashSessionRepository.findByLocalUuid(item.entity_local_uuid);
        if (!session) {
          // Retry pattern: entity_local_uuid puede ser cloud_id si falló el update local
          session = await CashSessionRepository.findByCloudId(item.entity_local_uuid);
        }
        
        if (!session) {
          throw new Error(`Sesión de caja no encontrada: ${item.entity_local_uuid}`);
        }

        // Si la sesión no tiene cloud_id, no se puede cerrar en backend
        if (!session.cloud_id) {
          throw new Error("Sesión de caja sin cloud_id, no se puede cerrar en backend");
        }

        const cloudId = session.cloud_id;

        if (!payload.closing_amount && payload.closing_amount !== 0) {
          throw new Error("closing_amount es requerido para cerrar sesión de caja");
        }

        const idempotencyKey = payload.idempotency_key || cloudId; // Ya es UUIDv4 válido

        console.log(`[SyncEngine] 🔒 Cerrando sesión de caja: ${cloudId}`);

        const response = await syncApi.closeCashSession(cloudId, {
          closing_amount: payload.closing_amount,
          notes: payload.notes || null,
          idempotency_key: idempotencyKey,
        });

        // Cerrar localmente con sync_status='synced' (ya se sincronizó con backend)
        await CashSessionRepository.close(item.entity_local_uuid, payload.closing_amount, 'synced');

        return cloudId;
      }

      default:
        throw new Error(`Acción no soportada para cash_session: ${item.action}`);
    }
  }

  static safeParseJson(str: string): any {
    try {
      return JSON.parse(str);
    } catch {
      return null;
    }
  }
}