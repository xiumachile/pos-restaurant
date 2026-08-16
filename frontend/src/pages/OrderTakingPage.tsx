import { useEffect } from "react";
import { useParams, useNavigate } from "react-router-dom";
import { useTables } from "@/hooks/useTables";
import { useCartStore } from "@/stores/useCartStore";
import { OrderCatalogPanel } from "@/components/orders/OrderCatalogPanel";
import { OrderCartPanel } from "@/components/orders/OrderCartPanel";
import { flattenAreas, TABLE_STATUS_LABELS, TABLE_STATUS_STYLES } from "@/types/tables";
import type { Product } from "@/types/catalog";
import { ArrowLeft, Users, Loader2 } from "lucide-react";

/**
 * Vista de toma de pedido para una mesa específica.
 * Layout: catálogo (izquierda) + pedido en curso (derecha).
 */
export function OrderTakingPage() {
  const { tableUuid } = useParams<{ tableUuid: string }>();
  const navigate = useNavigate();

  const { data: areas = [], isLoading } = useTables();
  const initCart = useCartStore((s) => s.initCart);
  const addItem = useCartStore((s) => s.addItem);

  const table = flattenAreas(areas).find((t) => t.uuid === tableUuid);

  // Inicializar carrito al entrar a la mesa
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

  const statusStyle = TABLE_STATUS_STYLES[table.status];

  const handleAddProduct = (product: Product) => {
    addItem(table.uuid, product);
  };

  return (
    <div className="flex flex-col h-full">
      {/* Header con info de la mesa */}
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
            <h1 className="text-2xl font-bold flex items-center gap-3">
              Mesa {table.table_number}
              <span
                className={`text-xs px-2.5 py-1 rounded-full border ${statusStyle.bg} ${statusStyle.text} ${statusStyle.border}`}
              >
                {TABLE_STATUS_LABELS[table.status]}
              </span>
            </h1>
            <p className="text-sm text-slate-400 flex items-center gap-2 mt-0.5">
              {table.area_name}
              <span className="flex items-center gap-1">
                <Users size={13} /> {table.capacity}
              </span>
            </p>
          </div>
        </div>
      </div>

      {/* Layout de 2 paneles */}
      <div className="flex-1 flex gap-4 overflow-hidden">
        <OrderCatalogPanel onAddProduct={handleAddProduct} />
        <OrderCartPanel tableUuid={table.uuid} tableNumber={table.table_number} />
      </div>
    </div>
  );
}
