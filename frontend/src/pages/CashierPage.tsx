import { useState, useEffect } from "react";
import { ChannelTabs } from "@/components/orders/ChannelTabs";
import type { ChannelFilter } from "@/hooks/useActiveOrders";
import { useTranslation } from 'react-i18next';
import {
  useCashierDashboard,
  useTablesWithBills,
  useInvalidateCashier,
} from "@/hooks/usePayments";
import { useActiveOrders } from "@/hooks/useActiveOrders";
import { CashSessionStatus } from "@/components/cashier/CashSessionStatus";
import { PaymentModal } from "@/components/cashier/PaymentModal";
import { OrderPaymentModal } from "@/components/cashier/OrderPaymentModal";
import type { Order } from "@/types/orders";
import type { TableBill } from "@/types/tableBill";
import {
  Loader2,
  Receipt,
  Users,
  Clock,
  AlertCircle,
} from "lucide-react";
import { formatPrice } from "@/types/catalog";
import { CapabilityGate } from "@/components/CapabilityGate";
import { CapabilityKey } from "@/types/capabilities";
import { useCapabilities } from "@/hooks/useCapabilities";

/**
 * Página de Caja.
 * Diseño: barra compacta de sesión arriba + grid de cuentas por cobrar
 * como protagonista absoluto de la pantalla.
 */
