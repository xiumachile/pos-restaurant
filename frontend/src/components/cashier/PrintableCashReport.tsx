import type { CashReport } from "@/types/cashier";
import { formatPrice } from "@/types/catalog";

interface PrintableCashReportProps {
  report: CashReport;
}

const METHOD_LABELS: Record<string, string> = {
  cash: "EFECTIVO",
  card: "TARJETA",
  transfer: "TRANSFER.",
  gift_card: "GIFT CARD",
};

/**
 * Reporte de caja (X o Z) en formato ticket 80mm.
 */
export function PrintableCashReport({ report }: PrintableCashReportProps) {
  const { type, generated_at, session, sales, cash } = report;

  const formatDT = (iso: string | null) => {
    if (!iso) return "-";
    return new Date(iso).toLocaleString("es-CL", {
      day: "2-digit",
      month: "2-digit",
      year: "numeric",
      hour: "2-digit",
      minute: "2-digit",
    });
  };

  const isZ = type === "Z";

  return (
    <>
      <style>{`
        @media print {
          body * { visibility: hidden !important; }
          #printable-cash-report, #printable-cash-report * { visibility: visible !important; }
          #printable-cash-report {
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
          @page { margin: 0; size: 80mm auto; }
        }
      `}</style>

      <div id="printable-cash-report" className="bg-white text-black p-6 font-mono text-xs">
        <div className="text-center border-b-2 border-black pb-2 mb-2">
          <div className="text-base font-bold">WOK & MESA</div>
          <div>Restaurant Asiático</div>
          <div className="mt-1 font-bold text-sm">
            *** {isZ ? "REPORTE Z (CIERRE)" : "REPORTE X (PARCIAL)"} ***
          </div>
          <div>Sesión: {session.session_number}</div>
        </div>

        <div className="border-b border-dashed border-gray-400 pb-2 mb-2">
          <div className="flex justify-between">
            <span>Cajero:</span>
            <span>{session.user_name || "-"}</span>
          </div>
          <div className="flex justify-between">
            <span>Caja:</span>
            <span>{session.register_name || "-"}</span>
          </div>
          <div className="flex justify-between">
            <span>Apertura:</span>
            <span>{formatDT(session.opened_at)}</span>
          </div>
          {isZ && (
            <div className="flex justify-between">
              <span>Cierre:</span>
              <span>{formatDT(session.closed_at)}</span>
            </div>
          )}
          <div className="flex justify-between">
            <span>Impreso:</span>
            <span>{formatDT(generated_at)}</span>
          </div>
        </div>

        <div className="border-b border-dashed border-gray-400 pb-2 mb-2">
          <div className="font-bold mb-1">VENTAS POR MÉTODO</div>
          {Object.entries(sales.breakdown).map(([key, val]) => {
            if (val.count === 0) return null;
            return (
              <div key={key} className="flex justify-between">
                <span>{METHOD_LABELS[key]} ({val.count}):</span>
                <span>{formatPrice(val.amount)}</span>
              </div>
            );
          })}
          <div className="flex justify-between mt-1 pt-1 border-t border-gray-300">
            <span>TOTAL VENTAS:</span>
            <span>{formatPrice(sales.total_sales)}</span>
          </div>
          {sales.total_tips > 0 && (
            <div className="flex justify-between">
              <span>PROPINAS:</span>
              <span>{formatPrice(sales.total_tips)}</span>
            </div>
          )}
          <div className="flex justify-between font-bold">
            <span>TOTAL TRANSACCIONES:</span>
            <span>{sales.total_transactions}</span>
          </div>
        </div>

        <div className="border-b border-dashed border-gray-400 pb-2 mb-2">
          <div className="font-bold mb-1">RESUMEN EFECTIVO</div>
          <div className="flex justify-between">
            <span>Monto inicial:</span>
            <span>{formatPrice(cash.opening)}</span>
          </div>
          <div className="flex justify-between">
            <span>+ Ventas efectivo:</span>
            <span>{formatPrice(cash.sales)}</span>
          </div>
          <div className="flex justify-between">
            <span>+ Propinas efectivo:</span>
            <span>{formatPrice(cash.tips)}</span>
          </div>
          {cash.deposits > 0 && (
            <div className="flex justify-between">
              <span>+ Depósitos:</span>
              <span>{formatPrice(cash.deposits)}</span>
            </div>
          )}
          {cash.withdrawals > 0 && (
            <div className="flex justify-between">
              <span>- Retiros:</span>
              <span>{formatPrice(cash.withdrawals)}</span>
            </div>
          )}
          <div className="flex justify-between font-bold mt-1 pt-1 border-t-2 border-black">
            <span>ESPERADO:</span>
            <span>{formatPrice(cash.expected)}</span>
          </div>
          {isZ && cash.counted !== null && cash.difference !== null && (
            <>
              <div className="flex justify-between">
                <span>CONTADO:</span>
                <span>{formatPrice(cash.counted)}</span>
              </div>
              <div className={`flex justify-between font-bold ${cash.difference === 0 ? "" : "underline"}`}>
                <span>DIFERENCIA:</span>
                <span>{formatPrice(cash.difference)}</span>
              </div>
            </>
          )}
        </div>

        <div className="text-center border-t border-dashed border-gray-400 pt-2">
          <div>www.wokmesa.cl</div>
        </div>
      </div>
    </>
  );
}
