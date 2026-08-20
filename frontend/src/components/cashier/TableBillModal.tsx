import { useState, useMemo, useEffect } from "react";
import type { Bill } from "@/types/bills";
import {
  useTablesWithBills,
  usePrepareTableBills,
} from "@/hooks/usePayments";
import { formatPrice } from "@/types/catalog";
import {
  X,
  Loader2,
  CheckCircle2,
  Receipt,
} from "lucide-react";
import { BillPaymentModalV2 } from "./BillPaymentModalV2";

interface TableBillModalProps {
  tableUuid: string;
  isOpen: boolean;
  onClose: () => void;
  onSuccess: () => void;
}

export function TableBillModal({
  tableUuid,
  isOpen,
  onClose,
  onSuccess,
}: TableBillModalProps) {
  const { data: tablesWithBills = [] } = useTablesWithBills();
  const tableBill = useMemo(
    () => tablesWithBills.find((t) => t.table_uuid === tableUuid),
    [tablesWithBills, tableUuid]
  );

  const [payingBills, setPayingBills] = useState<Bill[] | null>(null);
  const [showSuccessToast, setShowSuccessToast] = useState(false);
  const [successMessage, setSuccessMessage] = useState("");

  const prepareTableBills = usePrepareTableBills();

  useEffect(() => {
    if (showSuccessToast) {
      const timer = setTimeout(() => setShowSuccessToast(false), 3000);
      return () => clearTimeout(timer);
    }
  }, [showSuccessToast]);

  const handleOpenPayment = async () => {
    if (!tableBill || prepareTableBills.isPending) return;

    try {
      const result = await prepareTableBills.mutateAsync(tableBill.table_uuid);
      setPayingBills(result.bills);
    } catch (e) {
      console.error("Error preparando bills:", e);
      alert("No se pudieron preparar las sub-cuentas. Intenta de nuevo.");
    }
  };

  useEffect(() => {
    if (isOpen && tableBill === undefined) {
      onClose();
      onSuccess();
    }
  }, [isOpen, tableBill, onClose, onSuccess]);

  if (!isOpen || !tableBill) return null;

  const totalAmount = tableBill.total_amount;

  return (
    <>
      <div className="fixed inset-0 bg-black/80 z-50" onClick={onClose} />
      <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div
          className="bg-slate-900 rounded-xl shadow-2xl max-w-md w-full"
          onClick={(e) => e.stopPropagation()}
        >
          {/* Header */}
          <div className="flex items-center justify-between p-5 border-b border-slate-700">
            <div>
              <h2 className="text-xl font-bold flex items-center gap-2">
                <Receipt size={20} />
                Consumo de la Mesa
              </h2>
              <p className="text-sm text-slate-400 mt-0.5">
                Mesa {tableBill.table_number} · {tableBill.orders_count} pedido{tableBill.orders_count !== 1 ? "s" : ""} · {tableBill.total_items} ítem{tableBill.total_items !== 1 ? "s" : ""}
              </p>
            </div>
            <button
              onClick={onClose}
              disabled={prepareTableBills.isPending}
              className="p-2 hover:bg-slate-800 rounded-lg disabled:opacity-50"
            >
              <X size={20} />
            </button>
          </div>

          {/* Consumo */}
          <div className="p-5 space-y-3">
            <div className="bg-slate-800/50 rounded-lg p-4 space-y-2">
              <div className="flex justify-between text-sm">
                <span className="text-slate-400">Subtotal</span>
                <span className="text-white">{formatPrice(tableBill.subtotal)}</span>
              </div>
              <div className="flex justify-between text-sm">
                <span className="text-slate-400">IVA (19%)</span>
                <span className="text-white">{formatPrice(tableBill.tax_amount)}</span>
              </div>
              <div className="flex justify-between text-2xl font-bold pt-3 border-t border-slate-700">
                <span className="text-slate-200">Total</span>
                <span className="text-orange-400">{formatPrice(totalAmount)}</span>
              </div>
            </div>
          </div>

          {/* Botones */}
          <div className="border-t border-slate-700 p-4 flex gap-2">
            <button
              onClick={onClose}
              disabled={prepareTableBills.isPending}
              className="flex-1 px-4 py-3 bg-slate-700 hover:bg-slate-600 rounded-lg font-medium disabled:opacity-50"
            >
              Cancelar
            </button>
            <button
              onClick={handleOpenPayment}
              disabled={!tableBill || prepareTableBills.isPending}
              className="flex-1 px-4 py-3 bg-green-600 hover:bg-green-700 rounded-lg font-bold text-white disabled:opacity-50 flex items-center justify-center gap-2"
            >
              {prepareTableBills.isPending ? (
                <>
                  <Loader2 size={16} className="animate-spin" />
                  Preparando...
                </>
              ) : (
                <>
                  <CheckCircle2 size={16} />
                  Cobrar {formatPrice(totalAmount)}
                </>
              )}
            </button>
          </div>
        </div>
      </div>

      {/* Modal de pago */}
      <BillPaymentModalV2
        bills={payingBills}
        isOpen={payingBills !== null}
        onClose={() => setPayingBills(null)}
        onSuccess={() => {
          setPayingBills(null);
          setSuccessMessage("✅ Mesa cobrada correctamente");
          setShowSuccessToast(true);
          onSuccess();
        }}
      />

      {/* Toast de éxito */}
      {showSuccessToast && (
        <div className="fixed bottom-6 right-6 bg-green-600 text-white px-5 py-3 rounded-lg shadow-xl z-[60] flex items-center gap-2 animate-in slide-in-from-bottom-4">
          <CheckCircle2 size={18} />
          {successMessage}
        </div>
      )}
    </>
  );
}
