import { useState, useMemo } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { tipWizardService } from "@/services/tipService";
import { useCloseSession, useInvalidateCashier } from "@/hooks/usePayments";
import { CLP_DENOMINATIONS, type DenominationCount } from "@/types/cashier";
import { formatPrice } from "@/types/catalog";
import {
  X,
  DollarSign,
  Users,
  Calculator,
  Printer,
  CheckCircle2,
  Loader2,
  Banknote,
  Coins,
  ChevronRight,
} from "lucide-react";
import { PrintableTipVouchers } from "./PrintableTipVouchers";

interface CashCloseWizardProps {
  isOpen: boolean;
  onClose: () => void;
  sessionUuid: string;
  expectedAmount: number; // Esperado BRUTO (incluye propinas)
  pendingTips: number;
}

type WizardStep = 1 | 2 | 3;

export function CashCloseWizard({
  isOpen,
  onClose,
  sessionUuid,
  expectedAmount,
  pendingTips,
}: CashCloseWizardProps) {
  console.log("🔍 CashCloseWizard props:", { pendingTips, expectedAmount });
  
  const queryClient = useQueryClient();
  const [step, setStep] = useState<WizardStep>(1);
  const [generatedPayouts, setGeneratedPayouts] = useState<any[]>([]);
  const [countedAmount, setCountedAmount] = useState<string>("");
  const [counts, setCounts] = useState<Record<number, string>>({});
  const [useDenominations, setUseDenominations] = useState(false);
  const [notes, setNotes] = useState("");
  const [tipsDelivered, setTipsDelivered] = useState<boolean | null>(null);

  const { data: tipsByWaiter, isLoading: loadingTips } = useQuery({
    queryKey: ["tips-by-waiter"],
    queryFn: tipWizardService.getTipsByWaiter,
    enabled: isOpen && step === 1,
  });

  const generatePayouts = useMutation({
    mutationFn: tipWizardService.generatePayouts,
    onSuccess: (data) => {
      setGeneratedPayouts(data.payouts);
      setTipsDelivered(true); // Si generó entregas, asumimos que las entregará
      setStep(2);
      queryClient.invalidateQueries({ queryKey: ["cashier", "tip-payouts"] });
      queryClient.invalidateQueries({ queryKey: ["cashier", "tips-summary"] });
    },
  });

  const closeSession = useCloseSession();
  const invalidate = useInvalidateCashier();

  const hasPendingTips = pendingTips > 0;

  // LÓGICA FINAL:
  // expectedAmount YA viene NETO (sin propinas) desde el parent
  // Las propinas se entregan durante el wizard
  // Por lo tanto, finalExpected = expectedAmount (sin modificaciones)
  const finalExpected = expectedAmount;

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

  const denominationsTotal = denominations.reduce((sum, d) => sum + d.subtotal, 0);
  const total = useDenominations ? denominationsTotal : parseFloat(countedAmount) || 0;
  const difference = total - finalExpected;
  const isExact = Math.abs(difference) < 1;

  const handleGeneratePayouts = () => {
    if (!hasPendingTips) {
      setStep(3);
      return;
    }
    generatePayouts.mutate();
  };

  const handleContinueToArqueo = () => {
    // Si hay propinas pero no generó entregas, debe responder si las entregó
    if (hasPendingTips && generatedPayouts.length === 0) {
      if (tipsDelivered === null) {
        alert("Indica si ya entregaste las propinas antes de continuar");
        return;
      }
    }
    setStep(3);
  };

  const handleConfirmClose = async () => {

    const denominationsSummary = denominations
      .filter((d) => d.quantity > 0)
      .map((d) => `${d.quantity}×${d.label}`)
      .join(", ");

    const tipsNote = hasPendingTips 
      ? (tipsDelivered ? "Propinas entregadas antes del cierre" : "Propinas pendientes en caja")
      : "Sin propinas pendientes";

    const finalNotes = [
      denominationsSummary ? `Arqueo: ${denominationsSummary}` : "",
      tipsNote,
      notes || "",
    ].filter(Boolean).join(" | ");

    try {
      await closeSession.mutateAsync({
        sessionUuid,
        closingAmount: total,
        notes: finalNotes || undefined,
      });
      invalidate();
      onClose();
    } catch (e) {
      console.error(e);
    }
  };

  if (!isOpen) return null;

  return (
    <>
      {/* Componente imprimible para vouchers */}
      {step === 2 && generatedPayouts.length > 0 && (
        <div className="hidden">
          <PrintableTipVouchers payouts={generatedPayouts} />
        </div>
      )}

      <div className="fixed inset-0 bg-black/70 z-50" onClick={onClose} />
      <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div
          className="bg-slate-900 rounded-xl shadow-2xl max-w-2xl w-full max-h-[90vh] flex flex-col"
          onClick={(e) => e.stopPropagation()}
        >
          {/* Header con indicador de pasos */}
          <div className="p-5 border-b border-slate-700">
            <div className="flex items-center justify-between mb-4">
              <h2 className="text-xl font-bold flex items-center gap-2">
                <Calculator size={20} className="text-orange-400" />
                Cierre de Caja
              </h2>
              <button onClick={onClose} className="p-2 hover:bg-slate-800 rounded-lg">
                <X size={20} />
              </button>
            </div>

            {/* Indicador de pasos */}
            <div className="flex items-center gap-2">
              {[
                { num: 1, label: "Propinas", done: step > 1 || !hasPendingTips },
                { num: 2, label: "Entregar", done: step > 2 },
                { num: 3, label: "Arqueo", done: false },
              ].map((s, i) => (
                <div key={s.num} className="flex items-center gap-2 flex-1">
                  <div
                    className={`w-8 h-8 rounded-full flex items-center justify-center font-bold text-sm ${
                      step === s.num
                        ? "bg-orange-500 text-white"
                        : s.done || step > s.num
                        ? "bg-green-500 text-white"
                        : "bg-slate-700 text-slate-400"
                    }`}
                  >
                    {step > s.num || s.done ? "✓" : s.num}
                  </div>
                  <span className={`text-sm ${step === s.num ? "text-white font-semibold" : "text-slate-400"}`}>
                    {s.label}
                  </span>
                  {i < 2 && <ChevronRight size={14} className="text-slate-600 ml-auto" />}
                </div>
              ))}
            </div>
          </div>

          <div className="flex-1 overflow-y-auto p-5">
            {/* PASO 1: Propinas pendientes */}
            {step === 1 && (
              <div className="space-y-4">
                {!hasPendingTips ? (
                  <div className="text-center py-8">
                    <CheckCircle2 size={48} className="mx-auto text-green-400 mb-3" />
                    <h3 className="text-lg font-semibold mb-2">No hay propinas pendientes</h3>
                    <p className="text-slate-400">Puedes continuar directamente al arqueo.</p>
                  </div>
                ) : loadingTips ? (
                  <div className="flex items-center justify-center py-12">
                    <Loader2 className="animate-spin text-orange-500" size={32} />
                  </div>
                ) : (
                  <>
                    <div className="bg-amber-900/30 border border-amber-700/40 rounded-lg p-4 flex items-start gap-3">
                      <DollarSign size={20} className="text-amber-400 flex-shrink-0 mt-0.5" />
                      <div>
                        <div className="font-semibold text-amber-300">
                          Hay {formatPrice(pendingTips)} en propinas pendientes
                        </div>
                        <p className="text-sm text-amber-200/80 mt-1">
                          Las propinas pertenecen a los garzones. Se generarán las entregas automáticamente.
                        </p>
                      </div>
                    </div>

                    {/* Tabla de propinas por garzón */}
                    {tipsByWaiter && tipsByWaiter.by_waiter.length > 0 && (
                      <div className="bg-slate-800 rounded-lg overflow-hidden">
                        <div className="p-3 bg-slate-700/50 font-semibold text-sm flex items-center gap-2">
                          <Users size={16} className="text-blue-400" />
                          Propinas por garzón ({tipsByWaiter.policy.label})
                        </div>
                        <table className="w-full text-sm">
                          <thead className="text-xs text-slate-400 bg-slate-800/80">
                            <tr>
                              <th className="text-left p-3">Garzón</th>
                              <th className="text-right p-3">Efectivo</th>
                              <th className="text-right p-3">Tarjeta</th>
                              <th className="text-right p-3">Total</th>
                            </tr>
                          </thead>
                          <tbody>
                            {tipsByWaiter.by_waiter.map((w) => (
                              <tr key={w.waiter_id} className="border-t border-slate-700">
                                <td className="p-3 font-medium text-white">{w.waiter_name}</td>
                                <td className="p-3 text-right text-green-400">
                                  {formatPrice(w.cash)}
                                </td>
                                <td className="p-3 text-right text-blue-400">
                                  {formatPrice(w.card + w.transfer + w.gift_card)}
                                </td>
                                <td className="p-3 text-right font-bold text-orange-400">
                                  {formatPrice(w.pending)}
                                </td>
                              </tr>
                            ))}
                            <tr className="border-t-2 border-slate-600 bg-slate-700/30">
                              <td className="p-3 font-bold">TOTAL</td>
                              <td className="p-3 text-right font-bold text-green-400">
                                {formatPrice(tipsByWaiter.by_waiter.reduce((s, w) => s + w.cash, 0))}
                              </td>
                              <td className="p-3 text-right font-bold text-blue-400">
                                {formatPrice(tipsByWaiter.by_waiter.reduce((s, w) => s + w.card + w.transfer + w.gift_card, 0))}
                              </td>
                              <td className="p-3 text-right font-bold text-orange-400">
                                {formatPrice(tipsByWaiter.total_pending)}
                              </td>
                            </tr>
                          </tbody>
                        </table>
                      </div>
                    )}
                  </>
                )}

                <div className="flex gap-2 pt-4">
                  <button
                    onClick={onClose}
                    className="flex-1 px-4 py-3 bg-slate-700 hover:bg-slate-600 rounded-lg font-medium"
                  >
                    Cancelar
                  </button>
                  <button
                    onClick={handleGeneratePayouts}
                    disabled={generatePayouts.isPending || !hasPendingTips}
                    className="flex-1 px-4 py-3 bg-orange-500 hover:bg-orange-600 rounded-lg font-bold text-white disabled:opacity-50 flex items-center justify-center gap-2"
                  >
                    {generatePayouts.isPending ? (
                      <Loader2 size={16} className="animate-spin" />
                    ) : (
                      <DollarSign size={16} />
                    )}
                    Generar Entregas
                  </button>
                </div>
              </div>
            )}

            {/* PASO 2: Entregar propinas */}
            {step === 2 && (
              <div className="space-y-4">
                <div className="bg-green-900/30 border border-green-700/40 rounded-lg p-4 flex items-start gap-3">
                  <CheckCircle2 size={20} className="text-green-400 flex-shrink-0 mt-0.5" />
                  <div>
                    <div className="font-semibold text-green-300">
                      Entregas generadas correctamente
                    </div>
                    <p className="text-sm text-green-200/80 mt-1">
                      Entrega físicamente el efectivo a cada garzón y luego continúa al arqueo.
                    </p>
                  </div>
                </div>

                {/* Lista de entregas */}
                <div className="space-y-2">
                  {generatedPayouts.map((payout, i) => (
                    <div
                      key={payout.uuid}
                      className="bg-slate-800 rounded-lg p-3 flex items-center justify-between"
                    >
                      <div className="flex items-center gap-3">
                        <div className="w-8 h-8 bg-orange-500/20 rounded-full flex items-center justify-center">
                          <Users size={16} className="text-orange-400" />
                        </div>
                        <div>
                          <div className="font-semibold text-white">{payout.waiter_name}</div>
                          <div className="text-xs text-slate-400">
                            {payout.payment_method === "cash" ? "Efectivo" : payout.payment_method}
                          </div>
                        </div>
                      </div>
                      <span className="font-bold text-orange-400 text-lg">
                        {formatPrice(payout.amount)}
                      </span>
                    </div>
                  ))}
                </div>

                {/* Total a entregar */}
                <div className="bg-slate-800 rounded-lg p-4 flex justify-between items-center">
                  <span className="font-semibold text-slate-300">Total a entregar:</span>
                  <span className="font-bold text-orange-400 text-xl">
                    {formatPrice(generatedPayouts.reduce((s, p) => s + p.amount, 0))}
                  </span>
                </div>

                <div className="flex gap-2 pt-4">
                  <button
                    onClick={() => window.print()}
                    className="flex-1 px-4 py-3 bg-blue-500 hover:bg-blue-600 rounded-lg font-medium flex items-center justify-center gap-2"
                  >
                    <Printer size={16} />
                    Imprimir Vouchers
                  </button>
                  <button
                    onClick={handleContinueToArqueo}
                    className="flex-1 px-4 py-3 bg-orange-500 hover:bg-orange-600 rounded-lg font-bold text-white flex items-center justify-center gap-2"
                  >
                    <ChevronRight size={16} />
                    Ya entregué, continuar
                  </button>
                </div>
              </div>
            )}

            {/* PASO 3: Arqueo */}
            {step === 3 && (
              <div className="space-y-4">
                {/* Resumen */}
                <div className="bg-slate-800/50 rounded-lg p-4 grid grid-cols-3 gap-3">
                  <div className="text-center">
                    <div className="text-xs text-slate-400 mb-1">ESPERADO</div>
                    <div className="text-lg font-bold text-orange-400">
                      {formatPrice(finalExpected)}
                    </div>
                    {hasPendingTips && (
                      <div className="text-xs text-slate-500 mt-1">
                        Propinas ya entregadas
                      </div>
                    )}
                  </div>
                  <div className="text-center">
                    <div className="text-xs text-slate-400 mb-1">CONTADO</div>
                    <div className="text-lg font-bold text-white">
                      {formatPrice(total)}
                    </div>
                  </div>
                  <div className="text-center">
                    <div className="text-xs text-slate-400 mb-1">DIFERENCIA</div>
                    <div className={`text-lg font-bold ${isExact ? "text-green-400" : "text-red-400"}`}>
                      {isExact ? "✓ Cuadra" : formatPrice(difference)}
                    </div>
                  </div>
                </div>

                {/* Selector de modo */}
                <div className="flex gap-2">
                  <button
                    onClick={() => setUseDenominations(true)}
                    className={`flex-1 py-2 rounded-lg border-2 text-sm font-medium ${
                      useDenominations
                        ? "border-orange-500 bg-orange-500/10 text-white"
                        : "border-slate-700 bg-slate-800 text-slate-400"
                    }`}
                  >
                    <Banknote size={14} className="inline mr-1" />
                    Por denominaciones
                  </button>
                  <button
                    onClick={() => setUseDenominations(false)}
                    className={`flex-1 py-2 rounded-lg border-2 text-sm font-medium ${
                      !useDenominations
                        ? "border-orange-500 bg-orange-500/10 text-white"
                        : "border-slate-700 bg-slate-800 text-slate-400"
                    }`}
                  >
                    <Calculator size={14} className="inline mr-1" />
                    Total manual
                  </button>
                </div>

                {useDenominations ? (
                  <div className="space-y-3 max-h-96 overflow-y-auto">
                    {/* Billetes */}
                    <div>
                      <h4 className="text-sm font-bold text-slate-300 mb-2 flex items-center gap-2">
                        <Banknote size={14} className="text-green-400" />
                        Billetes
                      </h4>
                      <div className="space-y-1.5">
                        {denominations.filter(d => d.type === "bill").map((d) => (
                          <div key={d.value} className="flex items-center gap-2 bg-slate-800/60 rounded p-2">
                            <span className="font-bold text-green-400 w-20 text-sm">{d.label}</span>
                            <span className="text-slate-500 text-xs">×</span>
                            <input
                              type="number"
                              value={counts[d.value] || ""}
                              onChange={(e) => setCounts(prev => ({ ...prev, [d.value]: e.target.value }))}
                              min="0"
                              className="w-16 px-2 py-1 bg-slate-900 border border-slate-700 rounded text-white text-center text-sm font-bold"
                            />
                            <span className="text-slate-500 text-xs">=</span>
                            <span className="font-semibold text-white ml-auto text-sm">
                              {formatPrice(d.subtotal)}
                            </span>
                          </div>
                        ))}
                      </div>
                    </div>

                    {/* Monedas */}
                    <div>
                      <h4 className="text-sm font-bold text-slate-300 mb-2 flex items-center gap-2">
                        <Coins size={14} className="text-amber-400" />
                        Monedas
                      </h4>
                      <div className="space-y-1.5">
                        {denominations.filter(d => d.type === "coin").map((d) => (
                          <div key={d.value} className="flex items-center gap-2 bg-slate-800/60 rounded p-2">
                            <span className="font-bold text-amber-400 w-20 text-sm">{d.label}</span>
                            <span className="text-slate-500 text-xs">×</span>
                            <input
                              type="number"
                              value={counts[d.value] || ""}
                              onChange={(e) => setCounts(prev => ({ ...prev, [d.value]: e.target.value }))}
                              min="0"
                              className="w-16 px-2 py-1 bg-slate-900 border border-slate-700 rounded text-white text-center text-sm font-bold"
                            />
                            <span className="text-slate-500 text-xs">=</span>
                            <span className="font-semibold text-white ml-auto text-sm">
                              {formatPrice(d.subtotal)}
                            </span>
                          </div>
                        ))}
                      </div>
                    </div>
                  </div>
                ) : (
                  <div className="bg-slate-800/60 rounded-lg p-4">
                    <label className="block text-sm text-slate-300 mb-2 font-semibold">
                      ¿Cuánto efectivo cuentas físicamente?
                    </label>
                    <input
                      type="number"
                      value={countedAmount}
                      onChange={(e) => setCountedAmount(e.target.value)}
                      min="0"
                      autoFocus
                      className="w-full px-4 py-3 bg-slate-900 border-2 border-orange-500 rounded-lg text-white text-2xl font-bold text-center focus:outline-none"
                      placeholder="0"
                    />
                  </div>
                )}

                {/* Justificación si hay diferencia */}
                {!isExact && total > 0 && (
                  <div className="bg-amber-900/20 border border-amber-700/40 rounded-lg p-3">
                    <label className="block text-sm text-amber-200 mb-1 font-semibold">
                      ⚠️ Justifica la diferencia:
                    </label>
                    <textarea
                      value={notes}
                      onChange={(e) => setNotes(e.target.value)}
                      rows={2}
                      className="w-full px-3 py-2 bg-slate-900 border border-amber-700 rounded-lg text-white text-sm focus:outline-none focus:ring-2 focus:ring-amber-500"
                      placeholder="Motivo de la diferencia..."
                    />
                  </div>
                )}

                <div className="flex gap-2 pt-2">
                  <button
                    onClick={() => setStep(hasPendingTips ? 2 : 1)}
                    className="flex-1 px-4 py-3 bg-slate-700 hover:bg-slate-600 rounded-lg font-medium"
                  >
                    ← Volver
                  </button>
                  <button
                    onClick={handleConfirmClose}
                    disabled={closeSession.isPending || total === 0}
                    className="flex-1 px-4 py-3 bg-orange-500 hover:bg-orange-600 rounded-lg font-bold text-white disabled:opacity-50 flex items-center justify-center gap-2"
                  >
                    {closeSession.isPending ? (
                      <Loader2 size={16} className="animate-spin" />
                    ) : (
                      <CheckCircle2 size={16} />
                    )}
                    Confirmar Cierre
                  </button>
                </div>
              </div>
            )}
          </div>
        </div>
      </div>
    </>
  );
}
