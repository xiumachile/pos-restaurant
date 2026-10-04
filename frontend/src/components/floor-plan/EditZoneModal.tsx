import { useState, useEffect } from "react";
import { useTranslation } from "react-i18next";
import { X, Loader2 } from "lucide-react";
import { isValidHexColor } from "@/utils/floorPlanHelpers";
import type { DiningZone, UpdateDiningZonePayload } from "@/types/floorPlan";

interface Props {
  zone: DiningZone | null;
  isOpen: boolean;
  onClose: () => void;
  onSubmit: (uuid: string, payload: UpdateDiningZonePayload) => Promise<void>;
}

const PRESET_COLORS = [
  "#f97316",
  "#10b981",
  "#3b82f6",
  "#8b5cf6",
  "#ec4899",
  "#f59e0b",
  "#ef4444",
  "#64748b",
];

export function EditZoneModal({ isOpen, zone, onClose, onSubmit }: Props) {
  const { t } = useTranslation();
  const [nameEs, setNameEs] = useState("");
  const [nameZh, setNameZh] = useState("");
  const [color, setColor] = useState(PRESET_COLORS[0]);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (zone) {
      setNameEs(zone.name_translations?.es ?? "");
      setNameZh(zone.name_translations?.zh ?? "");
      setColor(zone.color);
      setError(null);
    }
  }, [zone]);

  if (!isOpen || !zone) return null;

  const canSubmit =
    nameEs.trim() !== "" &&
    nameZh.trim() !== "" &&
    isValidHexColor(color) &&
    !isSubmitting;

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!canSubmit) return;

    setIsSubmitting(true);
    setError(null);

    try {
      await onSubmit(zone.uuid, {
        name_translations: {
          es: nameEs.trim(),
          zh: nameZh.trim(),
        },
        color,
      });
      onClose();
    } catch (err: any) {
      const msg =
        err?.response?.data?.message ??
        t("floor_plan.messages.error_generic");
      setError(msg);
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="fixed inset-0 bg-black/60 flex items-center justify-center z-50 p-4">
      <div className="bg-slate-800 rounded-lg shadow-2xl max-w-md w-full border border-slate-700">
        <div className="flex items-center justify-between p-4 border-b border-slate-700">
          <div>
            <h2 className="text-xl font-bold text-white">
              {t("floor_plan.modal.edit.title")}
            </h2>
            <p className="text-sm text-slate-400 font-mono">{zone.code}</p>
          </div>
          <button
            onClick={onClose}
            className="text-slate-400 hover:text-white transition-colors"
          >
            <X size={20} />
          </button>
        </div>

        <form onSubmit={handleSubmit} className="p-4 space-y-4">
          {/* Nombre español */}
          <div>
            <label className="block text-sm font-medium text-slate-300 mb-1">
              {t("floor_plan.modal.fields.name_es")} *
            </label>
            <input
              type="text"
              value={nameEs}
              onChange={(e) => setNameEs(e.target.value)}
              maxLength={100}
              className="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-orange-500"
            />
          </div>

          {/* Nombre chino */}
          <div>
            <label className="block text-sm font-medium text-slate-300 mb-1">
              {t("floor_plan.modal.fields.name_zh")} *
            </label>
            <input
              type="text"
              value={nameZh}
              onChange={(e) => setNameZh(e.target.value)}
              maxLength={100}
              className="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-orange-500"
            />
          </div>

          {/* Color */}
          <div>
            <label className="block text-sm font-medium text-slate-300 mb-1">
              {t("floor_plan.modal.fields.color")}
            </label>
            <div className="flex flex-wrap gap-2 mb-2">
              {PRESET_COLORS.map((c) => (
                <button
                  key={c}
                  type="button"
                  onClick={() => setColor(c)}
                  className={`w-8 h-8 rounded-lg border-2 transition-all ${
                    color === c
                      ? "border-white scale-110"
                      : "border-transparent hover:scale-105"
                  }`}
                  style={{ backgroundColor: c }}
                />
              ))}
            </div>
            <input
              type="text"
              value={color}
              onChange={(e) => setColor(e.target.value)}
              maxLength={7}
              className="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white font-mono focus:outline-none focus:ring-2 focus:ring-orange-500"
            />
          </div>

          {error && (
            <div className="bg-red-900/30 border border-red-700 text-red-300 text-sm p-2 rounded">
              {error}
            </div>
          )}

          <div className="flex gap-2 pt-2">
            <button
              type="button"
              onClick={onClose}
              className="flex-1 px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg transition-colors"
            >
              {t("floor_plan.modal.buttons.cancel")}
            </button>
            <button
              type="submit"
              disabled={!canSubmit}
              className="flex-1 px-4 py-2 bg-orange-500 hover:bg-orange-600 disabled:bg-slate-700 disabled:text-slate-500 text-white rounded-lg transition-colors flex items-center justify-center gap-2"
            >
              {isSubmitting && <Loader2 size={14} className="animate-spin" />}
              {t("floor_plan.modal.buttons.update")}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
