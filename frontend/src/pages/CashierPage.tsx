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
  Receipt,
  Users,
  Clock,
  AlertCircle,
} from "lucide-react";
import { formatPrice } from "@/types/catalog";

/**
 * Página de Caja.
 * Diseño: barra compacta de sesión arriba + grid de cuentas por cobrar
 * como protagonista absoluto de la pantalla.
 */
export function CashierPage() {
  const { data: dashboard, isLoading: loadingDashboard } = useCashierDashboard();
  const { data: tablesWithBills = [], isLoading: loadingTables } = useTablesWithBills();
  const [selectedTableUuid, setSelectedTableUuid] = useState<string | null>(null);

  const isSessionOpen = !!dashboard?.current_session;

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
    <div className="flex flex-col h-full gap-4">
      {/* Barra compacta de estado de caja */}
      <CashSessionStatus session={dashboard?.current_session || null} />

      {/* Cuentas por cobrar: PROTAGONISTA */}
      <div className="flex-1 flex flex-col min-h-0">
        <div className="flex items-center justify-between mb-3">
          <div className="flex items-center gap-2">
            <Receipt size={20} className="text-orange-400" />
            <h2 className="text-lg font-bold">Cuentas por Cobrar</h2>
            <span className="px-2 py-0.5 bg-orange-500/20 border border-orange-700/50 rounded-full text-orange-300 text-xs font-bold">
              {tablesWithBills.length}
            </span>
          </div>
        </div>

        {!isSessionOpen && tablesWithBills.length > 0 && (
          <div className="bg-amber-900/30 border border-amber-700 rounded-lg p-2.5 mb-3 text-xs text-amber-200 flex items-center gap-2">
            <AlertCircle size={14} className="flex-shrink-0" />
            <span>
              Debes <strong>abrir caja</strong> antes de poder cobrar cuentas.
            </span>
          </div>
        )}

        {loadingTables ? (
          <div className="flex items-center justify-center py-12">
            <Loader2 className="animate-spin text-orange-500" size={32} />
          </div>
        ) : tablesWithBills.length === 0 ? (
          <div className="flex-1 flex items-center justify-center text-slate-500 bg-slate-800/30 rounded-xl border border-slate-700/50">
            <div className="text-center py-12">
              <Receipt size={48} className="mx-auto mb-3 opacity-30" />
              <p>No hay mesas con cuenta pendiente</p>
            </div>
          </div>
        ) : (
          <div className="flex-1 overflow-y-auto">
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
                    onClick={() => setSelectedTableUuid(table.table_uuid)}
                    disabled={!isSessionOpen}
                    className={`bg-slate-800 rounded-lg p-3.5 border-2 text-left transition-all hover:scale-[1.02] disabled:opacity-50 disabled:cursor-not-allowed ${
                      isUrgent
                        ? "border-red-500"
                        : "border-slate-700 hover:border-orange-500/60"
                    }`}
                  >
                    {/* Header */}
                    <div className="flex items-start justify-between mb-2">
                      <div className="text-xl font-bold text-white">
                        Mesa {table.table_number}
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

                    {/* Meta */}
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

                    {/* Total */}
                    <div className="pt-2 border-t border-slate-700 flex items-center justify-between">
                      <span className="text-[11px] text-slate-400">Total:</span>
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

      {/* Modal de precuenta + cobro */}
      {selectedTableUuid && (
        <TableBillModal
          tableUuid={selectedTableUuid}
          isOpen={!!selectedTableUuid}
          onClose={() => setSelectedTableUuid(null)}
          onSuccess={() => setSelectedTableUuid(null)}
        />
      )}
    </div>
  );
}
