import { EscPosBuilder, formatCLP, formatDateTime } from "./EscPosBuilder";
import { IVA_PERCENTAGE } from "@/config/tax";

/**
 * Formatters para generar tickets ESC/POS 100% offline.
 * 
 * Cada formatter toma los datos del negocio y retorna un EscPosBuilder
 * listo para construir los bytes finales.
 * 
 * NO depende de API, WebSocket ni Internet.
 * Todo el formato se genera localmente usando EscPosBuilder.
 */

// ═══════════════════════════════════════════════════════
// TIPOS DE ENTRADA
// ═══════════════════════════════════════════════════════

export interface ReceiptData {
  companyName?: string;
  branchName?: string;
  billNumber: string;
  items: Array<{
    name: string;
    quantity: number;
    unitPrice: number;
    subtotal: number;
    notes?: string | null;
  }>;
  subtotal: number;
  taxTotal: number;
  tipAmount: number;
  grandTotal: number;
  paymentMethod: string;
  paidAmount: number;
  remainingAmount: number;
  cashierName?: string;
  createdAt: Date;
}

export interface KitchenTicketData {
  orderNumber: string;
  tableNumber?: string | null;
  waiterName?: string | null;
  items: Array<{
    name: string;
    quantity: number;
    notes?: string | null;
  }>;
  createdAt: Date;
}

export interface CustomerTicketData {
  companyName?: string;
  billNumber: string;
  items: Array<{
    name: string;
    quantity: number;
    unitPrice: number;
    subtotal: number;
  }>;
  subtotal: number;
  taxTotal: number;
  grandTotal: number;
  createdAt: Date;
}

export interface CashCopyData {
  companyName?: string;
  cashierName: string;
  sessionOpenedAt: Date;
  sessionClosedAt?: Date | null;
  totalSales: number;
  cashSales: number;
  cardSales: number;
  transferSales: number;
  movements: Array<{
    type: "withdrawal" | "deposit";
    amount: number;
    reason?: string;
    createdAt: Date;
  }>;
  expectedAmount: number;
  actualAmount?: number | null;
  difference?: number | null;
}

// ═══════════════════════════════════════════════════════
// MAPPER DE MÉTODOS DE PAGO
// ═══════════════════════════════════════════════════════

const PAYMENT_METHOD_LABELS: Record<string, string> = {
  cash: "Efectivo",
  card: "Tarjeta",
  transfer: "Transferencia",
  gift_card: "Gift Card",
};

function getPaymentLabel(method: string): string {
  return PAYMENT_METHOD_LABELS[method] || method;
}

// ═══════════════════════════════════════════════════════
// 1. RECEIPT (Boleta / Comprobante de pago)
// ═══════════════════════════════════════════════════════

/**
 * Genera ticket de cobro completo.
 * Incluye: header, items, totales, método de pago, footer.
 */
export function formatReceipt(data: ReceiptData): EscPosBuilder {
  const builder = new EscPosBuilder();

  // Header
  builder.center().bold();
  builder.line(data.companyName || "WOK & MESA");
  builder.normal();
  if (data.branchName) {
    builder.line(data.branchName);
  }
  builder.emptyLines();

  // Info de cuenta
  builder.left();
  builder.leftRight("Cuenta:", `#${data.billNumber}`);
  builder.leftRight("Fecha:", formatDateTime(data.createdAt));
  if (data.cashierName) {
    builder.leftRight("Cajero:", data.cashierName);
  }
  builder.separator();

  // Items
  builder.bold().leftRight("Item", "Total").normal();
  builder.separator();

  for (const item of data.items) {
    const itemLine = `${item.quantity}x ${item.name}`;
    builder.leftRight(itemLine, formatCLP(item.subtotal));
    
    if (item.notes) {
      builder.left().line(`   Nota: ${item.notes}`);
    }
  }

  builder.separator();

  // Totales
  builder.leftRight("Subtotal:", formatCLP(data.subtotal));
  if (data.taxTotal > 0) {
    builder.leftRight(`IVA (${IVA_PERCENTAGE}%):`, formatCLP(data.taxTotal));
  }
  if (data.tipAmount > 0) {
    builder.leftRight("Propina:", formatCLP(data.tipAmount));
  }
  
  builder.doubleSeparator();
  builder.bold();
  builder.leftRight("TOTAL:", formatCLP(data.grandTotal));
  builder.normal();
  builder.separator();

  // Pago
  builder.bold().line("PAGO:").normal();
  builder.leftRight("Método:", getPaymentLabel(data.paymentMethod));
  builder.leftRight("Pagado:", formatCLP(data.paidAmount));
  if (data.remainingAmount > 0) {
    builder.leftRight("Saldo:", formatCLP(data.remainingAmount));
  }

  // Footer
  builder.emptyLines();
  builder.center().line("¡Gracias por su preferencia!");
  builder.line("www.wokymesa.cl");

  builder.cut();
  return builder;
}

// ═══════════════════════════════════════════════════════
// 2. KITCHEN TICKET (Comanda de cocina)
// ═══════════════════════════════════════════════════════

/**
 * Genera comanda para cocina.
 * Diseño compacto, grande y claro para lectura rápida.
 */
