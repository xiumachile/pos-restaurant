import { useXReport, useZReport } from "@/hooks/usePayments";
import { formatPrice } from "@/types/catalog";
import { X, Printer, Loader2, Banknote, CreditCard, Building2, Gift } from "lucide-react";
import { PrintableCashReport } from "./PrintableCashReport";

interface CashReportModalProps {
  isOpen: boolean;
  onClose: () => void;
  sessionUuid?: string | null; // Si se pasa, es Z-Report; si no, es X-Report
}

const METHOD_CONFIG: Record<string, { label: string; color: string; icon: any }> = {
  cash: { label: "Efectivo", color: "text-green-400", icon: Banknote },
  card: { label: "Tarjeta", color: "text-blue-400", icon: CreditCard },
  transfer: { label: "Transferencia", color: "text-purple-400", icon: Building2 },
  gift_card: { label: "Gift Card", color: "text-amber-400", icon: Gift },
};

export function CashReportModal({ isOpen, onClose, sessionUuid }: CashReportModalProps) {
  const isZReport = !!sessionUuid;

  const { data: xReport, isLoading: loadingX } = useXReport(isOpen && !isZReport);
  const { data: zReport, isLoading: loadingZ } = useZReport(sessionUuid || null, isOpen && isZReport);

  const report = isZReport ? zReport : xReport;
  const isLoading = isZReport ? loadingZ : loadingX;

  if (!isOpen) return null;

  const formatDT = (iso: string | null) => {
    if (!iso) return "-";
    return new Date(iso).toLocaleString("es-CL", {
      day: "2-digit",
      month: "2-digit",
      hour: "2-digit",
      minute: "2-digit",
    });
  };

  return (
    <>
      {report && (
        <div className="hidden">
          <PrintableCashReport report={report} />
        </div>
      )}

      <div className="fixed inset-0 bg-black/70 z-50" onClick={onClose} />
      <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div
          className="bg-slate-900 rounded-xl shadow-2xl max-w-2xl w-full max-h-[90vh] flex flex-col"
          onClick={(e) => e.stopPropagation()}
        >
          <div className="flex items-center justify-between p-5 border-b border-slate-700">
            <div>
              <h2 className="text-xl font-bold flex items-center gap-2">
                {isZReport ? "🔒 Reporte Z (Cierre)" : "📊 Reporte X (Parcial)"}
              </h2>
              <p className="text-sm text-slate-400 mt-0.5">
                Sesión {report?.session.session_number || "..."} · {report?.session.user_name || ""}
              </p>
            </div>
            <div className="flex items-center gap-2">
              <button
                onClick={() => window.print()}
                disabled={!report}
                className="flex items-center gap-1.5 px-3 py-2 bg-blue-500 hover:bg-blue-600 rounded-lg text-white text-sm font-medium disabled:opacity-40 transition-colors"
              >
                <Printer size={15} />
                Imprimir
              </button>
              <button onClick={onClose} className="p-2 hover:bg-slate-800 rounded-lg">
                <X size={20} />
              </button>
            </div>
          </div>

          <div className="flex-1 overflow-y-auto p-5 space-y-5">
            {isLoading ? (
              <div className="flex items-center justify-center py-12">
                <Loader2 className="animate-spin text-orange-500" size={32} />
              </div>
            ) : !report ? (
              <div className="text-center py-12 text-slate-500">
                No hay sesión abierta para reportar
              </div>
            ) : (
              <>
                {/* Info de sesión */}
                <div className="bg-slate-800/50 rounded-lg p-4 grid grid-cols-2 md:grid-cols-4 gap-3">
                  <div>
                    <div className="text-xs text-slate-400">Apertura</div>
                    <div className="font-semibold">{formatDT(report.session.opened_at)}</div>
                  </div>
                  {isZReport && (
                    <div>
                      <div className="text-xs text-slate-400">Cierre</div>
                      <div className="font-semibold">{formatDT(report.session.closed_at)}</div>
                    </div>
                  )}
                  <div>
                    <div className="text-xs text-slate-400">Caja</div>
                    <div className="font-semibold">{report.session.register_name || "-"}</div>
                  </div>
                  <div>
                    <div className="text-xs text-slate-400">Cajero</div>
                    <div className="font-semibold">{report.session.user_name || "-"}</div>
                  </div>
                </div>

                {/* Ventas por método */}
                <div className="bg-slate-800/50 rounded-lg p-4">
                  <h3 className="text-sm font-bold text-slate-300 mb-3">Ventas por método</h3>
                  <div className="space-y-2">
                    {Object.entries(report.sales.breakdown).map(([key, val]) => {
                      const cfg = METHOD_CONFIG[key];
                      const Icon = cfg?.icon || Banknote;
                      return (
                        <div
                          key={key}
                          className="flex items-center justify-between bg-slate-900/50 rounded-lg p-2.5"
                        >
                          <div className="flex items-center gap-2">
                            <Icon size={16} className={cfg?.color} />
                            <span className="text-sm">{cfg?.label || key}</span>
                            <span className="text-xs text-slate-500">({val.count})</span>
                          </div>
                          <div className="text-right">
                            <div className="font-bold text-white">
                              {formatPrice(val.amount)}
                            </div>
                            {val.tips > 0 && (
                              <div className="text-xs text-orange-300">
                                +{formatPrice(val.tips)} propina
                              </div>
                            )}
                          </div>
                        </div>
                      );
                    })}
                  </div>

                  <div className="mt-3 pt-3 border-t border-slate-700 grid grid-cols-3 gap-3">
                    <div>
                      <div className="text-xs text-slate-400">Ventas</div>
                      <div className="font-bold text-white">
                        {formatPrice(report.sales.total_sales)}
                      </div>
                    </div>
                    <div>
                      <div className="text-xs text-slate-400">Propinas</div>
                      <div className="font-bold text-orange-400">
                        {formatPrice(report.sales.total_tips)}
                      </div>
                    </div>
                    <div>
                      <div className="text-xs text-slate-400">Transacciones</div>
                      <div className="font-bold text-blue-400">
                        {report.sales.total_transactions}
                      </div>
                    </div>
                  </div>
                </div>

                {/* Resumen de efectivo */}
                <div className="bg-slate-800/50 rounded-lg p-4">
                  <h3 className="text-sm font-bold text-slate-300 mb-3">Resumen de efectivo</h3>
                  <div className="space-y-1.5 text-sm">
                    <div className="flex justify-between">
                      <span className="text-slate-400">Monto inicial:</span>
                      <span>{formatPrice(report.cash.opening)}</span>
                    </div>
                    <div className="flex justify-between text-green-400">
                      <span>+ Ventas efectivo:</span>
                      <span>{formatPrice(report.cash.sales)}</span>
                    </div>
                    <div className="flex justify-between text-green-400">
                      <span>+ Propinas efectivo:</span>
                      <span>{formatPrice(report.cash.tips)}</span>
                    </div>
                    {report.cash.deposits > 0 && (
                      <div className="flex justify-between text-blue-400">
                        <span>+ Depósitos:</span>
                        <span>{formatPrice(report.cash.deposits)}</span>
                      </div>
                    )}
                    {report.cash.withdrawals > 0 && (
                      <div className="flex justify-between text-red-400">
                        <span>- Retiros:</span>
                        <span>{formatPrice(report.cash.withdrawals)}</span>
                      </div>
                    )}
                    <div className="flex justify-between pt-2 mt-2 border-t border-slate-700 font-bold text-orange-400 text-base">
                      <span>ESPERADO EN CAJA:</span>
                      <span>{formatPrice(report.cash.expected)}</span>
                    </div>
                    {isZReport && report.cash.counted !== null && (
                      <>
                        <div className="flex justify-between">
                          <span className="text-slate-400">Contado:</span>
                          <span className="font-semibold">
                            {formatPrice(report.cash.counted)}
                          </span>
                        </div>
                        <div
                          className={`flex justify-between pt-2 border-t border-slate-700 font-bold ${
                            (report.cash.difference || 0) === 0
                              ? "text-green-400"
                              : "text-red-400"
                          }`}
                        >
                          <span>DIFERENCIA:</span>
                          <span>{formatPrice(report.cash.difference || 0)}</span>
                        </div>
                      </>
                    )}
                  </div>
                </div>

                {/* Movimientos */}
                {report.movements.length > 0 && (
                  <div className="bg-slate-800/50 rounded-lg p-4">
                    <h3 className="text-sm font-bold text-slate-300 mb-3">
                      Movimientos ({report.movements.length})
                    </h3>
                    <div className="space-y-1">
                      {report.movements.map((m, i) => (
                        <div key={i} className="flex justify-between text-xs">
                          <span className="text-slate-400">
                            {m.type === "withdrawal" ? "🔴 Retiro" : "🟢 Depósito"}
                          </span>
                          <span className="flex-1 mx-2 truncate">
                            {m.reason || "-"}
                          </span>
                          <span
                            className={`font-semibold ${
                              m.type === "withdrawal"
                                ? "text-red-400"
                                : "text-green-400"
                            }`}
                          >
                            {formatPrice(m.amount)}
                          </span>
                        </div>
                      ))}
                    </div>
                  </div>
                )}
              </>
            )}
          </div>
        </div>
      </div>
    </>
  );
}
