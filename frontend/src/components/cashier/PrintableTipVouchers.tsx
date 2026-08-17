import { formatPrice } from "@/types/catalog";

interface PrintableTipVouchersProps {
  payouts: Array<{
    uuid: string;
    waiter_name: string;
    amount: number;
    payment_method: string;
  }>;
}

/**
 * Vouchers de propinas imprimibles (80mm).
 */
export function PrintableTipVouchers({ payouts }: PrintableTipVouchersProps) {
  const now = new Date().toLocaleString("es-CL", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });

  return (
    <>
      <style>{`
        @media print {
          body * { visibility: hidden !important; }
          #printable-tip-vouchers, #printable-tip-vouchers * { visibility: visible !important; }
          #printable-tip-vouchers {
            position: absolute !important;
            left: 0 !important;
            top: 0 !important;
            width: 80mm !important;
            padding: 5mm !important;
            background: white !important;
            color: black !important;
            font-family: 'Courier New', monospace !important;
            font-size: 11px !important;
          }
          .tip-voucher {
            page-break-after: always;
            padding: 5mm 0;
            border-bottom: 1px dashed #000;
          }
          .tip-voucher:last-child {
            page-break-after: auto;
          }
          @page { margin: 0; size: 80mm auto; }
        }
      `}</style>

      <div id="printable-tip-vouchers" className="bg-white text-black p-6 font-mono text-xs">
        {payouts.map((payout, i) => (
          <div key={payout.uuid} className="tip-voucher">
            <div className="text-center border-b-2 border-black pb-2 mb-2">
              <div className="text-base font-bold">WOK & MESA</div>
              <div className="text-xs">Restaurant Asiático</div>
              <div className="mt-1 font-bold text-sm">*** PROPINA ***</div>
            </div>

            <div className="space-y-1">
              <div className="flex justify-between">
                <span>Garzón:</span>
                <span className="font-bold">{payout.waiter_name}</span>
              </div>
              <div className="flex justify-between">
                <span>Fecha:</span>
                <span>{now}</span>
              </div>
              <div className="flex justify-between">
                <span>Método:</span>
                <span>{payout.payment_method === "cash" ? "EFECTIVO" : payout.payment_method}</span>
              </div>
            </div>

            <div className="mt-3 pt-2 border-t-2 border-black">
              <div className="flex justify-between font-bold text-base">
                <span>TOTAL:</span>
                <span>{formatPrice(payout.amount)}</span>
              </div>
            </div>

            <div className="mt-4 pt-2 border-t border-dashed border-gray-400">
              <div className="text-center text-xs">
                <div className="mb-6">_________________________</div>
                <div>Firma del Garzón</div>
              </div>
            </div>
          </div>
        ))}
      </div>
    </>
  );
}