export function formatKitchenTicket(data: KitchenTicketData): EscPosBuilder {
  const builder = new EscPosBuilder();

  // Header destacado
  builder.center().bold();
  builder.line("*** COCINA ***");
  builder.emptyLines();

  // Info de orden
  builder.bold().line(`ORDEN #${data.orderNumber}`).normal();
  builder.line(formatDateTime(data.createdAt));
  
  if (data.tableNumber) {
    builder.bold().line(`MESA: ${data.tableNumber}`).normal();
  }
  
  if (data.waiterName) {
    builder.line(`Mesero: ${data.waiterName}`);
  }

  builder.doubleSeparator();

  // Items
  for (const item of data.items) {
    builder.bold().line(`${item.quantity}x ${item.name}`).normal();
    
    if (item.notes) {
      builder.line(`   >> ${item.notes}`);
    }
  }

  builder.emptyLines();
  builder.center().bold().line(`Total items: ${data.items.reduce((sum, i) => sum + i.quantity, 0)}`);

  builder.cut();
  return builder;
}

// ═══════════════════════════════════════════════════════
// 3. CUSTOMER TICKET (Ticket para cliente / split bill)
// ═══════════════════════════════════════════════════════

/**
 * Genera ticket para el cliente (sin info de pago).
 * Versión simplificada del receipt, enfocado en el detalle.
 */
export function formatCustomerTicket(data: CustomerTicketData): EscPosBuilder {
  const builder = new EscPosBuilder();

  // Header
  builder.center().bold();
  builder.line(data.companyName || "WOK & MESA");
  builder.normal();
  builder.line("Detalle de cuenta");
  builder.emptyLines();

  // Info
  builder.left();
  builder.leftRight("Cuenta:", `#${data.billNumber}`);
  builder.leftRight("Fecha:", formatDateTime(data.createdAt));
  builder.separator();

  // Items
  for (const item of data.items) {
    const itemLine = `${item.quantity}x ${item.name}`;
    builder.leftRight(itemLine, formatCLP(item.subtotal));
  }

  builder.separator();

  // Totales
  builder.leftRight("Subtotal:", formatCLP(data.subtotal));
  if (data.taxTotal > 0) {
    builder.leftRight(`IVA (${IVA_PERCENTAGE}%):`, formatCLP(data.taxTotal));
  }
  
  builder.doubleSeparator();
  builder.bold();
  builder.leftRight("TOTAL:", formatCLP(data.grandTotal));
  builder.normal();

  // Footer
  builder.emptyLines();
  builder.center().line("Solicite su boleta al cajero");

  builder.cut();
  return builder;
}

// ═══════════════════════════════════════════════════════
// 4. CASH COPY (Copia de caja)
// ═══════════════════════════════════════════════════════

/**
 * Genera reporte de cierre/apertura de caja.
 * Incluye: ventas por método, movimientos, arqueo.
 */
export function formatCashCopy(data: CashCopyData): EscPosBuilder {
  const builder = new EscPosBuilder();

  // Header
  builder.center().bold();
  builder.line(data.companyName || "WOK & MESA");
  builder.line("CIERRE DE CAJA");
  builder.normal();
  builder.emptyLines();

  // Info de cajero
  builder.left();
  builder.leftRight("Cajero:", data.cashierName);
  builder.leftRight("Apertura:", formatDateTime(data.sessionOpenedAt));
  if (data.sessionClosedAt) {
    builder.leftRight("Cierre:", formatDateTime(data.sessionClosedAt));
  }
  builder.separator();

  // Ventas por método
  builder.bold().line("VENTAS POR MÉTODO:").normal();
  builder.leftRight("Efectivo:", formatCLP(data.cashSales));
  builder.leftRight("Tarjeta:", formatCLP(data.cardSales));
  builder.leftRight("Transferencia:", formatCLP(data.transferSales));
  builder.separator();
  builder.bold().leftRight("TOTAL VENTAS:", formatCLP(data.totalSales)).normal();

  // Movimientos (si hay)
  if (data.movements.length > 0) {
    builder.separator();
    builder.bold().line("MOVIMIENTOS:").normal();
    
    for (const mov of data.movements) {
      const sign = mov.type === "deposit" ? "+" : "-";
      const label = mov.type === "deposit" ? "Depósito" : "Retiro";
      const reason = mov.reason ? ` (${mov.reason})` : "";
      builder.leftRight(
        `${label}${reason}`,
        `${sign}${formatCLP(mov.amount)}`
      );
    }
  }

  // Arqueo
  builder.separator();
  builder.bold().line("ARQUEO:").normal();
  builder.leftRight("Esperado:", formatCLP(data.expectedAmount));
  if (data.actualAmount !== null && data.actualAmount !== undefined) {
    builder.leftRight("Contado:", formatCLP(data.actualAmount));
    if (data.difference !== null && data.difference !== undefined) {
      const sign = data.difference >= 0 ? "+" : "";
      builder.leftRight("Diferencia:", `${sign}${formatCLP(data.difference)}`);
    }
  }

  builder.cut();
  return builder;
}

// ═══════════════════════════════════════════════════════
// HELPER: Generar bytes base64 directamente
// ═══════════════════════════════════════════════════════

/**
 * Helper para generar base64 listo para persistir en SQLite.
 * Útil para encolar print jobs sin tener que construir manualmente.
 */
export const ticketToBase64 = {
  receipt: (data: ReceiptData) => formatReceipt(data).buildBase64(),
  kitchen: (data: KitchenTicketData) => formatKitchenTicket(data).buildBase64(),
  customer: (data: CustomerTicketData) => formatCustomerTicket(data).buildBase64(),
  cashCopy: (data: CashCopyData) => formatCashCopy(data).buildBase64(),
};
