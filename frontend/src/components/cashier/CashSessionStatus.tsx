import { useState } from "react";
import type { CashSession } from "@/types/payments";
import {
  useOpenSession,
  useCloseSession,
  useInvalidateCashier,
} from "@/hooks/usePayments";
import { formatPrice } from "@/types/catalog";
import {
  Wallet,
  PlayCircle,
  StopCircle,
  Loader2,
  AlertCircle,
  Clock,
  DollarSign,
  Banknote,
  CreditCard,
  Building2,
  Gift,
  TrendingUp,
} from "lucide-react";

interface CashSessionStatusProps {
  session: CashSession | null;
}

export function CashSessionStatus({ session }: CashSessionStatusProps) {
  const [showOpenModal, setShowOpenModal] = useState(false);
  const [showCloseModal, setShowCloseModal] = useState(false);
  const [openingAmount, setOpeningAmount] = useState<string>("0");
  const [closingAmount, setClosingAmount] = useState<string>("");
  const [notes, setNotes] = useState("");

  const openSession = useOpenSession();
  const closeSession = useCloseSession();
  const invalidate = useInvalidateCashier();

  const handleOpen = async () => {
    const amount = parseFloat(openingAmount);
    if (isNaN(amount) || amount < 0) return;

    try {
      await openSession.mutateAsync({ openingAmount: amount, notes });
      setShowOpenModal(false);
      setOpeningAmount("0");
      setNotes("");
      invalidate();
    } catch (e) {
      console.error(e);
    }
  };

  const handleClose = async () => {
    if (!session) return;
    const amount = parseFloat(closingAmount);
    if (isNaN(amount) || amount < 0) return;

    try {
      await closeSession.mutateAsync({
        sessionUuid: session.uuid,
        closingAmount: amount,
        notes,
      });
      setShowCloseModal(false);
      setClosingAmount("");
      setNotes("");
      invalidate();
    } catch (e) {
      console.error(e);
    }
  };

  const formatTime = (isoString: string | null) => {
    if (!isoString) return "-";
    return new Date(isoString).toLocaleTimeString("es-CL", {
      hour: "2-digit",
      minute: "2-digit",
    });
  };

  const breakdown = session?.breakdown;
  const totalCashExpected = session?.total_cash_expected ?? 0;
  const totalGrandExpected = session?.total_grand_expected ?? 0;

  return (
    <>
      <div className="space-y-4">
        {/* Info de sesión */}
        <div className="bg-slate-800/50 border border-slate-700 rounded-xl p-6">
          <div className="flex items-center justify-between mb-4">
            <div className="flex items-center gap-3">
              <Wallet
                size={28}
                className={session ? "text-green-400" : "text-slate-500"}
              />
              <div>
                <h2 className="text-xl font-bold">
                  {session ? "Caja Abierta" : "Caja Cerrada"}
                </h2>
                <p className="text-sm text-slate-400">
                  {session
                    ? `Sesión ${session.session_number}`
                    : "Inicia sesión para empezar a cobrar"}
                </p>
              </div>
            </div>

            {session ? (
              <button
                onClick={() => setShowCloseModal(true)}
                className="flex items-center gap-2 px-4 py-2 bg-red-500 hover:bg-red-600 rounded-lg text-white font-medium transition-colors"
              >
                <StopCircle size={16} />
                Cerrar Caja
              </button>
            ) : (
              <button
                onClick={() => setShowOpenModal(true)}
                className="flex items-center gap-2 px-4 py-2 bg-green-500 hover:bg-green-600 rounded-lg text-white font-medium transition-colors"
              >
                <PlayCircle size={16} />
                Abrir Caja
              </button>
            )}
          </div>

          {session && (
            <div className="grid grid-cols-2 md:grid-cols-4 gap-3 pt-4 border-t border-slate-700">
              <div className="bg-slate-900/50 rounded-lg p-3">
                <div className="flex items-center gap-2 text-xs text-slate-400 mb-1">
                  <DollarSign size={12} />
                  Monto Inicial
                </div>
                <div className="text-lg font-bold text-white">
                  {formatPrice(session.opening_amount)}
                </div>
              </div>
              <div className="bg-slate-900/50 rounded-lg p-3">
                <div className="flex items-center gap-2 text-xs text-slate-400 mb-1">
                  <Clock size={12} />
                  Abierto a las
                </div>
                <div className="text-lg font-bold text-white">
                  {formatTime(session.opened_at)}
                </div>
              </div>
              <div className="bg-slate-900/50 rounded-lg p-3">
                <div className="flex items-center gap-2 text-xs text-slate-400 mb-1">
                  <TrendingUp size={12} />
                  Transacciones
                </div>
                <div className="text-lg font-bold text-blue-400">
                  {session.total_transactions ?? 0}
                </div>
              </div>
              <div className="bg-slate-900/50 rounded-lg p-3">
                <div className="flex items-center gap-2 text-xs text-slate-400 mb-1">
                  <Wallet size={12} />
                  Cajero
                </div>
                <div className="text-lg font-bold text-white truncate">
                  {session.user?.name || "-"}
                </div>
              </div>
            </div>
          )}
        </div>

        {/* Desglose por método de pago (solo si hay sesión activa) */}
        {session && breakdown && (
          <div className="bg-slate-800/50 border border-slate-700 rounded-xl p-6">
            <h3 className="text-lg font-bold mb-4 flex items-center gap-2">
              <DollarSign size={20} className="text-orange-400" />
              Desglose de Ventas en esta Sesión
            </h3>

            <div className="space-y-2">
              {/* Efectivo */}
              <div className="flex items-center justify-between bg-green-900/20 border border-green-700/30 rounded-lg p-3">
                <div className="flex items-center gap-3">
                  <Banknote size={20} className="text-green-400" />
                  <div>
                    <div className="font-semibold text-white">Efectivo</div>
                    <div className="text-xs text-slate-400">
                      {breakdown.cash.count} transacción{breakdown.cash.count !== 1 ? "es" : ""}
                    </div>
                  </div>
                </div>
                <div className="text-right">
                  <div className="font-bold text-green-400">
                    {formatPrice(breakdown.cash.amount)}
                  </div>
                  {breakdown.cash.tips > 0 && (
                    <div className="text-xs text-green-300">
                      +{formatPrice(breakdown.cash.tips)} propina
                    </div>
                  )}
                </div>
              </div>

              {/* Tarjeta */}
              <div className="flex items-center justify-between bg-blue-900/20 border border-blue-700/30 rounded-lg p-3">
                <div className="flex items-center gap-3">
                  <CreditCard size={20} className="text-blue-400" />
                  <div>
                    <div className="font-semibold text-white">Tarjeta</div>
                    <div className="text-xs text-slate-400">
                      {breakdown.card.count} transacción{breakdown.card.count !== 1 ? "es" : ""}
                    </div>
                  </div>
                </div>
                <div className="text-right">
                  <div className="font-bold text-blue-400">
                    {formatPrice(breakdown.card.amount)}
                  </div>
                  {breakdown.card.tips > 0 && (
                    <div className="text-xs text-blue-300">
                      +{formatPrice(breakdown.card.tips)} propina
                    </div>
                  )}
                </div>
              </div>

              {/* Transferencia */}
              <div className="flex items-center justify-between bg-purple-900/20 border border-purple-700/30 rounded-lg p-3">
                <div className="flex items-center gap-3">
                  <Building2 size={20} className="text-purple-400" />
                  <div>
                    <div className="font-semibold text-white">Transferencia</div>
                    <div className="text-xs text-slate-400">
                      {breakdown.transfer.count} transacción{breakdown.transfer.count !== 1 ? "es" : ""}
                    </div>
                  </div>
                </div>
                <div className="text-right">
                  <div className="font-bold text-purple-400">
                    {formatPrice(breakdown.transfer.amount)}
                  </div>
                  {breakdown.transfer.tips > 0 && (
                    <div className="text-xs text-purple-300">
                      +{formatPrice(breakdown.transfer.tips)} propina
                    </div>
                  )}
                </div>
              </div>

              {/* Gift Card */}
              <div className="flex items-center justify-between bg-amber-900/20 border border-amber-700/30 rounded-lg p-3">
                <div className="flex items-center gap-3">
                  <Gift size={20} className="text-amber-400" />
                  <div>
                    <div className="font-semibold text-white">Gift Card</div>
                    <div className="text-xs text-slate-400">
                      {breakdown.gift_card.count} transacción{breakdown.gift_card.count !== 1 ? "es" : ""}
                    </div>
                  </div>
                </div>
                <div className="text-right">
                  <div className="font-bold text-amber-400">
                    {formatPrice(breakdown.gift_card.amount)}
                  </div>
                  {breakdown.gift_card.tips > 0 && (
                    <div className="text-xs text-amber-300">
                      +{formatPrice(breakdown.gift_card.tips)} propina
                    </div>
                  )}
                </div>
              </div>
            </div>

            {/* Totales */}
            <div className="mt-4 pt-4 border-t border-slate-700 space-y-2">
              <div className="flex justify-between text-sm">
                <span className="text-slate-400">Ventas totales:</span>
                <span className="font-semibold">
                  {formatPrice(session.total_sales_amount ?? 0)}
                </span>
              </div>
              <div className="flex justify-between text-sm">
                <span className="text-slate-400">Propinas totales:</span>
                <span className="font-semibold text-orange-400">
                  {formatPrice(session.total_tips ?? 0)}
                </span>
              </div>
              <div className="flex justify-between text-lg font-bold pt-2 border-t border-slate-700">
                <span>Total esperado en caja:</span>
                <span className="text-green-400">
                  {formatPrice(totalCashExpected)}
                </span>
              </div>
              <div className="flex justify-between text-sm text-slate-400">
                <span>(Inicial + efectivo + propinas efectivo)</span>
              </div>
            </div>
          </div>
        )}
      </div>

      {/* Modal Abrir Caja */}
      {showOpenModal && (
        <>
          <div
            className="fixed inset-0 bg-black/70 z-50"
            onClick={() => setShowOpenModal(false)}
          />
          <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div
              className="bg-slate-900 rounded-xl shadow-2xl max-w-md w-full p-6"
              onClick={(e) => e.stopPropagation()}
            >
              <h2 className="text-xl font-bold mb-4 flex items-center gap-2">
                <PlayCircle size={24} className="text-green-400" />
                Abrir Caja
              </h2>

              <div className="space-y-4">
                <div>
                  <label className="block text-sm text-slate-400 mb-1">
                    Monto inicial en caja
                  </label>
                  <input
                    type="number"
                    value={openingAmount}
                    onChange={(e) => setOpeningAmount(e.target.value)}
                    step="0.01"
                    min="0"
                    className="w-full px-4 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-lg font-bold focus:outline-none focus:ring-2 focus:ring-green-500"
                    placeholder="0"
                  />
                </div>

                <div>
                  <label className="block text-sm text-slate-400 mb-1">
                    Notas (opcional)
                  </label>
                  <textarea
                    value={notes}
                    onChange={(e) => setNotes(e.target.value)}
                    rows={2}
                    className="w-full px-4 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-green-500"
                    placeholder="Ej: Caja inicial del turno mañana"
                  />
                </div>

                <div className="flex gap-2 pt-2">
                  <button
                    onClick={() => setShowOpenModal(false)}
                    className="flex-1 px-4 py-2 bg-slate-700 hover:bg-slate-600 rounded-lg"
                  >
                    Cancelar
                  </button>
                  <button
                    onClick={handleOpen}
                    disabled={openSession.isPending}
                    className="flex-1 px-4 py-2 bg-green-500 hover:bg-green-600 rounded-lg font-medium disabled:opacity-50 flex items-center justify-center gap-2"
                  >
                    {openSession.isPending && (
                      <Loader2 size={16} className="animate-spin" />
                    )}
                    Abrir
                  </button>
                </div>

                {openSession.isError && (
                  <div className="bg-red-900/30 border border-red-700 rounded-lg p-3 text-sm text-red-300 flex items-start gap-2">
                    <AlertCircle size={16} className="flex-shrink-0 mt-0.5" />
                    <span>
                      {(openSession.error as Error).message ||
                        "Error al abrir caja"}
                    </span>
                  </div>
                )}
              </div>
            </div>
          </div>
        </>
      )}

      {/* Modal Cerrar Caja */}
      {showCloseModal && session && (
        <>
          <div
            className="fixed inset-0 bg-black/70 z-50"
            onClick={() => setShowCloseModal(false)}
          />
          <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div
              className="bg-slate-900 rounded-xl shadow-2xl max-w-md w-full p-6"
              onClick={(e) => e.stopPropagation()}
            >
              <h2 className="text-xl font-bold mb-4 flex items-center gap-2">
                <StopCircle size={24} className="text-red-400" />
                Cerrar Caja (Arqueo)
              </h2>

              <div className="bg-slate-800 rounded-lg p-3 mb-4 space-y-1 text-sm">
                <div className="flex justify-between">
                  <span className="text-slate-400">Monto inicial:</span>
                  <span className="font-bold">
                    {formatPrice(session.opening_amount)}
                  </span>
                </div>
                <div className="flex justify-between text-green-400">
                  <span>+ Ventas efectivo:</span>
                  <span className="font-bold">
                    {formatPrice(breakdown?.cash.amount ?? 0)}
                  </span>
                </div>
                <div className="flex justify-between text-green-400">
                  <span>+ Propinas efectivo:</span>
                  <span className="font-bold">
                    {formatPrice(breakdown?.cash.tips ?? 0)}
                  </span>
                </div>
                <div className="flex justify-between pt-1 border-t border-slate-700">
                  <span className="text-slate-300 font-semibold">
                    Esperado en caja:
                  </span>
                  <span className="font-bold text-orange-400">
                    {formatPrice(totalCashExpected)}
                  </span>
                </div>
              </div>

              <div className="space-y-4">
                <div>
                  <label className="block text-sm text-slate-400 mb-1">
                    Monto real contado en caja
                  </label>
                  <input
                    type="number"
                    value={closingAmount}
                    onChange={(e) => setClosingAmount(e.target.value)}
                    step="0.01"
                    min="0"
                    autoFocus
                    className="w-full px-4 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-lg font-bold focus:outline-none focus:ring-2 focus:ring-red-500"
                    placeholder="0"
                  />
                  {closingAmount && (
                    <p className="text-xs mt-1 text-slate-400">
                      Diferencia:{" "}
                      <span
                        className={
                          parseFloat(closingAmount) - totalCashExpected >= 0
                            ? "text-green-400"
                            : "text-red-400"
                        }
                      >
                        {formatPrice(
                          parseFloat(closingAmount) - totalCashExpected
                        )}
                      </span>
                    </p>
                  )}
                </div>

                <div>
                  <label className="block text-sm text-slate-400 mb-1">
                    Notas de cierre (opcional)
                  </label>
                  <textarea
                    value={notes}
                    onChange={(e) => setNotes(e.target.value)}
                    rows={2}
                    className="w-full px-4 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-red-500"
                    placeholder="Ej: Faltante de $500 por error de cambio"
                  />
                </div>

                <div className="flex gap-2 pt-2">
                  <button
                    onClick={() => setShowCloseModal(false)}
                    className="flex-1 px-4 py-2 bg-slate-700 hover:bg-slate-600 rounded-lg"
                  >
                    Cancelar
                  </button>
                  <button
                    onClick={handleClose}
                    disabled={closeSession.isPending}
                    className="flex-1 px-4 py-2 bg-red-500 hover:bg-red-600 rounded-lg font-medium disabled:opacity-50 flex items-center justify-center gap-2"
                  >
                    {closeSession.isPending && (
                      <Loader2 size={16} className="animate-spin" />
                    )}
                    Cerrar
                  </button>
                </div>
              </div>
            </div>
          </div>
        </>
      )}
    </>
  );
}
