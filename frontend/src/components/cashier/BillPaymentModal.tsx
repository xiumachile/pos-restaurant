import { useState, useMemo } from "react";
import type { Bill } from "@/types/bills";
import type { PaymentMethod } from "@/types/payments";
import { usePaymentMethods, usePayBill, useInvalidateCashier } from "@/hooks/usePayments";
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
  Plus,
  Trash2,
} from "lucide-react";

interface BillPaymentModalProps {
  bill: Bill;
  isOpen: boolean;
  onClose: () => void;
  onSuccess: () => void;
}

interface PendingPayment {
  id: string; // UUID local para React key
  payment_method_uuid: string;
  method_name: string;
  method_type: "cash" | "card" | "transfer" | "gift_card";
  amount: number;
  tip_amount: number;
  received_amount: number; // solo relevante para cash
  idempotency_key: string;
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
  // Estado de pagos agregados
  const [payments, setPayments] = useState<PendingPayment[]>([]);

  // Estado del formulario "agregar pago"
  const [selectedMethod, setSelectedMethod] = useState<PaymentMethod | null>(null);
  const [amountInput, setAmountInput] = useState<string>("");
  const [tipInput, setTipInput] = useState<string>("0");
  const [receivedInput, setReceivedInput] = useState<string>("");

  // Estado de procesamiento
  const [isProcessing, setIsProcessing] = useState(false);
  const [processingErrors, setProcessingErrors] = useState<string[]>([]);

  const { data: methods = [], isLoading: loadingMethods } = usePaymentMethods();
  const payBill = usePayBill();
  const invalidate = useInvalidateCashier();

  // Cálculos derivados
  const billTotal = bill.total;
  const billInitialPending = bill.remaining_amount;
  const paymentsSum = useMemo(
    () => payments.reduce((sum, p) => sum + p.amount, 0),
    [payments]
  );
  const tipsSum = useMemo(
    () => payments.reduce((sum, p) => sum + p.tip_amount, 0),
    [payments]
  );
  const availableToAdd = Math.max(0, billInitialPending - paymentsSum);
  const finalPending = Math.max(0, billInitialPending - paymentsSum);

  const currentAmount = parseFloat(amountInput) || 0;
  const currentTip = parseFloat(tipInput) || 0;
  const currentReceived = parseFloat(receivedInput) || 0;
  const currentTotal = currentAmount + currentTip;
  const currentChange =
    selectedMethod?.type === "cash"
      ? Math.max(0, currentReceived - currentTotal)
      : 0;

  // Validación del formulario actual
  const canAddPayment = useMemo(() => {
    if (!selectedMethod) return false;
    if (currentAmount <= 0) return false;
    if (currentAmount > availableToAdd + 0.01) return false;
    if (currentTip < 0) return false;
    if (selectedMethod.type === "cash") {
      return currentReceived >= currentTotal;
    }
    return true;
  }, [selectedMethod, currentAmount, currentTip, currentReceived, currentTotal, availableToAdd]);

  // Validación para confirmar todos los pagos
  const canConfirm = useMemo(() => {
    if (payments.length === 0) return false;
    if (isProcessing) return false;
    // Permitir confirmar incluso si hay pendiente? NO - debe estar completo
    return finalPending < 0.01;
  }, [payments, finalPending, isProcessing]);

  // Agregar pago a la lista (estado local, no envía al backend)
  const handleAddPayment = () => {
    if (!selectedMethod || !canAddPayment) return;

    const newPayment: PendingPayment = {
      id: crypto.randomUUID(),
      payment_method_uuid: selectedMethod.uuid,
      method_name: selectedMethod.name,
      method_type: selectedMethod.type as any,
      amount: currentAmount,
      tip_amount: currentTip,
      received_amount: selectedMethod.type === "cash" ? currentReceived : 0,
      idempotency_key: crypto.randomUUID(),
    };

    setPayments([...payments, newPayment]);

    // Resetear formulario
    setSelectedMethod(null);
    setAmountInput("");
    setTipInput("0");
    setReceivedInput("");
  };

  // Llenar con monto restante (atajo)
  const handleFillRemaining = () => {
    setAmountInput(String(availableToAdd));
  };

  // Eliminar pago de la lista
  const handleRemovePayment = (id: string) => {
    setPayments(payments.filter((p) => p.id !== id));
  };

