import { useState, useMemo } from "react";
import type { TableBill } from "@/types/tableBill";
import type { PaymentMethod } from "@/types/payments";
import {
  usePaymentMethods,
  useChargeTable,
  useTablesWithBills,
} from "@/hooks/usePayments";
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
  Users,
  Clock,
} from "lucide-react";

interface TableBillModalProps {
  tableBill: TableBill;
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

export function TableBillModal({
  tableBill,
  isOpen,
  onClose,
  onSuccess,
}: TableBillModalProps) {
  const [selectedMethod, setSelectedMethod] = useState<PaymentMethod | null>(null);
  const [tipAmount, setTipAmount] = useState<string>("0");
  const [referenceCode, setReferenceCode] = useState("");
  const [receivedAmount, setReceivedAmount] = useState<string>("");

  const { data: methods = [], isLoading: loadingMethods } = usePaymentMethods();
  const chargeTable = useChargeTable();
  const { refetch: refetchTables } = useTablesWithBills();

  const grandTotal = useMemo(() => {
    const tip = parseFloat(tipAmount) || 0;
    return tableBill.total_amount + tip;
  }, [tableBill.total_amount, tipAmount]);

  const change = useMemo(() => {
    if (!selectedMethod || selectedMethod.type !== "cash") return 0;
    const received = parseFloat(receivedAmount) || 0;
    return Math.max(0, received - grandTotal);
  }, [receivedAmount, grandTotal, selectedMethod]);

  // Validar si el botón debe estar deshabilitado
  const isButtonDisabled = useMemo(() => {
    if (!selectedMethod || chargeTable.isPending) return true;
    
    // Para efectivo: validar monto recibido
    if (selectedMethod.type === "cash") {
      return (parseFloat(receivedAmount) || 0) < grandTotal;
    }
    
    // Para métodos que requieren referencia: validar que esté llena
    if (selectedMethod.requires_reference && !referenceCode.trim()) {
      return true;
    }
    
    return false;
  }, [selectedMethod, chargeTable.isPending, receivedAmount, grandTotal, referenceCode]);

  // Agrupar items de todos los pedidos por nombre
  const aggregatedItems = useMemo(() => {
    const map = new Map<
      string,
      { name: string; quantity: number; unitPrice: number; subtotal: number }
    >();
    for (const order of tableBill.orders) {
      for (const item of order.items) {
        const existing = map.get(item.name);
        if (existing) {
          existing.quantity += item.quantity;
          existing.subtotal += item.subtotal;
        } else {
          map.set(item.name, {
            name: item.name,
            quantity: item.quantity,
            unitPrice: item.unit_price,
            subtotal: item.subtotal,
          });
        }
      }
    }
    return Array.from(map.values());
  }, [tableBill.orders]);

  const handleCharge = async () => {
    if (!selectedMethod || isButtonDisabled) return;

    const idempotencyKey = crypto.randomUUID();

    try {
      await chargeTable.mutateAsync({
        tableUuid: tableBill.table_uuid,
        payload: {
          payment_method_uuid: selectedMethod.uuid,
          amount: tableBill.total_amount,
          tip_amount: parseFloat(tipAmount) || 0,
          reference_code: referenceCode.trim() || undefined,
          idempotency_key: idempotencyKey,
        },
      });
      
      // Forzar refetch inmediato de la lista de mesas
      await refetchTables();
      
      onSuccess();
      onClose();
    } catch (e) {
      console.error("Error al cobrar mesa:", e);
    }
  };

  if (!isOpen) return null;

  return (
    <>
      <div className="fixed inset-0 bg-black/70 z-50" onClick={onClose} />
      <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div
          className="bg-slate-900 rounded-xl shadow-2xl max-w-3xl w-full max-h-[90vh] overflow-y-auto"
          onClick={(e) => e.stopPropagation()}
        >
          {/* Header */}
          <div className="sticky top-0 bg-slate-900 flex items-center justify-between p-6 border-b border-slate-700">
            <div>
              <div className="flex items-center gap-2 mb-1">
                <Receipt size={24} className="text-orange-400" />
                <h2 className="text-2xl font-bold">
                  Precuenta · Mesa {tableBill.table_number}
                </h2>
              </div>
              <p className="text-sm text-slate-400 flex items-center gap-3">
                <span className="flex items-center gap-1">
                  <Users size={12} /> {tableBill.area_code}
                </span>
                <span className="flex items-center gap-1">
                  <Clock size={12} /> {tableBill.orders_count} pedidos
                </span>
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
            {/* Items agrupados */}
            <div className="bg-slate-800 rounded-lg p-4">
              <h3 className="text-sm font-semibold text-slate-400 mb-3 flex items-center gap-2">
                <Receipt size={14} />
                Consumo de la mesa
              </h3>
              <div className="space-y-1">
                {aggregatedItems.map((item) => (
                  <div
                    key={item.name}
                    className="flex justify-between py-1.5 border-b border-slate-700/50 last:border-0"
                  >
                    <span className="text-slate-200 flex-1">
                      <span className="font-semibold mr-2">
                        {item.quantity}×
                      </span>
                      {item.name}
                    </span>
                    <span className="text-white font-medium ml-4">
                      {formatPrice(item.subtotal)}
                    </span>
                  </div>
                ))}
              </div>

              <div className="mt-4 pt-3 border-t border-slate-700 space-y-1">
                <div className="flex justify-between text-sm">
                  <span className="text-slate-400">
                    Subtotal ({tableBill.total_items} items)
                  </span>
                  <span>{formatPrice(tableBill.subtotal)}</span>
                </div>
                <div className="flex justify-between text-sm">
                  <span className="text-slate-400">IVA (19%)</span>
                  <span>{formatPrice(tableBill.tax_amount)}</span>
                </div>
                <div className="flex justify-between text-xl font-bold pt-2 border-t border-slate-700">
                  <span>Total</span>
                  <span className="text-orange-400">
                    {formatPrice(tableBill.total_amount)}
                  </span>
                </div>
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
                <p className="text-sm mt-2 text-orange-300">
                  Total con propina:{" "}
                  <strong>{formatPrice(grandTotal)}</strong>
                </p>
              )}
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

