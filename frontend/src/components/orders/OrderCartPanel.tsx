import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { useTranslation } from 'react-i18next';
import { useCartStore } from "@/stores/useCartStore";
import { useInvalidateTables } from "@/hooks/useTables";
import { useInvalidateCashier } from "@/hooks/usePayments";
import { useTableOrders } from "@/hooks/useTableOrders";
import { aggregateOrders } from "@/types/orders";
import { getTranslatedName, formatPrice, parsePrice } from "@/types/catalog";
import { IVA_PERCENTAGE } from "@/config/tax";
import { useAuthStore } from "@/store/useAuthStore";
import { useCapabilities } from "@/hooks/useCapabilities";
import { CapabilityKey } from "@/types/capabilities";
import { useDefaultNotes } from "@/hooks/useDefaultNotes";
import { getDefaultNoteText } from "@/types/defaultNotes";
import { useSyncStore } from "@/store/useSyncStore";
import { OrderRepository } from "@/db/repositories/OrderRepository";
import { Plus, Minus, Trash2, Send, ShoppingCart, Loader2, CheckCircle2, AlertCircle, WifiOff } from "lucide-react";
import { ActiveOrderItems } from "./ActiveOrderItems";
import { mergeAuthContext } from "@/services/authContext";
import { channelToOrderType } from "@/stores/useActiveChannelStore";

interface OrderCartPanelProps {
  /** Clave del cart en el store (cartKey para mesa, "takeaway-{uuid}" para fuera de mesa) */
  cartKey: string;
  /** ID de la mesa para el backend. null para pedidos fuera de mesa */
  tableId: string | null;
  /** Identificador a mostrar junto al título del carrito (ej: número de mesa) */
  title: string;
}

type FeedbackState =
  | { type: "idle" }
  | { type: "loading"; message: string }
  | { type: "success"; message: string }
  | { type: "error"; message: string };

