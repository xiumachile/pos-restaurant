import { useState, useMemo } from "react";
import { useNavigate } from "react-router-dom";
import { useTranslation } from "react-i18next";
import { useActiveOrders, type ActiveOrder } from "@/hooks/useActiveOrders";
import { CHANNEL_LABELS } from "@/stores/useActiveChannelStore";
import { Edit3, Package, Truck, Clock, Loader2 } from "lucide-react";
import { formatPrice } from "@/types/catalog";

type OrderTab = "delivery" | "takeout";

export function OrdersPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { orders, loading } = useActiveOrders();

  const [activeTab, setActiveTab] = useState<OrderTab>("delivery");

  // Filtrar pedidos por canal (mapeando takeout <-> take_out)
  const activeOrders = useMemo(() => {
    return orders.filter((order) => {
      if (activeTab === "delivery") return order.order_type === "delivery";
      if (activeTab === "takeout") return order.order_type === "takeout";
      return false;
    });
  }, [orders, activeTab]);

  const handleEditOrder = (localUuid: string) => {
    navigate(`/orders/takeaway/edit/${localUuid}`);
  };

  const TabButton = ({ tab, label, icon: Icon }: { tab: OrderTab; label: string; icon: any }) => (
    <button
      onClick={() => setActiveTab(tab)}
      className={`flex items-center gap-2 px-6 py-3 font-medium transition-colors border-b-2 ${
        activeTab === tab
          ? "border-orange-500 text-orange-500"
          : "border-transparent text-slate-400 hover:text-slate-200"
      }`}
    >
      <Icon size={18} />
      {label}
    </button>
  );

  if (loading) {
    return (
      <div className="flex flex-col items-center justify-center h-full">
        <Loader2 className="animate-spin text-orange-500" size={48} />
        <p className="mt-4 text-slate-400">Cargando pedidos...</p>
      </div>
    );
  }

  return (
    <div className="flex flex-col h-full p-6">
      <div className="mb-6">
        <h1 className="text-3xl font-bold text-white mb-2">{t("orders.title")}</h1>
        <p className="text-slate-400">{t("orders.active_orders_hint", { defaultValue: "Gestiona los pedidos en curso" })}</p>
      </div>

      <div className="flex border-b border-slate-700 mb-6">
        <TabButton tab="delivery" label={t("orders.active_delivery")} icon={Truck} />
        <TabButton tab="takeout" label={t("orders.active_takeout")} icon={Package} />
      </div>

      <div className="flex-1 overflow-auto">
        {activeOrders.length === 0 ? (
          <div className="flex flex-col items-center justify-center h-64 text-slate-500">
            <Clock size={48} className="mb-4 opacity-50" />
            <p className="text-lg">{t("orders.no_active_orders")}</p>
          </div>
        ) : (
          <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            {activeOrders.map((order) => {
              const channelKey = order.order_type;
              const channelInfo = CHANNEL_LABELS[channelKey as keyof typeof CHANNEL_LABELS];
              return (
                <div
                  key={order.local_uuid}
                  className="bg-slate-800 border border-slate-700 rounded-xl p-5 hover:border-orange-500/50 transition-all"
                >
                  <div className="flex items-start justify-between mb-4">
                    <div className="flex items-center gap-3">
                      <span className="text-2xl">{channelInfo?.icon || "📦"}</span>
                      <div>
                        <h3 className="font-semibold text-white">
                          {order.customer_name || t("orders.no_customer", "Sin cliente")}
                        </h3>
                        <p className="text-sm text-slate-400">
                          {new Date(order.created_at).toLocaleTimeString("es-CL", {
                            hour: "2-digit",
                            minute: "2-digit",
                          })}
                        </p>
                      </div>
                    </div>
                    <span className="text-xs px-2 py-1 rounded-full bg-slate-700 text-slate-300">
                      {order.item_count} items
                    </span>
                  </div>

                  <div className="flex items-center justify-between mt-4 pt-4 border-t border-slate-700">
                    <span className="text-xl font-bold text-orange-400">
                      {formatPrice(order.grand_total)}
                    </span>
                    <button
                      onClick={() => handleEditOrder(order.local_uuid)}
                      className="flex items-center gap-2 px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg font-medium transition-colors text-sm"
                    >
                      <Edit3 size={16} />
                      {t("orders.edit_order")}
                    </button>
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </div>
    </div>
  );
}