            {/* Detalles según método */}
            {selectedMethod && (
              <div className="space-y-4">
                {selectedMethod.requires_reference && (
                  <div>
                    <label className="block text-sm text-slate-400 mb-1">
                      Código de referencia{" "}
                      <span className="text-red-400">*</span>
                    </label>
                    <input
                      type="text"
                      value={referenceCode}
                      onChange={(e) => setReferenceCode(e.target.value)}
                      maxLength={20}
                      className="w-full px-4 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-orange-500"
                      placeholder="Ej: 4521 (últimos 4 dígitos)"
                      autoFocus
                    />
                    {!referenceCode.trim() && (
                      <p className="text-xs text-amber-400 mt-1">
                        ⚠️ Requerido para {selectedMethod.name_translations?.es}
                      </p>
                    )}
                  </div>
                )}

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
              </div>
            )}

            {/* Error */}
            {chargeTable.isError && (
              <div className="bg-red-900/30 border border-red-700 rounded-lg p-3 text-sm text-red-300 flex items-start gap-2">
                <AlertCircle size={16} className="flex-shrink-0 mt-0.5" />
                <span>
                  {(chargeTable.error as Error).message ||
                    "Error al procesar el cobro"}
                </span>
              </div>
            )}

            {/* Botones */}
            <div className="flex gap-3">
              <button
                onClick={onClose}
                disabled={chargeTable.isPending}
                className="flex-1 px-4 py-3 bg-slate-700 hover:bg-slate-600 rounded-lg font-medium disabled:opacity-50"
              >
                Cancelar
              </button>
              <button
                onClick={handleCharge}
                disabled={isButtonDisabled}
                className="flex-1 px-4 py-3 bg-orange-500 hover:bg-orange-600 rounded-lg font-bold text-white disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
              >
                {chargeTable.isPending ? (
                  <>
                    <Loader2 size={18} className="animate-spin" />
                    Procesando...
                  </>
                ) : (
                  <>
                    <CheckCircle2 size={18} />
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
