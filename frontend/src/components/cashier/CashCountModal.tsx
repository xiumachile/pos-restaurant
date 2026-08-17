import { useState, useMemo } from "react";
import { CLP_DENOMINATIONS, type DenominationCount } from "@/types/cashier";
import { formatPrice } from "@/types/catalog";
import { X, Banknote, Coins, Calculator, Loader2 } from "lucide-react";

interface CashCountModalProps {
  isOpen: boolean;
  onClose: () => void;
  expectedAmount: number;
  onConfirm: (countedAmount: number, denominations: DenominationCount[]) => void;
  isLoading?: boolean;
}

/**
 * Modal de arqueo con conteo por denominaciones de billetes/monedas chilenas.
 */
export function CashCountModal({
  isOpen,
  onClose,
  expectedAmount,
  onConfirm,
  isLoading,
}: CashCountModalProps) {
  const [counts, setCounts] = useState<Record<number, string>>({});
  const [otherAmount, setOtherAmount] = useState<string>("");

  const denominations: DenominationCount[] = useMemo(
    () =>
      CLP_DENOMINATIONS.map((d) => {
        const qty = parseInt(counts[d.value] || "0") || 0;
        return {
          value: d.value,
          label: d.label,
          type: d.type,
          quantity: qty,
          subtotal: qty * d.value,
        };
      }),
    [counts]
  );

  const denominationsTotal = useMemo(
    () => denominations.reduce((sum, d) => sum + d.subtotal, 0),
    [denominations]
  );

  const otherTotal = parseFloat(otherAmount) || 0;
  const countedTotal = denominationsTotal + otherTotal;
  const difference = countedTotal - expectedAmount;

  const handleQtyChange = (value: number, qty: string) => {
    setCounts((prev) => ({ ...prev, [value]: qty }));
  };

  const handleConfirm = () => {
    onConfirm(countedTotal, denominations);
  };

  const handleReset = () => {
    setCounts({});
    setOtherAmount("");
  };

  if (!isOpen) return null;

  const bills = denominations.filter((d) => d.type === "bill");
  const coins = denominations.filter((d) => d.type === "coin");

  return (
    <>
      <div className="fixed inset-0 bg-black/70 z-50" onClick={onClose} />
      <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div
          className="bg-slate-900 rounded-xl shadow-2xl max-w-3xl w-full max-h-[90vh] flex flex-col"
          onClick={(e) => e.stopPropagation()}
        >
          <div className="flex items-center justify-between p-5 border-b border-slate-700">
            <div>
              <h2 className="text-xl font-bold flex items-center gap-2">
                <Calculator size={20} className="text-orange-400" />
                Arqueo de Caja
              </h2>
              <p className="text-sm text-slate-400 mt-0.5">
                Cuenta los billetes y monedas físicamente en caja
              </p>
            </div>
            <div className="flex items-center gap-2">
              <button
                onClick={handleReset}
                className="px-3 py-1.5 bg-slate-700 hover:bg-slate-600 rounded-lg text-sm"
              >
                Limpiar
              </button>
              <button onClick={onClose} className="p-2 hover:bg-slate-800 rounded-lg">
                <X size={20} />
              </button>
            </div>
          </div>

          <div className="flex-1 overflow-y-auto p-5 space-y-5">
            {/* Billetes */}
            <div>
              <h3 className="text-sm font-bold text-slate-300 mb-2 flex items-center gap-2">
                <Banknote size={16} className="text-green-400" />
                Billetes
              </h3>
              <div className="grid grid-cols-1 gap-2">
                {bills.map((d) => (
                  <div
                    key={d.value}
                    className="flex items-center gap-3 bg-slate-800/60 rounded-lg p-2.5"
                  >
                    <span className="font-bold text-green-400 w-20">{d.label}</span>
                    <span className="text-slate-500 text-sm">×</span>
                    <input
                      type="number"
                      value={counts[d.value] || ""}
                      onChange={(e) => handleQtyChange(d.value, e.target.value)}
                      min="0"
                      className="w-20 px-2 py-1 bg-slate-900 border border-slate-700 rounded text-white text-center font-bold focus:outline-none focus:ring-2 focus:ring-orange-500"
                    />
                    <span className="text-slate-500 text-sm">=</span>
                    <span className="font-semibold text-white ml-auto">
                      {formatPrice(d.subtotal)}
                    </span>
                  </div>
                ))}
              </div>
            </div>

            {/* Monedas */}
            <div>
              <h3 className="text-sm font-bold text-slate-300 mb-2 flex items-center gap-2">
                <Coins size={16} className="text-amber-400" />
                Monedas
              </h3>
              <div className="grid grid-cols-1 gap-2">
                {coins.map((d) => (
                  <div
                    key={d.value}
                    className="flex items-center gap-3 bg-slate-800/60 rounded-lg p-2.5"
                  >
                    <span className="font-bold text-amber-400 w-20">{d.label}</span>
                    <span className="text-slate-500 text-sm">×</span>
                    <input
                      type="number"
                      value={counts[d.value] || ""}
                      onChange={(e) => handleQtyChange(d.value, e.target.value)}
                      min="0"
                      className="w-20 px-2 py-1 bg-slate-900 border border-slate-700 rounded text-white text-center font-bold focus:outline-none focus:ring-2 focus:ring-orange-500"
                    />
                    <span className="text-slate-500 text-sm">=</span>
                    <span className="font-semibold text-white ml-auto">
                      {formatPrice(d.subtotal)}
                    </span>
                  </div>
                ))}
              </div>
            </div>

            {/* Otros */}
            <div className="bg-slate-800/60 rounded-lg p-4">
              <label className="block text-sm text-slate-400 mb-1">
                Otros montos en caja (vouchers, cheques, etc)
              </label>
              <input
                type="number"
                value={otherAmount}
                onChange={(e) => setOtherAmount(e.target.value)}
                min="0"
                step="0.01"
                className="w-full px-4 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white font-bold focus:outline-none focus:ring-2 focus:ring-orange-500"
                placeholder="0"
              />
            </div>

            {/* Resumen */}
            <div className="bg-slate-800 rounded-lg p-4 space-y-2 sticky bottom-0">
              <div className="flex justify-between text-sm">
                <span className="text-slate-400">Billetes:</span>
                <span className="font-semibold">{formatPrice(bills.reduce((s, d) => s + d.subtotal, 0))}</span>
              </div>
              <div className="flex justify-between text-sm">
                <span className="text-slate-400">Monedas:</span>
                <span className="font-semibold">{formatPrice(coins.reduce((s, d) => s + d.subtotal, 0))}</span>
              </div>
              {otherTotal > 0 && (
                <div className="flex justify-between text-sm">
                  <span className="text-slate-400">Otros:</span>
                  <span className="font-semibold">{formatPrice(otherTotal)}</span>
                </div>
              )}
              <div className="flex justify-between pt-2 border-t border-slate-700">
                <span className="text-slate-300 font-semibold">Esperado:</span>
                <span className="font-bold">{formatPrice(expectedAmount)}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-slate-300 font-semibold">Contado:</span>
                <span className="font-bold text-orange-400 text-lg">
                  {formatPrice(countedTotal)}
                </span>
              </div>
              <div
                className={`flex justify-between pt-2 border-t-2 font-bold text-lg ${
                  Math.abs(difference) < 0.01
                    ? "border-green-700 text-green-400"
                    : "border-red-700 text-red-400"
                }`}
              >
                <span>DIFERENCIA:</span>
                <span>
                  {difference === 0 ? "✓ Cuadra" : formatPrice(difference)}
                </span>
              </div>
            </div>
          </div>

          <div className="flex gap-2 p-5 border-t border-slate-700">
            <button
              onClick={onClose}
              disabled={isLoading}
              className="flex-1 px-4 py-3 bg-slate-700 hover:bg-slate-600 rounded-lg font-medium disabled:opacity-50"
            >
              Cancelar
            </button>
            <button
              onClick={handleConfirm}
              disabled={isLoading}
              className="flex-1 px-4 py-3 bg-orange-500 hover:bg-orange-600 rounded-lg font-bold text-white disabled:opacity-50 flex items-center justify-center gap-2"
            >
              {isLoading ? (
                <>
                  <Loader2 size={16} className="animate-spin" />
                  Cerrando...
                </>
              ) : (
                "Confirmar Cierre"
              )}
            </button>
          </div>
        </div>
      </div>
    </>
  );
}
