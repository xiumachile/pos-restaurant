import { useState } from "react";
import { useTranslation } from "react-i18next";
import { X, Lock, AlertTriangle } from "lucide-react";

interface AdminAuthModalProps {
  open: boolean;
  action: "remove" | "reduce";
  itemName: string;
  onConfirm: () => void;
  onCancel: () => void;
}

export function AdminAuthModal({ open, action, itemName, onConfirm, onCancel }: AdminAuthModalProps) {
  const { t } = useTranslation();
  const [pin, setPin] = useState("");
  const [error, setError] = useState("");

  if (!open) return null;

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    // TODO: Reemplazar "0000" con la lógica real de validación de PIN de administrador
    if (pin === "0000" || pin === "1234") { 
      onConfirm();
      setPin("");
      setError("");
    } else {
      setError(t("admin_auth.invalid_pin", "PIN de administrador inválido"));
    }
  };

  return (
    <div className="fixed inset-0 z-[100] flex items-center justify-center bg-black/80 p-4">
      <div className="bg-slate-900 border border-red-800/50 rounded-xl shadow-2xl max-w-sm w-full p-6">
        <div className="flex items-center justify-between mb-4">
          <div className="flex items-center gap-2">
            <Lock size={20} className="text-red-500" />
            <h2 className="text-lg font-bold text-white">{t("admin_auth.title", "Autorización Requerida")}</h2>
          </div>
          <button onClick={onCancel} className="p-1 hover:bg-slate-800 rounded-lg">
            <X size={18} className="text-slate-400" />
          </button>
        </div>

        <div className="bg-red-900/20 border border-red-800/50 rounded-lg p-3 mb-4 flex items-start gap-2">
          <AlertTriangle size={18} className="text-red-400 flex-shrink-0 mt-0.5" />
          <p className="text-sm text-red-200">
            {action === "remove" 
              ? t("admin_auth.remove_warning", "Está a punto de eliminar el item") 
              : t("admin_auth.reduce_warning", "Está a punto de reducir la cantidad a 0")}
            : <strong>{itemName}</strong>. {t("admin_auth.pin_required", "Ingrese el PIN de administrador para continuar.")}
          </p>
        </div>

        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <input
              type="password"
              inputMode="numeric"
              pattern="[0-9]*"
              value={pin}
              onChange={(e) => {
                setPin(e.target.value.replace(/\D/g, "").slice(0, 6));
                setError("");
              }}
              className="w-full bg-slate-800 border border-slate-700 rounded-lg py-3 px-4 text-white text-center text-2xl tracking-widest placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-red-500/50 focus:border-red-500"
              placeholder="••••"
              autoFocus
            />
            {error && <p className="text-sm text-red-400 mt-2 text-center">{error}</p>}
          </div>

          <div className="flex gap-3">
            <button
              type="button"
              onClick={onCancel}
              className="flex-1 px-4 py-2.5 bg-slate-800 hover:bg-slate-700 border border-slate-700 rounded-lg text-slate-300 font-medium transition-colors"
            >
              {t("common.cancel", "Cancelar")}
            </button>
            <button
              type="submit"
              disabled={pin.length < 4}
              className="flex-1 px-4 py-2.5 bg-red-600 hover:bg-red-700 disabled:bg-slate-700 disabled:cursor-not-allowed text-white rounded-lg font-medium transition-colors"
            >
              {t("common.authorize", "Autorizar")}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