  // Confirmar todos los pagos (envía al backend en secuencia)
  const handleConfirmAll = async () => {
    if (!canConfirm) return;

    setIsProcessing(true);
    setProcessingErrors([]);

    const errors: string[] = [];
    let successCount = 0;

    for (const payment of payments) {
      try {
        await payBill.mutateAsync({
          billUuid: bill.uuid,
          payload: {
            amount: payment.amount,
            payment_method_uuid: payment.payment_method_uuid,
            tip_amount: payment.tip_amount,
            idempotency_key: payment.idempotency_key,
          },
        });
        successCount++;
      } catch (e: any) {
        const msg =
          e?.response?.data?.message ||
          e?.message ||
          `Error procesando pago ${payment.method_name}`;
        errors.push(`${payment.method_name} (${formatPrice(payment.amount)}): ${msg}`);
      }
    }

    setIsProcessing(false);

    if (errors.length > 0) {
      setProcessingErrors(errors);
      // Algunos pagos fallaron, mantener los exitosos en la lista pero eliminar los procesados
      // (simplificación: si falló alguno, no cerrar y mostrar errores)
      return;
    }

    // Todo OK
    invalidate();
    onSuccess();
    onClose();
    // Resetear estado
    setPayments([]);
    setProcessingErrors([]);
  };

  // Reset al cerrar
  const handleClose = () => {
    if (isProcessing) return;
    setPayments([]);
    setProcessingErrors([]);
    setSelectedMethod(null);
    setAmountInput("");
    setTipInput("0");
    setReceivedInput("");
    onClose();
  };

  if (!isOpen) return null;

