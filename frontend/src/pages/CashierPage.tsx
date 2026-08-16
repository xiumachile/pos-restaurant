import { useState } from "react";
import {
  useCashierDashboard,
  useTablesWithBills,
} from "@/hooks/usePayments";
import { CashSessionStatus } from "@/components/cashier/CashSessionStatus";
import { TableBillModal } from "@/components/cashier/TableBillModal";
import type { TableBill } from "@/types/tableBill";
import {
  Loader2,
  DollarSign,
  TrendingUp,
  ShoppingBag,
  CheckCircle2,
  Receipt,
  Users,
  Clock,
  AlertCircle,
} from "lucide-react";
import { formatPrice } from "@/types/catalog";

export function CashierPage() {
  const { data: dashboard, isLoading: loadingDashboard } = useCashierDashboard();
  const { data: tablesWithBills = [], isLoading: loadingTables } = useTablesWithBills();
  const [selectedTable, setSelectedTable] = useState<TableBill | null>(null);

  const stats = dashboard?.statistics_today;
  const isSessionOpen = !!dashboard?.current_session;

  const totalExpectedInCash =
    (dashboard?.current_session?.opening_amount || 0) +
    (dashboard?.current_session?.expected_amount || 0);

  const formatTime = (isoString: string | null) => {
    if (!isoString) return "-";
    return new Date(isoString).toLocaleTimeString("es-CL", {
      hour: "2-digit",
      minute: "2-digit",
    });
  };

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
    <div className="space-y-6">
      {/* Header */}
      <div>
        <h1 className="text-3xl font-bold">Caja</h1>
        <p className="text-slate-400 mt-1">
          Gestiona sesión de caja y cobra cuentas por mesa
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
              Mesas por cobrar
            </div>
            <div className="text-2xl font-bold text-orange-400">
              {tablesWithBills.length}
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

      {/* Mesas con cuenta pendiente */}
      <div className="bg-slate-800/50 border border-slate-700 rounded-xl p-6">
        <div className="flex items-center justify-between mb-4">
          <div className="flex items-center gap-3">
            <Receipt size={24} className="text-orange-400" />
            <div>
              <h2 className="text-xl font-bold">Cuentas por Cobrar</h2>
              <p className="text-sm text-slate-400">
                {tablesWithBills.length}{" "}
                {tablesWithBills.length === 1 ? "mesa" : "mesas"} esperando pago
              </p>
            </div>
          </div>
        </div>

        {!isSessionOpen && tablesWithBills.length > 0 && (
          <div className="bg-amber-900/30 border border-amber-700 rounded-lg p-3 mb-4 text-sm text-amber-200 flex items-start gap-2">
            <AlertCircle size={16} className="flex-shrink-0 mt-0.5" />
            <span>
              Debes <strong>abrir caja</strong> antes de poder cobrar cuentas.
            </span>
          </div>
        )}

        {loadingTables ? (
          <div className="text-center py-12">
            <Loader2 className="animate-spin mx-auto text-orange-500" size={32} />
          </div>
        ) : tablesWithBills.length === 0 ? (
          <div className="text-center py-12 text-slate-500">
            <Receipt size={48} className="mx-auto mb-3 opacity-30" />
            <p>No hay mesas con cuenta pendiente</p>
          </div>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {tablesWithBills.map((table) => {
              const elapsed = getElapsedMinutes(table.first_order_at);
              const minutesSinceFirst = table.first_order_at
                ? Math.floor(
                    (Date.now() - new Date(table.first_order_at).getTime()) / 60000
                  )
                : 0;
              const isUrgent = minutesSinceFirst > 60;

              return (
                <div
                  key={table.table_uuid}
                  className={`bg-slate-800 rounded-lg p-4 border-2 transition-all ${
                    isUrgent
                      ? "border-red-500"
                      : "border-slate-700 hover:border-orange-500/50"
                  }`}
                >
                  {/* Header: número + área + tiempo */}
                  <div className="flex items-start justify-between mb-3">
                    <div>
                      <div className="text-2xl font-bold text-white mb-1">
                        Mesa {table.table_number}
                      </div>
                      <div className="flex items-center gap-2 text-xs text-slate-400">
                        <span>{table.area_code}</span>
                        <span>·</span>
                        <span className="flex items-center gap-1">
                          <Users size={12} /> {table.capacity}
                        </span>
                      </div>
                    </div>
                    {elapsed && (
                      <div
                        className={`flex items-center gap-1 text-xs px-2 py-1 rounded-full ${
                          isUrgent
                            ? "bg-red-900/40 text-red-300"
                            : "bg-slate-700 text-slate-300"
                        }`}
                      >
                        <Clock size={12} />
                        {elapsed}
                      </div>
                    )}
                  </div>

                  {/* Stats */}
                  <div className="space-y-1 mb-3 text-sm">
                    <div className="flex justify-between text-slate-300">
                      <span>Pedidos:</span>
                      <span className="font-semibold">
                        {table.orders_count}
                      </span>
                    </div>
                    <div className="flex justify-between text-slate-300">
                      <span>Items:</span>
                      <span className="font-semibold">
                        {table.total_items}
                      </span>
                    </div>
                  </div>

                  {/* Total + botón */}
                  <div className="pt-3 border-t border-slate-700">
                    <div className="flex items-center justify-between mb-3">
                      <span className="text-sm text-slate-400">Total mesa:</span>
                      <span className="text-2xl font-bold text-orange-400">
                        {formatPrice(table.total_amount)}
                      </span>
                    </div>
                    <button
                      onClick={() => setSelectedTable(table)}
                      disabled={!isSessionOpen}
                      className="w-full px-4 py-2.5 bg-orange-500 hover:bg-orange-600 rounded-lg font-medium flex items-center justify-center gap-2 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                    >
                      <Receipt size={16} />
                      Ver Precuenta
                    </button>
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </div>

      {/* Modal de precuenta + cobro */}
      {selectedTable && (
        <TableBillModal
          tableBill={selectedTable}
          isOpen={!!selectedTable}
          onClose={() => setSelectedTable(null)}
          onSuccess={() => setSelectedTable(null)}
        />
      )}
    </div>
  );
}
