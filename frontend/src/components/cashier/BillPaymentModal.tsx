import { useState, useMemo } from "react";
import type { Bill } from "@/types/bills";
import type { PaymentMethod } from "@/types/payments";
import { usePaymentMethods, usePayBill } from "@/hooks/usePayments";
import { formatPrice } from "@/types/catalog";
import {
  X,
  Loader2,
  AlertCircle,
  CheckCircle2,
  CreditCard,
  Banknote,
  Building2,
  Gift,
  Receipt,
} from "lucide-react";

interface BillPaymentModalProps {
  bill: Bill;
  isOpen: boolean;
  onClose: () => void;
  onSuccess: () => void;
}

const METHOD_ICONS: Record<string, any> = {
  cash: Banknote,
  card: CreditCard,
  transfer: Building2,
  gift_card: Gift,
};

export function BillPaymentModal({
  bill,
  isOpen,
  onClose,
  onSuccess,
}: BillPaymentModalProps) {
  const [selectedMethod, setSelectedMethod] = useState<PaymentMethod | null>(null);
  const [tipAmount, setTipAmount] = useState<string>("0");
  const [receivedAmount, setReceivedAmount] = useState<string>("");

  const { data: methods = [], isLoading: loadingMethods } = usePaymentMethods();
  const payBill = usePayBill();

  const amountToPay = bill.remaining_amount;

  const grandTotal = useMemo(() => {
    const tip = parseFloat(tipAmount) || 0;
    return amountToPay + tip;
  }, [amountToPay, tipAmount]);

  const change = useMemo(() => {
    if (!selectedMethod || selectedMethod.type !== "cash") return 0;
    const received = parseFloat(receivedAmount) || 0;
    return Math.max(0, received - grandTotal);
  }, [receivedAmount, grandTotal, selectedMethod]);

  const isButtonDisabled = useMemo(() => {
    if (!selectedMethod || payBill.isPending) return true;
    if (selectedMethod.type === "cash") {
      return (parseFloat(receivedAmount) || 0) < grandTotal;
    }
    return false;
  }, [selectedMethod, payBill.isPending, receivedAmount, grandTotal]);

  const handlePay = async () => {
    if (!selectedMethod || isButtonDisabled) return;

    const idempotencyKey = crypto.randomUUID();

    try {
      await payBill.mutateAsync({
        billUuid: bill.uuid,
        payload: {
          payment_method_uuid: selectedMethod.uuid,
          tip_amount: parseFloat(tipAmount) || 0,
          idempotency_key: idempotencyKey,
        },
      });
      onSuccess();
      onClose();
    } catch (e) {
      console.error("Error al cobrar bill:", e);
    }
  };

  if (!isOpen) return null;

  return (
    <>
      <div className="fixed inset-0 bg-black/80 z-50" onClick={onClose} />
      <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div
          className="bg-slate-900 rounded-xl shadow-2xl max-w-lg w-full max-h-[90vh] overflow-y-auto"
          onClick={(e) => e.stopPropagation()}
        >
          {/* Header */}
          <div className="sticky top-0 bg-slate-900 flex items-center justify-between p-5 border-b border-slate-700">
            <div>
              <div className="flex items-center gap-2 mb-1">
                <Receipt size={20} className="text-orange-400" />
                <h2 className="text-xl font-bold">Cobrar Sub-cuenta</h2>
              </div>
              <p className="text-sm text-slate-400">
                {bill.bill_number} · {bill.type === "equal_split" ? "División equitativa" : bill.type === "by_items" ? "Por productos" : "Por montos"}
              </p>
            </div>
            <button onClick={onClose} className="p-2 hover:bg-slate-800 rounded-lg">
              <X size={20} />
            </button>
          </div>

          <div className="p-6 space-y-5">
            {/* Resumen de la bill */}
            <div className="bg-slate-800 rounded-lg p-4 space-y-1">
              <div className="flex justify-between text-sm">
                <span className="text-slate-400">Subtotal:</span>
                <span>{formatPrice(bill.subtotal)}</span>
              </div>
              <div className="flex justify-between text-sm">
                <span className="text-slate-400">IVA:</span>
                <span>{formatPrice(bill.tax_amount)}</span>
              </div>
              {bill.paid_amount > 0 && (
                <div className="flex justify-between text-sm text-green-400">
                  <span>Ya pagado:</span>
                  <span>-{formatPrice(bill.paid_amount)}</span>
                </div>
              )}
              <div className="flex justify-between text-lg font-bold pt-2 border-t border-slate-700">
                <span>Por cobrar:</span>
                <span className="text-orange-400">
                  {formatPrice(amountToPay)}
                </span>
              </div>
            </div>

            {/* Propina */}
            <div>
              <label className="block text-sm text-slate-400 mb-1">
                Propina (opcional)
              </label>
              <input
                type="number"
                value={tipAmount}
                onChange={(e) => setTipAmount(e.target.value)}
                step="0.01"
                min="0"
                className="w-full px-4 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-lg font-bold focus:outline-none focus:ring-2 focus:ring-orange-500"
              />
              {parseFloat(tipAmount) > 0 && (
                <p className="text-sm mt-1 text-orange-300">
                  Total con propina:{" "}
                  <strong>{formatPrice(grandTotal)}</strong>
                </p>
              )}
            </div>

            {/* Método de pago */}
            <div>
              <h3 className="text-sm font-semibold text-slate-400 mb-2">
                Método de pago
              </h3>
              {loadingMethods ? (
                <div className="text-center py-3">
                  <Loader2 className="animate-spin mx-auto" size={24} />
                </div>
              ) : (
                <div className="grid grid-cols-2 gap-2">
                  {methods.map((method) => {
                    const Icon = METHOD_ICONS[method.type] || CreditCard;
                    const isSelected = selectedMethod?.uuid === method.uuid;
                    return (
                      <button
                        key={method.uuid}
                        onClick={() => {
                          setSelectedMethod(method);
                          if (method.type !== "cash") setReceivedAmount("");
                        }}
                        className={`p-3 rounded-lg border-2 transition-all flex items-center gap-2 ${
                          isSelected
                            ? "border-orange-500 bg-orange-500/10"
                            : "border-slate-700 bg-slate-800 hover:border-slate-600"
                        }`}
                      >
                        <span className="text-xl">
                          {method.icon || <Icon size={20} />}
                        </span>
                        <span className="font-semibold text-sm">
                          {method.name_translations?.es || method.code}
                        </span>
                      </button>
                    );
                  })}
                </div>
              )}
            </div>

            {/* Recibido (efectivo) */}
            {selectedMethod?.type === "cash" && (
              <div className="bg-green-900/20 border border-green-700/40 rounded-lg p-4 space-y-2">
                <label className="block text-sm text-green-300">
                  Monto recibido
                </label>
                <input
                  type="number"
                  value={receivedAmount}
                  onChange={(e) => setReceivedAmount(e.target.value)}
                  step="0.01"
                  min="0"
                  autoFocus
                  className="w-full px-4 py-2 bg-slate-800 border border-green-700 rounded-lg text-white text-xl font-bold focus:outline-none focus:ring-2 focus:ring-green-500"
                  placeholder="0"
                />
                {receivedAmount && (
                  <div className="flex justify-between items-center pt-2 border-t border-green-700/40">
                    <span className="text-green-300 font-semibold">Vuelto:</span>
                    <span
                      className={`text-xl font-bold ${
                        change >= 0 ? "text-green-400" : "text-red-400"
                      }`}
                    >
                      {formatPrice(change)}
                    </span>
                  </div>
                )}
              </div>
            )}

            {/* Error */}
            {payBill.isError && (
              <div className="bg-red-900/30 border border-red-700 rounded-lg p-3 text-sm text-red-300 flex items-start gap-2">
                <AlertCircle size={16} className="flex-shrink-0 mt-0.5" />
                <span>
                  {(payBill.error as Error).message || "Error al cobrar"}
                </span>
              </div>
            )}

            {/* Botones */}
            <div className="flex gap-2">
              <button
                onClick={onClose}
                disabled={payBill.isPending}
                className="flex-1 px-4 py-3 bg-slate-700 hover:bg-slate-600 rounded-lg font-medium disabled:opacity-50"
              >
                Cancelar
              </button>
              <button
                onClick={handlePay}
                disabled={isButtonDisabled}
                className="flex-1 px-4 py-3 bg-orange-500 hover:bg-orange-600 rounded-lg font-bold text-white disabled:opacity-50 flex items-center justify-center gap-2"
              >
                {payBill.isPending ? (
                  <>
                    <Loader2 size={16} className="animate-spin" />
                    Procesando...
                  </>
                ) : (
                  <>
                    <CheckCircle2 size={16} />
                    Cobrar {formatPrice(grandTotal)}
                  </>
                )}
              </button>
            </div>
          </div>
        </div>
      </div>
    </>
  );
}