  return (
    <>
      <div className="fixed inset-0 bg-black/80 z-50" onClick={handleClose} />
      <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div
          className="bg-slate-900 rounded-xl shadow-2xl max-w-2xl w-full max-h-[95vh] flex flex-col"
          onClick={(e) => e.stopPropagation()}
        >
          {/* Header */}
          <div className="flex items-center justify-between p-5 border-b border-slate-700">
            <div>
              <h2 className="text-xl font-bold flex items-center gap-2">
                💳 Cobrar Cuenta
              </h2>
              <p className="text-sm text-slate-400 mt-0.5">
                Cuenta #{bill.bill_number} · Total: {formatPrice(billTotal)}
              </p>
            </div>
            <button
              onClick={handleClose}
              disabled={isProcessing}
              className="p-2 hover:bg-slate-800 rounded-lg disabled:opacity-50"
            >
              <X size={20} />
            </button>
          </div>

          {/* Resumen rápido */}
          <div className="grid grid-cols-3 gap-3 p-4 bg-slate-800/30 border-b border-slate-700">
            <div className="text-center">
              <div className="text-xs text-slate-400">Total Cuenta</div>
              <div className="font-bold text-white">{formatPrice(billTotal)}</div>
            </div>
            <div className="text-center">
              <div className="text-xs text-slate-400">Pagado</div>
              <div className="font-bold text-green-400">{formatPrice(paymentsSum)}</div>
            </div>
            <div className="text-center">
              <div className="text-xs text-slate-400">Pendiente</div>
              <div className={`font-bold ${finalPending > 0 ? "text-orange-400" : "text-green-400"}`}>
                {formatPrice(finalPending)}
              </div>
            </div>
          </div>

          {/* Contenido scrollable */}
          <div className="flex-1 overflow-y-auto p-5 space-y-5">
            {/* Formulario: Agregar pago */}
            <div className="bg-slate-800/50 rounded-lg p-4 border border-slate-700">
              <h3 className="font-semibold text-slate-200 mb-3 flex items-center gap-2">
                <Plus size={16} />
                Agregar Pago
              </h3>

              {loadingMethods ? (
                <div className="flex items-center justify-center py-4">
                  <Loader2 className="animate-spin text-orange-500" size={24} />
                </div>
              ) : (
                <>
                  {/* Selector de método */}
                  <div className="grid grid-cols-2 md:grid-cols-4 gap-2 mb-3">
                    {methods.map((method) => {
                      const Icon = METHOD_ICONS[method.type] || CreditCard;
                      const isSelected = selectedMethod?.uuid === method.uuid;
                      return (
                        <button
                          key={method.uuid}
                          onClick={() => setSelectedMethod(method)}
                          disabled={!method.is_active}
                          className={`flex flex-col items-center gap-1 p-3 rounded-lg border-2 transition-all disabled:opacity-40 ${
                            isSelected
                              ? "border-orange-500 bg-orange-500/10"
                              : "border-slate-700 hover:border-slate-600"
                          }`}
                        >
                          <Icon size={20} className={isSelected ? "text-orange-400" : "text-slate-400"} />
                          <span className="text-xs font-medium text-center">{method.name}</span>
                        </button>
                      );
                    })}
                  </div>

                  {selectedMethod && (
                    <div className="space-y-3">
                      <div className="grid grid-cols-2 gap-3">
                        <div>
                          <label className="text-xs text-slate-400 block mb-1">
                            Monto a pagar
                          </label>
                          <div className="flex gap-1">
                            <input
                              type="number"
                              value={amountInput}
                              onChange={(e) => setAmountInput(e.target.value)}
                              placeholder="0"
                              step="100"
                              min="0"
                              max={availableToAdd}
                              className="flex-1 bg-slate-900 border border-slate-600 rounded px-3 py-2 text-white focus:outline-none focus:border-orange-500"
                            />
                            <button
                              onClick={handleFillRemaining}
                              className="px-2 text-xs bg-slate-700 hover:bg-slate-600 rounded text-slate-300"
                              title="Llenar con monto restante"
                            >
                              Máx
                            </button>
                          </div>
                          <div className="text-xs text-slate-500 mt-1">
                            Disponible: {formatPrice(availableToAdd)}
                          </div>
                        </div>

                        <div>
                          <label className="text-xs text-slate-400 block mb-1">
                            Propina
                          </label>
                          <input
                            type="number"
                            value={tipInput}
                            onChange={(e) => setTipInput(e.target.value)}
                            placeholder="0"
                            step="100"
                            min="0"
                            className="w-full bg-slate-900 border border-slate-600 rounded px-3 py-2 text-white focus:outline-none focus:border-orange-500"
                          />
                        </div>
                      </div>

                      {/* Campos específicos para efectivo */}
                      {selectedMethod.type === "cash" && (
                        <div className="grid grid-cols-2 gap-3 p-3 bg-green-900/10 rounded border border-green-700/30">
                          <div>
                            <label className="text-xs text-green-300 block mb-1">
                              Recibido
                            </label>
                            <input
                              type="number"
                              value={receivedInput}
                              onChange={(e) => setReceivedInput(e.target.value)}
                              placeholder="0"
                              step="100"
                              min={currentTotal}
                              className="w-full bg-slate-900 border border-green-700/50 rounded px-3 py-2 text-white focus:outline-none focus:border-green-500"
                            />
                          </div>
                          <div>
                            <label className="text-xs text-green-300 block mb-1">
                              Cambio
                            </label>
                            <div className="bg-slate-900 border border-green-700/50 rounded px-3 py-2 text-green-400 font-bold">
                              {formatPrice(currentChange)}
                            </div>
                          </div>
                        </div>
                      )}

                      {/* Total del pago actual */}
                      <div className="flex justify-between items-center pt-2 border-t border-slate-700">
                        <span className="text-sm text-slate-400">Total de este pago:</span>
                        <span className="font-bold text-white">
                          {formatPrice(currentTotal)}
                        </span>
                      </div>

                      <button
                        onClick={handleAddPayment}
                        disabled={!canAddPayment}
                        className="w-full px-4 py-2.5 bg-orange-500 hover:bg-orange-600 disabled:bg-slate-700 disabled:text-slate-500 rounded-lg font-medium text-white flex items-center justify-center gap-2 transition-colors"
                      >
                        <Plus size={16} />
                        Agregar a la lista
                      </button>
                    </div>
                  )}
                </>
              )}
            </div>

            {/* Lista de pagos agregados */}
            {payments.length > 0 && (
              <div className="bg-slate-800/50 rounded-lg p-4 border border-slate-700">
                <h3 className="font-semibold text-slate-200 mb-3 flex items-center gap-2">
                  📋 Pagos a Procesar ({payments.length})
                </h3>
                <div className="space-y-2">
                  {payments.map((p) => {
                    const Icon = METHOD_ICONS[p.method_type] || CreditCard;
                    const change = p.method_type === "cash"
                      ? p.received_amount - (p.amount + p.tip_amount)
                      : 0;
                    return (
                      <div
                        key={p.id}
                        className="bg-slate-900/50 rounded-lg p-3 flex items-center gap-3"
                      >
                        <div className="w-10 h-10 bg-orange-500/20 rounded-full flex items-center justify-center flex-shrink-0">
                          <Icon size={18} className="text-orange-400" />
                        </div>
                        <div className="flex-1 min-w-0">
                          <div className="font-semibold text-white">{p.method_name}</div>
                          <div className="text-xs text-slate-400 space-x-2">
                            <span>Monto: {formatPrice(p.amount)}</span>
                            {p.tip_amount > 0 && (
                              <span>· Propina: {formatPrice(p.tip_amount)}</span>
                            )}
                            {p.method_type === "cash" && change > 0 && (
                              <span className="text-green-400">· Cambio: {formatPrice(change)}</span>
                            )}
                          </div>
                        </div>
                        <div className="text-right">
                          <div className="font-bold text-orange-400">
                            {formatPrice(p.amount + p.tip_amount)}
                          </div>
                          <button
                            onClick={() => handleRemovePayment(p.id)}
                            disabled={isProcessing}
                            className="text-xs text-red-400 hover:text-red-300 flex items-center gap-1 mt-1 disabled:opacity-50"
                          >
                            <Trash2 size={12} />
                            Eliminar
                          </button>
                        </div>
                      </div>
                    );
                  })}
                </div>

                {/* Resumen de la lista */}
                <div className="mt-3 pt-3 border-t border-slate-700 space-y-1">
                  <div className="flex justify-between text-sm">
                    <span className="text-slate-400">Subtotal pagos:</span>
                    <span className="text-white">{formatPrice(paymentsSum)}</span>
                  </div>
                  <div className="flex justify-between text-sm">
                    <span className="text-slate-400">Propinas:</span>
                    <span className="text-white">{formatPrice(tipsSum)}</span>
                  </div>
                  <div className="flex justify-between font-bold text-base pt-1 border-t border-slate-700">
                    <span className="text-slate-200">Total a cobrar:</span>
                    <span className="text-orange-400">{formatPrice(paymentsSum + tipsSum)}</span>
                  </div>
                </div>
              </div>
            )}

            {/* Errores de procesamiento */}
            {processingErrors.length > 0 && (
              <div className="bg-red-900/20 border border-red-700/50 rounded-lg p-4">
                <div className="flex items-center gap-2 text-red-300 font-semibold mb-2">
                  <AlertCircle size={16} />
                  Errores al procesar pagos
                </div>
                <ul className="space-y-1 text-sm text-red-200">
                  {processingErrors.map((err, i) => (
                    <li key={i}>• {err}</li>
                  ))}
                </ul>
              </div>
            )}
          </div>

          {/* Footer */}
          <div className="border-t border-slate-700 p-4 space-y-3">
            {finalPending > 0 && payments.length > 0 && (
              <div className="flex items-center gap-2 text-amber-400 text-sm bg-amber-900/20 border border-amber-700/40 rounded-lg px-3 py-2">
                <AlertCircle size={16} />
                Falta {formatPrice(finalPending)} por cubrir antes de confirmar.
              </div>
            )}

            <div className="flex gap-2">
              <button
                onClick={handleClose}
                disabled={isProcessing}
                className="flex-1 px-4 py-3 bg-slate-700 hover:bg-slate-600 rounded-lg font-medium disabled:opacity-50"
              >
                Cancelar
              </button>
              <button
                onClick={handleConfirmAll}
                disabled={!canConfirm}
                className="flex-1 px-4 py-3 bg-green-600 hover:bg-green-700 disabled:bg-slate-700 disabled:text-slate-500 rounded-lg font-bold text-white flex items-center justify-center gap-2"
              >
                {isProcessing ? (
                  <>
                    <Loader2 size={16} className="animate-spin" />
                    Procesando...
                  </>
                ) : (
                  <>
                    <CheckCircle2 size={16} />
                    Confirmar {payments.length} {payments.length === 1 ? "pago" : "pagos"}
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
