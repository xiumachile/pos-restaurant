import { useEffect } from "react";
import { useParams, useNavigate } from "react-router-dom";
import { useTranslation } from "react-i18next";
import { ArrowLeft, Loader2, Package, Trash2 } from "lucide-react";
import { useCartStore } from "@/stores/useCartStore";
import { useQuery } from "@tanstack/react-query";
import { ordersService } from "@/services/ordersService";
import { OrderCatalogPanel } from "@/components/orders/OrderCatalogPanel";
import { OrderCartPanel } from "@/components/orders/OrderCartPanel";
import { CHANNEL_LABELS } from "@/stores/useActiveChannelStore";
import type { Product } from "@/types/catalog";
import { getTranslatedName } from "@/types/catalog";
import { useToastStore } from "@/store/useToastStore";

/**
 * Vista de toma de pedido para un pedido fuera de mesa (delivery/takeout).
 *
 * Usa el mismo patrón que OrderTakingPage pero sin concepto de mesa física:
 *   - Lee el cartKey desde la URL (viene de initOrder() con tableUuid=null)
 *   - Usa channel del cart (fijado al crear el pedido)
 *   - No muestra ChannelSelector (el canal ya está fijado)
 *
 * Si el cart no existe (por refresh manual o expiración), redirige a /.
 */
export function TakeawayOrderPage() {
  const { t } = useTranslation();
  const { cartKey } = useParams<{ cartKey: string }>();
  const navigate = useNavigate();
  const addToast = useToastStore((s) => s.addToast);

  const cart = useCartStore((s) => (cartKey ? s.carts[cartKey] : undefined));
  
  // Obtener el pedido existente si estamos en modo edición
  const editingOrderId = cart?.editingOrderId;
  const { data: existingOrder } = useQuery({
    queryKey: ['orders', 'existing', editingOrderId],
    queryFn: () => ordersService.getByUuid(editingOrderId!),
    enabled: !!editingOrderId,
  });
  const addItem = useCartStore((s) => s.addItem);
  const clearCart = useCartStore((s) => s.clearCart);

  // Redirigir si el cart no existe (ej: refresh con clave inválida)
  useEffect(() => {
    if (cartKey && !cart) {
      navigate("/");
    }
  }, [cartKey, cart, navigate]);

  if (!cartKey || !cart) {
    return (
      <div className="flex items-center justify-center h-64">
        <Loader2 className="animate-spin text-orange-500" size={48} />
      </div>
    );
  }

  // Fallback defensivo: si el canal no está en CHANNEL_LABELS, usar valores por defecto
  const channelLabel = CHANNEL_LABELS[cart.channel] ?? { icon: "📦" };

  const handleAddProduct = (product: Product) => {
    console.log("[TakeawayOrderPage] 🛒 handleAddProduct llamado");
    console.log("[TakeawayOrderPage] 🏷️ product.uuid:", product?.uuid);
    console.log("[TakeawayOrderPage] 📝 product.name:", product?.name_translations?.es || product?.name_translations?.en);
    addItem(cartKey, product);
    addToast(
      "success",
      t("orders.product_added", {
        name: getTranslatedName(product.name_translations),
      })
    );
  };

  const handleDiscard = () => {
    if (confirm(t("orders.discard_confirm", { defaultValue: "¿Descartar este pedido?" }))) {
      clearCart(cartKey);
      navigate("/");
    }
  };

  return (
    <div className="flex flex-col h-full">
      {/* Header */}
      <div className="flex items-center justify-between mb-4">
        <div className="flex items-center gap-4">
          <button
            onClick={() => navigate("/")}
            className="p-2 bg-slate-800 hover:bg-slate-700 rounded-lg transition-colors"
            title={t("tables.back_to_tables")}
          >
            <ArrowLeft size={20} />
          </button>

          <div>
            <div className="flex items-center gap-3">
              <h1 className="text-2xl font-bold flex items-center gap-3">
                <span className="text-2xl">{(channelLabel?.icon ?? "📦")}</span>
                {t(`orders.channel_${cart.channel}`)}
              </h1>
              <span className="text-xs px-2.5 py-1 rounded-full border border-blue-500/50 bg-blue-500/10 text-blue-400">
                {t("orders.takeaway_label", { defaultValue: "Fuera de mesa" })}
              </span>
            </div>
            <p className="text-sm text-slate-400 mt-1">
              {t("orders.takeaway_hint", {
                defaultValue: "Pedido sin mesa asignada. Al enviar se enviará directamente.",
              })}
            </p>
          </div>
        </div>

        <button
          onClick={handleDiscard}
          className="flex items-center gap-2 px-3 py-2 bg-red-900/30 hover:bg-red-900/50 border border-red-800/50 rounded-lg text-red-400 text-sm transition-colors"
          title={t("orders.discard")}
        >
          <Trash2 size={16} />
          {t("orders.discard")}
        </button>
      </div>

      {/* Indicador de items existentes (solo lectura) */}
      {editingOrderId && existingOrder && existingOrder.items.length > 0 && (
        <div className="mb-3 bg-blue-900/20 border border-blue-700/50 rounded-lg p-3">
          <p className="text-sm font-medium text-blue-200 mb-2">
            📋 Items existentes en {existingOrder.order_number}:
          </p>
          <div className="flex flex-wrap gap-2">
            {existingOrder.items.map((item: any) => (
              <span key={item.uuid} className="text-xs bg-blue-800/50 text-blue-100 px-2 py-1 rounded">
                {item.name} x{item.quantity}
              </span>
            ))}
          </div>
          <p className="text-xs text-blue-300 mt-2 italic">
            ℹ️ Los items nuevos que agregues se añadirán a este pedido
          </p>
        </div>
      )}

      {/* Panel de catálogo + carrito */}
      <div className="flex-1 flex gap-4 overflow-hidden">
        <OrderCatalogPanel onAddProduct={handleAddProduct} channel={cart.channel} />
        <OrderCartPanel
          cartKey={cartKey}
          tableId={null}
          title={t(`orders.channel_${cart.channel}`)}
        />
      </div>
    </div>
  );
}
