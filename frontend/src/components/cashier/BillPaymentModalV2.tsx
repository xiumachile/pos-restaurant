import { useState, useCallback } from "react";
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
  Trash2,
} from "lucide-react";

interface BillPaymentModalV2Props {
  bill?: Bill | null;
  bills?: Bill[] | null;
  isOpen: boolean;
  onClose: () => void;
  onSuccess: () => void;
}

interface PendingPayment {
  id: string;
  payment_method_uuid: string;
  method_code: string;
  amount: number;
  tip_amount: number;
  received_amount: number;
  idempotency_key: string;
}

const PAYMENT_CONFIG: Record<string, { label: string; icon: any; color: string }> = {
  CASH: { label: "Efectivo", icon: Banknote, color: "bg-green-600 hover:bg-green-700" },
  CARD: { label: "Tarjeta", icon: CreditCard, color: "bg-blue-600 hover:bg-blue-700" },
  TRANSFER: { label: "Transfer", icon: Building2, color: "bg-purple-600 hover:bg-purple-700" },
  GIFT_CARD: { label: "Gift Card", icon: Gift, color: "bg-amber-600 hover:bg-amber-700" },
  DEBIT_CARD: { label: "Débito", icon: CreditCard, color: "bg-blue-600 hover:bg-blue-700" },
  CREDIT_CARD: { label: "Crédito", icon: CreditCard, color: "bg-blue-600 hover:bg-blue-700" },
};

