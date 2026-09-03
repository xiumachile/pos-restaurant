import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { useCartStore } from "@/stores/useCartStore";
import { useInvalidateTables } from "@/hooks/useTables";
import { useTableOrders } from "@/hooks/useTableOrders";
import { aggregateOrders } from "@/types/orders";
import { getTranslatedName, formatPrice, parsePrice } from "@/types/catalog";
import { useAuthStore } from "@/store/useAuthStore";
import { useSyncStore } from "@/store/useSyncStore";
import { OrderRepository } from "@/db/repositories/OrderRepository";
import { Plus, Minus, Trash2, Send, ShoppingCart, Loader2, CheckCircle2, AlertCircle, WifiOff } from "lucide-react";
import { ActiveOrderItems } from "./ActiveOrderItems";

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
  const navigate = useNavigate();
  const cart = useCartStore((s) => s.carts[tableUuid]);
  const updateQuantity = useCartStore((s) => s.updateQuantity);
  const removeItem = useCartStore((s) => s.removeItem);
  const clearCart = useCartStore((s) => s.clearCart);
  const getTotals = useCartStore((s) => s.getTotals);
  const invalidateTables = useInvalidateTables();

  const user = useAuthStore((s) => s.user);
  const syncStatus = useSyncStore((s) => s.status);

  const { data: activeOrders = [], refetch: refetchActiveOrders } = useTableOrders(tableUuid);
  const [feedback, setFeedback] = useState<FeedbackState>({ type: "idle" });

  const totals = getTotals(tableUuid);
  const items = cart?.items ?? [];

  const aggregated = aggregateOrders(activeOrders);
  const previousOrdersTotal = aggregated.total;
  const grandTotal = previousOrdersTotal + totals.total;

  /**
   * Flujo OFFLINE-FIRST:
   * 1. Crear pedido en SQLite local (encola order/create automáticamente)
   * 2. Agregar items locales (encola item/create con idempotency_key estable)
   * 3. NO llamar confirm() — SyncEngine hace DRAFT → items → UPDATE confirmed
   * 4. Limpiar carrito local + refrescar UI
   * 5. Sync automático en background (worker cada 15s o trigger manual)
   */
  const handleSendOrder = async () => {
    if (items.length === 0 || !user) return;

    setFeedback({ type: "loading", message: "💾 Guardando pedido localmente..." });

    try {
      // 1. Crear pedido local (SQLite + encolado automático)
      const order = await OrderRepository.create({
        company_id: String(user.company_id),
        branch_id: String(user.branch_id),
        table_id: tableUuid,
        order_type: "dine_in",
      });

      setFeedback({
        type: "loading",
        message: `📦 Agregando ${items.length} items...`,
      });

      // 2. Agregar items locales (encolado automático con idempotency_key estable)
      for (const item of items) {
        await OrderRepository.addItem(order.local_uuid, {
          product_id: item.product.uuid,
          product_name: getTranslatedName(item.product.name_translations),
          quantity: item.quantity,
          unit_price: parsePrice(item.product.base_price),
          notes: item.notes,
        });
      }

      // 3. Limpiar carrito local
      clearCart(tableUuid);
      refetchActiveOrders();

      // 4. Disparar sync inmediatamente (no esperar al worker de 15s)
      setFeedback({
        type: "loading",
        message: syncStatus === "offline"
          ? "✓ Guardado offline. Sincronizará al reconectar."
          : "✓ Guardado. Sincronizando con cocina...",
      });

      if (syncStatus !== "offline") {
        try {
          await useSyncStore.getState().triggerFullSync();
          
          // Esperar 500ms para que el backend procese completamente
          await new Promise(resolve => setTimeout(resolve, 500));
          
          // Refrescar pedidos activos con retry
          await refetchActiveOrders();
          await new Promise(resolve => setTimeout(resolve, 300));
          await refetchActiveOrders();
          
          // Forzar refetch de tables DESPUÉS del sync (el backend ya actualizó el status)
          invalidateTables();
        } catch (syncErr) {
          console.warn("[OrderCartPanel] Sync diferida:", syncErr);
        }
      } else {
        // En offline, invalidamos igual porque ya hicimos el update optimista
        invalidateTables();
      }

      // 5. Feedback final
      setFeedback({
        type: "success",
        message: syncStatus === "offline"
          ? "✓ Pedido guardado (offline)"
          : "✓ Pedido enviado a cocina",
      });

      // 6. Navegar a Mesas
      setTimeout(() => {
        navigate("/");
      }, 1200);
    } catch (error: any) {
      console.error("[OrderCartPanel] Error creando pedido:", error);
      setFeedback({
        type: "error",
        message: error?.message || "Error al guardar el pedido",
      });
    }
  };

  const isProcessing = feedback.type === "loading";
  const hasActiveOrders = activeOrders.length > 0;

  return (
    <aside className="w-96 bg-slate-800/50 border border-slate-700 rounded-xl flex flex-col overflow-hidden">
      {/* Header */}
      <div className="p-4 border-b border-slate-700 flex items-center justify-between">
        <div className="flex items-center gap-2">
          <ShoppingCart size={20} className="text-orange-400" />
          <h2 className="text-lg font-bold">Pedido · Mesa {tableNumber}</h2>
        </div>
        <div className="flex items-center gap-2">
          {syncStatus === "offline" && (
            <div className="flex items-center gap-1 px-2 py-0.5 bg-yellow-500/20 border border-yellow-500/40 rounded-full">
              <WifiOff size={12} className="text-yellow-400" />
              <span className="text-xs text-yellow-300">Offline</span>
            </div>
          )}
          {items.length > 0 && (
            <span className="px-2 py-0.5 bg-orange-500 text-white text-sm font-bold rounded-full">
              {totals.itemCount}
            </span>
          )}
        </div>
      </div>

      {/* Scroll container */}
      <div className="flex-1 overflow-y-auto p-3 space-y-3">
        {/* Sección azul: pedidos activos agrupados */}
        <ActiveOrderItems orders={activeOrders} />

        {/* Separador si hay ambos */}
        {hasActiveOrders && items.length > 0 && (
          <div className="flex items-center gap-2 py-1">
            <div className="flex-1 h-px bg-slate-700" />
            <span className="text-xs text-orange-400 uppercase tracking-wide font-semibold">
              Agregando ahora
            </span>
            <div className="flex-1 h-px bg-slate-700" />
          </div>
        )}

        {/* Sección naranja: items del carrito local */}
        {items.length === 0 && !hasActiveOrders ? (
          <div className="text-center py-12 text-slate-500">
            <ShoppingCart size={48} className="mx-auto mb-3 opacity-30" />
            <p className="text-sm">
              Sin items aún.
              <br />
              Toca un producto del catálogo para agregarlo.
            </p>
          </div>
        ) : items.length === 0 && hasActiveOrders ? (
          <div className="text-center py-6 text-slate-500">
            <ShoppingCart size={32} className="mx-auto mb-2 opacity-30" />
            <p className="text-xs">Agrega más productos al pedido actual.</p>
          </div>
        ) : (
          items.map((item) => (
            <div
              key={item.id}
              className="bg-slate-800 rounded-lg p-3 border border-orange-500/30"
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
          {feedback.type === "loading" && <Loader2 size={16} className="animate-spin flex-shrink-0 mt-0.5" />}
          {feedback.type === "success" && <CheckCircle2 size={16} className="flex-shrink-0 mt-0.5" />}
          {feedback.type === "error" && <AlertCircle size={16} className="flex-shrink-0 mt-0.5" />}
          <span className="flex-1">{feedback.message}</span>
        </div>
      )}

      {/* Totales + acciones */}
      <div className="border-t border-slate-700 p-4 space-y-2 bg-slate-900/50">
        {hasActiveOrders ? (
          <>
            <div className="flex justify-between text-xs text-blue-300">
              <span>Consumo anterior</span>
              <span>{formatPrice(previousOrdersTotal)}</span>
            </div>
            {items.length > 0 && (
              <div className="flex justify-between text-xs text-orange-300">
                <span>Agregando ahora</span>
                <span>{formatPrice(totals.total)}</span>
              </div>
            )}
            <div className="flex justify-between text-base font-bold pt-1 border-t border-slate-700">
              <span>Total mesa</span>
              <span className="text-orange-400">{formatPrice(grandTotal)}</span>
            </div>
          </>
        ) : (
          <>
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
          </>
        )}

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
