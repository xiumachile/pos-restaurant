import { useState, useMemo } from "react";
import type { ServedOrder, PaymentMethod } from "@/types/payments";
import { usePaymentMethods, useCreatePayment, useMarkOrderAsPaid } from "@/hooks/usePayments";
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
} from "lucide-react";

interface PaymentModalProps {
  order: ServedOrder;
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

export function PaymentModal({ order, isOpen, onClose, onSuccess }: PaymentModalProps) {
  const [selectedMethod, setSelectedMethod] = useState<PaymentMethod | null>(null);
  const [amount, setAmount] = useState<string>(String(order.total));
  const [tipAmount, setTipAmount] = useState<string>("0");
  const [referenceCode, setReferenceCode] = useState("");
  const [receivedAmount, setReceivedAmount] = useState<string>("");

  const { data: methods = [], isLoading: loadingMethods } = usePaymentMethods();
  const createPayment = useCreatePayment();
  const markAsPaid = useMarkOrderAsPaid();

  const totalWithTip = useMemo(() => {
    const base = parseFloat(amount) || 0;
    const tip = parseFloat(tipAmount) || 0;
    return base + tip;
  }, [amount, tipAmount]);

  const change = useMemo(() => {
    if (!selectedMethod || selectedMethod.type !== "cash") return 0;
    const received = parseFloat(receivedAmount) || 0;
    return Math.max(0, received - totalWithTip);
  }, [receivedAmount, totalWithTip, selectedMethod]);

  const handleSubmit = async () => {
    if (!selectedMethod) return;

    const idempotencyKey = crypto.randomUUID();

    try {
      // 1. Registrar el pago
      await createPayment.mutateAsync({
        order_uuid: order.uuid,
        payment_method_uuid: selectedMethod.uuid,
        amount: parseFloat(amount),
        tip_amount: parseFloat(tipAmount) || 0,
        reference_code: referenceCode || undefined,
        idempotency_key: idempotencyKey,
      });

      // 2. Transicionar el pedido a paid
      await markAsPaid.mutateAsync(order.uuid);

      onSuccess();
      onClose();
    } catch (e) {
      console.error("Error al procesar pago:", e);
    }
  };

  if (!isOpen) return null;

  return (
    <>
      <div className="fixed inset-0 bg-black/70 z-50" onClick={onClose} />
      <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div
          className="bg-slate-900 rounded-xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto"
          onClick={(e) => e.stopPropagation()}
        >
          {/* Header */}
          <div className="sticky top-0 bg-slate-900 flex items-center justify-between p-6 border-b border-slate-700">
            <div>
              <h2 className="text-2xl font-bold">Cobrar Pedido</h2>
              <p className="text-sm text-slate-400 mt-1">
                {order.order_number}
                {order.table && ` · Mesa ${order.table.table_number}`}
              </p>
            </div>
            <button
              onClick={onClose}
              className="p-2 hover:bg-slate-800 rounded-lg"
            >
              <X size={20} />
            </button>
          </div>

          <div className="p-6 space-y-6">
            {/* Resumen del pedido */}
            <div className="bg-slate-800 rounded-lg p-4">
              <h3 className="text-sm font-semibold text-slate-400 mb-2">
                Resumen del pedido
              </h3>
              <div className="space-y-1 text-sm">
                {order.items.map((item) => (
                  <div key={item.uuid} className="flex justify-between">
                    <span className="text-slate-300">
                      {item.quantity}× {item.name}
                    </span>
                    <span className="text-slate-200 font-medium">
                      {formatPrice(item.subtotal)}
                    </span>
                  </div>
                ))}
              </div>
              <div className="mt-3 pt-3 border-t border-slate-700 space-y-1">
                <div className="flex justify-between text-sm">
                  <span className="text-slate-400">Subtotal</span>
                  <span>{formatPrice(order.subtotal)}</span>
                </div>
                <div className="flex justify-between text-sm">
                  <span className="text-slate-400">IVA (19%)</span>
                  <span>{formatPrice(order.tax_amount)}</span>
                </div>
                <div className="flex justify-between text-lg font-bold pt-2 border-t border-slate-700">
                  <span>Total</span>
                  <span className="text-orange-400">
                    {formatPrice(order.total)}
                  </span>
                </div>
              </div>
            </div>

            {/* Método de pago */}
            <div>
              <h3 className="text-sm font-semibold text-slate-400 mb-3">
                Método de pago
              </h3>
              {loadingMethods ? (
                <div className="text-center py-4">
                  <Loader2 className="animate-spin mx-auto" size={24} />
                </div>
              ) : (
                <div className="grid grid-cols-2 gap-3">
                  {methods.map((method) => {
                    const Icon = METHOD_ICONS[method.type] || CreditCard;
                    const isSelected = selectedMethod?.uuid === method.uuid;
                    return (
                      <button
                        key={method.uuid}
                        onClick={() => {
                          setSelectedMethod(method);
                          if (method.type !== "cash") {
                            setReceivedAmount("");
                          }
                        }}
                        className={`p-4 rounded-xl border-2 transition-all flex items-center gap-3 ${
                          isSelected
                            ? "border-orange-500 bg-orange-500/10"
                            : "border-slate-700 bg-slate-800 hover:border-slate-600"
                        }`}
                      >
                        <span className="text-2xl">
                          {method.icon || <Icon size={24} />}
                        </span>
                        <span className="font-semibold text-white text-left flex-1">
                          {method.name_translations?.es || method.code}
                        </span>
                      </button>
                    );
                  })}
                </div>
              )}
            </div>

            {/* Monto a pagar + propina */}
            {selectedMethod && (
              <div className="space-y-4">
                <div className="grid grid-cols-2 gap-3">
                  <div>
                    <label className="block text-sm text-slate-400 mb-1">
                      Monto a pagar
                    </label>
                    <input
                      type="number"
                      value={amount}
                      onChange={(e) => setAmount(e.target.value)}
                      step="0.01"
                      className="w-full px-4 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-lg font-bold focus:outline-none focus:ring-2 focus:ring-orange-500"
                    />
                  </div>
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
                  </div>
                </div>

                {/* Referencia para tarjeta/transferencia */}
                {selectedMethod.requires_reference && (
                  <div>
                    <label className="block text-sm text-slate-400 mb-1">
                      Código de referencia (últimos 4 dígitos, N° transacción)
                    </label>
                    <input
                      type="text"
                      value={referenceCode}
                      onChange={(e) => setReferenceCode(e.target.value)}
                      maxLength={20}
                      className="w-full px-4 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-orange-500"
                      placeholder="Ej: 4521"
                    />
                  </div>
                )}

                {/* Recibido + vuelto (solo efectivo) */}
                {selectedMethod.type === "cash" && (
                  <div className="bg-green-900/20 border border-green-700/40 rounded-lg p-4 space-y-3">
                    <div>
                      <label className="block text-sm text-green-300 mb-1">
                        Monto recibido del cliente
                      </label>
                      <input
                        type="number"
                        value={receivedAmount}
                        onChange={(e) => setReceivedAmount(e.target.value)}
                        step="0.01"
                        min="0"
                        autoFocus
                        className="w-full px-4 py-3 bg-slate-800 border border-green-700 rounded-lg text-white text-2xl font-bold focus:outline-none focus:ring-2 focus:ring-green-500"
                        placeholder="0"
                      />
                    </div>
                    {receivedAmount && (
                      <div className="flex justify-between items-center pt-2 border-t border-green-700/40">
                        <span className="text-green-300 font-semibold">
                          Vuelto:
                        </span>
                        <span
                          className={`text-2xl font-bold ${
                            change >= 0 ? "text-green-400" : "text-red-400"
                          }`}
                        >
                          {formatPrice(change)}
                        </span>
                      </div>
                    )}
                  </div>
                )}

                {/* Resumen final */}
                <div className="bg-slate-800 rounded-lg p-3 space-y-1">
                  <div className="flex justify-between text-sm">
                    <span className="text-slate-400">Total a cobrar:</span>
                    <span className="font-bold text-orange-400">
                      {formatPrice(totalWithTip)}
                    </span>
                  </div>
                </div>
              </div>
            )}

            {/* Error */}
            {(createPayment.isError || markAsPaid.isError) && (
              <div className="bg-red-900/30 border border-red-700 rounded-lg p-3 text-sm text-red-300 flex items-start gap-2">
                <AlertCircle size={16} className="flex-shrink-0 mt-0.5" />
                <span>
                  {((createPayment.error || markAsPaid.error) as Error)
                    .message || "Error al procesar el pago"}
                </span>
              </div>
            )}

            {/* Botones */}
            <div className="flex gap-3">
              <button
                onClick={onClose}
                disabled={createPayment.isPending || markAsPaid.isPending}
                className="flex-1 px-4 py-3 bg-slate-700 hover:bg-slate-600 rounded-lg font-medium disabled:opacity-50"
              >
                Cancelar
              </button>
              <button
                onClick={handleSubmit}
                disabled={
                  !selectedMethod ||
                  createPayment.isPending ||
                  markAsPaid.isPending ||
                  (selectedMethod.type === "cash" &&
                    (parseFloat(receivedAmount) || 0) < totalWithTip)
                }
                className="flex-1 px-4 py-3 bg-orange-500 hover:bg-orange-600 rounded-lg font-bold text-white disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
              >
                {createPayment.isPending || markAsPaid.isPending ? (
                  <>
                    <Loader2 size={18} className="animate-spin" />
                    Procesando...
                  </>
                ) : (
                  <>
                    <CheckCircle2 size={18} />
                    Confirmar Pago
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
