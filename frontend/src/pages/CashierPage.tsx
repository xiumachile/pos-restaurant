import { useCashierDashboard, useServedOrders } from "@/hooks/usePayments";
import { CashSessionStatus } from "@/components/cashier/CashSessionStatus";
import { ServedOrdersList } from "@/components/cashier/ServedOrdersList";
import { Loader2, DollarSign, TrendingUp, ShoppingBag, CheckCircle2 } from "lucide-react";
import { formatPrice } from "@/types/catalog";

export function CashierPage() {
  const { data: dashboard, isLoading: loadingDashboard } = useCashierDashboard();
  const { data: servedOrders = [], isLoading: loadingOrders } = useServedOrders();

  const stats = dashboard?.statistics_today;
  const isSessionOpen = !!dashboard?.current_session;

  const totalExpectedInCash =
    (dashboard?.current_session?.opening_amount || 0) +
    (dashboard?.current_session?.expected_amount || 0);

  if (loadingDashboard) {
    return (
      <div className="flex items-center justify-center h-64">
        <Loader2 className="animate-spin text-orange-500" size={48} />
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div>
        <h1 className="text-3xl font-bold">Caja</h1>
        <p className="text-slate-400 mt-1">
          Gestiona sesión de caja y cobra pedidos
        </p>
      </div>

      {/* Estado de la caja */}
      <CashSessionStatus session={dashboard?.current_session || null} />

      {/* Stats del día */}
      {stats && (
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div className="bg-slate-800/50 border border-slate-700 rounded-xl p-4">
            <div className="flex items-center gap-2 text-xs text-slate-400 mb-2">
              <ShoppingBag size={14} />
              Pedidos por cobrar
            </div>
            <div className="text-2xl font-bold text-orange-400">
              {servedOrders.length}
            </div>
          </div>
          <div className="bg-slate-800/50 border border-slate-700 rounded-xl p-4">
            <div className="flex items-center gap-2 text-xs text-slate-400 mb-2">
              <DollarSign size={14} />
              En caja (esperado)
            </div>
            <div className="text-2xl font-bold text-green-400">
              {formatPrice(totalExpectedInCash)}
            </div>
          </div>
          <div className="bg-slate-800/50 border border-slate-700 rounded-xl p-4">
            <div className="flex items-center gap-2 text-xs text-slate-400 mb-2">
              <TrendingUp size={14} />
              Sesiones hoy
            </div>
            <div className="text-2xl font-bold text-blue-400">
              {stats.sessions_today}
            </div>
          </div>
          <div className="bg-slate-800/50 border border-slate-700 rounded-xl p-4">
            <div className="flex items-center gap-2 text-xs text-slate-400 mb-2">
              <CheckCircle2 size={14} />
              Cerradas hoy
            </div>
            <div className="text-2xl font-bold text-slate-300">
              {stats.sessions_closed}
            </div>
          </div>
        </div>
      )}

      {/* Pedidos servidos listos para cobrar */}
      {loadingOrders ? (
        <div className="text-center py-8">
          <Loader2 className="animate-spin mx-auto text-orange-500" size={32} />
        </div>
      ) : (
        <ServedOrdersList orders={servedOrders} isCashierSessionOpen={isSessionOpen} />
      )}
    </div>
  );
}
