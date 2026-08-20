import { useState, useMemo, useEffect } from "react";
import type { Bill } from "@/types/bills";
import {
  useTablesWithBills,
  usePrepareTableBills,
} from "@/hooks/usePayments";
import { formatPrice } from "@/types/catalog";
import {
  X,
  Loader2,
  CheckCircle2,
  Receipt,
  Printer,
  AlertTriangle,
} from "lucide-react";
import { BillPaymentModalV2 } from "./BillPaymentModalV2";
import { PrintablePrecuenta } from "./PrintablePrecuenta";

interface TableBillModalProps {
  tableUuid: string;
  isOpen: boolean;
  onClose: () => void;
  onSuccess: () => void;
}

export function TableBillModal({
  tableUuid,
  isOpen,
  onClose,
  onSuccess,
}: TableBillModalProps) {
  const { data: tablesWithBills = [] } = useTablesWithBills();
  const tableBill = useMemo(
    () => tablesWithBills.find((t) => t.table_uuid === tableUuid),
    [tablesWithBills, tableUuid]
  );

  const [payingBills, setPayingBills] = useState<Bill[] | null>(null);
  const [showSuccessToast, setShowSuccessToast] = useState(false);
  const [successMessage, setSuccessMessage] = useState("");
  const [showPrintWarning, setShowPrintWarning] = useState(false);
  const [showUnservedWarning, setShowUnservedWarning] = useState(false);

  const prepareTableBills = usePrepareTableBills();

  // Estado de impresión (persistido en sessionStorage)
  const storageKey = `printed_${tableUuid}`;
  const [isPrinted, setIsPrinted] = useState(() => {
    try {
      return sessionStorage.getItem(storageKey) === "true";
    } catch {
      return false;
    }
  });

  // Resetear estado de impresión cuando cambia la mesa o se abre
  useEffect(() => {
    if (isOpen && tableBill) {
      // Si la mesa cambió o hay nuevos pedidos, resetear
      const currentKey = `printed_${tableUuid}`;
      if (currentKey !== storageKey) {
        setIsPrinted(false);
      }
    }
  }, [isOpen, tableUuid, tableBill]);

  useEffect(() => {
    if (showSuccessToast) {
      const timer = setTimeout(() => setShowSuccessToast(false), 3000);
      return () => clearTimeout(timer);
    }
  }, [showSuccessToast]);

  // Items agregados de todos los pedidos
  const aggregatedItems = useMemo(() => {
    if (!tableBill) return [];
    const map = new Map<string, { name: string; quantity: number; unitPrice: number; subtotal: number }>();
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
  }, [tableBill]);

  // Imprimir precuenta
  const handlePrint = () => {
    try {
      setIsPrinted(true);
      sessionStorage.setItem(storageKey, "true");
      // Pequeño delay para que React renderice antes de imprimir
      setTimeout(() => {
        window.print();
      }, 100);
    } catch (e) {
      console.error("Error al marcar como impreso:", e);
      window.print();
    }
  };

  // Click en Cobrar: verifica no servidos, luego impresión
  const handleChargeClick = () => {
    if (!tableBill || prepareTableBills.isPending) return;

    // Prioridad 1: advertir si hay productos sin servir
    if (tableBill.has_unserved_orders) {
      setShowUnservedWarning(true);
      return;
    }

    // Prioridad 2: advertir si no se ha impreso
    if (!isPrinted) {
      setShowPrintWarning(true);
      return;
    }

    proceedToPayment();
  };

  // Continuar después de advertencia de no servidos (verifica impresión)
  const handleContinueAfterUnserved = () => {
    setShowUnservedWarning(false);
    if (!tableBill) return;
    if (!isPrinted) {
      setShowPrintWarning(true);
      return;
    }
    proceedToPayment();
  };

  // Continuar al modal de pago (después de verificar impresión)
  const proceedToPayment = async () => {
    if (!tableBill) return;
    setShowPrintWarning(false);

    try {
      const result = await prepareTableBills.mutateAsync(tableBill.table_uuid);
      setPayingBills(result.bills);
    } catch (e) {
      console.error("Error preparando bills:", e);
      alert("No se pudieron preparar las sub-cuentas. Intenta de nuevo.");
    }
  };

  // Imprimir desde el modal de advertencia
  const handlePrintFromWarning = () => {
    setShowPrintWarning(false);
    handlePrint();
  };

  // Continuar sin imprimir
  const handleContinueWithoutPrint = () => {
    setShowPrintWarning(false);
    proceedToPayment();
  };

  useEffect(() => {
    if (isOpen && tableBill === undefined) {
      onClose();
      onSuccess();
    }
  }, [isOpen, tableBill, onClose, onSuccess]);

  if (!isOpen || !tableBill) return null;

  const totalAmount = tableBill.total_amount;

  return (
    <>
      <div className="fixed inset-0 bg-black/80 z-50" onClick={onClose} />
      <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div
          className="bg-slate-900 rounded-xl shadow-2xl max-w-lg w-full max-h-[90vh] flex flex-col"
          onClick={(e) => e.stopPropagation()}
        >
          {/* Header */}
          <div className="flex items-center justify-between p-5 border-b border-slate-700 flex-shrink-0">
            <div>
              <h2 className="text-xl font-bold flex items-center gap-2">
                <Receipt size={20} />
                Consumo de la Mesa
              </h2>
              <p className="text-sm text-slate-400 mt-0.5">
                Mesa {tableBill.table_number} · {tableBill.orders_count} pedido{tableBill.orders_count !== 1 ? "s" : ""} · {tableBill.total_items} ítem{tableBill.total_items !== 1 ? "s" : ""}
              </p>
            </div>
            <button
              onClick={onClose}
              disabled={prepareTableBills.isPending}
              className="p-2 hover:bg-slate-800 rounded-lg disabled:opacity-50"
            >
              <X size={20} />
            </button>
          </div>

          {/* Detalle de items (scrollable) */}
          <div className="flex-1 overflow-y-auto p-5 space-y-3">
            {/* Lista de items */}
            <div className="bg-slate-800/50 rounded-lg p-4">
              <h3 className="text-sm font-semibold text-slate-400 mb-3 flex items-center justify-between">
                <span>Detalle del Consumo</span>
                <span className="text-xs font-normal">{aggregatedItems.length} productos</span>
              </h3>
              <div className="space-y-1.5 max-h-[40vh] overflow-y-auto">
                {aggregatedItems.map((item) => (
                  <div
                    key={item.name}
                    className="flex items-center justify-between py-1.5 border-b border-slate-700/30 last:border-b-0"
                  >
                    <div className="flex items-center gap-2 flex-1 min-w-0">
                      <span className="bg-orange-500/20 text-orange-300 text-xs font-bold px-2 py-0.5 rounded">
                        {item.quantity}×
                      </span>
                      <span className="text-slate-200 text-sm truncate">{item.name}</span>
                    </div>
                    <span className="text-white font-medium text-sm ml-3">
                      {formatPrice(item.subtotal)}
                    </span>
                  </div>
                ))}
              </div>
            </div>

            {/* Totales */}
            <div className="bg-slate-800/50 rounded-lg p-4 space-y-2">
              <div className="flex justify-between text-sm">
                <span className="text-slate-400">Subtotal</span>
                <span className="text-white">{formatPrice(tableBill.subtotal)}</span>
              </div>
              <div className="flex justify-between text-sm">
                <span className="text-slate-400">IVA (19%)</span>
                <span className="text-white">{formatPrice(tableBill.tax_amount)}</span>
              </div>
              <div className="flex justify-between text-2xl font-bold pt-3 border-t border-slate-700">
                <span className="text-slate-200">Total</span>
                <span className="text-orange-400">{formatPrice(totalAmount)}</span>
              </div>
            </div>

            {/* Indicador de productos sin servir */}
            {tableBill.has_unserved_orders && (
              <div className="flex items-center gap-2 px-3 py-2 rounded-lg text-xs bg-red-900/20 border border-red-700/50 text-red-300">
                <AlertTriangle size={14} />
                <span>
                  <strong>{tableBill.unserved_items_count}</strong> producto{tableBill.unserved_items_count !== 1 ? "s" : ""} sin servir
                  {" "}({tableBill.unserved_orders_count} pedido{tableBill.unserved_orders_count !== 1 ? "s" : ""} en preparación)
                </span>
              </div>
            )}

            {/* Indicador de impresión */}
            <div className={`flex items-center gap-2 px-3 py-2 rounded-lg text-xs ${
              isPrinted
                ? "bg-green-900/20 border border-green-700/50 text-green-300"
                : "bg-amber-900/20 border border-amber-700/50 text-amber-300"
            }`}>
              {isPrinted ? (
                <>
                  <CheckCircle2 size={14} />
                  Cuenta impresa
                </>
              ) : (
                <>
                  <AlertTriangle size={14} />
                  Cuenta pendiente de impresión
                </>
              )}
            </div>
          </div>

          {/* Botones */}
          <div className="border-t border-slate-700 p-4 space-y-2 flex-shrink-0">
            <button
              onClick={handlePrint}
              className="w-full px-4 py-2.5 bg-blue-600 hover:bg-blue-700 rounded-lg font-medium text-white flex items-center justify-center gap-2"
            >
              <Printer size={16} />
              {isPrinted ? "Reimprimir Cuenta" : "Imprimir Cuenta"}
            </button>
            <div className="flex gap-2">
              <button
                onClick={onClose}
                disabled={prepareTableBills.isPending}
                className="flex-1 px-4 py-3 bg-slate-700 hover:bg-slate-600 rounded-lg font-medium disabled:opacity-50"
              >
                Cancelar
              </button>
              <button
                onClick={handleChargeClick}
                disabled={!tableBill || prepareTableBills.isPending}
                className="flex-1 px-4 py-3 bg-green-600 hover:bg-green-700 rounded-lg font-bold text-white disabled:opacity-50 flex items-center justify-center gap-2"
              >
                {prepareTableBills.isPending ? (
                  <>
                    <Loader2 size={16} className="animate-spin" />
                    Preparando...
                  </>
                ) : (
                  <>
                    <CheckCircle2 size={16} />
                    Cobrar {formatPrice(totalAmount)}
                  </>
                )}
              </button>
            </div>
          </div>
        </div>
      </div>

      {/* Modal de advertencia: productos sin servir */}
      {showUnservedWarning && tableBill && (
        <>
          <div className="fixed inset-0 bg-black/70 z-[60]" />
          <div className="fixed inset-0 z-[61] flex items-center justify-center p-4">
            <div className="bg-slate-900 rounded-xl shadow-2xl max-w-sm w-full border-2 border-red-500/50">
              <div className="p-5">
                <div className="flex items-center gap-3 mb-3">
                  <div className="w-12 h-12 bg-red-500/20 rounded-full flex items-center justify-center flex-shrink-0">
                    <AlertTriangle size={24} className="text-red-400" />
                  </div>
                  <div>
                    <h3 className="text-lg font-bold text-white">Productos sin servir</h3>
                    <p className="text-sm text-slate-400">
                      Hay {tableBill.unserved_items_count} producto{tableBill.unserved_items_count !== 1 ? "s" : ""} que aún {tableBill.unserved_items_count !== 1 ? "están" : "está"} en preparación.
                    </p>
                  </div>
                </div>
                <div className="bg-red-900/20 border border-red-700/50 rounded-lg p-3 space-y-2">
                  <p className="text-sm text-slate-200">
                    <strong>{tableBill.unserved_orders_count}</strong> pedido{tableBill.unserved_orders_count !== 1 ? "s" : ""} {tableBill.unserved_orders_count !== 1 ? "están" : "está"} en estados:
                  </p>
                  <ul className="text-xs text-slate-300 space-y-1 pl-4">
                    {tableBill.orders
                      .filter((o) => o.status !== "served")
                      .map((o) => (
                        <li key={o.uuid}>
                          <strong>#{o.order_number}</strong> ·{" "}
                          {o.status === "confirmed" && "Confirmado"}
                          {o.status === "preparing" && "En preparación"}
                          {o.status === "ready" && "Listo"}
                          {" "}({o.items.length} ítem{o.items.length !== 1 ? "s" : ""})
                        </li>
                      ))}
                  </ul>
                  <p className="text-sm text-slate-300 pt-2 border-t border-red-700/30">
                    Si continúas, se cobrarán <strong>todos</strong> los productos, incluyendo los que aún no han llegado a la mesa.
                  </p>
                </div>
              </div>
              <div className="border-t border-slate-700 p-4 space-y-2">
                <button
                  onClick={() => setShowUnservedWarning(false)}
                  className="w-full px-4 py-2.5 bg-amber-600 hover:bg-amber-700 rounded-lg font-medium text-white flex items-center justify-center gap-2"
                >
                  ⏸️ Esperar a que se sirvan
                </button>
                <button
                  onClick={handleContinueAfterUnserved}
                  className="w-full px-4 py-2.5 bg-green-600 hover:bg-green-700 rounded-lg font-medium text-white flex items-center justify-center gap-2"
                >
                  <CheckCircle2 size={16} />
                  Cobrar igual
                </button>
                <button
                  onClick={() => setShowUnservedWarning(false)}
                  className="w-full px-4 py-2.5 bg-slate-700 hover:bg-slate-600 rounded-lg font-medium text-slate-300"
                >
                  Cancelar
                </button>
              </div>
            </div>
          </div>
        </>
      )}

      {/* Modal de advertencia: cuenta no impresa */}
      {showPrintWarning && (
        <>
          <div className="fixed inset-0 bg-black/70 z-[60]" />
          <div className="fixed inset-0 z-[61] flex items-center justify-center p-4">
            <div className="bg-slate-900 rounded-xl shadow-2xl max-w-sm w-full border-2 border-amber-500/50">
              <div className="p-5">
                <div className="flex items-center gap-3 mb-3">
                  <div className="w-12 h-12 bg-amber-500/20 rounded-full flex items-center justify-center flex-shrink-0">
                    <AlertTriangle size={24} className="text-amber-400" />
                  </div>
                  <div>
                    <h3 className="text-lg font-bold text-white">Cuenta no impresa</h3>
                    <p className="text-sm text-slate-400">
                      La cuenta no ha sido impresa todavía.
                    </p>
                  </div>
                </div>
                <p className="text-sm text-slate-300 bg-slate-800/50 rounded-lg p-3">
                  ¿Qué deseas hacer?
                </p>
              </div>
              <div className="border-t border-slate-700 p-4 space-y-2">
                <button
                  onClick={handlePrintFromWarning}
                  className="w-full px-4 py-2.5 bg-blue-600 hover:bg-blue-700 rounded-lg font-medium text-white flex items-center justify-center gap-2"
                >
                  <Printer size={16} />
                  Imprimir Ahora
                </button>
                <button
                  onClick={handleContinueWithoutPrint}
                  className="w-full px-4 py-2.5 bg-green-600 hover:bg-green-700 rounded-lg font-medium text-white flex items-center justify-center gap-2"
                >
                  <CheckCircle2 size={16} />
                  Continuar sin Imprimir
                </button>
                <button
                  onClick={() => setShowPrintWarning(false)}
                  className="w-full px-4 py-2.5 bg-slate-700 hover:bg-slate-600 rounded-lg font-medium text-slate-300"
                >
                  Cancelar
                </button>
              </div>
            </div>
          </div>
        </>
      )}

      {/* Modal de pago */}
      <BillPaymentModalV2
        bills={payingBills}
        isOpen={payingBills !== null}
        onClose={() => setPayingBills(null)}
        onSuccess={() => {
          setPayingBills(null);
          setSuccessMessage("✅ Mesa cobrada correctamente");
          setShowSuccessToast(true);
          // Limpiar estado de impresión al cobrar exitosamente
          try {
            sessionStorage.removeItem(storageKey);
          } catch {}
          onSuccess();
        }}
      />

      {/* Componente oculto para impresión */}
      <PrintablePrecuenta tableBill={tableBill} />

      {/* Toast de éxito */}
      {showSuccessToast && (
        <div className="fixed bottom-6 right-6 bg-green-600 text-white px-5 py-3 rounded-lg shadow-xl z-[60] flex items-center gap-2">
          <CheckCircle2 size={18} />
          {successMessage}
        </div>
      )}
    </>
  );
}
