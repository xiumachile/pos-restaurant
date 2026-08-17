import { useState } from "react";
import { useSessionsHistory } from "@/hooks/usePayments";
import { formatPrice } from "@/types/catalog";
import { X, Loader2, FileText, AlertCircle, CheckCircle2 } from "lucide-react";
import { CashReportModal } from "./CashReportModal";

interface SessionsHistoryPanelProps {
  isOpen: boolean;
  onClose: () => void;
}

export function SessionsHistoryPanel({ isOpen, onClose }: SessionsHistoryPanelProps) {
  const { data: sessions = [], isLoading } = useSessionsHistory(isOpen);
  const [selectedSessionUuid, setSelectedSessionUuid] = useState<string | null>(null);

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
      <div className="fixed inset-0 bg-black/70 z-50" onClick={onClose} />
      <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div
          className="bg-slate-900 rounded-xl shadow-2xl max-w-4xl w-full max-h-[90vh] flex flex-col"
          onClick={(e) => e.stopPropagation()}
        >
          <div className="flex items-center justify-between p-5 border-b border-slate-700">
            <div>
              <h2 className="text-xl font-bold">Historial de Sesiones</h2>
              <p className="text-sm text-slate-400 mt-0.5">
                Cajas cerradas en esta sucursal
              </p>
            </div>
            <button onClick={onClose} className="p-2 hover:bg-slate-800 rounded-lg">
              <X size={20} />
            </button>
          </div>

          <div className="flex-1 overflow-y-auto p-5">
            {isLoading ? (
              <div className="flex items-center justify-center py-12">
                <Loader2 className="animate-spin text-orange-500" size={32} />
              </div>
            ) : sessions.length === 0 ? (
              <div className="text-center py-12 text-slate-500">
                <FileText size={48} className="mx-auto mb-3 opacity-30" />
                <p>No hay sesiones cerradas aún</p>
              </div>
            ) : (
              <table className="w-full text-sm">
                <thead className="text-xs text-slate-400 border-b border-slate-700">
                  <tr>
                    <th className="text-left py-2 px-2">Sesión</th>
                    <th className="text-left py-2 px-2">Cajero</th>
                    <th className="text-left py-2 px-2">Apertura</th>
                    <th className="text-left py-2 px-2">Cierre</th>
                    <th className="text-right py-2 px-2">Esperado</th>
                    <th className="text-right py-2 px-2">Contado</th>
                    <th className="text-right py-2 px-2">Diferencia</th>
                    <th className="text-center py-2 px-2">Estado</th>
                    <th className="text-center py-2 px-2">Acción</th>
                  </tr>
                </thead>
                <tbody>
                  {sessions.map((s) => (
                    <tr
                      key={s.uuid}
                      className="border-b border-slate-800 hover:bg-slate-800/50"
                    >
                      <td className="py-2 px-2 font-semibold text-white">
                        {s.session_number}
                      </td>
                      <td className="py-2 px-2 text-slate-300">{s.user_name || "-"}</td>
                      <td className="py-2 px-2 text-slate-400 text-xs">
                        {formatDT(s.opened_at)}
                      </td>
                      <td className="py-2 px-2 text-slate-400 text-xs">
                        {formatDT(s.closed_at)}
                      </td>
                      <td className="py-2 px-2 text-right">
                        {formatPrice(s.expected_amount)}
                      </td>
                      <td className="py-2 px-2 text-right">
                        {formatPrice(s.closing_amount || 0)}
                      </td>
                      <td
                        className={`py-2 px-2 text-right font-bold ${
                          s.has_discrepancy ? "text-red-400" : "text-green-400"
                        }`}
                      >
                        {formatPrice(s.difference)}
                      </td>
                      <td className="py-2 px-2 text-center">
                        {s.has_discrepancy ? (
                          <AlertCircle size={14} className="text-red-400 inline" />
                        ) : (
                          <CheckCircle2 size={14} className="text-green-400 inline" />
                        )}
                      </td>
                      <td className="py-2 px-2 text-center">
                        <button
                          onClick={() => setSelectedSessionUuid(s.uuid)}
                          className="px-2 py-1 bg-blue-500 hover:bg-blue-600 rounded text-white text-xs font-medium flex items-center gap-1 mx-auto"
                        >
                          <FileText size={12} />
                          Ver Z
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>
        </div>
      </div>

      {selectedSessionUuid && (
        <CashReportModal
          isOpen={!!selectedSessionUuid}
          sessionUuid={selectedSessionUuid}
          onClose={() => setSelectedSessionUuid(null)}
        />
      )}
    </>
  );
}
