import { useCartStore } from "@/stores/useCartStore";
import { X, Plus, Minus, Trash2, ShoppingCart, Send } from "lucide-react";
import { getTranslatedName, formatPrice, parsePrice } from "@/types/catalog";

interface CartDrawerProps {
  isOpen: boolean;
  onClose: () => void;
}

export function CartDrawer({ isOpen, onClose }: CartDrawerProps) {
  const { items, tableNumber, updateQuantity, removeItem, clearCart, getTotals } =
    useCartStore();
  const totals = getTotals();

  const handleSendOrder = () => {
    alert(
      `Enviar pedido a cocina:\n` +
      `Mesa: ${tableNumber}\n` +
      `Items: ${totals.itemCount}\n` +
      `Total: ${formatPrice(totals.total)}\n\n` +
      `Funcionalidad a implementar en F13.4`
    );
  };

  if (!isOpen) return null;

  return (
    <>
      <div className="fixed inset-0 bg-black/50 z-40" onClick={onClose} />

      <div className="fixed right-0 top-0 h-full w-96 bg-slate-900 shadow-2xl z-50 flex flex-col">
        <div className="flex items-center justify-between p-4 border-b border-slate-700">
          <div className="flex items-center gap-2">
            <ShoppingCart size={20} className="text-orange-400" />
            <h2 className="text-xl font-bold">
              Pedido {tableNumber && `- Mesa ${tableNumber}`}
            </h2>
          </div>
          <button onClick={onClose} className="p-2 hover:bg-slate-800 rounded-lg">
            <X size={20} />
          </button>
        </div>

        <div className="flex-1 overflow-y-auto p-4 space-y-3">
          {items.length === 0 ? (
            <div className="text-center py-12 text-slate-500">
              <ShoppingCart size={48} className="mx-auto mb-3 opacity-30" />
              <p>El carrito está vacío</p>
            </div>
          ) : (
            items.map((item) => (
              <div key={item.id} className="bg-slate-800 rounded-lg p-3 border border-slate-700">
                <div className="flex justify-between items-start mb-2">
                  <div className="flex-1">
                    <h3 className="font-semibold text-white">
                      {getTranslatedName(item.product.name_translations)}
                    </h3>
                    <p className="text-xs text-slate-500">{item.product.sku}</p>
                  </div>
                  <button
                    onClick={() => removeItem(item.id)}
                    className="p-1 hover:bg-red-500/20 rounded"
                  >
                    <Trash2 size={16} className="text-red-400" />
                  </button>
                </div>

                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <button
                      onClick={() => updateQuantity(item.id, item.quantity - 1)}
                      className="p-1 bg-slate-700 hover:bg-slate-600 rounded"
                    >
                      <Minus size={14} />
                    </button>
                    <span className="text-lg font-semibold w-8 text-center">
                      {item.quantity}
                    </span>
                    <button
                      onClick={() => updateQuantity(item.id, item.quantity + 1)}
                      className="p-1 bg-slate-700 hover:bg-slate-600 rounded"
                    >
                      <Plus size={14} />
                    </button>
                  </div>
                  <span className="text-lg font-bold text-orange-400">
                    {formatPrice(parsePrice(item.product.base_price) * item.quantity)}
                  </span>
                </div>

                {item.notes && (
                  <p className="text-xs text-slate-400 mt-2 italic">📝 {item.notes}</p>
                )}
              </div>
            ))
          )}
        </div>

        {items.length > 0 && (
          <div className="border-t border-slate-700 p-4 space-y-3">
            <div className="space-y-1 text-sm">
              <div className="flex justify-between">
                <span className="text-slate-400">Subtotal:</span>
                <span>{formatPrice(totals.subtotal)}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-slate-400">IVA (19%):</span>
                <span>{formatPrice(totals.tax)}</span>
              </div>
              <div className="flex justify-between text-lg font-bold pt-2 border-t border-slate-700">
                <span>Total:</span>
                <span className="text-orange-400">{formatPrice(totals.total)}</span>
              </div>
            </div>

            <div className="flex gap-2">
              <button
                onClick={clearCart}
                className="flex-1 px-4 py-2 bg-slate-700 hover:bg-slate-600 rounded-lg transition-colors"
              >
                Limpiar
              </button>
              <button
                onClick={handleSendOrder}
                className="flex-1 px-4 py-2 bg-orange-500 hover:bg-orange-600 rounded-lg transition-colors flex items-center justify-center gap-2"
              >
                <Send size={16} />
                Enviar
              </button>
            </div>
          </div>
        )}
      </div>
    </>
  );
}
