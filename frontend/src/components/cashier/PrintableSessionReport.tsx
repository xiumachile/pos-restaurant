import type { SessionPaymentsData } from "@/types/payments";
import { formatPrice } from "@/types/catalog";

interface PrintableSessionReportProps {
  data: SessionPaymentsData;
}

const METHOD_LABELS: Record<string, string> = {
  CASH: "EFECTIVO",
  CARD: "TARJETA",
  TRANSFER: "TRANSFERENCIA",
  GIFT_CARD: "GIFT CARD",
};

/**
 * Reporte de ventas de la sesión en formato ticket 80mm.
 * Visible solo al imprimir (window.print()).
 */
export function PrintableSessionReport({ data }: PrintableSessionReportProps) {
  const { session, payments, summary } = data;
  if (!session || !summary) return null;

  const formatDateTime = (iso: string | null) => {
    if (!iso) return "-";
    return new Date(iso).toLocaleString("es-CL", {
      day: "2-digit",
      month: "2-digit",
      year: "numeric",
      hour: "2-digit",
      minute: "2-digit",
    });
  };

  const formatTime = (iso: string | null) => {
    if (!iso) return "-";
    return new Date(iso).toLocaleTimeString("es-CL", {
      hour: "2-digit",
      minute: "2-digit",
    });
  };

  return (
    <>
      <style>{`
        @media print {
          body * {
            visibility: hidden !important;
          }
          #printable-session-report, #printable-session-report * {
            visibility: visible !important;
          }
          #printable-session-report {
            position: absolute !important;
            left: 0 !important;
            top: 0 !important;
            width: 80mm !important;
            padding: 5mm !important;
            background: white !important;
            color: black !important;
            font-family: 'Courier New', monospace !important;
            font-size: 10px !important;
          }
          @page {
            margin: 0;
            size: 80mm auto;
          }
        }
      `}</style>

      <div id="printable-session-report" className="bg-white text-black p-6 font-mono text-xs">
        {/* Header */}
        <div className="text-center border-b-2 border-black pb-2 mb-2">
          <div className="text-base font-bold">WOK & MESA</div>
          <div>Restaurant Asiático</div>
          <div className="mt-1 font-bold">*** REPORTE DE VENTAS ***</div>
          <div>Sesión: {session.session_number}</div>
        </div>

        {/* Info sesión */}
        <div className="border-b border-dashed border-gray-400 pb-2 mb-2">
          <div className="flex justify-between">
            <span>Cajero:</span>
            <span>{session.user_name || "-"}</span>
          </div>
          <div className="flex justify-between">
            <span>Apertura:</span>
            <span>{formatDateTime(session.opened_at)}</span>
          </div>
          <div className="flex justify-between">
            <span>Impreso:</span>
            <span>{formatDateTime(new Date().toISOString())}</span>
          </div>
          <div className="flex justify-between">
            <span>Monto inicial:</span>
            <span>{formatPrice(session.opening_amount)}</span>
          </div>
        </div>

        {/* Detalle de pagos */}
        <div className="border-b border-dashed border-gray-400 pb-2 mb-2">
          <div className="font-bold mb-1">
            VENTAS COBRADAS ({summary.transactions_count})
          </div>
          {payments.length === 0 ? (
            <div className="text-center">Sin ventas en esta sesión</div>
          ) : (
            payments.map((p) => (
              <div key={p.uuid} className="mb-1">
                <div className="flex justify-between">
                  <span>{formatTime(p.paid_at)}</span>
                  <span>M{p.table_number || "-"}</span>
                  <span>{METHOD_LABELS[p.method_code] || p.method_code}</span>
                  <span>{formatPrice(p.total_amount)}</span>
                </div>
                {p.tip_amount > 0 && (
                  <div className="text-right text-[9px]">
                    (incl. propina {formatPrice(p.tip_amount)})
                  </div>
                )}
              </div>
            ))
          )}
        </div>

        {/* Resumen por método */}
        <div className="border-b border-dashed border-gray-400 pb-2 mb-2">
          <div className="font-bold mb-1">RESUMEN POR MÉTODO</div>
          <div className="flex justify-between">
            <span>EFECTIVO ({summary.by_method.cash.count}):</span>
            <span>{formatPrice(summary.by_method.cash.amount)}</span>
          </div>
          <div className="flex justify-between">
            <span>TARJETA ({summary.by_method.card.count}):</span>
            <span>{formatPrice(summary.by_method.card.amount)}</span>
          </div>
          <div className="flex justify-between">
            <span>TRANSFER. ({summary.by_method.transfer.count}):</span>
            <span>{formatPrice(summary.by_method.transfer.amount)}</span>
          </div>
          <div className="flex justify-between">
            <span>GIFT CARD ({summary.by_method.gift_card.count}):</span>
            <span>{formatPrice(summary.by_method.gift_card.amount)}</span>
          </div>
        </div>

        {/* Totales */}
        <div className="space-y-1">
          <div className="flex justify-between">
            <span>TOTAL VENTAS:</span>
            <span>{formatPrice(summary.total_sales)}</span>
          </div>
          <div className="flex justify-between">
            <span>TOTAL PROPINAS:</span>
            <span>{formatPrice(summary.total_tips)}</span>
          </div>
          <div className="flex justify-between font-bold border-t-2 border-black pt-1">
            <span>TOTAL GENERAL:</span>
            <span>{formatPrice(summary.total_grand)}</span>
          </div>
          <div className="flex justify-between font-bold">
            <span>ESPERADO EN CAJA:</span>
            <span>{formatPrice(summary.total_cash_expected)}</span>
          </div>
        </div>

        {/* Footer */}
        <div className="text-center border-t border-dashed border-gray-400 pt-2 mt-2">
          <div>¡Gracias por su trabajo!</div>
          <div>www.wokmesa.cl</div>
        </div>
      </div>
    </>
  );
}
