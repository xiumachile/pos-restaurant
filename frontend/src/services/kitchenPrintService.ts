import { OrderRepository } from "@/db/repositories/OrderRepository";
import { LocalPrintJobRepository } from "@/db/repositories/LocalPrintJobRepository";
import { ticketToBase64, type KitchenTicketData } from "@/services/printing/ticketFormatters";
import { getCashierContextSafe } from "@/services/authContext";

/**
 * kitchenPrintService: Genera tickets de cocina 100% offline.
 * 
 * USO:
 *   await kitchenPrintService.sendOrderToKitchen(orderLocalUuid);
 * 
 * FLUJO:
 * 1. Obtiene order y items desde SQLite
 * 2. Valida que existan items (no tiene sentido imprimir orden vacía)
 * 3. Construye KitchenTicketData
 * 4. Genera escpos_base64 con ticketToBase64.kitchen()
 * 5. Encola LocalPrintJob con job_type "kitchen_command"
 * 6. OfflinePrintEngine lo imprime automáticamente (polling 3s)
 * 
 * OFFLINE-FIRST:
 * - No depende de API ni WebSocket
 * - Todo se genera localmente usando EscPosBuilder
 * - Funciona sin conexión a internet
 * 
 * IDEMPOTENCIA:
 * - Usa idempotency_key basado en orderUuid + timestamp
 * - Si se llama dos veces, crea dos tickets distintos (reimpresión intencional)
 * - Esto es correcto: reenviar a cocina debe imprimir nuevo ticket
 */
export const kitchenPrintService = {
  /**
   * Envía una orden a cocina: genera ticket e imprime localmente.
   * 
   * @param orderLocalUuid UUID local de la orden
   * @returns { success, printJobUuid, itemsCount } o lanza error si no hay items
   */
  async sendOrderToKitchen(orderLocalUuid: string): Promise<{
    success: boolean;
    printJobUuid: string;
    itemsCount: number;
  }> {
    // 1. Obtener order
    const order = await OrderRepository.findByLocalUuid(orderLocalUuid);
    if (!order) {
      throw new Error(`Order no encontrada: ${orderLocalUuid}`);
    }

    // 2. Obtener items de la orden
    const items = await OrderRepository.findItemsByOrderLocalUuid(orderLocalUuid);
    if (items.length === 0) {
      throw new Error(`Order ${orderLocalUuid} no tiene items. No se puede enviar a cocina.`);
    }

    // 3. Construir KitchenTicketData
    const kitchenData: KitchenTicketData = {
      orderNumber: order.order_number,
      tableNumber: order.table_id ? `Mesa ${order.table_id}` : null,
      waiterName: order.waiter_name || null,
      items: items.map((item) => ({
        name: item.product_name,
        quantity: item.quantity,
        notes: item.notes || null,
      })),
      createdAt: new Date(order.created_at),
    };

    // 4. Generar bytes ESC/POS (100% offline)
    const escposBase64 = ticketToBase64.kitchen(kitchenData);

    // 5. Obtener contexto del cajero (opcional, para auditoría)
    const ctx = getCashierContextSafe();
    if (!ctx) {
      throw new Error("No hay contexto de cajero (usuario no autenticado)");
    }

    // 6. Encolar print job
    const printJobUuid = await LocalPrintJobRepository.create({
      job_type: "kitchen_command",
      entity_type: "order",
      entity_uuid: orderLocalUuid,
      payload: kitchenData,
      escpos_base64: escposBase64,
      printer_name: "kitchen-printer",
      printer_type: "kitchen",
      company_id: ctx.company_id,
      branch_id: ctx.branch_id,
      terminal_id: ctx.terminal_id,
      user_id: ctx.user_id,
      user_name: ctx.user_name || undefined,
      reference_number: `Orden #${order.order_number}`,
      idempotency_key: `kitchen-${orderLocalUuid}-${Date.now()}`,
    });

    console.log(
      `[kitchenPrintService] 🖨️  Ticket de cocina encolado (${items.length} items, ${escposBase64.length} bytes)`
    );

    return {
      success: true,
      printJobUuid,
      itemsCount: items.length,
    };
  },

  /**
   * Reimprime ticket de cocina de una orden existente.
   * Útil si el cocinero perdió el ticket original.
   */
  async reprintKitchenTicket(orderLocalUuid: string): Promise<{
    success: boolean;
    printJobUuid: string;
    itemsCount: number;
  }> {
    console.log(`[kitchenPrintService] 🔁 Reimprimiendo ticket para order ${orderLocalUuid}`);
    return this.sendOrderToKitchen(orderLocalUuid);
  },
};
