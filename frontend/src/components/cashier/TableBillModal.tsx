import { useToastStore } from '@/store/useToastStore';
import { useOfflinePrintJob } from '@/hooks/useOfflinePrintJob';
import { formatPrecuenta, type PrecuentaData } from '@/services/printing/ticketFormatters';
import { useTranslation, Trans } from 'react-i18next';
import { useState, useMemo, useEffect } from "react";
import type { Bill } from "@/types/bills";
import {
  useTablesWithBills,
  usePrepareTableBills,
} from "@/hooks/usePayments";
import { formatPrice } from "@/types/catalog";
import { IVA_PERCENTAGE } from "@/config/tax";
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
  const { t } = useTranslation();
  const { data: tablesWithBills = [] } = useTablesWithBills();
  const tableBill = useMemo(
    () => tablesWithBills.find((t) => t.table_uuid === tableUuid),
    [tablesWithBills, tableUuid]
  );

  const [payingBills, setPayingBills] = useState<Bill[] | null>(null);
  
  
  const [showPrintWarning, setShowPrintWarning] = useState(false);
  const [showUnservedWarning, setShowUnservedWarning] = useState(false);

  const prepareTableBills = usePrepareTableBills();
  const { enqueueReceipt } = useOfflinePrintJob();

  // Estado de impresión (persistido en sessionStorage)
  const storageKey = `printed_${tableUuid}`;
  const versionKey = `printed_version_${tableUuid}`;
  
  // Versión actual: string de UUIDs de items para detectar cambios en los pedidos
  const currentVersion = tableBill 
    ? JSON.stringify(tableBill.orders.flatMap((o: any) => o.items.map((item: any) => item.uuid)).sort())
    : '';
  const [isPrinted, setIsPrinted] = useState(() => {
    try {
      const savedVersion = sessionStorage.getItem(versionKey);
      return sessionStorage.getItem(storageKey) === "true" && savedVersion === currentVersion;
    } catch {
      return false;
    }
  });

  // Resetear estado de impresión si cambian los pedidos (nuevos items, modificaciones)
  useEffect(() => {
    if (isOpen && tableBill) {
      const savedVersion = sessionStorage.getItem(versionKey);
      if (savedVersion !== currentVersion) {
        setIsPrinted(false);
      }
    }
  }, [isOpen, tableUuid, currentVersion]);



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

  // Imprimir precuenta usando el motor ESC/POS unificado
  const handlePrint = async () => {
    if (!tableBill) return;

    try {
      const precuentaData: PrecuentaData = {
        tableNumber: tableBill.table_number,
        areaCode: tableBill.area_code,
        ordersCount: tableBill.orders_count,
        items: aggregatedItems.map(item => ({
          name: item.name,
          quantity: item.quantity,
          unitPrice: item.unitPrice,
          subtotal: item.subtotal,
        })),
        subtotal: tableBill.subtotal,
        taxTotal: tableBill.tax_amount,
        grandTotal: tableBill.total_amount,
        totalItems: tableBill.total_items,
        createdAt: new Date(),
      };

      const builder = formatPrecuenta(precuentaData);
      const escposBase64 = builder.buildBase64();

      await enqueueReceipt.mutateAsync({
        entity_uuid: tableBill.table_uuid,
        entity_type: "table_bill",
        payload: precuentaData,
        escpos_base64: escposBase64,
        printer_name: "receipt-printer",
        reference_number: t("table_bill.precuenta_reference") + " " + tableBill.table_number,
      });

      sessionStorage.setItem(storageKey, "true");
      sessionStorage.setItem(versionKey, currentVersion);
      setIsPrinted(true);
      
      useToastStore.getState().addToast('success', 'Precuenta enviada a impresion');
    } catch (e: any) {
      console.error("Error al encolar impresion:", e);
      useToastStore.getState().addToast('error', 'Error al enviar a impresion');
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
      useToastStore.getState().addToast("warning", "$1");
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
                {t("cashier.table_consumption")}
              </h2>
              <p className="text-sm text-slate-400 mt-0.5">
                {t("cashier.table")} {tableBill.table_number} · {tableBill.orders_count} {t("cashier.orders")} · {tableBill.total_items} {t("cashier.items")}
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
                <span>{t("table_bill.consumption_detail")}</span>
                <span className="text-xs font-normal">{aggregatedItems.length} {t("table_bill.products_count")}</span>
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
                <span className="text-slate-400">{t("table_bill.subtotal")}</span>
                <span className="text-white">{formatPrice(tableBill.subtotal)}</span>
              </div>
              <div className="flex justify-between text-sm">
                <span className="text-slate-400">IVA ({IVA_PERCENTAGE}%)</span>
                <span className="text-white">{formatPrice(tableBill.tax_amount)}</span>
              </div>
              <div className="flex justify-between text-2xl font-bold pt-3 border-t border-slate-700">
                <span className="text-slate-200">{t("orders.total")}</span>
                <span className="text-orange-400">{formatPrice(totalAmount)}</span>
              </div>
            </div>

            {/* Indicador de productos sin servir */}
            {tableBill.has_unserved_orders && (
              <div className="flex items-center gap-2 px-3 py-2 rounded-lg text-xs bg-red-900/20 border border-red-700/50 text-red-300">
                <AlertTriangle size={14} />
                <span>
                  <strong>{tableBill.unserved_items_count}</strong> {t("table_bill.unserved_items_indicator", { count: "", plural: tableBill.unserved_items_count !== 1 ? "s" : "" }).replace("{count}", "")}
                  {" "}{t("table_bill.preparing_orders_indicator", { count: tableBill.unserved_orders_count, plural: tableBill.unserved_orders_count !== 1 ? "s" : "" })}
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
                  {t("table_bill.bill_printed")}
                </>
              ) : (
                <>
                  <AlertTriangle size={14} />
                  {t("table_bill.bill_pending_print")}
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
              {isPrinted ? t("table_bill.reprint_bill") : t("table_bill.print_bill")}
            </button>
            <div className="flex gap-2">
              <button
                onClick={onClose}
                disabled={prepareTableBills.isPending}
                className="flex-1 px-4 py-3 bg-slate-700 hover:bg-slate-600 rounded-lg font-medium disabled:opacity-50"
              >
                {t("common.cancel")}
              </button>
              <button
                onClick={handleChargeClick}
                disabled={!tableBill || prepareTableBills.isPending}
                className="flex-1 px-4 py-3 bg-green-600 hover:bg-green-700 rounded-lg font-bold text-white disabled:opacity-50 flex items-center justify-center gap-2"
              >
                {prepareTableBills.isPending ? (
                  <>
                    <Loader2 size={16} className="animate-spin" />
                    {t("table_bill.preparing")}
                  </>
                ) : (
                  <>
                    <CheckCircle2 size={16} />
                    {t("table_bill.charge_amount")} {formatPrice(totalAmount)}
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
                    <h3 className="text-lg font-bold text-white">{t("table_bill.products_not_served")}</h3>
                    <p className="text-sm text-slate-400">
                      {t("table_bill.unserved_items_message", { count: tableBill.unserved_items_count, plural: tableBill.unserved_items_count !== 1 ? "s" : "", verb: tableBill.unserved_items_count !== 1 ? "están" : "está" })}
                    </p>
                  </div>
                </div>
                <div className="bg-red-900/20 border border-red-700/50 rounded-lg p-3 space-y-2">
                  <p className="text-sm text-slate-200">
                    {t("table_bill.unserved_orders_message", { count: tableBill.unserved_orders_count, plural: tableBill.unserved_orders_count !== 1 ? "s" : "", verb: tableBill.unserved_orders_count !== 1 ? "están" : "está" })}
                  </p>
                  <ul className="text-xs text-slate-300 space-y-1 pl-4">
                    {tableBill.orders
                      .filter((o) => o.status !== "served")
                      .map((o) => (
                        <li key={o.uuid}>
                          <strong>#{o.order_number}</strong> ·{" "}
                          {o.status === "confirmed" && t("table_bill.status_confirmed")}
                          {o.status === "preparing" && t("table_bill.status_preparing")}
                          {o.status === "ready" && t("table_bill.status_ready")}
                          {" "}({t("table_bill.items_count_label", { count: o.items.length, plural: o.items.length !== 1 ? "s" : "" })})
                        </li>
                      ))}
                  </ul>
                  <p className="text-sm text-slate-300 pt-2 border-t border-red-700/30">
                    <Trans i18nKey="table_bill.unserved_warning_full" components={{ strong: <strong /> }} />
                  </p>
                </div>
              </div>
              <div className="border-t border-slate-700 p-4 space-y-2">
                <button
                  onClick={() => setShowUnservedWarning(false)}
                  className="w-full px-4 py-2.5 bg-amber-600 hover:bg-amber-700 rounded-lg font-medium text-white flex items-center justify-center gap-2"
                >
                  {t("table_bill.wait_to_serve")}
                </button>
                <button
                  onClick={handleContinueAfterUnserved}
                  className="w-full px-4 py-2.5 bg-green-600 hover:bg-green-700 rounded-lg font-medium text-white flex items-center justify-center gap-2"
                >
                  <CheckCircle2 size={16} />
                  {t("table_bill.charge_anyway")}
                </button>
                <button
                  onClick={() => setShowUnservedWarning(false)}
                  className="w-full px-4 py-2.5 bg-slate-700 hover:bg-slate-600 rounded-lg font-medium text-slate-300"
                >
                  {t("common.cancel")}
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
                    <h3 className="text-lg font-bold text-white">{t("table_bill.bill_not_printed")}</h3>
                    <p className="text-sm text-slate-400">
                      {t("table_bill.bill_not_printed_message")}
                    </p>
                  </div>
                </div>
                <p className="text-sm text-slate-300 bg-slate-800/50 rounded-lg p-3">
                  {t("table_bill.what_do_you_want")}
                </p>
              </div>
              <div className="border-t border-slate-700 p-4 space-y-2">
                <button
                  onClick={handlePrintFromWarning}
                  className="w-full px-4 py-2.5 bg-blue-600 hover:bg-blue-700 rounded-lg font-medium text-white flex items-center justify-center gap-2"
                >
                  <Printer size={16} />
                  {t("table_bill.print_now")}
                </button>
                <button
                  onClick={handleContinueWithoutPrint}
                  className="w-full px-4 py-2.5 bg-green-600 hover:bg-green-700 rounded-lg font-medium text-white flex items-center justify-center gap-2"
                >
                  <CheckCircle2 size={16} />
                  {t("table_bill.continue_without_print")}
                </button>
                <button
                  onClick={() => setShowPrintWarning(false)}
                  className="w-full px-4 py-2.5 bg-slate-700 hover:bg-slate-600 rounded-lg font-medium text-slate-300"
                >
                  {t("common.cancel")}
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
          useToastStore.getState().addToast('success', t("table_bill.table_paid_success"));
          // Limpiar estado de impresión al cobrar exitosamente
          try {
            sessionStorage.removeItem(storageKey);
          } catch {}
          onSuccess();
        }}
      />

      {/* Componente oculto para impresión */}
      <PrintablePrecuenta tableBill={tableBill} />


    </>
  );
}
