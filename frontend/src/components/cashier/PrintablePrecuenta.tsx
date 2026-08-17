import type { TableBill } from "@/types/tableBill";
import { formatPrice } from "@/types/catalog";
import { useMemo } from "react";

interface PrintablePrecuentaProps {
  tableBill: TableBill;
}

/**
 * Componente de impresión de precuenta (formato ticket 80mm).
 * Usa window.print() con estilos @media print para compatibilidad
 * con cualquier impresora instalada (térmica, láser, etc.)
 */
export function PrintablePrecuenta({ tableBill }: PrintablePrecuentaProps) {
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

  const formatDate = (isoString: string | null) => {
    if (!isoString) return "-";
    return new Date(isoString).toLocaleString("es-CL", {
      day: "2-digit",
      month: "2-digit",
      year: "numeric",
      hour: "2-digit",
      minute: "2-digit",
    });
  };

  return (
    <>
      {/* Estilos específicos para impresión */}
      <style>{`
        @media print {
          body * {
            visibility: hidden !important;
          }
          #printable-precuenta, #printable-precuenta * {
            visibility: visible !important;
          }
          #printable-precuenta {
            position: absolute !important;
            left: 0 !important;
            top: 0 !important;
            width: 80mm !important;
            padding: 5mm !important;
            background: white !important;
            color: black !important;
            font-family: 'Courier New', monospace !important;
            font-size: 11px !important;
          }
          .no-print {
            display: none !important;
          }
          @page {
            margin: 0;
            size: 80mm auto;
          }
        }
      `}</style>

      <div id="printable-precuenta" className="bg-white text-black p-6 font-mono text-sm">
        {/* Header */}
        <div className="text-center border-b-2 border-black pb-2 mb-2">
          <div className="text-lg font-bold">WOK & MESA</div>
          <div className="text-xs">Restaurant Asiático</div>
          <div className="text-xs mt-1">
            {formatDate(new Date().toISOString())}
          </div>
        </div>

        {/* Info de mesa */}
        <div className="border-b border-dashed border-gray-400 pb-2 mb-2">
          <div className="flex justify-between">
            <span>Mesa:</span>
            <span className="font-bold">{tableBill.table_number}</span>
          </div>
          <div className="flex justify-between">
            <span>Área:</span>
            <span>{tableBill.area_code}</span>
          </div>
          <div className="flex justify-between">
            <span>Pedidos:</span>
            <span>{tableBill.orders_count}</span>
          </div>
          <div className="text-center text-xs mt-1 font-bold">
            *** PRECUENTA ***
          </div>
        </div>

        {/* Items */}
        <div className="border-b border-dashed border-gray-400 pb-2 mb-2">
          <div className="text-xs font-bold mb-1 flex justify-between">
            <span>CANT</span>
            <span>DESCRIPCIÓN</span>
            <span>TOTAL</span>
          </div>
          {aggregatedItems.map((item) => (
            <div key={item.name} className="mb-1">
              <div className="flex justify-between text-xs">
                <span>{item.quantity}x</span>
                <span className="flex-1 mx-2 truncate">{item.name}</span>
                <span>{formatPrice(item.subtotal)}</span>
              </div>
            </div>
          ))}
        </div>

        {/* Totales */}
        <div className="space-y-1 mb-2">
          <div className="flex justify-between text-xs">
            <span>Subtotal:</span>
            <span>{formatPrice(tableBill.subtotal)}</span>
          </div>
          <div className="flex justify-between text-xs">
            <span>IVA (19%):</span>
            <span>{formatPrice(tableBill.tax_amount)}</span>
          </div>
          <div className="flex justify-between font-bold border-t-2 border-black pt-1 mt-1">
            <span>TOTAL:</span>
            <span>{formatPrice(tableBill.total_amount)}</span>
          </div>
        </div>

        {/* Footer */}
        <div className="text-center border-t border-dashed border-gray-400 pt-2 text-xs">
          <div>Items: {tableBill.total_items}</div>
          <div className="mt-1">¡Gracias por su preferencia!</div>
          <div className="mt-1">www.wokmesa.cl</div>
        </div>
      </div>
    </>
  );
}
