import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { tipPayoutService } from "@/services/tipService";
import {
  useTipPayouts,
  useTipSummary,
  useCreateTipPayout,
  useVoidTipPayout,
} from "@/hooks/usePayments";
import { formatPrice } from "@/types/catalog";
import {
  X,
  Loader2,
  AlertCircle,
  CheckCircle2,
  Plus,
  Trash2,
  Users,
  DollarSign,
  Banknote,
  CreditCard,
} from "lucide-react";

interface TipPayoutModalProps {
  isOpen: boolean;
  onClose: () => void;
}

export function TipPayoutModal({ isOpen, onClose }: TipPayoutModalProps) {
  const [showForm, setShowForm] = useState(false);
  const [selectedWaiter, setSelectedWaiter] = useState<number | null>(null);
  const [amount, setAmount] = useState<string>("");
  const [paymentMethod, setPaymentMethod] = useState<"cash" | "card">("cash");
  const [notes, setNotes] = useState("");

  const { data: payouts = [] } = useTipPayouts(isOpen);
  const { data: summary } = useTipSummary(isOpen);
  const { data: waiters = [] } = useQuery({
    queryKey: ["waiters"],
    queryFn: tipPayoutService.listWaiters,
    enabled: isOpen,
  });

  const createPayout = useCreateTipPayout();
  const voidPayout = useVoidTipPayout();

  const handleSubmit = async () => {
    if (!selectedWaiter || !amount) return;
    
    try {
      await createPayout.mutateAsync({
        waiter_id: selectedWaiter,
        amount: parseFloat(amount),
        payment_method: paymentMethod,
        notes: notes || undefined,
      });
      
      setShowForm(false);
      setSelectedWaiter(null);
      setAmount("");
      setNotes("");
      setPaymentMethod("cash");
    } catch (e) {
      console.error(e);
    }
  };

  const handleVoid = async (uuid: string) => {
    if (!confirm("¿Anular esta entrega de propina?")) return;
    
    try {
      await voidPayout.mutateAsync(uuid);
    } catch (e) {
      console.error(e);
    }
  };

  if (!isOpen) return null;

  return (
    <>
      <div className="fixed inset-0 bg-black/70 z-50" onClick={onClose} />
      <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div
          className="bg-slate-900 rounded-xl shadow-2xl max-w-2xl w-full max-h-[90vh] flex flex-col"
          onClick={(e) => e.stopPropagation()}
        >
          <div className="flex items-center justify-between p-5 border-b border-slate-700">
            <div>
              <h2 className="text-xl font-bold flex items-center gap-2">
                <DollarSign size={20} className="text-orange-400" />
                Entregas de Propinas
              </h2>
              <p className="text-sm text-slate-400 mt-0.5">
                {summary?.policy.label || "Política de propinas"}
              </p>
            </div>
            <div className="flex items-center gap-2">
              <button
                onClick={() => setShowForm(!showForm)}
                className="flex items-center gap-1.5 px-3 py-2 bg-orange-500 hover:bg-orange-600 rounded-lg text-white text-sm font-medium"
              >
                <Plus size={15} />
                Nueva Entrega
              </button>
              <button onClick={onClose} className="p-2 hover:bg-slate-800 rounded-lg">
                <X size={20} />
              </button>
            </div>
          </div>

          {/* Resumen de propinas */}
          {summary && (
            <div className="bg-slate-800/50 border-b border-slate-700 p-4 grid grid-cols-4 gap-3">
              <div className="text-center">
                <div className="text-xs text-slate-400">Recibidas</div>
                <div className="font-bold text-green-400">
                  {formatPrice(summary.tips_received.total)}
                </div>
              </div>
              <div className="text-center">
                <div className="text-xs text-slate-400">Entregadas</div>
                <div className="font-bold text-blue-400">
                  {formatPrice(summary.payouts.total)}
                </div>
              </div>
              <div className="text-center">
                <div className="text-xs text-slate-400">Pendientes</div>
                <div className={`font-bold ${summary.pending > 0 ? "text-orange-400" : "text-slate-500"}`}>
                  {formatPrice(summary.pending)}
                </div>
              </div>
              <div className="text-center">
                <div className="text-xs text-slate-400">Garzones</div>
                <div className="font-bold text-white">
                  {summary.by_waiter.length}
                </div>
              </div>
            </div>
          )}

          <div className="flex-1 overflow-y-auto p-5 space-y-4">
            {/* Formulario de nueva entrega */}
            {showForm && (
              <div className="bg-slate-800 rounded-lg p-4 space-y-3">
                <h3 className="font-semibold">Registrar entrega</h3>
                
                <div>
                  <label className="block text-sm text-slate-400 mb-1">
                    Garzón
                  </label>
                  <select
                    value={selectedWaiter || ""}
                    onChange={(e) => setSelectedWaiter(parseInt(e.target.value))}
                    className="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-orange-500"
                  >
                    <option value="">Seleccionar garzón...</option>
                    {waiters.map((w) => (
                      <option key={w.id} value={w.id}>
                        {w.name}
                      </option>
                    ))}
                  </select>
                </div>

                <div>
                  <label className="block text-sm text-slate-400 mb-1">
                    Monto
                  </label>
                  <input
                    type="number"
                    value={amount}
                    onChange={(e) => setAmount(e.target.value)}
                    min="0.01"
                    step="0.01"
                    className="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white font-bold focus:outline-none focus:ring-2 focus:ring-orange-500"
                    placeholder="0"
                  />
                </div>

                <div>
                  <label className="block text-sm text-slate-400 mb-1">
                    Método de pago
                  </label>
                  <div className="flex gap-2">
                    <button
                      onClick={() => setPaymentMethod("cash")}
                      className={`flex-1 py-2 rounded-lg border-2 text-sm font-medium ${
                        paymentMethod === "cash"
                          ? "border-green-500 bg-green-500/10 text-green-400"
                          : "border-slate-700 bg-slate-800 text-slate-400"
                      }`}
                    >
                      <Banknote size={14} className="inline mr-1" />
                      Efectivo
                    </button>
                    <button
                      onClick={() => setPaymentMethod("card")}
                      className={`flex-1 py-2 rounded-lg border-2 text-sm font-medium ${
                        paymentMethod === "card"
                          ? "border-blue-500 bg-blue-500/10 text-blue-400"
                          : "border-slate-700 bg-slate-800 text-slate-400"
                      }`}
                    >
                      <CreditCard size={14} className="inline mr-1" />
                      Tarjeta
                    </button>
                  </div>
                </div>

                <div className="flex gap-2 pt-2">
                  <button
                    onClick={() => setShowForm(false)}
                    className="flex-1 px-3 py-2 bg-slate-700 hover:bg-slate-600 rounded-lg text-sm"
                  >
                    Cancelar
                  </button>
                  <button
                    onClick={handleSubmit}
                    disabled={!selectedWaiter || !amount || createPayout.isPending}
                    className="flex-1 px-3 py-2 bg-orange-500 hover:bg-orange-600 rounded-lg text-sm font-medium text-white disabled:opacity-50 flex items-center justify-center gap-1"
                  >
                    {createPayout.isPending ? (
                      <Loader2 size={14} className="animate-spin" />
                    ) : (
                      <CheckCircle2 size={14} />
                    )}
                    Registrar
                  </button>
                </div>

                {createPayout.isError && (
                  <div className="bg-red-900/30 border border-red-700 rounded-lg p-2 text-xs text-red-300">
                    {(createPayout.error as Error).message}
                  </div>
                )}
              </div>
            )}

            {/* Lista de entregas */}
            {payouts.length === 0 && !showForm ? (
              <div className="text-center py-8 text-slate-500">
                <Users size={48} className="mx-auto mb-3 opacity-30" />
                <p>No hay entregas registradas en esta sesión</p>
              </div>
            ) : (
              <div className="space-y-2">
                {payouts.map((payout) => (
                  <div
                    key={payout.uuid}
                    className="bg-slate-800/60 rounded-lg p-3 flex items-center justify-between"
                  >
                    <div className="flex-1">
                      <div className="font-semibold text-white">
                        {payout.waiter_name}
                      </div>
                      <div className="text-xs text-slate-400">
                        {new Date(payout.created_at).toLocaleTimeString("es-CL", {
                          hour: "2-digit",
                          minute: "2-digit",
                        })}
                        {payout.notes && ` · ${payout.notes}`}
                      </div>
                    </div>
                    <div className="flex items-center gap-3">
                      <span className={`text-xs px-2 py-0.5 rounded ${
                        payout.payment_method === "cash"
                          ? "bg-green-900/30 text-green-400"
                          : "bg-blue-900/30 text-blue-400"
                      }`}>
                        {payout.payment_method === "cash" ? "Efectivo" : "Tarjeta"}
                      </span>
                      <span className="font-bold text-orange-400">
                        {formatPrice(payout.amount)}
                      </span>
                      <button
                        onClick={() => handleVoid(payout.uuid)}
                        disabled={voidPayout.isPending}
                        className="p-1.5 text-red-400 hover:bg-red-900/30 rounded"
                        title="Anular entrega"
                      >
                        <Trash2 size={14} />
                      </button>
                    </div>
                  </div>
                ))}
              </div>
            )}

            {/* Resumen por garzón */}
            {summary && summary.by_waiter.length > 0 && (
              <div className="bg-slate-800/50 rounded-lg p-4">
                <h3 className="text-sm font-bold text-slate-300 mb-3">
                  Resumen por garzón
                </h3>
                <div className="space-y-2">
                  {summary.by_waiter.map((w) => (
                    <div
                      key={w.waiter_id}
                      className="flex justify-between text-sm py-1 border-b border-slate-700/50 last:border-0"
                    >
                      <span className="text-slate-300">{w.waiter_name}</span>
                      <span className="font-semibold text-white">
                        {formatPrice(w.total_amount)}
                      </span>
                    </div>
                  ))}
                </div>
              </div>
            )}
          </div>
        </div>
      </div>
    </>
  );
}
