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

type ActiveField = "amount" | "tip" | "received";

const PAYMENT_CONFIG: Record<string, { label: string; icon: any; color: string }> = {
  CASH: { label: "Efectivo", icon: Banknote, color: "bg-green-600" },
  CARD: { label: "Tarjeta", icon: CreditCard, color: "bg-blue-600" },
  TRANSFER: { label: "Transfer", icon: Building2, color: "bg-purple-600" },
  GIFT_CARD: { label: "Gift Card", icon: Gift, color: "bg-amber-600" },
  DEBIT_CARD: { label: "Débito", icon: CreditCard, color: "bg-blue-600" },
  CREDIT_CARD: { label: "Crédito", icon: CreditCard, color: "bg-blue-600" },
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
  const [activeField, setActiveField] = useState<ActiveField>("amount");
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

  // Teclado escribe en el campo activo
  const handleKeyPress = useCallback((key: string) => {
    const setters: Record<ActiveField, React.Dispatch<React.SetStateAction<string>>> = {
      amount: setAmountInput,
      tip: setTipInput,
      received: setReceivedInput,
    };
    const setter = setters[activeField];
    let current = activeField === "amount" ? amountInput : activeField === "tip" ? tipInput : receivedInput;

    if (key === "C") {
      setter("");
      return;
    } else if (key === "⌫") {
      setter(current.slice(0, -1));
      return;
    } else if (key === ".") {
      if (!current.includes(".")) setter(current + ".");
      return;
    } else if (key === "000") {
      // Si el campo está en "0" o vacío, iniciar desde cero limpio
      if (current === "0" || current === "") {
        setter("000");
      } else {
        setter(current + "000");
      }
      return;
    }

    // Dígito normal: si el campo tiene solo "0", reemplazarlo
    if (current === "0") {
      setter(key);
    } else if (current === "" && key === "0") {
      setter("0");
    } else {
      setter(current + key);
    }
  }, [activeField, amountInput, tipInput, receivedInput]);

  const handleFillRemaining = () => {
    setAmountInput(String(Math.floor(remaining)));
    setActiveField("amount");
  };

  const handleSelectMethod = (method: PaymentMethod) => {
    setSelectedMethod(method);
    // Auto-cambiar al campo amount al seleccionar método
    setActiveField("amount");
    // Si ya había monto en el campo, no resetear
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
    // Resetear campos pero mantener método seleccionado
    setAmountInput("");
    setTipInput("0");
    setReceivedInput("");
    setActiveField("amount");
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

  const fieldClass = (field: ActiveField, borderColor: string = "orange") => {
    const isActive = activeField === field;
    const colorMap: Record<string, string> = {
      orange: isActive ? "border-orange-500 bg-orange-500/10" : "border-slate-700 hover:border-slate-600",
      green: isActive ? "border-green-500 bg-green-500/10" : "border-slate-700 hover:border-slate-600",
    };
    return `bg-slate-950 border-2 rounded-lg px-3 py-2 transition-all cursor-pointer ${colorMap[borderColor]}`;
  };

  return (
    <div className="fixed inset-0 bg-black z-50 flex flex-col">
      {/* Header */}
      <div className="bg-slate-900 border-b border-slate-700 px-4 py-3 flex items-center justify-between flex-shrink-0">
        <div>
          <h2 className="text-lg font-bold text-white flex items-center gap-2">
            💳 Cobrar Cuenta
          </h2>
          <p className="text-xs text-slate-400">
            {effectiveBills.length === 1
              ? `Cuenta #${effectiveBills[0].bill_number}`
              : `${effectiveBills.length} sub-cuentas`}
            {" · "}Total: <span className="text-orange-400 font-semibold">{formatPrice(billTotal)}</span>
          </p>
        </div>
        <button
          onClick={handleClose}
          disabled={isProcessing}
          className="p-2 hover:bg-slate-800 rounded-lg disabled:opacity-50"
        >
          <X size={22} />
        </button>
      </div>

      {/* Contenido principal */}
      <div className="flex-1 flex flex-col lg:flex-row overflow-hidden bg-slate-950">
        {/* Columna izquierda: Pagos agregados */}
        <div className="lg:w-1/2 flex flex-col border-r border-slate-800 overflow-hidden">
          {/* Resumen */}
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
                  Usa el teclado para agregar pagos
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

        {/* Columna derecha: Teclado + inputs */}
        <div className="lg:w-1/2 flex flex-col overflow-hidden bg-slate-900">
          {/* Pendiente destacado */}
          <div className={`p-3 border-b border-slate-800 flex-shrink-0 ${remaining > 0 ? "bg-orange-900/10" : "bg-green-900/10"}`}>
            <div className="text-[10px] text-slate-400 uppercase">Pendiente por pagar</div>
            <div className={`text-2xl font-bold ${remaining > 0 ? "text-orange-400" : "text-green-400"}`}>
              {formatPrice(remaining)}
            </div>
          </div>

          {/* Métodos de pago */}
          <div className="p-3 border-b border-slate-800 flex-shrink-0">
            <div className="text-[10px] text-slate-400 uppercase mb-2">Método de pago</div>
            <div className="grid grid-cols-4 gap-2">
              {methods.map((method) => {
                const config = PAYMENT_CONFIG[method.code.toUpperCase()] || {
                  label: method.code,
                  icon: CreditCard,
                  color: "bg-slate-600",
                };
                const Icon = config.icon;
                const isSelected = selectedMethod?.uuid === method.uuid;

                return (
                  <button
                    key={method.uuid}
                    onClick={() => handleSelectMethod(method)}
                    disabled={!method.is_active}
                    className={`relative p-2 rounded-lg border-2 transition-all disabled:opacity-40 flex flex-col items-center gap-1 ${
                      isSelected
                        ? "border-orange-500 bg-orange-500/10"
                        : "border-slate-700 hover:border-slate-600"
                    }`}
                  >
                    <div className={`w-8 h-8 rounded-md ${config.color} flex items-center justify-center`}>
                      <Icon size={16} className="text-white" />
                    </div>
                    <span className="text-[10px] font-semibold text-center leading-tight">{config.label}</span>
                  </button>
                );
              })}
            </div>
          </div>

          {/* Inputs clickeables */}
          {selectedMethod && (
            <div className="p-3 border-b border-slate-800 flex-shrink-0 space-y-2">
              {/* Monto (grande) */}
              <div
                onClick={() => setActiveField("amount")}
                className={fieldClass("amount", "orange")}
              >
                <div className="flex justify-between items-center">
                  <span className="text-[10px] text-slate-400 uppercase">Monto</span>
                  {remaining > 0 && activeField === "amount" && (
                    <button
                      onClick={(e) => { e.stopPropagation(); handleFillRemaining(); }}
                      className="text-[10px] px-2 py-0.5 bg-orange-500/20 text-orange-300 rounded"
                    >
                      Usar pendiente
                    </button>
                  )}
                </div>
                <div className="text-2xl font-bold text-white text-right tabular-nums">
                  ${amountInput || "0"}
                </div>
              </div>

              {/* Propina + Recibido en fila */}
              <div className="grid grid-cols-2 gap-2">
                <div
                  onClick={() => setActiveField("tip")}
                  className={fieldClass("tip", "orange")}
                >
                  <div className="text-[10px] text-slate-400 uppercase">Propina</div>
                  <div className="text-lg font-bold text-orange-400 text-right tabular-nums">
                    ${tipInput || "0"}
                  </div>
                </div>
                {selectedMethod.type === "cash" ? (
                  <div
                    onClick={() => setActiveField("received")}
                    className={fieldClass("received", "green")}
                  >
                    <div className="text-[10px] text-slate-400 uppercase">Recibido</div>
                    <div className="text-lg font-bold text-green-400 text-right tabular-nums">
                      ${receivedInput || "0"}
                    </div>
                  </div>
                ) : (
                  <div className="bg-slate-800/50 border-2 border-slate-800 rounded-lg px-3 py-2 flex items-center justify-center">
                    <span className="text-xs text-slate-500">Sin cambio</span>
                  </div>
                )}
              </div>

              {/* Cambio */}
              {selectedMethod.type === "cash" && change > 0 && (
                <div className="flex justify-between items-center bg-green-900/20 border border-green-700/50 rounded px-3 py-1.5">
                  <span className="text-xs text-green-300">Cambio:</span>
                  <span className="text-sm font-bold text-green-400">{formatPrice(change)}</span>
                </div>
              )}
            </div>
          )}

          {/* Teclado numérico */}
          <div className="flex-1 p-3 grid grid-cols-3 gap-2 overflow-hidden min-h-0">
            {["1", "2", "3", "4", "5", "6", "7", "8", "9", "000", "0", "⌫"].map((key) => (
              <button
                key={key}
                onClick={() => handleKeyPress(key)}
                className={`rounded-lg font-bold text-xl transition-colors active:scale-95 ${
                  key === "⌫"
                    ? "bg-red-500/20 hover:bg-red-500/30 text-red-400 border border-red-500/30"
                    : key === "000"
                    ? "bg-blue-500/20 hover:bg-blue-500/30 text-blue-400 border border-blue-500/30 text-sm"
                    : "bg-slate-800 hover:bg-slate-700 text-white border border-slate-700"
                }`}
              >
                {key}
              </button>
            ))}
          </div>

          {/* Botón Agregar */}
          {selectedMethod && (
            <div className="p-3 border-t border-slate-800 flex-shrink-0">
              <button
                onClick={handleAddPayment}
                disabled={currentAmount <= 0 || currentAmount > remaining + 0.01}
                className="w-full py-3 bg-orange-500 hover:bg-orange-600 disabled:bg-slate-700 disabled:text-slate-500 rounded-lg font-bold text-white flex items-center justify-center gap-2"
              >
                + Agregar Pago
              </button>
            </div>
          )}
        </div>
      </div>

      {/* Footer */}
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
