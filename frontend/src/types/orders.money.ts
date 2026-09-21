/**
 * Versión del tipo Order con Money Contract aplicado.
 * Este archivo puede reemplazar o extender types/orders.ts
 */
import type { CLPMoney } from '@/schemas/money';

export interface Order {
  uuid: string;
  order_number: string;
  items: Array<{
    uuid: string;
    unit_price: CLPMoney;  // 🟢 Branded
    quantity: number;       // No es dinero
    subtotal: CLPMoney;    // 🟢 Branded
  }>;
  subtotal: CLPMoney;       // 🟢 Branded
  tax_amount: CLPMoney;     // 🟢 Branded
  discount_amount: CLPMoney; // 🟢 Branded
  total: CLPMoney;          // 🟢 Branded
}

// Ejemplo de uso:
// const total: CLPMoney = order.total;
// const qty: number = 5;
// const bad: CLPMoney = qty;  // ❌ TypeScript error
