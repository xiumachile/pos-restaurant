import { useState } from "react";
import { useCartStore } from "@/stores/useCartStore";
import { ordersService } from "@/services/ordersService";
import { useInvalidateTables } from "@/hooks/useTables";
import { getTranslatedName, formatPrice, parsePrice } from "@/types/catalog";
import { Plus, Minus, Trash2, Send, ShoppingCart, Loader2, CheckCircle2, AlertCircle } from "lucide-react";

interface OrderCartPanelProps {
  tableUuid: string;
  tableNumber: string;
}

type FeedbackState =
  | { type: "idle" }
  | { type: "loading"; message: string }
  | { type: "success"; message: string }
  | { type: "error"; message: string };

export function OrderCartPanel({ tableUuid, tableNumber }: OrderCartPanelProps) {
  const cart = useCartStore((s) => s.carts[tableUuid]);
  const updateQuantity = useCartStore((s) => s.updateQuantity);
  const removeItem = useCartStore((s) => s.removeItem);
  const clearCart = useCartStore((s) => s.clearCart);
  const getTotals = useCartStore((s) => s.getTotals);
  const invalidateTables = useInvalidateTables();

  const [feedback, setFeedback] = useState<FeedbackState>({ type: "idle" });

  const totals = getTotals(tableUuid);
  const items = cart?.items ?? [];

  const handleSendOrder = async () => {
    if (items.length === 0) return;

    // Verificar que todos los items tengan menu_item_uuid
    const missingMenuItem = items.find((i) => !i.product.menu_item_uuid);
    if (missingMenuItem) {
      setFeedback({
        type: "error",
        message: `El producto "${getTranslatedName(missingMenuItem.product.name_translations)}" no tiene MenuItem asociado. Contacta al administrador.`,
      });
      return;
    }

    setFeedback({ type: "loading", message: "Creando pedido..." });

    try {
      // 1. Crear order draft
      const order = await ordersService.create({
        type: "dine_in",
        table_uuid: tableUuid,
      });

      setFeedback({ type: "loading", message: "Agregando items..." });

      // 2. Agregar cada item
      for (const item of items) {
        await ordersService.addItem(order.uuid, {
          menu_item_uuid: item.product.menu_item_uuid!,
          quantity: item.quantity,
          notes: item.notes,
        });
      }

      setFeedback({ type: "loading", message: "Confirmando pedido..." });

      // 3. Confirmar (dispara reserva de stock + impresión en cocina)
      await ordersService.confirm(order.uuid);

      // 4. Éxito: limpiar carrito y refrescar mesas
      clearCart(tableUuid);
      invalidateTables();

      setFeedback({
        type: "success",
        message: `✓ Pedido ${order.order_number} enviado a cocina`,
      });

      // Auto-limpiar el mensaje después de 3 segundos
      setTimeout(() => setFeedback({ type: "idle" }), 3000);
    } catch (error: any) {
      const message =
        error?.response?.data?.message ||
        error?.response?.data?.error ||
        error?.message ||
        "Error al enviar el pedido";
      setFeedback({ type: "error", message });
    }
  };

  const isProcessing = feedback.type === "loading";

  return (
    <aside className="w-96 bg-slate-800/50 border border-slate-700 rounded-xl flex flex-col overflow-hidden">
      {/* Header */}
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

      {/* Items */}
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
                  disabled={isProcessing}
                  className="p-1 hover:bg-red-500/20 rounded ml-2 disabled:opacity-40"
                >
                  <Trash2 size={15} className="text-red-400" />
                </button>
              </div>

              <div className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                  <button
                    onClick={() => updateQuantity(tableUuid, item.id, item.quantity - 1)}
                    disabled={isProcessing}
                    className="p-1.5 bg-slate-700 hover:bg-slate-600 rounded disabled:opacity-40"
                  >
                    <Minus size={13} />
                  </button>
                  <span className="text-base font-bold w-7 text-center">
                    {item.quantity}
                  </span>
                  <button
                    onClick={() => updateQuantity(tableUuid, item.id, item.quantity + 1)}
                    disabled={isProcessing}
                    className="p-1.5 bg-slate-700 hover:bg-slate-600 rounded disabled:opacity-40"
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

      {/* Feedback */}
      {feedback.type !== "idle" && (
        <div
          className={`mx-3 mb-2 p-3 rounded-lg text-sm flex items-start gap-2 ${
            feedback.type === "loading"
              ? "bg-blue-900/30 border border-blue-700 text-blue-200"
              : feedback.type === "success"
              ? "bg-green-900/30 border border-green-700 text-green-200"
              : "bg-red-900/30 border border-red-700 text-red-200"
          }`}
        >
          {feedback.type === "loading" && (
            <Loader2 size={16} className="animate-spin flex-shrink-0 mt-0.5" />
          )}
          {feedback.type === "success" && (
            <CheckCircle2 size={16} className="flex-shrink-0 mt-0.5" />
          )}
          {feedback.type === "error" && (
            <AlertCircle size={16} className="flex-shrink-0 mt-0.5" />
          )}
          <span className="flex-1">{feedback.message}</span>
        </div>
      )}

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
            disabled={items.length === 0 || isProcessing}
            className="px-3 py-2.5 bg-slate-700 hover:bg-slate-600 rounded-lg text-sm disabled:opacity-40"
          >
            Limpiar
          </button>
          <button
            onClick={handleSendOrder}
            disabled={items.length === 0 || isProcessing}
            className="flex-1 px-4 py-2.5 bg-orange-500 hover:bg-orange-600 rounded-lg font-semibold flex items-center justify-center gap-2 disabled:opacity-40 disabled:cursor-not-allowed"
          >
            {isProcessing ? (
              <>
                <Loader2 size={16} className="animate-spin" />
                Enviando...
              </>
            ) : (
              <>
                <Send size={16} />
                Enviar a Cocina
              </>
            )}
          </button>
        </div>
      </div>
    </aside>
  );
}
