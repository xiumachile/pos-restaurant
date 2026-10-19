import { useState, useEffect, useMemo } from "react";
import { useTranslation } from 'react-i18next';
import {
  useCashierDashboard,
  useInvalidateCashier,
} from "@/hooks/usePayments";
import { CashSessionStatus } from "@/components/cashier/CashSessionStatus";
import { TableBillModal } from "@/components/cashier/TableBillModal";
import { localPaymentsService } from "@/services/localPaymentsService";
import { useSyncStore } from "@/store/useSyncStore";
import { useQuery } from "@tanstack/react-query";
import { Loader2, Receipt, Users, Clock, AlertCircle, Truck, Package, LayoutGrid } from "lucide-react";

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
  const [activeTab, setActiveTab] = useState<'all' | 'table' | 'delivery' | 'takeout'>('all');
  const [selectedAccountId, setSelectedAccountId] = useState<string | null>(null);
  const invalidateCashier = useInvalidateCashier();
  const syncStatus = useSyncStore((s) => s.status);

  // Obtener todas las cuentas pendientes (mesas + delivery + takeout)
  const { data: pendingAccounts = [], isLoading: loadingAccounts } = useQuery({
    queryKey: ['pending-accounts'],
    queryFn: async () => {
      if (syncStatus === 'offline') {
        return await localPaymentsService.listPendingAccountsOffline();
      }
      // En online, intentamos el backend, si falla usamos local
      try {
        // TODO: Implementar endpoint backend /cashier/pending-accounts
        return await localPaymentsService.listPendingAccountsOffline();
      } catch (e) {
        return await localPaymentsService.listPendingAccountsOffline();
      }
    },
    refetchInterval: 10000,
  });

  // Filtrar por tab activo
  const filteredAccounts = useMemo(() => {
    if (activeTab === 'all') return pendingAccounts;
    return pendingAccounts.filter((a: any) => a.type === activeTab);
  }, [pendingAccounts, activeTab]);

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

      {/* Cuentas por cobrar: PROTAGONISTA */}
      <div className="flex-1 flex flex-col min-h-0">
        <div className="flex items-center justify-between mb-3">
          <div className="flex items-center gap-2">
            <Receipt size={20} className="text-orange-400" />
            <h2 className="text-lg font-bold">{t("cashier.bills_to_collect", "Cuentas por Cobrar")}</h2>
            <span className="px-2 py-0.5 bg-orange-500/20 border border-orange-700/50 rounded-full text-orange-300 text-xs font-bold">
              {filteredAccounts.length}
            </span>
          </div>
        </div>

        {/* Tabs de filtrado */}
        <div className="flex gap-2 mb-4 overflow-x-auto pb-1">
          <TabButton 
            active={activeTab === 'all'} 
            onClick={() => setActiveTab('all')} 
            icon={<LayoutGrid size={16} />} 
            label={t("cashier.tab_all", "Todos")} 
            count={pendingAccounts.length} 
          />
          <TabButton 
            active={activeTab === 'table'} 
            onClick={() => setActiveTab('table')} 
            icon={<Users size={16} />} 
            label={t("cashier.tab_tables", "Mesas")} 
            count={pendingAccounts.filter((a: any) => a.type === 'table').length} 
          />
          <TabButton 
            active={activeTab === 'delivery'} 
            onClick={() => setActiveTab('delivery')} 
            icon={<Truck size={16} />} 
            label={t("cashier.tab_delivery", "Delivery")} 
            count={pendingAccounts.filter((a: any) => a.type === 'delivery').length} 
          />
          <TabButton 
            active={activeTab === 'takeout'} 
            onClick={() => setActiveTab('takeout')} 
            icon={<Package size={16} />} 
            label={t("cashier.tab_takeout", "Para llevar")} 
            count={pendingAccounts.filter((a: any) => a.type === 'takeout').length} 
          />
        </div>

        <CapabilityGate requires={CapabilityKey.REQUIRES_CASHIER_SESSION}>
          {!isSessionOpen && filteredAccounts.length > 0 && (
            <div className="bg-amber-900/30 border border-amber-700 rounded-lg p-2.5 mb-3 text-xs text-amber-200 flex items-center gap-2">
              <AlertCircle size={14} className="flex-shrink-0" />
              <span>
                {t("cashier.must_open_session")}
              </span>
            </div>
          )}
        </CapabilityGate>

        {loadingAccounts ? (
          <div className="flex items-center justify-center py-12">
            <Loader2 className="animate-spin text-orange-500" size={32} />
          </div>
        ) : filteredAccounts.length === 0 ? (
          <div className="flex-1 flex items-center justify-center text-slate-400 bg-slate-800/30 rounded-xl border border-slate-700/50">
            <div className="text-center py-12">
              <Receipt size={48} className="mx-auto mb-3 opacity-30" />
              <p>No hay mesas con cuenta pendiente</p>
            </div>
          </div>
        ) : (
          <div className="flex-1 overflow-y-auto">
            <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3">
              {filteredAccounts.map((account) => {
                const elapsed = getElapsedMinutes(account.first_order_at);
                const minutesSinceFirst = account.first_order_at
                  ? Math.floor(
                      (Date.now() - new Date(account.first_order_at).getTime()) / 60000
                    )
                  : 0;
                const isUrgent = minutesSinceFirst > 60;

                return (
                  <button
                    key={account.id}
                    onClick={() => setSelectedAccountId(account.id)}
                    disabled={requiresCashierSession && !isSessionOpen}
                    className={`bg-slate-800 rounded-lg p-3.5 border-2 text-left transition-all hover:scale-[1.02] disabled:opacity-50 disabled:cursor-not-allowed ${
                      isUrgent
                        ? "border-red-500"
                        : "border-slate-700 hover:border-orange-500/60"
                    }`}
                  >
                    {/* Header */}
                    <div className="flex items-start justify-between mb-2">
                      <div className="text-xl font-bold text-white">
                        {account.type === 'table' ? (
                          <>{t("cashier.table", "Mesa")} {account.identifier}</>
                        ) : account.type === 'delivery' ? (
                          <><Truck size={16} className="inline mr-1" />{account.identifier}</>
                        ) : (
                          <><Package size={16} className="inline mr-1" />{t("cashier.takeout", "Para llevar")}</>
                        )}
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
                      <span>{account.area_code}</span>
                      <span>·</span>
                      <span className="flex items-center gap-0.5">
                        <Users size={10} /> {account.total_items}
                      </span>
                      <span>·</span>
                      <span>
                        {account.orders_count} ped. / {account.total_items} items
                      </span>
                    </div>

                    {/* Total */}
                    <div className="pt-2 border-t border-slate-700 flex items-center justify-between">
                      <span className="text-[11px] text-slate-400">{t("orders.total")}:</span>
                      <span className="text-lg font-bold text-orange-400">
                        {formatPrice(account.total_amount)}
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
      {selectedAccountId && (
        <TableBillModal
          tableUuid={selectedAccountId}
          isOpen={!!selectedAccountId}
          onClose={() => setSelectedAccountId(null)}
          onSuccess={() => setSelectedAccountId(null)}
        />
      )}
    </div>
  );
}

function TabButton({ active, onClick, icon, label, count }: { active: boolean; onClick: () => void; icon: React.ReactNode; label: string; count: number }) {
  return (
    <button
      onClick={onClick}
      className={`flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-colors whitespace-nowrap ${
        active
          ? "bg-orange-500 text-white"
          : "bg-slate-800 text-slate-400 hover:bg-slate-700 hover:text-slate-200"
      }`}
    >
      {icon}
      <span>{label}</span>
      <span className={`text-xs px-1.5 py-0.5 rounded-full ${active ? "bg-white/20" : "bg-slate-700"}`}>
        {count}
      </span>
    </button>
  );
}
