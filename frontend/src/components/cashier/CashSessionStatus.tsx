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
  Banknote,
  CreditCard,
  Building2,
  Gift,
  List,
} from "lucide-react";
import { SessionSalesModal } from "./SessionSalesModal";

interface CashSessionStatusProps {
  session: CashSession | null;
}

/**
 * Barra compacta de estado de caja.
 * Diseño minimalista: una sola fila con lo esencial,
 * dejando protagonismo a las cuentas por cobrar.
 */
export function CashSessionStatus({ session }: CashSessionStatusProps) {
  const [showOpenModal, setShowOpenModal] = useState(false);
  const [showCloseModal, setShowCloseModal] = useState(false);
  const [openingAmount, setOpeningAmount] = useState<string>("0");
  const [closingAmount, setClosingAmount] = useState<string>("");
  const [notes, setNotes] = useState("");
  const [showSalesModal, setShowSalesModal] = useState(false);

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

  const b = session?.breakdown;
  const totalCashExpected = session?.total_cash_expected ?? 0;

  return (
    <>
      {/* Barra compacta */}
      <div className="bg-slate-800/50 border border-slate-700 rounded-xl px-4 py-3 flex flex-wrap items-center gap-x-5 gap-y-2">
        {/* Estado + acción */}
        <div className="flex items-center gap-2">
          <span
            className={`w-2.5 h-2.5 rounded-full ${
              session ? "bg-green-400" : "bg-slate-500"
            }`}
          />
          <span className="font-semibold">
            {session ? "Caja abierta" : "Caja cerrada"}
          </span>
          {session ? (
            <>
            <button
              onClick={() => setShowSalesModal(true)}
              className="ml-1 flex items-center gap-1 px-2.5 py-1 bg-blue-500/20 hover:bg-blue-500/30 border border-blue-700/50 rounded text-blue-300 text-xs font-medium transition-colors"
              title="Ver ventas cobradas en esta sesión"
            >
              <List size={12} />
              Ventas
            </button>
            <button
              onClick={() => setShowCloseModal(true)}
              className="ml-1 flex items-center gap-1 px-2.5 py-1 bg-red-500/20 hover:bg-red-500/30 border border-red-700/50 rounded text-red-300 text-xs font-medium transition-colors"
            >
              <StopCircle size={12} />
              Cerrar
            </button>
            </>
          ) : (
            <button
              onClick={() => setShowOpenModal(true)}
              className="ml-1 flex items-center gap-1 px-2.5 py-1 bg-green-500/20 hover:bg-green-500/30 border border-green-700/50 rounded text-green-300 text-xs font-medium transition-colors"
            >
              <PlayCircle size={12} />
              Abrir
            </button>
          )}
        </div>

        {session && (
          <>
            <div className="h-6 w-px bg-slate-700" />

            {/* Monto inicial */}
            <div className="text-sm">
              <span className="text-slate-400">Inicial: </span>
              <span className="font-semibold">
                {formatPrice(session.opening_amount)}
              </span>
            </div>

            {/* Desglose compacto en línea */}
            <div className="flex items-center gap-3 text-sm">
              <span className="flex items-center gap-1" title="Efectivo">
                <Banknote size={14} className="text-green-400" />
                <span className="font-semibold text-green-400">
                  {formatPrice(b?.cash.amount ?? 0)}
                </span>
                <span className="text-xs text-slate-500">
                  ({b?.cash.count ?? 0})
                </span>
              </span>
              <span className="flex items-center gap-1" title="Tarjeta">
                <CreditCard size={14} className="text-blue-400" />
                <span className="font-semibold text-blue-400">
                  {formatPrice(b?.card.amount ?? 0)}
                </span>
                <span className="text-xs text-slate-500">
                  ({b?.card.count ?? 0})
                </span>
              </span>
              <span className="flex items-center gap-1" title="Transferencia">
                <Building2 size={14} className="text-purple-400" />
                <span className="font-semibold text-purple-400">
                  {formatPrice(b?.transfer.amount ?? 0)}
                </span>
                <span className="text-xs text-slate-500">
                  ({b?.transfer.count ?? 0})
                </span>
              </span>
              <span className="flex items-center gap-1" title="Gift Card">
                <Gift size={14} className="text-amber-400" />
                <span className="font-semibold text-amber-400">
                  {formatPrice(b?.gift_card.amount ?? 0)}
                </span>
                <span className="text-xs text-slate-500">
                  ({b?.gift_card.count ?? 0})
                </span>
              </span>
            </div>

            <div className="h-6 w-px bg-slate-700" />

            {/* Esperado en caja (destacado) */}
            <div className="text-sm ml-auto">
              <span className="text-slate-400">Esperado en caja: </span>
              <span className="font-bold text-green-400 text-base">
                {formatPrice(totalCashExpected)}
              </span>
            </div>
          </>
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

      {/* Modal Cerrar Caja (Arqueo) */}
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
                    {formatPrice(b?.cash.amount ?? 0)}
                  </span>
                </div>
                <div className="flex justify-between text-green-400">
                  <span>+ Propinas efectivo:</span>
                  <span className="font-bold">
                    {formatPrice(b?.cash.tips ?? 0)}
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

      {/* Modal de ventas de la sesión */}
      <SessionSalesModal
        isOpen={showSalesModal}
        onClose={() => setShowSalesModal(false)}
      />
    </>
  );
}
