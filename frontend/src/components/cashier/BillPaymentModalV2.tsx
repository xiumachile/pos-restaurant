import { useState, useCallback } from "react";
import { useTranslation } from "react-i18next";
import { useSettingsStore } from "../../store/useSettingsStore";
import { usePopupStore } from "../../store/usePopupStore";
import { parseCLPAmount } from '@/utils/money';
import type { Bill } from "@/types/bills";
import type { PaymentMethod } from "@/types/payments";
import { usePaymentMethods, usePayBill, useInvalidateCashier } from "@/hooks/usePayments";
import { useOfflinePayment } from "@/hooks/useOfflinePayment";
import { useConnectionMode } from "@/hooks/useConnectionMode";
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
import { SUGGESTED_TIP_PERCENTAGES } from "@/constants/tips";

export const getPaymentConfig = (t: (key: string) => string): Record<string, { label: string; icon: any; color: string }> => ({
  CASH: { label: t("bill_payment.payment_method_cash"), icon: Banknote, color: "bg-green-600" },
  CARD: { label: t("bill_payment.payment_method_card"), icon: CreditCard, color: "bg-blue-600" },
  TRANSFER: { label: t("bill_payment.payment_method_transfer"), icon: Building2, color: "bg-purple-600" },
  GIFT_CARD: { label: t("bill_payment.payment_method_gift_card"), icon: Gift, color: "bg-amber-600" },
  DEBIT_CARD: { label: t("bill_payment.payment_method_debit_card"), icon: CreditCard, color: "bg-blue-600" },
});

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
  change_amount: number;
  idempotency_key: string;
  reference_code?: string;
}

type ActiveField = "amount" | "tip" | "received" | "reference";

