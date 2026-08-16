import { useCartStore } from "@/stores/useCartStore";
import { getTranslatedName, formatPrice, parsePrice } from "@/types/catalog";
import { Plus, Minus, Trash2, Send, ShoppingCart } from "lucide-react";

interface OrderCartPanelProps {
  tableUuid: string;
  tableNumber: string;
}

/**
 * Panel derecho con el pedido en curso de la mesa.
 * Siempre visible para feedback inmediato al agregar productos.
 */
export function OrderCartPanel({ tableUuid, tableNumber }: OrderCartPanelProps) {
  const cart = useCartStore((s) => s.carts[tableUuid]);
  const updateQuantity = useCartStore((s) => s.updateQuantity);
  const removeItem = useCartStore((s) => s.removeItem);
  const clearCart = useCartStore((s) => s.clearCart);
  const getTotals = useCartStore((s) => s.getTotals);

  const totals = getTotals(tableUuid);
  const items = cart?.items ?? [];

  const handleSendOrder = () => {
    alert(
      `Enviar pedido a cocina:\n` +
        `Mesa: ${tableNumber}\n` +
        `Items: ${totals.itemCount}\n` +
        `Total: ${formatPrice(totals.total)}\n\n` +
        `Se implementará en F13.4 (crear Order real en backend)`
    );
  };

  return (
    <aside className="w-96 bg-slate-800/50 border border-slate-700 rounded-xl flex flex-col overflow-hidden">
      {/* Header del panel */}
      <div className="p-4 border-b border-slate-700 flex items-center justify-between">
        <div className="flex items-center gap-2">
          <ShoppingCart size={20} className="text-orange-400" />
          <h2 className="text-lg font-bold">Pedido · Mesa {tableNumber}</h2>
        </div>
        {items.length > 0 && (
          <span className="px-2 py-0.5 bg-orange-500 text-white text-sm font-bold rounded-full">
            {totals.itemCount}
          </span>
        )}
      </div>

      {/* Lista de items */}
      <div className="flex-1 overflow-y-auto p-3 space-y-2">
        {items.length === 0 ? (
          <div className="text-center py-12 text-slate-500">
            <ShoppingCart size={48} className="mx-auto mb-3 opacity-30" />
            <p className="text-sm">
              Sin items aún.
              <br />
              Toca un producto del catálogo para agregarlo.
            </p>
          </div>
        ) : (
          items.map((item) => (
            <div
              key={item.id}
              className="bg-slate-800 rounded-lg p-3 border border-slate-700"
            >
              <div className="flex justify-between items-start mb-2">
                <div className="flex-1 min-w-0">
                  <h3 className="font-semibold text-white truncate">
                    {getTranslatedName(item.product.name_translations)}
                  </h3>
                  <p className="text-xs text-slate-500">
                    {formatPrice(item.product.base_price)} c/u
                  </p>
                </div>
                <button
                  onClick={() => removeItem(tableUuid, item.id)}
                  className="p-1 hover:bg-red-500/20 rounded ml-2"
                >
                  <Trash2 size={15} className="text-red-400" />
                </button>
              </div>

              <div className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                  <button
                    onClick={() => updateQuantity(tableUuid, item.id, item.quantity - 1)}
                    className="p-1.5 bg-slate-700 hover:bg-slate-600 rounded"
                  >
                    <Minus size={13} />
                  </button>
                  <span className="text-base font-bold w-7 text-center">
                    {item.quantity}
                  </span>
                  <button
                    onClick={() => updateQuantity(tableUuid, item.id, item.quantity + 1)}
                    className="p-1.5 bg-slate-700 hover:bg-slate-600 rounded"
                  >
                    <Plus size={13} />
                  </button>
                </div>
                <span className="font-bold text-orange-400">
                  {formatPrice(parsePrice(item.product.base_price) * item.quantity)}
                </span>
              </div>
            </div>
          ))
        )}
      </div>

      {/* Totales + acciones */}
      <div className="border-t border-slate-700 p-4 space-y-2 bg-slate-900/50">
        <div className="flex justify-between text-sm">
          <span className="text-slate-400">Subtotal</span>
          <span>{formatPrice(totals.subtotal)}</span>
        </div>
        <div className="flex justify-between text-sm">
          <span className="text-slate-400">IVA (19%)</span>
          <span>{formatPrice(totals.tax)}</span>
        </div>
        <div className="flex justify-between text-lg font-bold pt-2 border-t border-slate-700">
          <span>Total</span>
          <span className="text-orange-400">{formatPrice(totals.total)}</span>
        </div>

        <div className="flex gap-2 pt-2">
          <button
            onClick={() => clearCart(tableUuid)}
            disabled={items.length === 0}
            className="px-3 py-2.5 bg-slate-700 hover:bg-slate-600 rounded-lg text-sm disabled:opacity-40"
          >
            Limpiar
          </button>
          <button
            onClick={handleSendOrder}
            disabled={items.length === 0}
            className="flex-1 px-4 py-2.5 bg-orange-500 hover:bg-orange-600 rounded-lg font-semibold flex items-center justify-center gap-2 disabled:opacity-40 disabled:cursor-not-allowed"
          >
            <Send size={16} />
            Enviar a Cocina
          </button>
        </div>
      </div>
    </aside>
  );
}
