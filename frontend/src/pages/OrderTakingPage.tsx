import { useEffect } from "react";
import { useParams, useNavigate } from "react-router-dom";
import { useTables } from "@/hooks/useTables";
import { useTableOrders } from "@/hooks/useTableOrders";
import { useCartStore } from "@/stores/useCartStore";
import { OrderCatalogPanel } from "@/components/orders/OrderCatalogPanel";
import { OrderCartPanel } from "@/components/orders/OrderCartPanel";
import { flattenAreas, TABLE_STATUS_LABELS, TABLE_STATUS_STYLES } from "@/types/tables";
import type { Product } from "@/types/catalog";
import { ArrowLeft, Users, Loader2, AlertCircle, Scissors } from "lucide-react";
import { useToastStore } from "@/store/useToastStore";
import { CapabilityGate } from "@/components/CapabilityGate";
import { CapabilityKey } from "@/types/capabilities";

/**
 * Vista de toma de pedido para una mesa específica.
 * Valida que la mesa tenga pedidos activos (no solo paid/closed).
 */
export function OrderTakingPage() {
  const { tableUuid } = useParams<{ tableUuid: string }>();
  const navigate = useNavigate();

  const { data: areas = [], isLoading } = useTables();
  const { data: activeOrders = [] } = useTableOrders(tableUuid || null);
  const initCart = useCartStore((s) => s.initCart);
  const addItem = useCartStore((s) => s.addItem);

  const table = flattenAreas(areas).find((t) => t.uuid === tableUuid);

  useEffect(() => {
    if (table) {
      initCart(table.uuid, table.table_number, table.area_name);
    }
  }, [table, initCart]);

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-64">
        <Loader2 className="animate-spin text-orange-500" size={48} />
      </div>
    );
  }

  if (!table) {
    return (
      <div className="bg-red-900/30 border border-red-800 rounded-lg p-6 text-center">
        <p className="text-red-300">Mesa no encontrada</p>
        <button
          onClick={() => navigate("/")}
          className="mt-3 px-4 py-2 bg-slate-700 hover:bg-slate-600 rounded-lg"
        >
          Volver a mesas
        </button>
      </div>
    );
  }

  // ✅ Validación: si la mesa está disponible, es una nueva cuenta
  const tableIsFree = table.status === "available" || table.status === "maintenance";
  const hasActiveOrders = activeOrders.length > 0;

  const statusStyle = TABLE_STATUS_STYLES[table.status];

  const handleAddProduct = (product: Product) => {
    addItem(table.uuid, product);
  };

  return (
    <div className="flex flex-col h-full">
      <div className="flex items-center justify-between mb-4">
        <div className="flex items-center gap-4">
          <button
            onClick={() => navigate("/")}
            className="p-2 bg-slate-800 hover:bg-slate-700 rounded-lg transition-colors"
            title="Volver a mesas"
          >
            <ArrowLeft size={20} />
          </button>

          <div>
            <div className="flex items-center gap-3">
              <h1 className="text-2xl font-bold flex items-center gap-3">
                Mesa {table.table_number}
                <span
                  className={`text-xs px-2.5 py-1 rounded-full border ${statusStyle.bg} ${statusStyle.text} ${statusStyle.border}`}
                >
                  {TABLE_STATUS_LABELS[table.status]}
                </span>
              </h1>
              
              {/* Botón dividir cuenta (solo si está habilitado) */}
              <CapabilityGate requires={CapabilityKey.CAN_SPLIT_BILLS}>
                {hasActiveOrders && (
                  <button
                    className="ml-4 px-3 py-1.5 bg-blue-600 hover:bg-blue-500 rounded-lg text-sm font-medium flex items-center gap-2 transition-colors"
                    onClick={() => {
                      // TODO(post-demo): Conectar SplitBillModal cuando feature esté validada
                      // Infraestructura existente:
                      //   - SplitBillModal.tsx (equal/items/custom)
                      //   - useSplitOrder() hook
                      //   - Backend: POST /orders/{uuid}/split
                      //   - Capability: can_split_bills (ya habilitada)
                      // Decisión: Mantener desconectado para demo del 11 Sep
                      //           para evitar introducir bugs en flujo crítico.
                      useToastStore.getState().addToast(
                        "info",
                        "División de cuenta estará disponible en próxima versión. Por ahora, crea sub-cuentas manualmente desde el flujo de pedido.",
                        5000
                      );
                    }}
                    title="Dividir cuenta entre varios clientes"
                  >
                    <Scissors size={16} />
                    Dividir Cuenta
                  </button>
                )}
              </CapabilityGate>
            </div>
            <p className="text-sm text-slate-400 mt-1 flex items-center gap-3">
              <span className="flex items-center gap-1">
                <Users size={14} /> {table.capacity} personas
              </span>
              <span>·</span>
              <span>{table.area_name}</span>
              {hasActiveOrders && (
                <>
                  <span>·</span>
                  <span className="text-blue-400">
                    {activeOrders.length} pedido{activeOrders.length !== 1 ? "s" : ""} activo{activeOrders.length !== 1 ? "s" : ""}
                  </span>
                </>
              )}
            </p>
          </div>
        </div>
      </div>

      {/* Advertencia si la mesa está libre (nueva cuenta) */}
      {tableIsFree && (
        <div className="mb-4 bg-blue-900/20 border border-blue-700/50 rounded-lg p-3 flex items-center gap-2 text-sm text-blue-200">
          <AlertCircle size={16} className="flex-shrink-0" />
          <span>
            Mesa libre. Al enviar un pedido se marcará como <strong>Ocupada</strong>.
          </span>
        </div>
      )}

      <div className="flex-1 flex gap-4 overflow-hidden">
        <OrderCatalogPanel onAddProduct={handleAddProduct} />
        <OrderCartPanel
          tableUuid={table.uuid}
          tableNumber={table.table_number}
        />
      </div>
    </div>
  );
}