export function BillPaymentModalV2({
  bill,
  bills,
  isOpen,
  onClose,
  onSuccess,
}: BillPaymentModalV2Props) {
  const { t } = useTranslation();
  const effectiveBills = bills && bills.length > 0 ? bills : bill ? [bill] : [];

  const [selectedMethod, setSelectedMethod] = useState<PaymentMethod | null>(null);
  const [amountInput, setAmountInput] = useState<string>("");
  const [tipInput, setTipInput] = useState<string>("0");
  const [tipPct, setTipPct] = useState<number | null>(null);
  const [receivedInput, setReceivedInput] = useState<string>("");
  const [referenceCode, setReferenceCode] = useState<string>("");
  const [activeField, setActiveField] = useState<ActiveField>("amount");
  const [payments, setPayments] = useState<PendingPayment[]>([]);
  const [isProcessing, setIsProcessing] = useState(false);
  const [networkError, setNetworkError] = useState<string | null>(null);
  const [errors, setErrors] = useState<string[]>([]);
  
  const { showChangePopup: changePopupEnabled, changePopupDuration, requirePaymentReference } = useSettingsStore();
  const showPopup = usePopupStore((state) => state.showPopup);

  const [processedPaymentIds, setProcessedPaymentIds] = useState<Set<string>>(new Set());

  const { data: methods = [] } = usePaymentMethods();
  const isOffline = useConnectionMode();
  
  const onlinePayBill = usePayBill();
  const offlinePayBill = useOfflinePayment();

  const hasCloudId = effectiveBills.some((b: any) => b.cloud_id);
  const forceOffline = isOffline || !hasCloudId;
  
  const invalidate = useInvalidateCashier();

  const billTotal = effectiveBills.reduce((sum, b) => sum + b.total, 0);
  const billPending = effectiveBills.reduce((sum, b) => sum + b.remaining_amount, 0);
  const paymentsSum = payments.reduce((sum, p) => sum + p.amount, 0);
  const tipsSum = payments.reduce((sum, p) => sum + p.tip_amount, 0);
  const remaining = Math.max(0, billPending - paymentsSum);
  const canCharge = remaining === 0 && payments.length > 0 && !isProcessing;

  const currentAmount = parseCLPAmount(amountInput);
  const tipBase = billPending;
  const suggestedTipPercentages: number[] = SUGGESTED_TIP_PERCENTAGES;
  
  const currentTip = tipPct !== null
    ? Math.round(tipBase * tipPct / 100)
    : parseCLPAmount(tipInput);
  const currentReceived = parseCLPAmount(receivedInput);
  
  const isCash = selectedMethod?.type === "cash";
  const amountToCharge = amountInput === "" ? remaining : currentAmount;
  const receivedEffective = receivedInput === "" ? amountToCharge + currentTip : currentReceived;
  const change = isCash ? Math.max(0, receivedEffective - (amountToCharge + currentTip)) : 0;
  
  const cashShort = isCash && receivedEffective < amountToCharge + currentTip;
  const maxAmountAllowed = isCash ? Number.MAX_SAFE_INTEGER : remaining;
  const canAdd = !!selectedMethod && amountToCharge > 0 && amountToCharge <= maxAmountAllowed && !cashShort;
  
  const getDisabledReason = () => {
    if (!selectedMethod) return t("bill_payment.error_select_method");
    if (amountToCharge <= 0) return t("bill_payment.error_amount_must_be_positive");
    if (!isCash && amountToCharge > remaining) return t("bill_payment.error_amount_exceeds", { amount: formatPrice(currentAmount), pending: formatPrice(remaining) });
    if (cashShort) return t("bill_payment.error_cash_short", { amount: formatPrice(amountToCharge + currentTip - receivedEffective) });
    return null;
  };
  const disabledReason = getDisabledReason();

  const handleKeyPress = useCallback((key: string) => {
    if (activeField === "reference") {
      if (key === "⌫") {
        setReferenceCode((prev) => prev.slice(0, -1));
        return;
      }
      if (/^[a-zA-Z0-9\s\-_]*$/.test(key)) {
        setReferenceCode((prev) => prev + key);
      }
      return;
    }

    const setters: Record<ActiveField, React.Dispatch<React.SetStateAction<string>>> = {
      amount: setAmountInput,
      tip: setTipInput,
      received: setReceivedInput,
      reference: setReferenceCode,
    };
    const setter = setters[activeField];
    let current = activeField === "amount" ? amountInput : activeField === "tip" ? tipInput : receivedInput;

    if (key === "C") {
      setter("");
      return;
    } else if (key === "⌫") {
      setter(current.slice(0, -1));
      return;
    } else if (key === "000") {
      if (current === "0" || current === "") {
        setter("000");
      } else {
        setter(current + "000");
      }
      return;
    }

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
    setActiveField("amount");
  };

  const handleApplyTipPercentage = (percentage: number) => {
    setTipPct(percentage);
    const calculatedTip = Math.round(tipBase * (percentage / 100));
    setTipInput(calculatedTip.toString());
  };

  const handleAddPayment = () => {
    setErrors([]);

    if (!selectedMethod) {
      setErrors([t("bill_payment.error_select_method")]);
      return;
    }
    if (currentAmount <= 0) {
      setErrors([t("bill_payment.error_amount_positive")]);
      return;
    }
    if (!isCash && currentAmount > remaining) {
      setErrors([t("bill_payment.error_amount_exceeds", { amount: formatPrice(currentAmount), pending: formatPrice(remaining) })]);
      return;
    }

    // ✅ VALIDACIÓN: Exigir código de referencia si está configurado
    if (selectedMethod.requires_reference && requirePaymentReference && !referenceCode.trim()) {
      setErrors([t("bill_payment.error_missing_reference")]);
      return;
    }

    const actualAmount = isCash ? Math.min(currentAmount, remaining) : currentAmount;
    const actualReceived = isCash ? receivedEffective : 0;
    const changeAmount = isCash ? Math.max(0, actualReceived - actualAmount - currentTip) : 0;

    const paymentId = crypto.randomUUID();
    const newPayment: PendingPayment = {
      id: paymentId,
      payment_method_uuid: selectedMethod.uuid,
      method_code: selectedMethod.code,
      amount: actualAmount,
      tip_amount: currentTip,
      received_amount: actualReceived,
      change_amount: changeAmount,
      idempotency_key: paymentId,
      reference_code: referenceCode.trim() || undefined,
    };

    setPayments([...payments, newPayment]);
    setAmountInput("");
    setTipInput("0");
    setTipPct(null);
    setReceivedInput("");
    setReferenceCode("");
    setActiveField("amount");
  };

  const handleRemovePayment = (id: string) => {
    setPayments(payments.filter(p => p.id !== id));
  };

  const handleCharge = async () => {
    console.log('[DEBUG handleCharge] Iniciando, canCharge:', canCharge, 'payments:', payments.length);
    if (!canCharge || effectiveBills.length === 0) return;

    setIsProcessing(true);
    setErrors([]);
    
    const totalChange = payments.reduce((sum, p) => sum + (p.change_amount || 0), 0);
    console.log('[DEBUG handleCharge] totalChange:', totalChange, 'changePopupEnabled:', changePopupEnabled);

    const billsRemaining = effectiveBills.map(b => ({
      uuid: b.uuid,
      remaining: b.remaining_amount,
    }));

    const errorsList: string[] = [];

    for (const payment of payments) {
      if (processedPaymentIds.has(payment.id)) {
        continue;
      }

      // ✅ DEFENSA EN PROFUNDIDAD: Validar que el código de referencia exista si es requerido
      const method = methods.find(m => m.uuid === payment.payment_method_uuid);
      if (method?.requires_reference && requirePaymentReference && !payment.reference_code) {
        errorsList.push(`${payment.method_code}: ${t("bill_payment.error_missing_reference")}`);
        continue;
      }

      try {
        let amountLeft = payment.amount;
        let tipLeft = payment.tip_amount;
        let paymentSuccess = true;

        while (amountLeft > 0) {
          const nextBill = billsRemaining.find(b => b.remaining > 0);
          if (!nextBill) {
            errorsList.push(`${payment.method_code}: ${t("bill_payment.error_no_bills_with_balance")}`);
            paymentSuccess = false;
            break;
          }

          const amountForBill = Math.min(amountLeft, nextBill.remaining);
          const tipForBill = amountLeft === payment.amount ? tipLeft : 0;

          const activeMutation = forceOffline ? offlinePayBill : onlinePayBill;
          const billUuidForPayment = forceOffline ? nextBill.uuid : ((nextBill as any).cloud_id || nextBill.uuid);
          console.log("[BillPaymentModalV2] Modo:", forceOffline ? "OFFLINE" : "ONLINE", "| billUuid:", billUuidForPayment);
          
          await activeMutation.mutateAsync({
            billUuid: billUuidForPayment,
            payload: {
              amount: Math.round(amountForBill),
              payment_method_uuid: payment.payment_method_uuid,
              tip_amount: Math.round(tipForBill),
              reference_code: payment.reference_code || (method?.requires_reference && !requirePaymentReference ? "REF-AUTO" : undefined),
              idempotency_key: payment.idempotency_key,
            },
          });

          amountLeft -= amountForBill;
          tipLeft -= tipForBill;
          nextBill.remaining -= amountForBill;
        }

        if (paymentSuccess && amountLeft === 0) {
          setProcessedPaymentIds(prev => new Set(prev).add(payment.id));
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
    
    if (changePopupEnabled && totalChange > 0) {
      console.log('[DEBUG handleCharge] Mostrando popup con monto:', totalChange);
      showPopup('change', totalChange, changePopupDuration);
    }
    setPayments([]);
    setProcessedPaymentIds(new Set());
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
      <div className="bg-slate-900 border-b border-slate-700 px-4 py-3 flex items-center justify-between flex-shrink-0">
        <div>
          <h2 className="text-lg font-bold text-white flex items-center gap-2">
            {t("bill_payment.charge_bill")}
          </h2>
          <p className="text-xs text-slate-400">
            {effectiveBills.length === 1
              ? t("bill_payment.single_bill", { billNumber: effectiveBills[0].bill_number })
              : t("bill_payment.multiple_bills", { count: effectiveBills.length })}
            {" · "}{t("bill_payment.pending")} <span className="text-orange-400 font-semibold">{formatPrice(billPending)}</span>
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

      <div className="flex-1 flex flex-col lg:flex-row overflow-hidden bg-slate-950">
        <div className="lg:w-1/2 flex flex-col border-r border-slate-800 overflow-hidden">
          <div className="bg-slate-900 border-b border-slate-800 p-3 grid grid-cols-3 gap-2 flex-shrink-0">
            <div className="text-center">
              <div className="text-xs text-slate-400 uppercase">{t("bill_payment.total")}</div>
              <div className="text-sm font-bold text-white">{formatPrice(billPending)}</div>
            </div>
            <div className="text-center">
              <div className="text-xs text-slate-400 uppercase">{t("bill_payment.paid")}</div>
              <div className="text-sm font-bold text-blue-400">{formatPrice(paymentsSum)}</div>
            </div>
            <div className="text-center">
              <div className="text-xs text-slate-400 uppercase">{t("bill_payment.pending_amount")}</div>
              <div className={`text-sm font-bold ${remaining > 0 ? "text-orange-400" : "text-green-400"}`}>
                {formatPrice(remaining)}
              </div>
            </div>
          </div>

          <div className="flex-1 overflow-y-auto p-3 space-y-2">
            {payments.length === 0 ? (
              <div className="text-center py-12 text-slate-600">
                <div className="text-5xl mb-3">💰</div>
                <p className="text-sm">{t("bill_payment.no_payments_added")}</p>
                <p className="text-xs mt-1 text-slate-400">
                  {t("bill_payment.use_keyboard_hint")}
                </p>
              </div>
            ) : (
              <>
                {payments.map((p) => {
                  const config = getPaymentConfig(t)[p.method_code.toUpperCase()] || {
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
                          {p.change_amount > 0 && (
                            <span className="ml-2 text-green-400 font-semibold">
                              ({t("bill_payment.change")}: {formatPrice(p.change_amount)})
                            </span>
                          )}
                          {p.tip_amount > 0 && (
                            <span className="ml-2 text-orange-400">+ {formatPrice(p.tip_amount)} {t("bill_payment.tip")}</span>
                          )}
                          {p.method_code.toUpperCase() === "CASH" && p.received_amount > (p.amount + p.tip_amount) && (
                            <span className="ml-2 text-green-400">
                              · {t("bill_payment.cash_change")}: {formatPrice(p.received_amount - p.amount - p.tip_amount)}
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
                    <span className="text-slate-400">{t("bill_payment.payments_subtotal")}</span>
                    <span className="text-white">{formatPrice(paymentsSum)}</span>
                  </div>
                  <div className="flex justify-between text-xs">
                    <span className="text-slate-400">{t("bill_payment.tips")}</span>
                    <span className="text-orange-400">{formatPrice(tipsSum)}</span>
                  </div>
                  <div className="flex justify-between text-sm font-bold pt-2 border-t border-slate-800">
                    <span className="text-slate-200">{t("bill_payment.total_charged")}</span>
                    <span className="text-green-400">{formatPrice(paymentsSum + tipsSum)}</span>
                  </div>
                </div>
              </>
            )}

            {errors.length > 0 && (
              <div className="bg-red-900/20 border border-red-700/50 rounded-lg p-3 mt-2">
                <div className="flex items-center gap-2 text-red-300 font-semibold text-xs mb-1">
                  <AlertCircle size={14} />
                  {t("bill_payment.errors_title")}
                </div>
                <ul className="text-xs text-red-200 space-y-0.5">
                  {errors.map((err, i) => <li key={i}>• {err}</li>)}
                </ul>
              </div>
            )}
          </div>
        </div>

        <div className="lg:w-1/2 flex flex-col overflow-hidden bg-slate-900">
          <div className={`p-3 border-b border-slate-800 flex-shrink-0 ${remaining > 0 ? "bg-orange-900/10" : "bg-green-900/10"}`}>
            <div className="text-xs text-slate-400 uppercase">{t("bill_payment.pending_to_pay")}</div>
            <div className={`text-2xl font-bold ${remaining > 0 ? "text-orange-400" : "text-green-400"}`}>
              {formatPrice(remaining)}
            </div>
          </div>

          <div className="p-3 border-b border-slate-800 flex-shrink-0">
            <div className="text-xs text-slate-400 uppercase mb-2">{t("bill_payment.payment_method_label")}</div>
            <div className="grid grid-cols-4 gap-2">
              {methods.map((method) => {
                const config = getPaymentConfig(t)[method.code.toUpperCase()] || {
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
                    <span className="text-xs font-semibold text-center leading-tight">{config.label}</span>
                  </button>
                );
              })}
            </div>
          </div>

          {selectedMethod && (
            <div className="p-3 border-b border-slate-800 flex-shrink-0 space-y-2">
              <div
                onClick={() => setActiveField("amount")}
                className={fieldClass("amount", "orange")}
              >
                <div className="flex justify-between items-center">
                  <span className="text-xs text-slate-400 uppercase">{t("bill_payment.amount_to_charge")}</span>
                  {remaining > 0 && activeField === "amount" && (
                    <button
                      onClick={(e) => { e.stopPropagation(); handleFillRemaining(); }}
                      className="text-xs px-2 py-0.5 bg-orange-500/20 text-orange-300 rounded"
                    >
                      {t("bill_payment.use_remaining")}
                    </button>
                  )}
                </div>
                <div className="text-2xl font-bold text-white text-right tabular-nums">
                  ${amountInput || "0"}
                </div>
              </div>

              {(selectedMethod?.requires_reference && requirePaymentReference) && (
                <div className="space-y-2">
                  <label className="text-sm font-medium text-slate-300">
                    {t("bill_payment.reference_code_label")}
                  </label>
                  <input
                    type="text"
                    inputMode="text"
                    value={referenceCode}
                    onChange={(e) => setReferenceCode(e.target.value)}
                    onFocus={() => setActiveField("reference")}
                    placeholder={t("bill_payment.reference_code_placeholder")}
                    className="w-full p-3 bg-slate-800 border border-slate-600 rounded-lg text-white text-sm focus:ring-2 focus:ring-orange-500 outline-none"
                    autoComplete="off"
                  />
                </div>
              )}

              <div className="space-y-2">
                <div className="flex gap-2">
                  {suggestedTipPercentages.map((pct: number) => {
                    const calcTip = Math.round(tipBase * (pct / 100));
                    const isActive = currentTip === calcTip && currentTip > 0;
                    return (
                      <button
                        key={pct}
                        type="button"
                        onClick={() => handleApplyTipPercentage(pct)}
                        className={`flex-1 py-2 rounded-lg text-xs font-bold transition-all ${
                          isActive
                            ? "bg-orange-500 text-white shadow-lg shadow-orange-500/20"
                            : "bg-slate-800 text-slate-300 hover:bg-slate-700 border border-slate-700"
                        }`}
                      >
                        {pct}%
                        <span className="block text-[10px] font-normal opacity-80">
                          ${calcTip}
                        </span>
                      </button>
                    );
                  })}
                  <button
                    type="button"
                    onClick={() => setTipInput("0")}
                    className={`flex-1 py-2 rounded-lg text-xs font-bold transition-all ${
                      currentTip === 0
                        ? "bg-slate-600 text-white"
                        : "bg-slate-800 text-slate-300 hover:bg-slate-700 border border-slate-700"
                    }`}
                  >
                    {t("bill_payment.no_tip")}
                    <span className="block text-[10px] font-normal opacity-80">$0</span>
                  </button>
                </div>
                
                <div className="grid grid-cols-2 gap-2">
                  <div
                    onClick={() => setActiveField("tip")}
                    className={fieldClass("tip", "orange")}
                  >
                    <div className="text-xs text-slate-400 uppercase">{t("bill_payment.tip_manual")}</div>
                    <div className="text-lg font-bold text-orange-400 text-right tabular-nums">
                      ${tipInput || "0"}
                    </div>
                  </div>
                {selectedMethod.type === "cash" ? (
                  <div
                    onClick={() => setActiveField("received")}
                    className={fieldClass("received", "green")}
                  >
                    <div className="text-xs text-slate-400 uppercase">{t("bill_payment.received")}</div>
                    <div className="text-lg font-bold text-green-400 text-right tabular-nums">
                      ${receivedInput || "0"}
                    </div>
                  </div>
                ) : (
                  <div className="bg-slate-800/50 border-2 border-slate-800 rounded-lg px-3 py-2 flex items-center justify-center">
                    <span className="text-xs text-slate-400">{t("bill_payment.no_change")}</span>
                  </div>
                )}
              </div>
              </div>

              {selectedMethod.type === "cash" && change > 0 && (
                <div className="flex justify-between items-center bg-green-900/20 border border-green-700/50 rounded px-3 py-1.5">
                  <span className="text-xs text-green-300">{t("bill_payment.change_label")}</span>
                  <span className="text-sm font-bold text-green-400">{formatPrice(change)}</span>
                </div>
              )}
            </div>
          )}

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

          {selectedMethod && (
            <div className="p-3 border-t border-slate-800 flex-shrink-0">
                {errors.length > 0 && (
                  <div className="bg-red-900/30 border border-red-700 rounded-lg p-3 text-xs text-red-300 space-y-1 mb-4">
                    {errors.map((err, idx) => (
                      <div key={idx} className="flex items-start gap-2">
                        <AlertCircle size={14} className="mt-0.5 flex-shrink-0" />
                        <span>{err}</span>
                      </div>
                    ))}
                  </div>
                )}
              <button
                onClick={handleAddPayment}
                disabled={!canAdd}
                className="w-full py-3 bg-orange-500 hover:bg-orange-600 disabled:bg-slate-700 disabled:text-slate-400 rounded-lg font-bold text-white flex items-center justify-center gap-2"
              >
                {t("bill_payment.add_payment")}
              </button>
            </div>
          )}
        </div>
      </div>

      <div className="bg-slate-900 border-t border-slate-700 p-3 flex gap-2 flex-shrink-0">
        <button
          onClick={handleClose}
          disabled={isProcessing}
          className="flex-1 px-4 py-3 bg-slate-700 hover:bg-slate-600 rounded-lg font-bold disabled:opacity-50"
        >
          {t("common.cancel")}
        </button>
        <button
          onClick={handleCharge}
          disabled={!canCharge}
          className="flex-[2] px-4 py-3 bg-green-600 hover:bg-green-700 disabled:bg-slate-700 disabled:text-slate-400 rounded-lg font-bold text-white flex items-center justify-center gap-2"
        >
          {isProcessing ? (
            <>
              <Loader2 size={18} className="animate-spin" />
              {t("bill_payment.processing")}
            </>
          ) : (
            <>
              <CheckCircle2 size={18} />
              {t("bill_payment.charge")} {formatPrice(billPending + tipsSum)}
            </>
          )}
        </button>
      </div>
    </div>
  );
}
