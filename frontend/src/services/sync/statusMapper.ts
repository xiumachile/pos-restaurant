/**
 * Mapeador bidireccional entre estados locales (SQLite) y backend (OrderStatus).
 *
 * RESPONSABILIDAD:
 * Garantiza que no haya pérdida de información al sincronizar.
 * Cada estado del backend tiene una representación local válida.
 *
 * DEUDA TÉCNICA (post-demo 11 Sep):
 * - Migrar SQLite para usar el mismo enum que el backend
 * - Eliminar `pending` (mapear directamente a `draft` o `confirmed`)
 * - Considerar usar una sola fuente de verdad para estados
 */

import type { OrderStatus } from "@/types/orders";

/**
 * Estados locales válidos en SQLite.
 * Mantener sincronizado con LocalOrder.status.
 */
export type LocalOrderStatus =
  | "draft"
  | "confirmed"
  | "preparing"
  | "ready"
  | "ready_for_pickup"
  | "picked_up"
  | "dispatched"
  | "delivered"
  | "served"
  | "paid"
  | "closed"
  | "cancelled";

/**
 * Estados del item (cocina) locales.
 * `pending` es el único estado sin correspondencia directa (equivale a draft).
 */
export type LocalItemStatus =
  | "pending"
  | "confirmed"
  | "preparing"
  | "ready"
  | "ready_for_pickup"
  | "served"
  | "delivered"
  | "cancelled";

/**
 * Convierte un estado del backend al formato local.
 * Todos los estados del backend son válidos localmente (subconjunto).
 */
export function backendToLocalOrder(status: OrderStatus): LocalOrderStatus {
  // Todos los estados del backend ya son válidos en LocalOrderStatus
  return status as LocalOrderStatus;
}

/**
 * Convierte un estado local al formato del backend.
 * Maneja el caso especial de `pending` → `draft`.
 */
export function localToBackendOrder(status: LocalOrderStatus): OrderStatus {
  // `pending` no existe en backend, mapear a `draft`
  if (status === "pending" as LocalOrderStatus) {
    return "draft";
  }
  return status as OrderStatus;
}

/**
 * Convierte estado del item (cocina) a estado del order padre.
 * Útil cuando el item cambia de estado y el order debe reflejarlo.
 */
export function itemToOrderStatus(itemStatus: LocalItemStatus): LocalOrderStatus {
  const map: Record<LocalItemStatus, LocalOrderStatus> = {
    pending: "draft",
    confirmed: "confirmed",
    preparing: "preparing",
    ready: "ready",
    ready_for_pickup: "ready_for_pickup",
    served: "served",
    delivered: "delivered",
    cancelled: "cancelled",
  };
  return map[itemStatus];
}

/**
 * Valida si un estado local es válido (defensa contra corrupción de SQLite).
 */
export function isValidLocalStatus(status: string): status is LocalOrderStatus {
  const valid = [
    "draft", "confirmed", "preparing", "ready", "ready_for_pickup",
    "picked_up", "dispatched", "delivered", "served", "paid",
    "closed", "cancelled",
  ];
  return valid.includes(status);
}