export function BillPaymentModalV2({
  bill,
  bills,
  isOpen,
  onClose,
  onSuccess,
}: BillPaymentModalV2Props) {
  const effectiveBills = bills && bills.length > 0 ? bills : bill ? [bill] : [];

  const [selectedMethod, setSelectedMethod] = useState<PaymentMethod | null>(null);
  const [amountInput, setAmountInput] = useState<string>("");
  const [tipInput, setTipInput] = useState<string>("0");
  const [receivedInput, setReceivedInput] = useState<string>("");
  const [payments, setPayments] = useState<PendingPayment[]>([]);
  const [isProcessing, setIsProcessing] = useState(false);
  const [errors, setErrors] = useState<string[]>([]);

  const { data: methods = [] } = usePaymentMethods();
  const payBill = usePayBill();
  const invalidate = useInvalidateCashier();

  const billTotal = effectiveBills.reduce((sum, b) => sum + b.total, 0);
  const billPending = effectiveBills.reduce((sum, b) => sum + b.remaining_amount, 0);
  const paymentsSum = payments.reduce((sum, p) => sum + p.amount, 0);
  const tipsSum = payments.reduce((sum, p) => sum + p.tip_amount, 0);
  const remaining = Math.max(0, billPending - paymentsSum);
  const canCharge = remaining < 0.01 && payments.length > 0 && !isProcessing;

  const currentAmount = parseFloat(amountInput) || 0;
  const currentTip = parseFloat(tipInput) || 0;
  const currentReceived = parseFloat(receivedInput) || 0;
  const change = selectedMethod?.type === "cash"
    ? Math.max(0, currentReceived - (currentAmount + currentTip))
    : 0;

  const handleKeyPress = useCallback((key: string) => {
    if (key === "C") {
      setAmountInput("");
    } else if (key === "⌫") {
      setAmountInput(prev => prev.slice(0, -1));
    } else if (key === ".") {
      if (!amountInput.includes(".")) setAmountInput(prev => prev + ".");
    } else if (currentAmount === 0 && key === "0") {
      // no permitir ceros a la izquierda
    } else {
      setAmountInput(prev => prev + key);
    }
  }, [amountInput, currentAmount]);

  const handleFillRemaining = () => {
    setAmountInput(String(Math.floor(remaining)));
  };

  const handleAddPayment = () => {
    if (!selectedMethod || currentAmount <= 0 || currentAmount > remaining + 0.01) return;

    const newPayment: PendingPayment = {
      id: crypto.randomUUID(),
      payment_method_uuid: selectedMethod.uuid,
      method_code: selectedMethod.code,
      amount: currentAmount,
      tip_amount: currentTip,
      received_amount: selectedMethod.type === "cash" ? currentReceived : 0,
      idempotency_key: crypto.randomUUID(),
    };

    setPayments([...payments, newPayment]);
    setAmountInput("");
    setTipInput("0");
    setReceivedInput("");
  };

  const handleRemovePayment = (id: string) => {
    setPayments(payments.filter(p => p.id !== id));
  };

  const handleCharge = async () => {
    if (!canCharge || effectiveBills.length === 0) return;

    setIsProcessing(true);
    setErrors([]);

    const billsRemaining = effectiveBills.map(b => ({
      uuid: b.uuid,
      remaining: b.remaining_amount,
    }));

    const errorsList: string[] = [];

    for (const payment of payments) {
      try {
        let amountLeft = payment.amount;
        let tipLeft = payment.tip_amount;

        while (amountLeft > 0.01) {
          const nextBill = billsRemaining.find(b => b.remaining > 0.01);
          if (!nextBill) {
            errorsList.push(`${payment.method_code}: No hay bills con saldo`);
            break;
          }

          const amountForBill = Math.min(amountLeft, nextBill.remaining);
          const tipForBill = amountLeft === payment.amount ? tipLeft : 0;

          await payBill.mutateAsync({
            billUuid: nextBill.uuid,
            payload: {
              amount: amountForBill,
              payment_method_uuid: payment.payment_method_uuid,
              tip_amount: tipForBill,
              idempotency_key: crypto.randomUUID(),
            },
          });

          amountLeft -= amountForBill;
          tipLeft -= tipForBill;
          nextBill.remaining -= amountForBill;
        }
      } catch (e: any) {
        const msg = e?.response?.data?.message || e?.message || "Error";
        errorsList.push(`${payment.method_code}: ${msg}`);
      }
    }

    setIsProcessing(false);

    if (errorsList.length > 0) {
      setErrors(errorsList);
      return;
    }

    invalidate();
    onSuccess();
    onClose();
    setPayments([]);
    setErrors([]);
  };

  const handleClose = () => {
    if (isProcessing) return;
    setPayments([]);
    setErrors([]);
    setSelectedMethod(null);
    setAmountInput("");
    setTipInput("0");
    setReceivedInput("");
    onClose();
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 bg-black z-50 flex flex-col">
      {/* Header compacto */}
      <div className="bg-slate-900 border-b border-slate-700 px-4 py-3 flex items-center justify-between flex-shrink-0">
        <div>
          <h2 className="text-lg font-bold text-white flex items-center gap-2">
            💳 Cobrar Cuenta
          </h2>
          <p className="text-xs text-slate-400">
            {effectiveBills.length === 1
              ? `Cuenta #${effectiveBills[0].bill_number}`
              : `${effectiveBills.length} sub-cuentas`}
          </p>
        </div>
        <button
          onClick={handleClose}
          disabled={isProcessing}
          className="p-2 hover:bg-slate-800 rounded-lg disabled:opacity-50"
        >
          <X size={24} />
        </button>
      </div>

      {/* Contenido principal: flex row en desktop, column en mobile */}
      <div className="flex-1 flex flex-col lg:flex-row overflow-hidden bg-slate-950">
        {/* Columna izquierda: Pagos agregados (scrollable) */}
        <div className="lg:w-1/2 flex flex-col border-r border-slate-800 overflow-hidden">
          {/* Resumen de totales */}
          <div className="bg-slate-900 border-b border-slate-800 p-3 grid grid-cols-3 gap-2 flex-shrink-0">
            <div className="text-center">
              <div className="text-[10px] text-slate-500 uppercase">Total</div>
              <div className="text-sm font-bold text-white">{formatPrice(billTotal)}</div>
            </div>
            <div className="text-center">
              <div className="text-[10px] text-slate-500 uppercase">Pagado</div>
              <div className="text-sm font-bold text-blue-400">{formatPrice(paymentsSum)}</div>
            </div>
            <div className="text-center">
              <div className="text-[10px] text-slate-500 uppercase">Pendiente</div>
              <div className={`text-sm font-bold ${remaining > 0 ? "text-orange-400" : "text-green-400"}`}>
                {formatPrice(remaining)}
              </div>
            </div>
          </div>

          {/* Lista de pagos */}
          <div className="flex-1 overflow-y-auto p-3 space-y-2">
            {payments.length === 0 ? (
              <div className="text-center py-12 text-slate-600">
                <div className="text-5xl mb-3">💰</div>
                <p className="text-sm">Sin pagos agregados</p>
                <p className="text-xs mt-1 text-slate-500">
                  Usa el teclado de la derecha para agregar pagos
                </p>
              </div>
            ) : (
              <>
                {payments.map((p) => {
                  const config = PAYMENT_CONFIG[p.method_code.toUpperCase()] || {
                    label: p.method_code,
                    icon: CreditCard,
                    color: "bg-slate-600",
                  };
                  const Icon = config.icon;

                  return (
                    <div
                      key={p.id}
                      className="bg-slate-900 rounded-lg p-3 flex items-center gap-3 border border-slate-800"
                    >
                      <div className={`w-10 h-10 rounded-lg ${config.color} flex items-center justify-center flex-shrink-0`}>
                        <Icon size={18} className="text-white" />
                      </div>
                      <div className="flex-1 min-w-0">
                        <div className="font-semibold text-white text-sm">{config.label}</div>
                        <div className="text-xs text-slate-400">
                          {formatPrice(p.amount)}
                          {p.tip_amount > 0 && (
                            <span className="ml-2 text-orange-400">+ {formatPrice(p.tip_amount)} propina</span>
                          )}
                          {p.method_code.toUpperCase() === "CASH" && p.received_amount > (p.amount + p.tip_amount) && (
                            <span className="ml-2 text-green-400">
                              · Cambio: {formatPrice(p.received_amount - p.amount - p.tip_amount)}
                            </span>
                          )}
                        </div>
                      </div>
                      <button
                        onClick={() => handleRemovePayment(p.id)}
                        disabled={isProcessing}
                        className="p-2 text-red-400 hover:text-red-300 hover:bg-red-500/10 rounded disabled:opacity-50"
                      >
                        <Trash2 size={16} />
                      </button>
                    </div>
                  );
                })}

                {/* Resumen final */}
                <div className="bg-slate-900 rounded-lg p-3 border border-slate-800 mt-3 space-y-1">
                  <div className="flex justify-between text-xs">
                    <span className="text-slate-400">Subtotal pagos:</span>
                    <span className="text-white">{formatPrice(paymentsSum)}</span>
                  </div>
                  <div className="flex justify-between text-xs">
                    <span className="text-slate-400">Propinas:</span>
                    <span className="text-orange-400">{formatPrice(tipsSum)}</span>
                  </div>
                  <div className="flex justify-between text-sm font-bold pt-2 border-t border-slate-800">
                    <span className="text-slate-200">Total cobrado:</span>
                    <span className="text-green-400">{formatPrice(paymentsSum + tipsSum)}</span>
                  </div>
                </div>
              </>
            )}

            {/* Errores */}
            {errors.length > 0 && (
              <div className="bg-red-900/20 border border-red-700/50 rounded-lg p-3 mt-2">
                <div className="flex items-center gap-2 text-red-300 font-semibold text-xs mb-1">
                  <AlertCircle size={14} />
                  Errores
                </div>
                <ul className="text-xs text-red-200 space-y-0.5">
                  {errors.map((err, i) => <li key={i}>• {err}</li>)}
                </ul>
              </div>
            )}
          </div>
        </div>

        {/* Columna derecha: Teclado numérico fijo */}
        <div className="lg:w-1/2 flex flex-col overflow-hidden bg-slate-900">
          {/* Método seleccionado + Monto */}
          <div className="p-3 border-b border-slate-800 flex-shrink-0">
            <div className="flex items-center justify-between mb-2">
              <span className="text-xs text-slate-400 uppercase">Monto a pagar</span>
              {remaining > 0 && (
                <button
                  onClick={handleFillRemaining}
                  className="text-xs px-2 py-1 bg-orange-500/20 hover:bg-orange-500/30 text-orange-300 rounded border border-orange-500/30"
                >
                  Usar pendiente
                </button>
              )}
            </div>
            <div className="bg-slate-950 border-2 border-slate-700 rounded-lg p-3 text-right">
              <div className="text-4xl font-bold text-white tabular-nums">
                {amountInput || "0"}
              </div>
            </div>
          </div>

          {/* Métodos de pago (fila compacta) */}
          <div className="p-3 border-b border-slate-800 flex-shrink-0">
            <div className="text-xs text-slate-400 uppercase mb-2">Método de pago</div>
            <div className="grid grid-cols-4 gap-2">
              {methods.map((method) => {
                const config = PAYMENT_CONFIG[method.code.toUpperCase()] || {
                  label: method.code,
                  icon: CreditCard,
                  color: "bg-slate-600 hover:bg-slate-700",
                };
                const Icon = config.icon;
                const isSelected = selectedMethod?.uuid === method.uuid;

                return (
                  <button
                    key={method.uuid}
                    onClick={() => setSelectedMethod(method)}
                    disabled={!method.is_active}
                    className={`relative p-2 rounded-lg border-2 transition-all disabled:opacity-40 flex flex-col items-center gap-1 ${
                      isSelected
                        ? "border-orange-500 bg-orange-500/10"
                        : "border-slate-700 hover:border-slate-600"
                    }`}
                  >
                    <Icon size={18} className={isSelected ? "text-orange-400" : "text-slate-400"} />
                    <span className="text-[10px] font-semibold text-center leading-tight">{config.label}</span>
                  </button>
                );
              })}
            </div>
          </div>

          {/* Teclado numérico (ocupa el resto del espacio) */}
          <div className="flex-1 p-3 grid grid-cols-3 gap-2 overflow-hidden">
            {["1", "2", "3", "4", "5", "6", "7", "8", "9", ".", "0", "⌫"].map((key) => (
              <button
                key={key}
                onClick={() => handleKeyPress(key)}
                className={`rounded-lg font-bold text-2xl transition-colors active:scale-95 ${
                  key === "⌫"
                    ? "bg-red-500/20 hover:bg-red-500/30 text-red-400 border border-red-500/30"
                    : "bg-slate-800 hover:bg-slate-700 text-white border border-slate-700"
                }`}
              >
                {key}
              </button>
            ))}
          </div>

          {/* Propina + Recibido (compacto) */}
          {selectedMethod && (
            <div className="p-3 border-t border-slate-800 flex-shrink-0 space-y-2">
              <div className="grid grid-cols-2 gap-2">
                <div>
                  <label className="text-[10px] text-slate-400 uppercase block mb-1">Propina</label>
                  <input
                    type="number"
                    value={tipInput}
                    onChange={(e) => setTipInput(e.target.value)}
                    placeholder="0"
                    className="w-full bg-slate-950 border border-slate-700 rounded px-2 py-1.5 text-sm text-white focus:outline-none focus:border-orange-500"
                  />
                </div>
                {selectedMethod.type === "cash" && (
                  <div>
                    <label className="text-[10px] text-slate-400 uppercase block mb-1">Recibido</label>
                    <input
                      type="number"
                      value={receivedInput}
                      onChange={(e) => setReceivedInput(e.target.value)}
                      placeholder="0"
                      className="w-full bg-slate-950 border border-green-700/50 rounded px-2 py-1.5 text-sm text-white focus:outline-none focus:border-green-500"
                    />
                  </div>
                )}
              </div>

              {selectedMethod.type === "cash" && change > 0 && (
                <div className="flex justify-between items-center bg-green-900/20 border border-green-700/50 rounded px-2 py-1.5">
                  <span className="text-xs text-green-300">Cambio:</span>
                  <span className="text-sm font-bold text-green-400">{formatPrice(change)}</span>
                </div>
              )}

              <button
                onClick={handleAddPayment}
                disabled={currentAmount <= 0 || currentAmount > remaining + 0.01}
                className="w-full py-2.5 bg-orange-500 hover:bg-orange-600 disabled:bg-slate-700 disabled:text-slate-500 rounded-lg font-bold text-white text-sm flex items-center justify-center gap-2"
              >
                + Agregar Pago
              </button>
            </div>
          )}
        </div>
      </div>

      {/* Footer: botones de acción */}
      <div className="bg-slate-900 border-t border-slate-700 p-3 flex gap-2 flex-shrink-0">
        <button
          onClick={handleClose}
          disabled={isProcessing}
          className="flex-1 px-4 py-3 bg-slate-700 hover:bg-slate-600 rounded-lg font-bold disabled:opacity-50"
        >
          Cancelar
        </button>
        <button
          onClick={handleCharge}
          disabled={!canCharge}
          className="flex-[2] px-4 py-3 bg-green-600 hover:bg-green-700 disabled:bg-slate-700 disabled:text-slate-500 rounded-lg font-bold text-white flex items-center justify-center gap-2"
        >
          {isProcessing ? (
            <>
              <Loader2 size={18} className="animate-spin" />
              Procesando...
            </>
          ) : (
            <>
              <CheckCircle2 size={18} />
              Cobrar {formatPrice(billTotal)}
            </>
          )}
        </button>
      </div>
    </div>
  );
}
