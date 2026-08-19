import { useState, useEffect } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { tipService } from "@/services/tipService";
import type { TipPolicy } from "@/types/tips";
import {
  Settings,
  Users,
  User,
  Percent,
  CreditCard,
  Banknote,
  Wallet,
  Loader2,
  CheckCircle2,
  AlertCircle,
} from "lucide-react";

export function TipSettingsPage() {
  const queryClient = useQueryClient();
  const [policy, setPolicy] = useState<TipPolicy | null>(null);
  const [saved, setSaved] = useState(false);

  const { data, isLoading } = useQuery({
    queryKey: ["tip-policy"],
    queryFn: tipService.getPolicy,
  });

  const mutation = useMutation({
    mutationFn: tipService.updatePolicy,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["tip-policy"] });
      setSaved(true);
      setTimeout(() => setSaved(false), 3000);
    },
  });

  useEffect(() => {
    if (data && !policy) {
      setPolicy(data);
    }
  }, [data, policy]);

  const handleSave = () => {
    if (!policy) return;
    mutation.mutate(policy);
  };

  if (isLoading || !policy) {
    return (
      <div className="flex items-center justify-center h-64">
        <Loader2 className="animate-spin text-orange-500" size={48} />
      </div>
    );
  }

  return (
    <div className="max-w-3xl mx-auto space-y-6">
      <div>
        <h1 className="text-3xl font-bold flex items-center gap-3">
          <Settings size={28} className="text-orange-400" />
          Configuración de Propinas
        </h1>
        <p className="text-slate-400 mt-1">
          Define cómo se reparten las propinas en tu restaurante
        </p>
      </div>

      {/* Mensaje de éxito */}
      {saved && (
        <div className="bg-green-900/30 border border-green-700 rounded-lg p-3 flex items-center gap-2 text-green-300">
          <CheckCircle2 size={18} />
          <span>Política guardada correctamente</span>
        </div>
      )}

      {/* Mensaje de error */}
      {mutation.isError && (
        <div className="bg-red-900/30 border border-red-700 rounded-lg p-3 flex items-center gap-2 text-red-300">
          <AlertCircle size={18} />
          <span>{(mutation.error as Error).message}</span>
        </div>
      )}

      {/* Política de reparto */}
      <div className="bg-slate-800/50 border border-slate-700 rounded-xl p-6">
        <h2 className="text-lg font-bold mb-4 flex items-center gap-2">
          <Users size={20} className="text-blue-400" />
          Política de Reparto
        </h2>

        <div className="space-y-3">
          {/* Opción 1: waiter_keeps */}
          <label
            className={`flex items-start gap-3 p-4 rounded-lg border-2 cursor-pointer transition-all ${
              policy.policy_type === "waiter_keeps"
                ? "border-orange-500 bg-orange-500/10"
                : "border-slate-700 bg-slate-800 hover:border-slate-600"
            }`}
          >
            <input
              type="radio"
              name="policy_type"
              checked={policy.policy_type === "waiter_keeps"}
              onChange={() =>
                setPolicy({ ...policy, policy_type: "waiter_keeps" })
              }
              className="mt-1"
            />
            <div className="flex-1">
              <div className="flex items-center gap-2 font-semibold">
                <User size={16} className="text-orange-400" />
                Propina íntegra al garzón que atendió
              </div>
              <p className="text-sm text-slate-400 mt-1">
                Cada garzón se lleva el 100% de las propinas de las mesas que atendió.
              </p>
            </div>
          </label>

          {/* Opción 2: shared_pool */}
          <label
            className={`flex items-start gap-3 p-4 rounded-lg border-2 cursor-pointer transition-all ${
              policy.policy_type === "shared_pool"
                ? "border-orange-500 bg-orange-500/10"
                : "border-slate-700 bg-slate-800 hover:border-slate-600"
            }`}
          >
            <input
              type="radio"
              name="policy_type"
              checked={policy.policy_type === "shared_pool"}
              onChange={() =>
                setPolicy({ ...policy, policy_type: "shared_pool" })
              }
              className="mt-1"
            />
            <div className="flex-1">
              <div className="flex items-center gap-2 font-semibold">
                <Users size={16} className="text-blue-400" />
                Pozo común repartido
              </div>
              <p className="text-sm text-slate-400 mt-1">
                Todas las propinas se juntan en un pozo y se reparten entre los garzones del turno.
              </p>

              {policy.policy_type === "shared_pool" && (
                <div className="mt-3 space-y-2">
                  <label className="flex items-center gap-2 text-sm">
                    <input
                      type="radio"
                      checked={policy.pool_distribution === "equal"}
                      onChange={() =>
                        setPolicy({ ...policy, pool_distribution: "equal" })
                      }
                    />
                    Partes iguales entre garzones
                  </label>
                  <label className="flex items-center gap-2 text-sm">
                    <input
                      type="radio"
                      checked={policy.pool_distribution === "by_hours"}
                      onChange={() =>
                        setPolicy({ ...policy, pool_distribution: "by_hours" })
                      }
                    />
                    Proporcional a horas trabajadas
                  </label>
                </div>
              )}
            </div>
          </label>

          {/* Opción 3: percentage_split */}
          <label
            className={`flex items-start gap-3 p-4 rounded-lg border-2 cursor-pointer transition-all ${
              policy.policy_type === "percentage_split"
                ? "border-orange-500 bg-orange-500/10"
                : "border-slate-700 bg-slate-800 hover:border-slate-600"
            }`}
          >
            <input
              type="radio"
              name="policy_type"
              checked={policy.policy_type === "percentage_split"}
              onChange={() =>
                setPolicy({ ...policy, policy_type: "percentage_split" })
              }
              className="mt-1"
            />
            <div className="flex-1">
              <div className="flex items-center gap-2 font-semibold">
                <Percent size={16} className="text-purple-400" />
                Reparto porcentual
              </div>
              <p className="text-sm text-slate-400 mt-1">
                Un porcentaje va al garzón y el resto a un pozo común.
              </p>

              {policy.policy_type === "percentage_split" && (
                <div className="mt-3 grid grid-cols-2 gap-3">
                  <div>
                    <label className="block text-sm text-slate-400 mb-1">
                      % Garzón
                    </label>
                    <input
                      type="number"
                      value={String(policy.waiter_percentage ?? "")}
                      onChange={(e) =>
                        setPolicy({
                          ...policy,
                          waiter_percentage: parseFloat(e.target.value) || 0,
                          pool_percentage: 100 - (parseFloat(e.target.value) || 0),
                        })
                      }
                      min="0"
                      max="100"
                      className="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-orange-500"
                    />
                  </div>
                  <div>
                    <label className="block text-sm text-slate-400 mb-1">
                      % Pozo común
                    </label>
                    <input
                      type="number"
                      value={String(policy.pool_percentage ?? "")}
                      readOnly
                      className="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-slate-400"
                    />
                  </div>
                </div>
              )}
            </div>
          </label>
        </div>
      </div>

      {/* Propinas con tarjeta */}
      <div className="bg-slate-800/50 border border-slate-700 rounded-xl p-6">
        <h2 className="text-lg font-bold mb-4 flex items-center gap-2">
          <CreditCard size={20} className="text-purple-400" />
          Propinas con Tarjeta
        </h2>
        <p className="text-sm text-slate-400 mb-4">
          Cuando un cliente paga la propina con tarjeta, ¿cómo se entrega al garzón?
        </p>

        <div className="space-y-3">
          <label className="flex items-center gap-3 p-3 rounded-lg border border-slate-700 bg-slate-800 cursor-pointer hover:border-slate-600">
            <input
              type="radio"
              checked={policy.card_tip_handling === "cash_payout"}
              onChange={() =>
                setPolicy({ ...policy, card_tip_handling: "cash_payout" })
              }
            />
            <Banknote size={16} className="text-green-400" />
            <span>Entregar en efectivo (sale de caja)</span>
          </label>

          <label className="flex items-center gap-3 p-3 rounded-lg border border-slate-700 bg-slate-800 cursor-pointer hover:border-slate-600">
            <input
              type="radio"
              checked={policy.card_tip_handling === "payroll"}
              onChange={() =>
                setPolicy({ ...policy, card_tip_handling: "payroll" })
              }
            />
            <Wallet size={16} className="text-blue-400" />
            <span>Acumular para nómina (no sale de caja)</span>
          </label>

          <label className="flex items-center gap-3 p-3 rounded-lg border border-slate-700 bg-slate-800 cursor-pointer hover:border-slate-600">
            <input
              type="radio"
              checked={policy.card_tip_handling === "mixed"}
              onChange={() =>
                setPolicy({ ...policy, card_tip_handling: "mixed" })
              }
            />
            <CreditCard size={16} className="text-orange-400" />
            <span>Mixto: efectivo inmediato, tarjeta a nómina</span>
          </label>
        </div>
      </div>

      {/* Botón guardar */}
      <div className="flex gap-3">
        <button
          onClick={handleSave}
          disabled={mutation.isPending}
          className="flex-1 px-6 py-3 bg-orange-500 hover:bg-orange-600 rounded-lg font-bold text-white disabled:opacity-50 flex items-center justify-center gap-2"
        >
          {mutation.isPending ? (
            <>
              <Loader2 size={18} className="animate-spin" />
              Guardando...
            </>
          ) : (
            <>
              <CheckCircle2 size={18} />
              Guardar Cambios
            </>
          )}
        </button>
      </div>
    </div>
  );
}
