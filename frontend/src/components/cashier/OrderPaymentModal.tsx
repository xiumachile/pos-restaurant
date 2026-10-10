import { useState, useMemo } from "react";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useTranslation } from "react-i18next";
import {
  X,
  Loader2,
  CreditCard,
  Banknote,
  Building2,
  Gift,
} from "lucide-react";
import type { Order } from "@/types/orders";
import type { PaymentMethod } from "@/types/payments";
import { paymentsService } from "@/services/paymentsService";
import { formatPrice } from "@/types/catalog";
import { usePaymentMethods } from "@/hooks/usePayments";
import { useToastStore } from "@/store/useToastStore";
import { useSettingsStore } from "@/store/useSettingsStore";
import { usePopupStore } from "@/store/usePopupStore";
import { SUGGESTED_TIP_PERCENTAGES } from "@/constants/tips";
import { parseCLPAmount } from "@/utils/money";

// Reutilizar config de métodos de pago (igual que BillPaymentModalV2)
const getPaymentConfig = (t: (key: string) => string): Record<string, { label: string; icon: any; color: string }> => ({
  CASH: { label: t("bill_payment.payment_method_cash"), icon: Banknote, color: "bg-green-600" },
  CARD: { label: t("bill_payment.payment_method_card"), icon: CreditCard, color: "bg-blue-600" },
  TRANSFER: { label: t("bill_payment.payment_method_transfer"), icon: Building2, color: "bg-purple-600" },
  GIFT_CARD: { label: t("bill_payment.payment_method_gift_card"), icon: Gift, color: "bg-amber-600" },
  DEBIT_CARD: { label: t("bill_payment.payment_method_debit_card"), icon: CreditCard, color: "bg-blue-600" },
});

interface OrderPaymentModalProps {
  order: Order;
  isOpen: boolean;
  onClose: () => void;
  onSuccess: () => void;
}

