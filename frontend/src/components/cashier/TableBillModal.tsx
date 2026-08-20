import { useState, useMemo, useEffect } from "react";
import type { TableBill, TableBillOrder } from "@/types/tableBill";
import type { Bill } from "@/types/bills";
import type { PaymentMethod } from "@/types/payments";
import {
  usePaymentMethods,
  useChargeTable,
  useTablesWithBills,
  usePrepareTableBills,
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
  Printer,
  Scissors,
  ChevronDown,
  ChevronRight,
} from "lucide-react";
import { PrintablePrecuenta } from "./PrintablePrecuenta";
import { SplitBillModal } from "./SplitBillModal";
import { BillPaymentModalV2 } from "./BillPaymentModalV2";

interface TableBillModalProps {
  tableUuid: string; // Solo pasamos el UUID, cargamos datos internamente
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
  tableUuid,
  isOpen,
  onClose,
  onSuccess,
}: TableBillModalProps) {
  // Cargar datos de la mesa internamente (se actualiza automáticamente)
  const { data: tablesWithBills = [] } = useTablesWithBills();
  const tableBill = useMemo(
    () => tablesWithBills.find((t) => t.table_uuid === tableUuid),
    [tablesWithBills, tableUuid]
  );

  const [selectedMethod, setSelectedMethod] = useState<PaymentMethod | null>(null);
  const [tipAmount, setTipAmount] = useState<string>("0");
  const [receivedAmount, setReceivedAmount] = useState<string>("");
  const [expandedOrderUuid, setExpandedOrderUuid] = useState<string | null>(null);

  // Modales
  const [showSplitModal, setShowSplitModal] = useState(false);
  const [splittingOrder, setSplittingOrder] = useState<TableBillOrder | null>(null);
  const [payingBill, setPayingBill] = useState<Bill | null>(null);
  const [payingBills, setPayingBills] = useState<Bill[] | null>(null);

  // Toast de éxito
  const [showSuccessToast, setShowSuccessToast] = useState(false);
  const [successMessage, setSuccessMessage] = useState("");

  const { data: methods = [], isLoading: loadingMethods } = usePaymentMethods();
  const chargeTable = useChargeTable();
  const prepareTableBills = usePrepareTableBills();

  // Auto-ocultar toast
  useEffect(() => {
    if (showSuccessToast) {
      const timer = setTimeout(() => setShowSuccessToast(false), 3000);
      return () => clearTimeout(timer);
    }
  }, [showSuccessToast]);

  // Verificar si hay bills en algún order (modo split activo)
  const hasBills = useMemo(
    () => tableBill?.orders.some((o) => o.bills && o.bills.length > 0) ?? false,
    [tableBill?.orders]
  );

  // Todas las bills de la mesa (planas)
  const allBills = useMemo(
    () =>
      tableBill?.orders.flatMap((o) =>
        (o.bills || []).map((b) => ({ ...b, order_number: o.order_number }))
      ) ?? [],
    [tableBill?.orders]
  );

  const pendingBillsCount = useMemo(
    () => allBills.filter((b) => b.status === "open").length,
    [allBills]
  );

  const grandTotal = useMemo(() => {
    if (!tableBill) return 0;
    const tip = parseFloat(tipAmount) || 0;
    return tableBill.total_amount + tip;
  }, [tableBill?.total_amount, tipAmount]);

  const change = useMemo(() => {
    if (!selectedMethod || selectedMethod.type !== "cash") return 0;
    const received = parseFloat(receivedAmount) || 0;
    return Math.max(0, received - grandTotal);
  }, [receivedAmount, grandTotal, selectedMethod]);

  const isButtonDisabled = useMemo(() => {
    if (!selectedMethod || chargeTable.isPending || !tableBill) return true;
    if (selectedMethod.type === "cash") {
      return (parseFloat(receivedAmount) || 0) < grandTotal;
    }
    return false;
  }, [selectedMethod, chargeTable.isPending, receivedAmount, grandTotal, tableBill]);

  const aggregatedItems = useMemo(() => {
    if (!tableBill) return [];
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
  }, [tableBill?.orders]);

  // Botón "Cobrar con pagos divididos" (nuevo)
  const handleOpenSplitPayment = async () => {
    if (!tableBill || prepareTableBills.isPending) return;

    try {
      const result = await prepareTableBills.mutateAsync(tableBill.table_uuid);
      setPayingBills(result.bills);
    } catch (e) {
      console.error("Error preparando bills:", e);
      alert("No se pudieron preparar las sub-cuentas. Intenta de nuevo.");
    }
  };

  // Botón "Cobrar" tradicional (legacy - pago único)
  const handleCharge = async () => {
    if (!selectedMethod || isButtonDisabled || !tableBill) return;
    const idempotencyKey = crypto.randomUUID();

    try {
      await chargeTable.mutateAsync({
        tableUuid: tableBill.table_uuid,
        payload: {
          payment_method_uuid: selectedMethod.uuid,
          amount: tableBill.total_amount,
          tip_amount: parseFloat(tipAmount) || 0,
          idempotency_key: idempotencyKey,
        },
      });
      onSuccess();
      onClose();
    } catch (e) {
      console.error("Error al cobrar mesa:", e);
    }
  };

  const handlePrint = () => window.print();

  const handleOpenSplit = (order: TableBillOrder) => {
    // Si ya tiene bills, mostrar confirmación
    if (order.bills && order.bills.length > 0) {
      const confirmed = window.confirm(
        `Este pedido ya tiene ${order.bills.length} sub-cuenta(s) creada(s).\n\n¿Deseas eliminarlas y crear nuevas?`
      );
      if (!confirmed) return;
    }
    setSplittingOrder(order);
    setShowSplitModal(true);
  };

  const handleSplitSuccess = (bills: Bill[]) => {
    setShowSplitModal(false);
    setSplittingOrder(null);
    // Mostrar toast de éxito
    setSuccessMessage(`✅ ${bills.length} sub-cuenta(s) creada(s) exitosamente`);
    setShowSuccessToast(true);
  };

  // Si la mesa ya no existe (fue cobrada), cerrar el modal
  useEffect(() => {
    if (isOpen && tableBill === undefined) {
      onClose();
      onSuccess();
    }
  }, [isOpen, tableBill, onClose, onSuccess]);

  if (!isOpen || !tableBill) return null;

  return (
    <>
      <div className="hidden">
        <PrintablePrecuenta tableBill={tableBill} />
      </div>

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
                {hasBills && (
                  <span className="flex items-center gap-1 text-orange-400">
                    <Scissors size={12} /> Dividida
                  </span>
                )}
              </p>
            </div>
            <button onClick={onClose} className="p-2 hover:bg-slate-800 rounded-lg">
              <X size={20} />
            </button>
          </div>

          <div className="p-6 space-y-5">
            {/* Items agrupados */}
            <div className="bg-slate-800 rounded-lg p-4">
              <div className="flex items-center justify-between mb-3">
                <h3 className="text-sm font-semibold text-slate-400 flex items-center gap-2">
                  <Receipt size={14} />
                  Consumo de la mesa
                </h3>
                <button
                  onClick={handlePrint}
                  className="flex items-center gap-1 px-3 py-1.5 bg-blue-500 hover:bg-blue-600 rounded-lg text-white text-xs font-medium transition-colors"
                >
                  <Printer size={14} />
                  Imprimir
                </button>
              </div>
              <div className="space-y-1">
                {aggregatedItems.map((item) => (
                  <div
                    key={item.name}
                    className="flex justify-between py-1.5 border-b border-slate-700/50 last:border-0"
                  >
                    <span className="text-slate-200 flex-1">
                      <span className="font-semibold mr-2">{item.quantity}×</span>
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
                  <span className="text-slate-400">Subtotal ({tableBill.total_items} items)</span>
                  <span>{formatPrice(tableBill.subtotal)}</span>
                </div>
                <div className="flex justify-between text-sm">
                  <span className="text-slate-400">IVA (19%)</span>
                  <span>{formatPrice(tableBill.tax_amount)}</span>
                </div>
                <div className="flex justify-between text-xl font-bold pt-2 border-t border-slate-700">
                  <span>Total mesa</span>
                  <span className="text-orange-400">
                    {formatPrice(tableBill.total_amount)}
                  </span>
                </div>
              </div>
            </div>

            {/* Pedidos individuales (con botón dividir) */}
            <div className="bg-slate-800/60 rounded-lg p-4">
              <h3 className="text-sm font-semibold text-slate-400 mb-3">
                Pedidos ({tableBill.orders.length})
              </h3>
              <div className="space-y-2">
                {tableBill.orders.map((order) => {
                  const isExpanded = expandedOrderUuid === order.uuid;
                  const hasBillsThis = order.bills && order.bills.length > 0;

                  return (
                    <div
                      key={order.uuid}
                      className="bg-slate-900/50 rounded-lg border border-slate-700/50"
                    >
                      <div
                        role="button"
                        tabIndex={0}
                        onClick={() =>
                          setExpandedOrderUuid(isExpanded ? null : order.uuid)
                        }
                        onKeyDown={(e) => {
                          if (e.key === 'Enter' || e.key === ' ') {
                            e.preventDefault();
                            setExpandedOrderUuid(isExpanded ? null : order.uuid);
                          }
                        }}
                        className="w-full p-3 flex items-center justify-between hover:bg-slate-800/50 transition-colors cursor-pointer"
                      >
                        <div className="flex items-center gap-2">
                          {isExpanded ? (
                            <ChevronDown size={14} className="text-slate-400" />
                          ) : (
                            <ChevronRight size={14} className="text-slate-400" />
                          )}
                          <span className="font-semibold text-sm">
                            {order.order_number}
                          </span>
                          {hasBillsThis && (
                            <span className="text-xs px-1.5 py-0.5 bg-orange-500/20 text-orange-400 rounded">
                              <Scissors size={10} className="inline" /> {order.bills!.length} sub-cuentas
                            </span>
                          )}
                          <span className="text-xs text-slate-500">
                            ({order.items.length} items)
                          </span>
                        </div>
                        <div className="flex items-center gap-2">
                          <span className="font-bold text-orange-400">
                            {formatPrice(order.total)}
                          </span>
                          <button
                            onClick={(e) => {
                              e.stopPropagation();
                              handleOpenSplit(order);
                            }}
                            disabled={hasBillsThis}
                            className={`flex items-center gap-1 px-2 py-1 border rounded text-xs font-medium transition-colors ${
                              hasBillsThis
                                ? "bg-slate-700/50 border-slate-600 text-slate-500 cursor-not-allowed"
                                : "bg-purple-500/20 hover:bg-purple-500/30 border-purple-700/50 text-purple-300"
                            }`}
                            title={
                              hasBillsThis
                                ? "Ya tiene sub-cuentas"
                                : "Dividir este pedido"
                            }
                          >
                            <Scissors size={11} />
                            {hasBillsThis ? "Ya dividida" : "Dividir"}
                          </button>
                        </div>
                      </div>

                      {isExpanded && (
                        <div className="px-3 pb-3 border-t border-slate-700/50 pt-2 space-y-3">
                          {/* Items del pedido */}
                          <div className="space-y-1">
                            {order.items.map((item) => (
                              <div
                                key={item.uuid}
                                className="flex justify-between text-xs py-1"
                              >
                                <span className="text-slate-300">
                                  {item.quantity}× {item.name}
                                </span>
                                <span className="text-slate-200">
                                  {formatPrice(item.subtotal)}
                                </span>
                              </div>
                            ))}
                          </div>

                          {/* Bills de este pedido (si existen) */}
                          {hasBillsThis && (
                            <div className="pt-2 border-t border-slate-700/50">
                              <h4 className="text-xs font-semibold text-slate-400 mb-2 flex items-center gap-1">
                                <Scissors size={12} />
                                Sub-cuentas
                              </h4>
                              <div className="space-y-1.5">
                                {order.bills!.map((bill, idx) => (
                                  <div
                                    key={bill.uuid}
                                    className={`flex items-center justify-between p-2 rounded-lg border ${
                                      bill.status === "paid"
                                        ? "bg-green-900/20 border-green-700/50"
                                        : "bg-orange-900/20 border-orange-700/50"
                                    }`}
                                  >
                                    <div className="flex items-center gap-2">
                                      <span className="text-xs font-bold text-slate-300">
                                        #{idx + 1}
                                      </span>
                                      <span className="text-xs text-slate-400">
                                        {bill.type === "equal_split"
                                          ? "Equitativa"
                                          : bill.type === "by_items"
                                          ? "Por items"
                                          : "Montos"}
                                      </span>
                                      {bill.status === "paid" && (
                                        <CheckCircle2 size={12} className="text-green-400" />
                                      )}
                                    </div>
                                    <div className="flex items-center gap-2">
                                      <span className="text-sm font-bold text-white">
                                        {formatPrice(bill.total)}
                                      </span>
                                      {bill.status !== "paid" && (
                                        <button
                                          onClick={() => setPayingBill(bill)}
                                          className="px-2 py-1 bg-green-500 hover:bg-green-600 rounded text-white text-xs font-medium"
                                        >
                                          Cobrar
                                        </button>
                                      )}
                                    </div>
                                  </div>
                                ))}
                              </div>
                            </div>
                          )}
                        </div>
                      )}
                    </div>
                  );
                })}
              </div>
            </div>

            {/* Cobro completo (solo si no hay bills) */}
            {!hasBills ? (
              <>
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
                      Total con propina: <strong>{formatPrice(grandTotal)}</strong>
                    </p>
                  )}
                </div>

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

                {chargeTable.isError && (
                  <div className="bg-red-900/30 border border-red-700 rounded-lg p-3 text-sm text-red-300 flex items-start gap-2">
                    <AlertCircle size={16} className="flex-shrink-0 mt-0.5" />
                    <span>
                      {(chargeTable.error as Error).message || "Error al cobrar"}
                    </span>
                  </div>
                )}

                <div className="flex gap-2">
                  <button
                    onClick={onClose}
                    disabled={chargeTable.isPending}
                    className="flex-1 px-4 py-3 bg-slate-700 hover:bg-slate-600 rounded-lg font-medium disabled:opacity-50"
                  >
                    Cancelar
                  </button>
                  <button
                    onClick={handleOpenSplitPayment}
                    disabled={!tableBill || prepareTableBills.isPending}
                    className="flex-1 px-4 py-3 bg-green-600 hover:bg-green-700 rounded-lg font-bold text-white disabled:opacity-50 flex items-center justify-center gap-2"
                    title="Permite pagar con múltiples métodos (efectivo + tarjeta + transferencia)"
                  >
                    {prepareTableBills.isPending ? (
                      <>
                        <Loader2 size={16} className="animate-spin" />
                        Preparando...
                      </>
                    ) : (
                      <>
                        <CheckCircle2 size={16} />
                        Cobrar {formatPrice(grandTotal)}
                      </>
                    )}
                  </button>
                </div>
              </>
            ) : (
              /* Si hay bills, mostrar resumen de pendientes */
              <div className="bg-orange-900/20 border border-orange-700/40 rounded-lg p-4">
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <Scissors size={18} className="text-orange-400" />
                    <span className="font-semibold text-orange-300">
                      Cuenta dividida
                    </span>
                  </div>
                  <span className="text-sm text-slate-400">
                    {pendingBillsCount} sub-cuenta{pendingBillsCount !== 1 ? "s" : ""} pendiente{pendingBillsCount !== 1 ? "s" : ""}
                  </span>
                </div>
                <p className="text-xs text-slate-400 mt-1">
                  Expande cada pedido arriba y cobra sus sub-cuentas individualmente.
                </p>
              </div>
            )}
          </div>
        </div>
      </div>

      {/* Toast de éxito */}
      {showSuccessToast && (
        <div className="fixed top-4 right-4 z-[100] bg-green-500 text-white px-4 py-3 rounded-lg shadow-lg flex items-center gap-2 animate-in slide-in-from-top-2">
          <CheckCircle2 size={18} />
          <span className="font-medium">{successMessage}</span>
        </div>
      )}

      {/* Modal de Split */}
      {splittingOrder && (
        <SplitBillModal
          orderUuid={splittingOrder.uuid}
          orderNumber={splittingOrder.order_number}
          orderTotal={splittingOrder.total}
          orderSubtotal={splittingOrder.subtotal}
          orderItems={splittingOrder.items}
          isOpen={showSplitModal}
          onClose={() => {
            setShowSplitModal(false);
            setSplittingOrder(null);
          }}
          onSuccess={handleSplitSuccess}
        />
      )}

      {/* Modal de cobro de bill individual */}
      {payingBill && (
        <BillPaymentModalV2
          bill={payingBill}
          isOpen={!!payingBill}
          onClose={() => setPayingBill(null)}
          onSuccess={() => {
            setPayingBill(null);
            setSuccessMessage("✅ Sub-cuenta cobrada correctamente");
            setShowSuccessToast(true);
          }}
        />
      )}

      {/* Modal de pagos divididos (nuevo diseño POS) */}
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
    </>
  );
}