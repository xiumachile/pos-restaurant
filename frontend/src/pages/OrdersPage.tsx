/**
 * TODO: Futuro "Historial de Pedidos"
 * Actualmente esta ruta muestra el estado de sincronización.
 * En una versión futura, se debe crear una página real de historial de pedidos
 * para que garzones y managers puedan ver cuentas cerradas del día.
 * Por ahora, la gestión de sync se hace desde /sync-queue.
 */
import { OrdersSyncList } from "@/components/orders/OrdersSyncList";

export function OrdersPage() {
  return (
    <div className="container mx-auto px-4 py-8 max-w-6xl">
      <OrdersSyncList />
    </div>
  );
}