export function OrderPaymentModal({ order, isOpen, onClose, onSuccess }: OrderPaymentModalProps) {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  const addToast = useToastStore((state) => state.addToast);
  const { showChangePopup: changePopupEnabled, changePopupDuration } = useSettingsStore();
  const showPopup = usePopupStore((state) => state.showPopup);

  const { data: methods = [] } = usePaymentMethods();

  const [selectedMethod, setSelectedMethod] = useState<PaymentMethod | null>(null);
  const [tipPct, setTipPct] = useState<number | null>(null);
  const [tipManual, setTipManual] = useState<string>("0");
  const [receivedInput, setReceivedInput] = useState<string>("");
  const [isProcessing, setIsProcessing] = useState(false);

  const paymentConfig = useMemo(() => getPaymentConfig(t), [t]);

  // Cálculos
  const subtotal = order.subtotal;
  const taxAmount = order.tax_amount;
  const baseTotal = order.total;

  // Tip: derivar de % o manual
  const tipAmount = tipPct !== null
    ? Math.round(baseTotal * tipPct / 100)
    : parseCLPAmount(tipManual);

  const grandTotal = baseTotal + tipAmount;

  const isCash = selectedMethod?.type === "cash";
  const received = receivedInput === "" ? grandTotal : parseCLPAmount(receivedInput);
  const change = isCash ? Math.max(0, received - grandTotal) : 0;
  const cashShort = isCash && received < grandTotal;

  const canCharge = !!selectedMethod && !cashShort && !isProcessing;

  const payMutation = useMutation({
    mutationFn: () => {
      const idempotencyKey = crypto.randomUUID();
      return paymentsService.payOrder(order.uuid, {
        payment_method_uuid: selectedMethod!.uuid,
        tip_amount: tipAmount,
        idempotency_key: idempotencyKey,
      });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["orders", "active"] });
      queryClient.invalidateQueries({ queryKey: ["cashier"] });

      // Mostrar popup de vuelto si es efectivo y hay cambio
      if (isCash && change > 0 && changePopupEnabled) {
        console.log('[OrderPaymentModal] Mostrando popup de vuelto:', change);
        showPopup('change', change, changePopupDuration);
      }

      addToast("success", `${t("order_payment.payment_success")} · ${order.order_number}`);
      setIsProcessing(false);
      onSuccess();
      onClose();
    },
    onError: (error: any) => {
      setIsProcessing(false);
      addToast("error", error?.response?.data?.message || t("order_payment.payment_error"));
    },
  });

  const handleSelectMethod = (method: PaymentMethod) => {
    setSelectedMethod(method);
    setReceivedInput(""); // Resetear recibido al cambiar método
  };

  const handleTipPct = (pct: number) => {
    setTipPct(pct);
    setTipManual("0");
  };

  const handleTipManual = () => {
    setTipPct(null);
  };

  const handleCharge = () => {
    if (!canCharge) return;
    setIsProcessing(true);
    payMutation.mutate();
  };

  if (!isOpen) return null;

  const typeKey = order.type === "delivery" ? "order_payment.delivery" : "order_payment.takeout";

  return (
    <div className="fixed inset-0 bg-black/80 z-50 flex items-center justify-center p-2 md:p-4">
      <div className="bg-slate-900 border border-slate-700 rounded-xl shadow-2xl max-w-2xl w-full max-h-[95vh] flex flex-col overflow-hidden">
        {/* HEADER */}
        <div className="bg-slate-900 border-b border-slate-700 px-4 py-3 flex items-center justify-between flex-shrink-0">
          <div className="flex-1 min-w-0">
            <h2 className="text-lg md:text-xl font-bold text-white flex items-center gap-2">
              {t("order_payment.charge_order")}
            </h2>
            <p className="text-xs text-slate-400 mt-0.5 truncate">
              {t(typeKey)} · {t("order_payment.order_number", { number: order.order_number })}
              {order.customer_name && ` · ${order.customer_name}`}
            </p>
          </div>
          <button
            onClick={onClose}
            disabled={isProcessing}
            className="p-2 hover:bg-slate-800 rounded-lg disabled:opacity-50 ml-2"
          >
            <X size={20} className="text-slate-400" />
          </button>
        </div>

        {/* BODY (scrollable) */}
        <div className="flex-1 overflow-y-auto p-4 space-y-4">
          {/* RESUMEN DEL PEDIDO */}
          <div className="bg-slate-800/50 border border-slate-700 rounded-lg p-3">
            <div className="space-y-1 max-h-32 overflow-y-auto">
              {order.items.slice(0, 5).map((item) => (
                <div key={item.uuid} className="flex justify-between text-sm">
                  <span className="text-slate-300 truncate pr-2">
                    <span className="text-slate-500">{item.quantity}x</span> {item.name}
                  </span>
                  <span className="text-white font-medium tabular-nums">
                    {formatPrice(item.subtotal)}
                  </span>
                </div>
              ))}
              {order.items.length > 5 && (
                <div className="text-xs text-slate-500 text-center pt-1">
                  +{order.items.length - 5} {t("order_payment.items").toLowerCase()}...
                </div>
              )}
            </div>

            <div className="border-t border-slate-700 mt-2 pt-2 space-y-1">
              <div className="flex justify-between text-xs text-slate-400">
                <span>{t("order_payment.subtotal")}</span>
                <span className="tabular-nums">{formatPrice(subtotal)}</span>
              </div>
              <div className="flex justify-between text-xs text-slate-400">
                <span>{t("order_payment.tax")}</span>
                <span className="tabular-nums">{formatPrice(taxAmount)}</span>
              </div>
              {tipAmount > 0 && (
                <div className="flex justify-between text-xs text-orange-300">
                  <span>{t("order_payment.tip")}</span>
                  <span className="tabular-nums font-semibold">{formatPrice(tipAmount)}</span>
                </div>
              )}
              <div className="flex justify-between text-base font-bold text-white pt-1">
                <span>{t("order_payment.total")}</span>
                <span className="text-orange-400 tabular-nums">{formatPrice(grandTotal)}</span>
              </div>
            </div>
          </div>

          {/* MÉTODO DE PAGO (Grid táctil grande) */}
          <div>
            <h3 className="text-xs font-semibold text-slate-400 uppercase mb-2">
              {t("order_payment.select_payment_method")}
            </h3>
            <div className="grid grid-cols-2 md:grid-cols-4 gap-2">
              {methods.map((method) => {
                const config = paymentConfig[method.code] || {
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
                    disabled={isProcessing}
                    className={`
                      flex flex-col items-center justify-center gap-2 p-3 rounded-lg border-2 transition-all min-h-[72px]
                      disabled:opacity-50
                      ${isSelected
                        ? "bg-slate-800 border-orange-500 shadow-lg shadow-orange-500/20"
                        : "bg-slate-800/50 border-slate-700 hover:border-slate-600"
                      }
                    `}
                  >
                    <div className={`w-10 h-10 rounded-lg ${config.color} flex items-center justify-center`}>
                      <Icon size={20} className="text-white" />
                    </div>
                    <span className="text-xs font-semibold text-white text-center leading-tight">
                      {config.label}
                    </span>
                  </button>
                );
              })}
            </div>
          </div>

          {/* PROPINA */}
          <div>
            <h3 className="text-xs font-semibold text-slate-400 uppercase mb-2">
              {t("order_payment.select_tip")}
            </h3>
            <div className="grid grid-cols-4 gap-2">
              <button
                onClick={() => { setTipPct(0); setTipManual("0"); }}
                disabled={isProcessing}
                className={`
                  py-3 px-2 rounded-lg font-semibold transition-all min-h-[56px]
                  disabled:opacity-50
                  ${tipPct === 0
                    ? "bg-orange-500 text-white"
                    : "bg-slate-800 text-slate-300 border border-slate-700 hover:bg-slate-700"
                  }
                `}
              >
                {t("order_payment.no_tip")}
              </button>
              {SUGGESTED_TIP_PERCENTAGES.map((pct) => {
                const isActive = tipPct === pct;
                return (
                  <button
                    key={pct}
                    onClick={() => handleTipPct(pct)}
                    disabled={isProcessing}
                    className={`
                      py-3 px-2 rounded-lg font-bold transition-all min-h-[56px]
                      disabled:opacity-50
                      ${isActive
                        ? "bg-orange-500 text-white"
                        : "bg-slate-800 text-slate-300 border border-slate-700 hover:bg-slate-700"
                      }
                    `}
                  >
                    {pct}%
                    <div className="text-[10px] font-normal opacity-75 mt-0.5">
                      {formatPrice(Math.round(baseTotal * pct / 100))}
                    </div>
                  </button>
                );
              })}
            </div>
            {/* Propina manual */}
            <div className="mt-2 flex items-center gap-2">
              <button
                onClick={handleTipManual}
                disabled={isProcessing}
                className={`
                  px-3 py-2 rounded-lg text-xs font-semibold transition-all whitespace-nowrap
                  disabled:opacity-50
                  ${tipPct === null
                    ? "bg-orange-500 text-white"
                    : "bg-slate-800 text-slate-400 border border-slate-700"
                  }
                `}
              >
                {t("order_payment.tip_manual")}
              </button>
              <input
                type="number"
                value={tipManual}
                onChange={(e) => { setTipManual(e.target.value); setTipPct(null); }}
                disabled={isProcessing || tipPct !== null}
                className="flex-1 bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm font-semibold tabular-nums disabled:opacity-50"
                placeholder="$0"
              />
            </div>
          </div>

          {/* EFECTIVO: Recibido + Vuelto */}
          {isCash && (
            <div className="bg-green-900/20 border border-green-800/50 rounded-lg p-3 space-y-2">
              <div className="flex items-center justify-between">
                <span className="text-xs font-semibold text-green-300 uppercase">
                  {t("order_payment.received")}
                </span>
                <input
                  type="number"
                  value={receivedInput}
                  onChange={(e) => setReceivedInput(e.target.value)}
                  disabled={isProcessing}
                  className="bg-slate-900 border border-slate-700 rounded px-3 py-1.5 text-green-400 font-bold tabular-nums w-32 text-right"
                  placeholder={formatPrice(grandTotal)}
                />
              </div>
              {change > 0 && (
                <div className="flex items-center justify-between pt-2 border-t border-green-800/30">
                  <span className="text-sm font-semibold text-green-200">
                    {t("order_payment.change")}
                  </span>
                  <span className="text-lg font-bold text-green-400 tabular-nums">
                    {formatPrice(change)}
                  </span>
                </div>
              )}
              {cashShort && (
                <div className="text-xs text-red-400 font-semibold">
                  ⚠ {t("order_payment.error_insufficient_cash")}
                </div>
              )}
            </div>
          )}
        </div>

        {/* FOOTER */}
        <div className="bg-slate-900 border-t border-slate-700 p-3 flex-shrink-0">
          <div className="flex items-center justify-between mb-2 px-1">
            <span className="text-xs text-slate-400 uppercase font-semibold">
              {t("order_payment.total")}
            </span>
            <span className="text-2xl font-bold text-orange-400 tabular-nums">
              {formatPrice(grandTotal)}
            </span>
          </div>
          <div className="grid grid-cols-2 gap-2">
            <button
              onClick={onClose}
              disabled={isProcessing}
              className="py-3 rounded-lg border border-slate-700 text-slate-300 font-semibold hover:bg-slate-800 transition-colors disabled:opacity-50 min-h-[52px]"
            >
              {t("order_payment.cancel")}
            </button>
            <button
              onClick={handleCharge}
              disabled={!canCharge}
              className="py-3 rounded-lg bg-orange-500 text-white font-bold hover:bg-orange-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed min-h-[52px] flex items-center justify-center gap-2"
            >
              {isProcessing ? (
                <>
                  <Loader2 className="animate-spin" size={20} />
                  {t("order_payment.processing")}
                </>
              ) : (
                <>
                  <CreditCard size={20} />
                  {t("order_payment.charge")} {formatPrice(grandTotal)}
                </>
              )}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
