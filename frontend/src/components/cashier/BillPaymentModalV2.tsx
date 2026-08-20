import { useState, useMemo, useCallback } from "react";
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
  Percent,
  Plus,
  Minus,
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

const PAYMENT_LABELS: Record<string, { label: string; icon: any; color: string }> = {
  CASH: { label: "Efectivo", icon: Banknote, color: "from-green-500 to-green-600" },
  CARD: { label: "Tarjeta", icon: CreditCard, color: "from-blue-500 to-blue-600" },
  TRANSFER: { label: "Transfer", icon: Building2, color: "from-purple-500 to-purple-600" },
  GIFT_CARD: { label: "Gift Card", icon: Gift, color: "from-amber-500 to-amber-600" },
  DEBIT_CARD: { label: "Débito", icon: CreditCard, color: "from-blue-500 to-blue-600" },
  CREDIT_CARD: { label: "Crédito", icon: CreditCard, color: "from-blue-500 to-blue-600" },
};

export function BillPaymentModalV2({
  bill,
  bills,
  isOpen,
  onClose,
  onSuccess,
}: BillPaymentModalV2Props) {
  const effectiveBills = bills && bills.length > 0
    ? bills
    : (bill ? [bill] : []);

  // Estado del modal
  const [selectedMethod, setSelectedMethod] = useState<PaymentMethod | null>(null);
  const [amountInput, setAmountInput] = useState<string>("");
  const [tipInput, setTipInput] = useState<string>("0");
  const [receivedInput, setReceivedInput] = useState<string>("");
  const [payments, setPayments] = useState<PendingPayment[]>([]);
  const [isProcessing, setIsProcessing] = useState(false);
  const [errors, setErrors] = useState<string[]>([]);

  const { data: methods = [], isLoading: loadingMethods } = usePaymentMethods();
  const payBill = usePayBill();
  const invalidate = useInvalidateCashier();

  // Cálculos
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

  // Teclado numérico
  const handleKeyPress = useCallback((key: string) => {
    if (key === "C") {
      setAmountInput("");
    } else if (key === "←") {
      setAmountInput(prev => prev.slice(0, -1));
    } else if (key === ".") {
      if (!amountInput.includes(".")) {
        setAmountInput(prev => prev + ".");
      }
    } else {
      setAmountInput(prev => prev + key);
    }
  }, [amountInput]);

  // Agregar pago
  const handleAddPayment = () => {
    if (!selectedMethod || currentAmount <= 0 || currentAmount > remaining + 0.01) {
      return;
    }

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
    setSelectedMethod(null);
    setAmountInput("");
    setTipInput("0");
    setReceivedInput("");
  };

  // Eliminar pago
  const handleRemovePayment = (id: string) => {
    setPayments(payments.filter(p => p.id !== id));
  };

  // Cobrar (enviar al backend)
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

  if (!isOpen) return null;

  return (
    <>
      <div className="fixed inset-0 bg-black/90 z-50" onClick={onClose} />
      <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div
          className="bg-slate-900 rounded-2xl shadow-2xl max-w-5xl w-full max-h-[95vh] flex flex-col"
          onClick={(e) => e.stopPropagation()}
        >
          {/* Header */}
          <div className="flex items-center justify-between p-6 border-b border-slate-700">
            <div>
              <h2 className="text-2xl font-bold text-white flex items-center gap-3">
                💳 Cobrar Cuenta
              </h2>
              <p className="text-sm text-slate-400 mt-1">
                {effectiveBills.length === 1
                  ? `Cuenta #${effectiveBills[0].bill_number}`
                  : `${effectiveBills.length} sub-cuentas`}
                {" · "}Total: <span className="text-orange-400 font-semibold">{formatPrice(billTotal)}</span>
              </p>
            </div>
            <button
              onClick={onClose}
              disabled={isProcessing}
              className="p-3 hover:bg-slate-800 rounded-xl disabled:opacity-50"
            >
              <X size={28} />
            </button>
          </div>

          {/* Contenido */}
          <div className="flex-1 overflow-y-auto p-6">
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
              {/* Columna izquierda: Input + Teclado */}
              <div className="space-y-4">
                {/* Pendiente */}
                <div className={`rounded-xl p-4 border-2 ${
                  remaining > 0 
                    ? "bg-orange-900/20 border-orange-500/50" 
                    : "bg-green-900/20 border-green-500/50"
                }`}>
                  <div className="text-sm text-slate-400 mb-1">Pendiente por pagar</div>
                  <div className={`text-4xl font-bold ${remaining > 0 ? "text-orange-400" : "text-green-400"}`}>
                    {formatPrice(remaining)}
                  </div>
                </div>

                {/* Métodos de pago */}
                <div>
                  <label className="text-sm text-slate-400 mb-2 block">Método de pago</label>
                  <div className="grid grid-cols-2 gap-3">
                    {methods.map((method) => {
                      const config = PAYMENT_LABELS[method.code.toUpperCase()] || {
                        label: method.code,
                        icon: CreditCard,
                        color: "from-slate-500 to-slate-600"
                      };
                      const Icon = config.icon;
                      const isSelected = selectedMethod?.uuid === method.uuid;

                      return (
                        <button
                          key={method.uuid}
                          onClick={() => setSelectedMethod(method)}
                          disabled={!method.is_active}
                          className={`relative p-4 rounded-xl border-2 transition-all disabled:opacity-40 ${
                            isSelected
                              ? "border-orange-500 bg-orange-500/10 scale-105"
                              : "border-slate-700 hover:border-slate-600"
                          }`}
                        >
                          <div className={`w-12 h-12 rounded-lg bg-gradient-to-br ${config.color} flex items-center justify-center mb-2 mx-auto`}>
                            <Icon size={24} className="text-white" />
                          </div>
                          <div className="text-sm font-semibold text-center">{config.label}</div>
                        </button>
                      );
                    })}
                  </div>
                </div>

                {/* Monto input */}
                {selectedMethod && (
                  <div className="space-y-3">
                    <div>
                      <label className="text-sm text-slate-400 mb-2 block">Monto a pagar</label>
                      <div className="bg-slate-800 border-2 border-slate-600 rounded-xl p-4 text-right">
                        <div className="text-4xl font-bold text-white">
                          {amountInput || "0"}
                        </div>
                      </div>
                    </div>

                    {/* Teclado numérico */}
                    <div className="grid grid-cols-3 gap-2">
                      {["1", "2", "3", "4", "5", "6", "7", "8", "9", "C", "0", "←"].map((key) => (
                        <button
                          key={key}
                          onClick={() => handleKeyPress(key)}
                          className={`py-4 rounded-xl font-bold text-xl transition-all ${
                            key === "C" || key === "←"
                              ? "bg-red-500/20 hover:bg-red-500/30 text-red-400"
                              : "bg-slate-700 hover:bg-slate-600 text-white"
                          }`}
                        >
                          {key}
                        </button>
                      ))}
                    </div>

                    {/* Propina */}
                    <div>
                      <label className="text-sm text-slate-400 mb-2 block flex items-center gap-2">
                        <Percent size={14} />
                        Propina (opcional)
                      </label>
                      <input
                        type="number"
                        value={tipInput}
                        onChange={(e) => setTipInput(e.target.value)}
                        placeholder="0"
                        className="w-full bg-slate-800 border-2 border-slate-600 rounded-xl px-4 py-3 text-xl text-white focus:outline-none focus:border-orange-500"
                      />
                    </div>

                    {/* Efectivo: recibido y cambio */}
                    {selectedMethod.type === "cash" && (
                      <div className="grid grid-cols-2 gap-3">
                        <div>
                          <label className="text-sm text-slate-400 mb-2 block">Recibido</label>
                          <input
                            type="number"
                            value={receivedInput}
                            onChange={(e) => setReceivedInput(e.target.value)}
                            placeholder="0"
                            className="w-full bg-slate-800 border-2 border-green-600/50 rounded-xl px-4 py-3 text-xl text-white focus:outline-none focus:border-green-500"
                          />
                        </div>
                        <div>
                          <label className="text-sm text-slate-400 mb-2 block">Cambio</label>
                          <div className="bg-green-900/20 border-2 border-green-600/50 rounded-xl px-4 py-3 text-xl text-green-400 font-bold">
                            {formatPrice(change)}
                          </div>
                        </div>
                      </div>
                    )}

                    {/* Botón agregar */}
                    <button
                      onClick={handleAddPayment}
                      disabled={currentAmount <= 0 || currentAmount > remaining + 0.01}
                      className="w-full py-4 bg-orange-500 hover:bg-orange-600 disabled:bg-slate-700 disabled:text-slate-500 rounded-xl font-bold text-white text-lg flex items-center justify-center gap-2 transition-colors"
                    >
                      <Plus size={20} />
                      Agregar Pago
                    </button>
                  </div>
                )}
              </div>

              {/* Columna derecha: Lista de pagos */}
              <div className="space-y-4">
                <div className="bg-slate-800/50 rounded-xl p-4 border border-slate-700">
                  <h3 className="font-bold text-lg text-white mb-3 flex items-center gap-2">
                    📋 Pagos Agregados ({payments.length})
                  </h3>

                  {payments.length === 0 ? (
                    <div className="text-center py-12 text-slate-500">
                      <div className="text-6xl mb-3">💰</div>
                      <p>No hay pagos agregados</p>
                      <p className="text-sm mt-1">Selecciona un método y agrega pagos</p>
                    </div>
                  ) : (
                    <div className="space-y-2 max-h-[400px] overflow-y-auto">
                      {payments.map((p) => {
                        const config = PAYMENT_LABELS[p.method_code.toUpperCase()] || {
                          label: p.method_code,
                          icon: CreditCard,
                          color: "from-slate-500 to-slate-600"
                        };
                        const Icon = config.icon;

                        return (
                          <div
                            key={p.id}
                            className="bg-slate-900/50 rounded-lg p-4 flex items-center gap-3"
                          >
                            <div className={`w-12 h-12 rounded-lg bg-gradient-to-br ${config.color} flex items-center justify-center flex-shrink-0`}>
                              <Icon size={20} className="text-white" />
                            </div>
                            <div className="flex-1 min-w-0">
                              <div className="font-bold text-white">{config.label}</div>
                              <div className="text-sm text-slate-400">
                                {formatPrice(p.amount)}
                                {p.tip_amount > 0 && (
                                  <span className="ml-2 text-orange-400">
                                    + {formatPrice(p.tip_amount)} propina
                                  </span>
                                )}
                              </div>
                            </div>
                            <button
                              onClick={() => handleRemovePayment(p.id)}
                              disabled={isProcessing}
                              className="p-2 text-red-400 hover:text-red-300 hover:bg-red-500/10 rounded-lg disabled:opacity-50"
                            >
                              <Trash2 size={20} />
                            </button>
                          </div>
                        );
                      })}
                    </div>
                  )}
                </div>

                {/* Resumen */}
                {payments.length > 0 && (
                  <div className="bg-slate-800/50 rounded-xl p-4 border border-slate-700 space-y-2">
                    <div className="flex justify-between text-sm">
                      <span className="text-slate-400">Subtotal pagos:</span>
                      <span className="text-white font-semibold">{formatPrice(paymentsSum)}</span>
                    </div>
                    <div className="flex justify-between text-sm">
                      <span className="text-slate-400">Propinas:</span>
                      <span className="text-orange-400 font-semibold">{formatPrice(tipsSum)}</span>
                    </div>
                    <div className="flex justify-between text-lg pt-2 border-t border-slate-700">
                      <span className="text-white font-bold">Total a cobrar:</span>
                      <span className="text-green-400 font-bold">{formatPrice(paymentsSum + tipsSum)}</span>
                    </div>
                  </div>
                )}

                {/* Errores */}
                {errors.length > 0 && (
                  <div className="bg-red-900/20 border border-red-700/50 rounded-xl p-4">
                    <div className="flex items-center gap-2 text-red-300 font-semibold mb-2">
                      <AlertCircle size={16} />
                      Errores al procesar
                    </div>
                    <ul className="space-y-1 text-sm text-red-200">
                      {errors.map((err, i) => (
                        <li key={i}>• {err}</li>
                      ))}
                    </ul>
                  </div>
                )}
              </div>
            </div>
          </div>

          {/* Footer */}
          <div className="border-t border-slate-700 p-6 flex gap-3">
            <button
              onClick={onClose}
              disabled={isProcessing}
              className="flex-1 px-6 py-4 bg-slate-700 hover:bg-slate-600 rounded-xl font-bold text-lg disabled:opacity-50"
            >
              Cancelar
            </button>
            <button
              onClick={handleCharge}
              disabled={!canCharge}
              className="flex-1 px-6 py-4 bg-green-600 hover:bg-green-700 disabled:bg-slate-700 disabled:text-slate-500 rounded-xl font-bold text-lg text-white flex items-center justify-center gap-2"
            >
              {isProcessing ? (
                <>
                  <Loader2 size={20} className="animate-spin" />
                  Procesando...
                </>
              ) : (
                <>
                  <CheckCircle2 size={20} />
                  Cobrar {formatPrice(billTotal)}
                </>
              )}
            </button>
          </div>
        </div>
      </div>
    </>
  );
}