export function CashierPage() {
  const { t } = useTranslation();
  const { data: dashboard, isLoading: loadingDashboard } = useCashierDashboard();
  const { data: tablesWithBills = [], isLoading: loadingTables } = useTablesWithBills();
  const [paymentEntity, setPaymentEntity] = useState<{ uuid: string; type: 'table' | 'order'; order?: Order } | null>(null);
  const [activeChannel, setActiveChannel] = useState<ChannelFilter>('all');
  const { data: activeOrders = [] } = useActiveOrders(activeChannel);
  const invalidateCashier = useInvalidateCashier();

  // FIX: Forzar invalidateCashier al montar la página.
  // Esto garantiza que en modo offline se ejecute el bypass de React Query
  // y se carguen las mesas con cuenta desde localPaymentsService.
  // Sin esto, el cache puede estar vacío y la página aparece sin mesas
  // hasta que otro evento (como crear un pedido) fuerce el refetch.
  useEffect(() => {
    console.debug("[CashierPage] 🔍 Montando, forzando invalidateCashier");
    invalidateCashier();
  }, []);

  const isSessionOpen = !!dashboard?.current_session;
  const { isFeatureEnabled } = useCapabilities();
  const requiresCashierSession = isFeatureEnabled(CapabilityKey.REQUIRES_CASHIER_SESSION);

  const getElapsedMinutes = (isoString: string | null) => {
  
    if (!isoString) return "";
    const minutes = Math.floor(
      (Date.now() - new Date(isoString).getTime()) / 60000
    );
    if (minutes < 1) return "< 1 min";
    if (minutes < 60) return `${minutes} min`;
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;
    return `${hours}h ${mins}m`;
  };

  if (loadingDashboard) {
    return (
      <div className="flex items-center justify-center h-64">
        <Loader2 className="animate-spin text-orange-500" size={48} />
      </div>
    );
  }

  return (
    <div className="flex flex-col h-full gap-4 p-4 md:p-6">
      {/* Barra compacta de estado de caja (solo si requiere sesión) */}
      <CapabilityGate requires={CapabilityKey.REQUIRES_CASHIER_SESSION}>
        <CashSessionStatus session={dashboard?.current_session || null} />
      </CapabilityGate>

      {/* Filtro por canal */}
      <ChannelTabs
        activeChannel={activeChannel}
        onChannelChange={setActiveChannel}
      />

      {/* Cuentas por cobrar: PROTAGONISTA */}
      <div className="flex-1 flex flex-col min-h-0">
        <div className="flex items-center justify-between mb-3">
          <div className="flex items-center gap-2">
            <Receipt size={20} className="text-orange-400" />
            <h2 className="text-lg font-bold">
              {activeChannel === 'all' 
                ? t("cashier.bills_to_collect")
                : activeChannel === 'tables'
                ? t("orders.channels.tables", "Mesas")
                : activeChannel === 'delivery'
                ? t("orders.channels.delivery", "Delivery")
                : t("orders.channels.takeout", "Takeout")
              }
            </h2>
            <span className="px-2 py-0.5 bg-orange-500/20 border border-orange-700/50 rounded-full text-orange-300 text-xs font-bold">
              {activeChannel === 'all' 
                ? tablesWithBills.length + activeOrders.length
                : activeChannel === 'tables'
                ? tablesWithBills.length
                : activeOrders.filter(o => o.type === activeChannel).length
              }
            </span>
          </div>
        </div>

        <CapabilityGate requires={CapabilityKey.REQUIRES_CASHIER_SESSION}>
          {!isSessionOpen && (tablesWithBills.length > 0 || activeOrders.length > 0) && (
            <div className="bg-amber-900/30 border border-amber-700 rounded-lg p-2.5 mb-3 text-xs text-amber-200 flex items-center gap-2">
              <AlertCircle size={14} className="flex-shrink-0" />
              <span>
                {t("cashier.must_open_session")}
              </span>
            </div>
          )}
        </CapabilityGate>

        {/* Pedidos activos (delivery/takeout) */}
        {(activeChannel === 'delivery' || activeChannel === 'takeout') && (
          <div className="flex-1 overflow-y-auto">
            {activeOrders.filter(o => o.type === activeChannel).length === 0 ? (
              <div className="flex-1 flex items-center justify-center text-slate-400 bg-slate-800/30 rounded-xl border border-slate-700/50">
                <div className="text-center py-12">
                  <Clock size={48} className="mx-auto mb-3 opacity-30" />
                  <p>No hay pedidos {activeChannel === 'delivery' ? 'delivery' : 'takeout'} activos</p>
                </div>
              </div>
            ) : (
              <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3">
                {activeOrders
                  .filter(order => order.type === activeChannel)
                  .map((order) => (
                  <button
                    key={order.uuid}
                    onClick={() => setPaymentEntity({ uuid: order.uuid, type: "order", order })}
                    className="bg-slate-800 rounded-lg p-3.5 border-2 border-blue-500/30 hover:border-blue-500/60 transition-all hover:scale-[1.02] cursor-pointer text-left w-full"
                  >
                    {/* Header */}
                    <div className="flex items-start justify-between mb-2">
                      <div className="flex items-center gap-2">
                        <span className="text-xl">
                          {order.type === 'delivery' ? '🚗' : '🥡'}
                        </span>
                        <span className="text-lg font-bold text-white">{order.order_number}</span>
                      </div>
                      <span className={`flex items-center gap-1 text-[10px] px-1.5 py-0.5 rounded-full ${
                        order.status === 'ready' || order.status === 'ready_for_pickup'
                          ? 'bg-green-900/40 text-green-300'
                          : order.status === 'preparing'
                          ? 'bg-yellow-900/40 text-yellow-300'
                          : 'bg-blue-900/40 text-blue-300'
                      }`}>
                        <Clock size={10} />
                        {order.status}
                      </span>
                    </div>

                    {/* Cliente */}
                    {order.customer_name && (
                      <div className="text-xs text-slate-300 mb-2">
                        👤 {order.customer_name}
                      </div>
                    )}

                    {/* Meta */}
                    <div className="flex items-center gap-2 text-[11px] text-slate-400 mb-2">
                      <span>{order.items.length} items</span>
                    </div>

                    {/* Total */}
                    <div className="pt-2 border-t border-slate-700 flex items-center justify-between">
                      <span className="text-[11px] text-slate-400">{t("orders.total")}:</span>
                      <span className="text-lg font-bold text-blue-400">
                        {formatPrice(order.total)}
                      </span>
                    </div>
                  </button>
                ))}
              </div>
            )}
          </div>
        )}

        {/* Mesas con cuentas (canal 'all' o 'tables') */}
        {(activeChannel === 'all' || activeChannel === 'tables') && (
          <>
            {loadingTables ? (
              <div className="flex items-center justify-center py-12">
                <Loader2 className="animate-spin text-orange-500" size={32} />
              </div>
            ) : tablesWithBills.length === 0 && activeOrders.length === 0 ? (
              <div className="flex-1 flex items-center justify-center text-slate-400 bg-slate-800/30 rounded-xl border border-slate-700/50">
                <div className="text-center py-12">
                  <Receipt size={48} className="mx-auto mb-3 opacity-30" />
                  <p>No hay cuentas pendientes</p>
                </div>
              </div>
            ) : (
              <div className="flex-1 overflow-y-auto space-y-3">
                {/* Pedidos activos primero */}
                {activeOrders.length > 0 && (
                  <div className="mb-4">
                    <h3 className="text-sm font-semibold text-blue-300 mb-2 flex items-center gap-2">
                      <Clock size={16} />
                      Pedidos Activos ({activeOrders.length})
                    </h3>
                    <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3">
                      {activeOrders.map((order) => (
                        <div
                          key={order.uuid}
                          className="bg-slate-800 rounded-lg p-3.5 border-2 border-blue-500/30 hover:border-blue-500/60 transition-all hover:scale-[1.02] cursor-pointer"
                        >
                          <div className="flex items-start justify-between mb-2">
                            <div className="flex items-center gap-2">
                              <span className="text-xl">
                                {order.type === 'delivery' ? '🚗' : '🥡'}
                              </span>
                              <span className="text-lg font-bold text-white">{order.order_number}</span>
                            </div>
                          </div>
                          {order.customer_name && (
                            <div className="text-xs text-slate-300 mb-2">
                              👤 {order.customer_name}
                            </div>
                          )}
                          <div className="pt-2 border-t border-slate-700 flex items-center justify-between">
                            <span className="text-[11px] text-slate-400">{t("orders.total")}:</span>
                            <span className="text-lg font-bold text-blue-400">
                              {formatPrice(order.total)}
                            </span>
                          </div>
                        </div>
                      ))}
                    </div>
                  </div>
                )}

                {/* Mesas después */}
                {tablesWithBills.length > 0 && (
                  <div>
                    <h3 className="text-sm font-semibold text-orange-300 mb-2 flex items-center gap-2">
                      <Receipt size={16} />
                      Mesas con Cuenta ({tablesWithBills.length})
                    </h3>
                    <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3">
                      {tablesWithBills.map((table) => {
                        const elapsed = getElapsedMinutes(table.first_order_at);
                        const minutesSinceFirst = table.first_order_at
                          ? Math.floor(
                              (Date.now() - new Date(table.first_order_at).getTime()) / 60000
                            )
                          : 0;
                        const isUrgent = minutesSinceFirst > 60;

                        return (
                          <button
                            key={table.table_uuid}
                            onClick={() => setPaymentEntity({ uuid: table.table_uuid, type: "table" })}
                            disabled={requiresCashierSession && !isSessionOpen}
                            className={`bg-slate-800 rounded-lg p-3.5 border-2 text-left transition-all hover:scale-[1.02] disabled:opacity-50 disabled:cursor-not-allowed ${
                              isUrgent
                                ? "border-red-500"
                                : "border-slate-700 hover:border-orange-500/60"
                            }`}
                          >
                            <div className="flex items-start justify-between mb-2">
                              <div className="text-xl font-bold text-white">
                                {t("cashier.table")} {table.table_number}
                              </div>
                              {elapsed && (
                                <span
                                  className={`flex items-center gap-1 text-[10px] px-1.5 py-0.5 rounded-full ${
                                    isUrgent
                                      ? "bg-red-900/40 text-red-300"
                                      : "bg-slate-700 text-slate-300"
                                  }`}
                                >
                                  <Clock size={10} />
                                  {elapsed}
                                </span>
                              )}
                            </div>
                            <div className="flex items-center gap-2 text-[11px] text-slate-400 mb-2">
                              <span>{table.area_code}</span>
                              <span>·</span>
                              <span className="flex items-center gap-0.5">
                                <Users size={10} /> {table.capacity}
                              </span>
                              <span>·</span>
                              <span>
                                {table.orders_count} ped. / {table.total_items} items
                              </span>
                            </div>
                            <div className="pt-2 border-t border-slate-700 flex items-center justify-between">
                              <span className="text-[11px] text-slate-400">{t("orders.total")}:</span>
                              <span className="text-lg font-bold text-orange-400">
                                {formatPrice(table.total_amount)}
                              </span>
                            </div>
                          </button>
                        );
                      })}
                    </div>
                  </div>
                )}
              </div>
            )}
          </>
        )}
      </div>



      {/* Modal de pago universal */}
      {paymentEntity && (
        <PaymentModal
          entityUuid={paymentEntity.uuid}
          entityType={paymentEntity.type}
          order={paymentEntity.order}
          isOpen={!!paymentEntity}
          onClose={() => setPaymentEntity(null)}
          onSuccess={() => setPaymentEntity(null)}
        />
      )}
    </div>
  );
}
