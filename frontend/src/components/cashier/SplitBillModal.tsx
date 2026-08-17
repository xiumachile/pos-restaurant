import { useState, useMemo } from "react";
import type { Bill } from "@/types/bills";
import { useSplitOrder } from "@/hooks/usePayments";
import { formatPrice } from "@/types/catalog";
import {
  X,
  Loader2,
  AlertCircle,
  Users,
  ListOrdered,
  DollarSign,
  Plus,
  Trash2,
} from "lucide-react";

interface OrderItem {
  uuid: string;
  id?: number;
  name: string;
  quantity: number;
  unit_price: number;
  subtotal: number;
}

interface SplitBillModalProps {
  orderUuid: string;
  orderNumber: string;
  orderTotal: number;
  orderSubtotal: number;
  orderItems: OrderItem[];
  isOpen: boolean;
  onClose: () => void;
  onSuccess: (bills: Bill[]) => void;
}

type SplitMode = "equal" | "items" | "custom";

export function SplitBillModal({
  orderUuid,
  orderNumber,
  orderTotal,
  orderSubtotal,
  orderItems,
  isOpen,
  onClose,
  onSuccess,
}: SplitBillModalProps) {
  const [mode, setMode] = useState<SplitMode>("equal");

  // Equal split
  const [parts, setParts] = useState<number>(2);

  // By items
  const [itemGroups, setItemGroups] = useState<Record<string, number>>({});
  const [groupCount, setGroupCount] = useState(2);

  // Custom amounts
  const [customAmounts, setCustomAmounts] = useState<number[]>([0, 0]);

  const splitOrder = useSplitOrder();

  const amountPerPart = useMemo(() => orderTotal / parts, [orderTotal, parts]);

  const customAmountsTotal = useMemo(
    () => customAmounts.reduce((sum, a) => sum + (a || 0), 0),
    [customAmounts]
  );

  // Tolerancia de $1 por redondeo (igual que backend)
  const customAmountsValid = Math.abs(customAmountsTotal - orderTotal) <= 1;

  // Calcular totales por grupo en "by items" (misma lógica que backend)
  const groupTotals = useMemo(() => {
    // Primero calcular el total de subtotales agrupados
    let totalGroupedSubtotal = 0;
    for (let g = 0; g < groupCount; g++) {
      for (const item of orderItems) {
        const groupId = itemGroups[item.uuid];
        if (groupId === g) {
          totalGroupedSubtotal += item.subtotal;
        }
      }
    }

    if (totalGroupedSubtotal === 0) {
      return Array(groupCount).fill(0);
    }

    // Ahora calcular cada grupo con prorrateo
    const totals: number[] = [];
    const orderTax = orderTotal - orderSubtotal;
    
    for (let g = 0; g < groupCount; g++) {
      let groupSubtotal = 0;
      for (const item of orderItems) {
        const groupId = itemGroups[item.uuid];
        if (groupId === g) {
          groupSubtotal += item.subtotal;
        }
      }
      
      // Prorratear impuesto según proporción del subtotal (igual que backend)
      const ratio = groupSubtotal / totalGroupedSubtotal;
      const groupTax = Math.round(orderTax * ratio * 100) / 100;
      const groupTotal = Math.round((groupSubtotal + groupTax) * 100) / 100;
      
      totals.push(groupTotal);
    }
    
    return totals;
  }, [orderItems, itemGroups, groupCount, orderSubtotal, orderTotal]);

  const handleSplit = async () => {
    try {
      let payload: any;

      if (mode === "equal") {
        payload = { type: "equal_split", parts };
      } else if (mode === "items") {
        const groups = Array.from({ length: groupCount }, (_, i) => ({
          item_ids: orderItems
            .filter((item) => itemGroups[item.uuid] === i && item.id)
            .map((item) => item.id!),
          guest_count: 1,
        }));

        // Validar que todos los items estén asignados
        const allAssigned = orderItems.every(
          (item) => itemGroups[item.uuid] !== undefined
        );
        if (!allAssigned) {
          alert("Debes asignar todos los productos a un grupo");
          return;
        }

        payload = { type: "by_items", groups };
      } else {
        // Normalizar y redondear montos para evitar errores de precisión
        const normalizedAmounts = customAmounts.map((a) =>
          Math.round((parseFloat(String(a)) || 0) * 100) / 100
        );
        
        console.log("🔍 Custom amounts payload:", {
          raw: customAmounts,
          normalized: normalizedAmounts,
          sum: normalizedAmounts.reduce((a, b) => a + b, 0),
          orderTotal,
        });
        
        payload = { type: "custom_amount", amounts: normalizedAmounts };
      }

      const bills = await splitOrder.mutateAsync({
        orderUuid,
        payload,
      });

      onSuccess(bills);
      onClose();
    } catch (e: any) {
      console.error("❌ ERROR COMPLETO:", e);
      console.error("❌ ERROR RESPONSE:", e?.response?.data);
      console.error("❌ ERROR STATUS:", e?.response?.status);
      console.error("❌ ERROR MESSAGE:", e?.message);
      alert("Error al dividir: " + (e?.response?.data?.message || e?.message || "Error desconocido"));
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
          <div className="sticky top-0 bg-slate-900 flex items-center justify-between p-5 border-b border-slate-700">
            <div>
              <h2 className="text-xl font-bold">Dividir Cuenta</h2>
              <p className="text-sm text-slate-400">
                {orderNumber} · Total: {formatPrice(orderTotal)}
              </p>
            </div>
            <button onClick={onClose} className="p-2 hover:bg-slate-800 rounded-lg">
              <X size={20} />
            </button>
          </div>

          <div className="p-6 space-y-5">
            {/* Selector de modo */}
            <div className="grid grid-cols-3 gap-2">
              <button
                onClick={() => setMode("equal")}
                className={`p-3 rounded-lg border-2 transition-all flex flex-col items-center gap-1 ${
                  mode === "equal"
                    ? "border-orange-500 bg-orange-500/10"
                    : "border-slate-700 bg-slate-800 hover:border-slate-600"
                }`}
              >
                <Users size={20} />
                <span className="font-semibold text-sm">Equitativa</span>
                <span className="text-xs text-slate-400">Partes iguales</span>
              </button>
              <button
                onClick={() => setMode("items")}
                className={`p-3 rounded-lg border-2 transition-all flex flex-col items-center gap-1 ${
                  mode === "items"
                    ? "border-orange-500 bg-orange-500/10"
                    : "border-slate-700 bg-slate-800 hover:border-slate-600"
                }`}
              >
                <ListOrdered size={20} />
                <span className="font-semibold text-sm">Por Productos</span>
                <span className="text-xs text-slate-400">Cada uno lo suyo</span>
              </button>
              <button
                onClick={() => setMode("custom")}
                className={`p-3 rounded-lg border-2 transition-all flex flex-col items-center gap-1 ${
                  mode === "custom"
                    ? "border-orange-500 bg-orange-500/10"
                    : "border-slate-700 bg-slate-800 hover:border-slate-600"
                }`}
              >
                <DollarSign size={20} />
                <span className="font-semibold text-sm">Por Montos</span>
                <span className="text-xs text-slate-400">Montos libres</span>
              </button>
            </div>

            {/* MODO: Equitativa */}
            {mode === "equal" && (
              <div className="space-y-3">
                <div>
                  <label className="block text-sm text-slate-400 mb-1">
                    Número de personas
                  </label>
                  <div className="flex items-center gap-3">
                    <button
                      onClick={() => setParts(Math.max(2, parts - 1))}
                      className="w-10 h-10 bg-slate-800 hover:bg-slate-700 rounded-lg font-bold text-xl"
                    >
                      -
                    </button>
                    <input
                      type="number"
                      value={parts}
                      onChange={(e) =>
                        setParts(Math.max(2, Math.min(50, parseInt(e.target.value) || 2)))
                      }
                      className="flex-1 px-4 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-2xl font-bold text-center focus:outline-none focus:ring-2 focus:ring-orange-500"
                    />
                    <button
                      onClick={() => setParts(Math.min(50, parts + 1))}
                      className="w-10 h-10 bg-slate-800 hover:bg-slate-700 rounded-lg font-bold text-xl"
                    >
                      +
                    </button>
                  </div>
                </div>

                <div className="bg-slate-800 rounded-lg p-4">
                  <div className="flex justify-between text-sm">
                    <span className="text-slate-400">Total:</span>
                    <span>{formatPrice(orderTotal)}</span>
                  </div>
                  <div className="flex justify-between text-sm">
                    <span className="text-slate-400">÷ {parts} personas:</span>
                    <span className="font-bold">
                      {formatPrice(Math.floor(orderTotal / parts))}
                    </span>
                  </div>
                  <div className="mt-2 pt-2 border-t border-slate-700 text-xs text-slate-500">
                    La primera persona absorbe los centavos de redondeo
                  </div>
                </div>
              </div>
            )}

            {/* MODO: Por productos */}
            {mode === "items" && (
              <div className="space-y-3">
                <div className="flex items-center justify-between">
                  <label className="text-sm text-slate-400">
                    Número de grupos (personas)
                  </label>
                  <div className="flex items-center gap-2">
                    <button
                      onClick={() => setGroupCount(Math.max(2, groupCount - 1))}
                      className="w-8 h-8 bg-slate-800 hover:bg-slate-700 rounded"
                    >
                      -
                    </button>
                    <span className="font-bold w-6 text-center">{groupCount}</span>
                    <button
                      onClick={() => setGroupCount(Math.min(10, groupCount + 1))}
                      className="w-8 h-8 bg-slate-800 hover:bg-slate-700 rounded"
                    >
                      +
                    </button>
                  </div>
                </div>

                <div className="space-y-2">
                  {orderItems.map((item) => (
                    <div
                      key={item.uuid}
                      className="bg-slate-800 rounded-lg p-3 flex items-center gap-3"
                    >
                      <div className="flex-1 min-w-0">
                        <div className="text-sm font-semibold">
                          {item.quantity}× {item.name}
                        </div>
                        <div className="text-xs text-slate-400">
                          {formatPrice(item.subtotal)}
                        </div>
                      </div>
                      <div className="flex gap-1">
                        {Array.from({ length: groupCount }, (_, i) => (
                          <button
                            key={i}
                            onClick={() =>
                              setItemGroups((prev) => ({
                                ...prev,
                                [item.uuid]: i,
                              }))
                            }
                            className={`px-3 py-1 rounded text-xs font-bold ${
                              itemGroups[item.uuid] === i
                                ? "bg-orange-500 text-white"
                                : "bg-slate-700 text-slate-300"
                            }`}
                          >
                            P{i + 1}
                          </button>
                        ))}
                      </div>
                    </div>
                  ))}
                </div>

                <div className="bg-slate-800 rounded-lg p-3 space-y-1">
                  {groupTotals.map((total, i) => (
                    <div key={i} className="flex justify-between text-sm">
                      <span className="text-slate-400">Persona {i + 1}:</span>
                      <span className="font-bold text-orange-400">
                        {formatPrice(total)}
                      </span>
                    </div>
                  ))}
                </div>
              </div>
            )}

            {/* MODO: Por montos */}
            {mode === "custom" && (
              <div className="space-y-3">
                <div className="space-y-2">
                  {customAmounts.map((amount, i) => (
                    <div key={i} className="flex items-center gap-2">
                      <span className="text-sm text-slate-400 w-20">
                        Persona {i + 1}
                      </span>
                      <input
                        type="number"
                        value={amount || ""}
                        onChange={(e) => {
                          const newAmounts = [...customAmounts];
                          newAmounts[i] = parseFloat(e.target.value) || 0;
                          setCustomAmounts(newAmounts);
                        }}
                        step="0.01"
                        min="0"
                        className="flex-1 px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-orange-500"
                      />
                      {customAmounts.length > 2 && (
                        <button
                          onClick={() =>
                            setCustomAmounts(customAmounts.filter((_, j) => j !== i))
                          }
                          className="p-2 text-red-400 hover:bg-red-900/30 rounded"
                        >
                          <Trash2 size={16} />
                        </button>
                      )}
                    </div>
                  ))}
                </div>

                <button
                  onClick={() => setCustomAmounts([...customAmounts, 0])}
                  className="w-full py-2 bg-slate-800 hover:bg-slate-700 rounded-lg flex items-center justify-center gap-2 text-sm"
                >
                  <Plus size={16} />
                  Agregar persona
                </button>

                <div
                  className={`rounded-lg p-3 ${
                    customAmountsValid
                      ? "bg-green-900/20 border border-green-700"
                      : "bg-red-900/20 border border-red-700"
                  }`}
                >
                  <div className="flex justify-between text-sm">
                    <span>Total asignado:</span>
                    <span className="font-bold">
                      {formatPrice(customAmountsTotal)}
                    </span>
                  </div>
                  <div className="flex justify-between text-sm">
                    <span>Total orden:</span>
                    <span className="font-bold">
                      {formatPrice(orderTotal)}
                    </span>
                  </div>
                  <div
                    className={`flex justify-between text-sm font-bold pt-1 border-t ${
                      customAmountsValid
                        ? "border-green-700 text-green-400"
                        : "border-red-700 text-red-400"
                    }`}
                  >
                    <span>Diferencia:</span>
                    <span>
                      {formatPrice(customAmountsTotal - orderTotal)}
                    </span>
                  </div>
                </div>
              </div>
            )}

            {/* Error */}
            {splitOrder.isError && (
              <div className="bg-red-900/30 border border-red-700 rounded-lg p-3 text-sm text-red-300 flex items-start gap-2">
                <AlertCircle size={16} className="flex-shrink-0 mt-0.5" />
                <span>
                  {(splitOrder.error as Error).message ||
                    "Error al dividir la cuenta"}
                </span>
              </div>
            )}

            {/* Botones */}
            <div className="flex gap-3">
              <button
                onClick={onClose}
                disabled={splitOrder.isPending}
                className="flex-1 px-4 py-3 bg-slate-700 hover:bg-slate-600 rounded-lg font-medium disabled:opacity-50"
              >
                Cancelar
              </button>
              <button
                onClick={handleSplit}
                disabled={
                  splitOrder.isPending ||
                  (mode === "custom" && !customAmountsValid)
                }
                className="flex-1 px-4 py-3 bg-orange-500 hover:bg-orange-600 rounded-lg font-bold text-white disabled:opacity-50 flex items-center justify-center gap-2"
              >
                {splitOrder.isPending ? (
                  <>
                    <Loader2 size={18} className="animate-spin" />
                    Dividiendo...
                  </>
                ) : (
                  "Generar Sub-cuentas"
                )}
              </button>
            </div>
          </div>
        </div>
      </div>
    </>
  );
}
