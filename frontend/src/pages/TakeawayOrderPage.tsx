import { useEffect, useState } from "react";
import { useParams, useNavigate } from "react-router-dom";
import { useTranslation } from "react-i18next";
import { ArrowLeft, Loader2, Trash2 } from "lucide-react";
import { useCartStore } from "@/stores/useCartStore";
import { OrderCatalogPanel } from "@/components/orders/OrderCatalogPanel";
import { OrderCartPanel } from "@/components/orders/OrderCartPanel";
import { CustomerModal } from "@/components/orders/CustomerModal";
import { OrderRepository } from "@/db/repositories/OrderRepository";
import { CHANNEL_LABELS, type ChannelType } from "@/stores/useActiveChannelStore";
import type { Product } from "@/types/catalog";
import { getTranslatedName } from "@/types/catalog";
import { useToastStore } from "@/store/useToastStore";

export function TakeawayOrderPage() {
  const { t } = useTranslation();
  const { cartKey, orderLocalUuid } = useParams<{ cartKey?: string; orderLocalUuid?: string }>();
  const navigate = useNavigate();
  const addToast = useToastStore((s) => s.addToast);

  const isEditMode = !!orderLocalUuid;
  const effectiveCartKey = isEditMode ? `edit-${orderLocalUuid}` : (cartKey || "");

  const cart = useCartStore((s) => s.carts[effectiveCartKey]);
  const setCart = useCartStore((s) => s.setCart);
  const addItem = useCartStore((s) => s.addItem);
  const clearCart = useCartStore((s) => s.clearCart);
  const updateCustomer = useCartStore((s) => s.updateCustomer);

  const [showCustomerModal, setShowCustomerModal] = useState(false);

  useEffect(() => {
    const cartIsEmpty = cart && (!cart.items || cart.items.length === 0);
    if (!isEditMode || !orderLocalUuid || (cart && !cartIsEmpty)) return;

    let isMounted = true;
    const loadOrder = async () => {
      try {
        const order = await OrderRepository.findOrderWithItems(orderLocalUuid);
        if (!order) {
          if (isMounted) navigate("/");
          return;
        }

        const mappedChannel: ChannelType =
          order.order_type === "take_out" ? "takeout" : (order.order_type as ChannelType);

        const editCart = {
          tableUuid: order.table_id,
          tableNumber: "",
          areaName: undefined,
          channel: mappedChannel,
          items: order.items.map((item: any) => ({
            id: item.local_uuid,
            product: {
              uuid: item.product_id,
              name_translations: { es: item.product_name },
              base_price: String(item.unit_price),
            } as any,
            quantity: item.quantity,
            notes: item.notes || undefined,
          })),
          createdAt: order.created_at,
          customerName: (order as any).customer_name || undefined,
          customerPhone: (order as any).customer_phone || undefined,
          customerAddress: (order as any).delivery_address || undefined,
          customerReference: (order as any).delivery_notes || undefined,
          originalItemIds: order.items.map((item: any) => item.local_uuid),
        };

        if (isMounted) {
          setCart(effectiveCartKey, editCart);
        }
      } catch (error) {
        console.error("[TakeawayOrderPage] Error cargando pedido para edición:", error);
        if (isMounted) navigate("/");
      }
    };

    loadOrder();
    return () => { isMounted = false; };
  }, [isEditMode, orderLocalUuid, cart, effectiveCartKey, navigate, setCart]);

  useEffect(() => {
    if (!isEditMode && cartKey && !cart) {
      navigate("/");
    }
  }, [isEditMode, cartKey, cart, navigate]);

  if (!effectiveCartKey || !cart) {
    return (
      <div className="flex items-center justify-center h-64">
        <Loader2 className="animate-spin text-orange-500" size={48} />
      </div>
    );
  }

  const channelLabel = CHANNEL_LABELS[cart.channel as keyof typeof CHANNEL_LABELS] || CHANNEL_LABELS.dine_in;

  const handleSaveCustomer = (data: { name: string; phone: string; address: string; reference: string }) => {
    updateCustomer(effectiveCartKey, data);
    setShowCustomerModal(false);
  };

  const handleAddProduct = (product: Product) => {
    addItem(effectiveCartKey, product);
    addToast(
      "success",
      t("orders.product_added", {
        name: getTranslatedName(product.name_translations),
      })
    );
  };

  const handleDiscard = () => {
    if (confirm(t("orders.discard_confirm"))) {
      clearCart(effectiveCartKey);
      navigate("/");
    }
  };

  const handleModalClose = () => {
    if (confirm(t("orders.discard_customer_data_confirm"))) {
      clearCart(effectiveCartKey);
      navigate("/");
    }
  };

  return (
    <div className="flex flex-col h-full relative">
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
                <span className="text-2xl">{channelLabel.icon}</span>
                {isEditMode ? t("orders.editing_order") : t(`orders.channel_${cart.channel}`)}
              </h1>
              {isEditMode ? (
                <span className="text-xs px-2.5 py-1 rounded-full border border-yellow-500/50 bg-yellow-500/10 text-yellow-400">
                  {t("orders.edit_mode")}
                </span>
              ) : (
                <span className="text-xs px-2.5 py-1 rounded-full border border-blue-500/50 bg-blue-500/10 text-blue-400">
                  {t("orders.takeaway_label")}
                </span>
              )}
            </div>
            <p className="text-sm text-slate-400 mt-1">
              {isEditMode
                ? t("orders.edit_hint")
                : t("orders.takeaway_hint")}
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

      <div className="flex-1 flex gap-4 overflow-hidden">
        <OrderCatalogPanel onAddProduct={handleAddProduct} channel={cart.channel} />
        <OrderCartPanel
          cartKey={effectiveCartKey}
          tableId={null}
          title={isEditMode ? t("orders.editing_order") : t(`orders.channel_${cart.channel}`)}
        />
      </div>

      <CustomerModal
        open={showCustomerModal}
        initialPhone={cart.customerPhone || ""}
        onSave={handleSaveCustomer}
        onClose={handleModalClose}
      />
    </div>
  );
}
