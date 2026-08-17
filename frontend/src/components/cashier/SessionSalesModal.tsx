import { useSessionPayments } from "@/hooks/usePayments";
import { formatPrice } from "@/types/catalog";
import { X, Printer, Loader2, Banknote, CreditCard, Building2, Gift } from "lucide-react";
import { PrintableSessionReport } from "./PrintableSessionReport";

interface SessionSalesModalProps {
  isOpen: boolean;
  onClose: () => void;
}

const METHOD_CONFIG: Record<string, { label: string; color: string; icon: any }> = {
  CASH: { label: "Efectivo", color: "text-green-400", icon: Banknote },
  CARD: { label: "Tarjeta", color: "text-blue-400", icon: CreditCard },
  TRANSFER: { label: "Transferencia", color: "text-purple-400", icon: Building2 },
  GIFT_CARD: { label: "Gift Card", color: "text-amber-400", icon: Gift },
};

export function SessionSalesModal({ isOpen, onClose }: SessionSalesModalProps) {
  const { data, isLoading } = useSessionPayments(isOpen);

  if (!isOpen) return null;

  const formatTime = (iso: string | null) => {
    if (!iso) return "-";
    return new Date(iso).toLocaleTimeString("es-CL", {
      hour: "2-digit",
      minute: "2-digit",
    });
  };

  return (
    <>
      {/* Componente imprimible oculto */}
      {data && <div className="hidden"><PrintableSessionReport data={data} /></div>}

      <div className="fixed inset-0 bg-black/70 z-50" onClick={onClose} />
      <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div
          className="bg-slate-900 rounded-xl shadow-2xl max-w-3xl w-full max-h-[90vh] flex flex-col"
          onClick={(e) => e.stopPropagation()}
        >
          {/* Header */}
          <div className="flex items-center justify-between p-5 border-b border-slate-700">
            <div>
              <h2 className="text-xl font-bold">
                Ventas de la Sesión {data?.session?.session_number || ""}
              </h2>
              <p className="text-sm text-slate-400 mt-0.5">
                {data?.summary?.transactions_count || 0} transacciones ·
                Cajero: {data?.session?.user_name || "-"}
              </p>
            </div>
            <div className="flex items-center gap-2">
              <button
                onClick={() => window.print()}
                disabled={!data || data.payments.length === 0}
                className="flex items-center gap-1.5 px-3 py-2 bg-blue-500 hover:bg-blue-600 rounded-lg text-white text-sm font-medium disabled:opacity-40 transition-colors"
              >
                <Printer size={15} />
                Imprimir Ticket
              </button>
              <button onClick={onClose} className="p-2 hover:bg-slate-800 rounded-lg">
                <X size={20} />
              </button>
            </div>
          </div>

          {/* Resumen compacto */}
          {data?.summary && (
            <div className="grid grid-cols-4 gap-2 p-4 border-b border-slate-700 bg-slate-800/50">
              <div className="text-center">
                <div className="text-xs text-slate-400">Ventas</div>
                <div className="font-bold text-white">
                  {formatPrice(data.summary.total_sales)}
                </div>
              </div>
              <div className="text-center">
                <div className="text-xs text-slate-400">Propinas</div>
                <div className="font-bold text-orange-400">
                  {formatPrice(data.summary.total_tips)}
                </div>
              </div>
              <div className="text-center">
                <div className="text-xs text-slate-400">Total general</div>
                <div className="font-bold text-white">
                  {formatPrice(data.summary.total_grand)}
                </div>
              </div>
              <div className="text-center">
                <div className="text-xs text-slate-400">Esperado caja</div>
                <div className="font-bold text-green-400">
                  {formatPrice(data.summary.total_cash_expected)}
                </div>
              </div>
            </div>
          )}

          {/* Tabla de pagos */}
          <div className="flex-1 overflow-y-auto p-4">
            {isLoading ? (
              <div className="flex items-center justify-center py-12">
                <Loader2 className="animate-spin text-orange-500" size={32} />
              </div>
            ) : !data || data.payments.length === 0 ? (
              <div className="text-center py-12 text-slate-500">
                <p>Aún no hay ventas cobradas en esta sesión</p>
              </div>
            ) : (
              <table className="w-full text-sm">
                <thead className="text-xs text-slate-400 border-b border-slate-700">
                  <tr>
                    <th className="text-left py-2 px-2">Hora</th>
                    <th className="text-left py-2 px-2">Mesa</th>
                    <th className="text-left py-2 px-2">Pedido</th>
                    <th className="text-left py-2 px-2">Método</th>
                    <th className="text-right py-2 px-2">Monto</th>
                    <th className="text-right py-2 px-2">Propina</th>
                    <th className="text-right py-2 px-2">Total</th>
                  </tr>
                </thead>
                <tbody>
                  {data.payments.map((p) => {
                    const config = METHOD_CONFIG[p.method_code];
                    const Icon = config?.icon || Banknote;
                    return (
                      <tr key={p.uuid} className="border-b border-slate-800 hover:bg-slate-800/50">
                        <td className="py-2 px-2 text-slate-300">{formatTime(p.paid_at)}</td>
                        <td className="py-2 px-2 font-semibold text-white">
                          {p.table_number ? `M${p.table_number}` : "-"}
                        </td>
                        <td className="py-2 px-2 text-slate-400 text-xs">
                          {p.order_number?.slice(-9) || "-"}
                        </td>
                        <td className="py-2 px-2">
                          <span className={`flex items-center gap-1.5 ${config?.color}`}>
                            <Icon size={14} />
                            {config?.label || p.method_code}
                          </span>
                        </td>
                        <td className="py-2 px-2 text-right text-slate-200">
                          {formatPrice(p.amount)}
                        </td>
                        <td className="py-2 px-2 text-right text-orange-300">
                          {p.tip_amount > 0 ? formatPrice(p.tip_amount) : "-"}
                        </td>
                        <td className="py-2 px-2 text-right font-bold text-white">
                          {formatPrice(p.total_amount)}
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            )}
          </div>
        </div>
      </div>
    </>
  );
}