export function OrderCartPanel({ cartKey, tableId, title }: OrderCartPanelProps) {
  const { t, i18n } = useTranslation();
  const navigate = useNavigate();
  const cart = useCartStore((s) => s.carts[cartKey]);
  const { capabilities } = useCapabilities();
  const clearAuth = useAuthStore((s) => s.clearAuth);
  const updateQuantity = useCartStore((s) => s.updateQuantity);
  const updateItemNotes = useCartStore((s) => s.updateItemNotes);
  const removeItem = useCartStore((s) => s.removeItem);
  const clearCart = useCartStore((s) => s.clearCart);
  const getTotals = useCartStore((s) => s.getTotals);
  const invalidateTables = useInvalidateTables();
  const invalidateCashier = useInvalidateCashier();

  const user = useAuthStore((s) => s.user);
  const syncStatus = useSyncStore((s) => s.status);

  const { data: activeOrders = [], refetch: refetchActiveOrders } = useTableOrders(tableId);
  const [feedback, setFeedback] = useState<FeedbackState>({ type: "idle" });
  const [noteEditingId, setNoteEditingId] = useState<string | null>(null);
  const [noteInput, setNoteInput] = useState("");
  const { notes: defaultNotes, isLoading: isLoadingNotes } = useDefaultNotes();

  const totals = getTotals(cartKey);
  const items = cart?.items ?? [];

  const aggregated = aggregateOrders(activeOrders);
  const previousOrdersTotal = aggregated.total;
  const grandTotal = previousOrdersTotal + totals.total;

  const handleSendOrder = async () => {
    if (items.length === 0 || !user) return;

    setFeedback({ type: "loading", message: `💾 ${t("orders.sending")} ${items.length} items...` });

    try {
      // 1. Crear pedido (operación crítica)
      // REGLA DE DOMINIO: Si hay mesa (tableId), el pedido SIEMPRE es dine_in
      // No puede existir un pedido delivery/takeout con mesa asignada
      const orderType = tableId ? 'dine_in' : channelToOrderType(cart.channel);
      
      const order = await OrderRepository.createWithItems(
        mergeAuthContext({
          table_id: tableId,
          order_type: orderType,
        }),
        items.map(item => ({
          product_id: item.product.uuid,
          product_name: getTranslatedName(item.product.name_translations),
          quantity: item.quantity,
          unit_price: parsePrice(item.product.base_price),
          notes: item.notes,
        }))
      );

      // 2. Limpiar carrito y actualizar UI inmediatamente
      clearCart(cartKey);
      refetchActiveOrders();

      // 3. Mostrar feedback de éxito
      setFeedback({
        type: "success",
        message: syncStatus === "offline"
          ? "✓ Pedido guardado offline. Sincronizará al reconectar."
          : "✓ Pedido enviado a cocina",
      });

      // 4. Invalidar cache (operación no crítica, no debe fallar el flujo)
      try {
        await invalidateTables();
        await invalidateCashier();
      } catch (cacheError) {
        console.warn("[OrderCartPanel] Error invalidando cache (no crítico):", cacheError);
        // No mostramos error al usuario porque el pedido ya se guardó exitosamente
      }

      // 5. Redirección inteligente según configuración de la empresa
      setTimeout(async () => {
        const returnToLogin = capabilities[CapabilityKey.RETURN_TO_LOGIN_AFTER_ORDER]?.is_enabled;
        if (returnToLogin) {
          await clearAuth();
          navigate("/login", { replace: true });
        } else {
          navigate("/");
        }
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
    <aside className="w-full md:w-80 lg:w-96 bg-slate-800/50 border border-slate-700 rounded-xl flex flex-col overflow-hidden">
      {/* Header */}
      <div className="p-4 border-b border-slate-700 flex items-center justify-between">
        <div className="flex items-center gap-2">
          <ShoppingCart size={20} className="text-orange-400" />
          <h2 className="text-lg font-bold">{t("orders.cart_title")} {title}</h2>
        </div>
        <div className="flex items-center gap-2">
          {syncStatus === "offline" && (
            <div className="flex items-center gap-1 px-2 py-0.5 bg-yellow-500/20 border border-yellow-500/40 rounded-full">
              <WifiOff size={12} className="text-yellow-400" />
              <span className="text-xs text-yellow-300">{t("sync.offline")}</span>
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
        <ActiveOrderItems orders={activeOrders} />

        {hasActiveOrders && items.length > 0 && (
          <div className="flex items-center gap-2 py-1">
            <div className="flex-1 h-px bg-slate-700" />
            <span className="text-xs text-orange-400 uppercase tracking-wide font-semibold">
              {t("orders.adding_now")}
            </span>
            <div className="flex-1 h-px bg-slate-700" />
          </div>
        )}

        {items.length === 0 && !hasActiveOrders ? (
          <div className="text-center py-12 text-slate-400">
            <ShoppingCart size={48} className="mx-auto mb-3 opacity-30" />
            <p className="text-sm">
              {t("orders.no_items")}
              <br />
              {t("orders.no_items_desc")}
            </p>
          </div>
        ) : items.length === 0 && hasActiveOrders ? (
          <div className="text-center py-6 text-slate-400">
            <ShoppingCart size={32} className="mx-auto mb-2 opacity-30" />
            <p className="text-xs">{t("orders.add_more")}</p>
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
                  <p className="text-xs text-slate-400">
                    {formatPrice(item.product.base_price)} c/u
                  </p>
                </div>
                <button
                  onClick={() => removeItem(cartKey, item.id)}
                  disabled={isProcessing}
                  className="min-w-[44px] min-h-[44px] p-2 hover:bg-red-500/20 rounded-lg ml-2 disabled:opacity-40 flex items-center justify-center"
                 aria-label="Eliminar"><Trash2 size={20} className="text-red-400" />
                </button>
              </div>

              {/* Notas */}
              {item.notes && (
                <div className="mt-2 mb-2 p-2 bg-yellow-900/20 border border-yellow-700/50 rounded text-xs text-yellow-200 flex items-start gap-2">
                  <span>📝</span>
                  <span className="flex-1">{item.notes}</span>
                  <button
                    onClick={() => {
                      setNoteEditingId(item.id);
                      setNoteInput(item.notes || "");
                    }}
                    className="text-yellow-400 hover:text-yellow-300"
                  >
                    Editar
                  </button>
                </div>
              )}

              <div className="flex items-center justify-between mt-2">
                <div className="flex items-center gap-2">
                  <button
                    onClick={() => updateQuantity(cartKey, item.id, item.quantity - 1)}
                    disabled={isProcessing}
                    className="min-w-[44px] min-h-[44px] p-2 bg-slate-700 hover:bg-slate-600 rounded-lg disabled:opacity-40 flex items-center justify-center"
                   aria-label="Disminuir cantidad"><Minus size={20} />
                  </button>
                  <span className="text-base font-bold w-7 text-center">
                    {item.quantity}
                  </span>
                  <button
                    onClick={() => updateQuantity(cartKey, item.id, item.quantity + 1)}
                    disabled={isProcessing}
                    className="min-w-[44px] min-h-[44px] p-2 bg-slate-700 hover:bg-slate-600 rounded-lg disabled:opacity-40 flex items-center justify-center"
                   aria-label="Aumentar cantidad"><Plus size={20} />
                  </button>
                  <button
                    onClick={() => {
                      setNoteEditingId(item.id);
                      setNoteInput(item.notes || "");
                    }}
                    disabled={isProcessing}
                    className="p-1.5 bg-slate-700 hover:bg-slate-600 rounded disabled:opacity-40 ml-2"
                    title={t("orders.add_note")}
                  >
                    📝
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
              <span>{t("orders.previous_consumption")}</span>
              <span>{formatPrice(previousOrdersTotal)}</span>
            </div>
            {items.length > 0 && (
              <div className="flex justify-between text-xs text-orange-300">
                <span>{t("orders.adding_now")}</span>
                <span>{formatPrice(totals.total)}</span>
              </div>
            )}
            <div className="flex justify-between text-base font-bold pt-1 border-t border-slate-700">
              <span>{t("orders.table_total")}</span>
              <span className="text-orange-400">{formatPrice(grandTotal)}</span>
            </div>
          </>
        ) : (
          <>
            <div className="flex justify-between text-sm">
              <span className="text-slate-400">{t("orders.net_amount", "Neto")}</span>
              <span>{formatPrice(totals.subtotal)}</span>
            </div>
            <div className="flex justify-between text-sm">
              <span className="text-slate-400">IVA ({IVA_PERCENTAGE}%)</span>
              <span>{formatPrice(totals.tax)}</span>
            </div>
            <div className="flex justify-between text-lg font-bold pt-2 border-t border-slate-700">
              <span>{t("orders.total")}</span>
              <span className="text-orange-400">{formatPrice(totals.total)}</span>
            </div>
          </>
        )}

        <div className="flex gap-2 pt-2">
          <button
            onClick={() => clearCart(cartKey)}
            disabled={items.length === 0 || isProcessing}
            className="px-3 py-2.5 bg-slate-700 hover:bg-slate-600 rounded-lg text-sm disabled:opacity-40"
          >
            {t("orders.clear")}
          </button>
          <button
            onClick={handleSendOrder}
            disabled={items.length === 0 || isProcessing}
            className="flex-1 px-4 py-2.5 bg-orange-500 hover:bg-orange-600 rounded-lg font-semibold flex items-center justify-center gap-2 disabled:opacity-40 disabled:cursor-not-allowed"
          >
            {isProcessing ? (
              <>
                <Loader2 size={16} className="animate-spin" />
                {t("orders.sending")}
              </>
            ) : (
              <>
                <Send size={16} />
                {t("orders.send_to_kitchen")}
              </>
            )}
          </button>
        </div>
      </div>

      {/* Modal de Notas */}
      {noteEditingId && (
        <div className="fixed inset-0 bg-black/80 z-[60] flex items-center justify-center p-4" onClick={() => setNoteEditingId(null)}>
          <div className="bg-slate-800 rounded-xl p-6 w-full max-w-sm border border-slate-700" onClick={(e) => e.stopPropagation()}>
            <h3 className="text-lg font-bold text-white mb-4">{t("orders.add_note")}</h3>
            
            {/* Notas predefinidas dinámicas */}
            <div className="flex flex-wrap gap-2 mb-4">
              {isLoadingNotes ? (
                <span className="text-xs text-slate-400">Cargando notas...</span>
              ) : defaultNotes.length === 0 ? (
                <span className="text-xs text-slate-400">No hay notas predefinidas configuradas</span>
              ) : (
                defaultNotes.map((note) => {
                  const noteText = getDefaultNoteText(note, i18n.language);
                  return (
                    <button
                      key={note.uuid}
                      onClick={() => setNoteInput((prev) => {
                        return prev.includes(noteText) ? prev.replace(noteText, "").trim() : (prev ? prev + ", " + noteText : noteText);
                      })}
                      className="px-3 py-1.5 bg-slate-700 hover:bg-slate-600 rounded-full text-xs text-slate-200 border border-slate-600"
                    >
                      {noteText}
                    </button>
                  );
                })
              )}
            </div>

            {/* Input personalizado */}
            <textarea
              value={noteInput}
              onChange={(e) => setNoteInput(e.target.value)}
              placeholder={t("orders.custom_note")}
              className="w-full p-3 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm mb-4 focus:ring-2 focus:ring-orange-500 focus:border-transparent outline-none"
              rows={3}
            />

            <div className="flex gap-2">
              <button
                onClick={() => {
                  updateItemNotes(cartKey, noteEditingId, noteInput.trim());
                  setNoteEditingId(null);
                  setNoteInput("");
                }}
                className="flex-1 px-4 py-2.5 bg-orange-500 hover:bg-orange-600 rounded-lg font-semibold text-white"
              >
                {t("common.save")}
              </button>
              <button
                onClick={() => {
                  updateItemNotes(cartKey, noteEditingId, "");
                  setNoteEditingId(null);
                  setNoteInput("");
                }}
                className="px-4 py-2.5 bg-slate-700 hover:bg-slate-600 rounded-lg font-semibold text-slate-300"
              >
                {t("common.clear")}
              </button>
              <button
                onClick={() => {
                  setNoteEditingId(null);
                  setNoteInput("");
                }}
                className="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 border border-slate-600 rounded-lg font-semibold text-slate-400"
              >
                {t("common.cancel")}
              </button>
            </div>
          </div>
        </div>
      )}
    </aside>
  );
}
