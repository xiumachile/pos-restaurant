import { useState, useEffect } from "react";
import { useTranslation } from "react-i18next";
import { X, Loader2 } from "lucide-react";
import type { CanvasConfig } from "@/types/floorPlan";

interface Props {
  canvasConfig: CanvasConfig;
  onSave: (config: CanvasConfig) => Promise<void> | void;
  onClose: () => void;
  isOpen: boolean;
}

export function CanvasSettingsPanel({ canvasConfig, onSave, onClose, isOpen }: Props) {
  const { t } = useTranslation();
  const [width, setWidth] = useState<string>(String(canvasConfig.width));
  const [height, setHeight] = useState<string>(String(canvasConfig.height));
  const [gridSize, setGridSize] = useState<string>(String(canvasConfig.grid_size));
  const [isSaving, setIsSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // Resetear el formulario cada vez que se abre el modal
  useEffect(() => {
    if (isOpen) {
      setWidth(String(canvasConfig.width));
      setHeight(String(canvasConfig.height));
      setGridSize(String(canvasConfig.grid_size));
      setError(null);
    }
  }, [isOpen, canvasConfig]);

  if (!isOpen) return null;

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    const w = parseInt(width, 10);
    const h = parseInt(height, 10);
    const g = parseInt(gridSize, 10);

    if (isNaN(w) || isNaN(h) || isNaN(g) || w <= 0 || h <= 0 || g <= 0) {
      setError(t("floor_plan.canvas_settings.invalid", "Ingresa valores numéricos válidos mayores a 0"));
      return;
    }

    // Valores razonables para evitar líenzos extremos
    if (w < 400 || h < 400) {
      setError(t("floor_plan.canvas_settings.too_small", "El lienzo debe medir al menos 400×400"));
      return;
    }
    if (w > 10000 || h > 10000) {
      setError(t("floor_plan.canvas_settings.too_big", "El lienzo no puede superar 10000×10000"));
      return;
    }

    setIsSaving(true);
    setError(null);
    try {
      await onSave({ width: w, height: h, grid_size: g });
    } finally {
      setIsSaving(false);
    }
  };

  const inputClass =
    "w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent";

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm" onClick={onClose}>
      <div
        className="w-full max-w-md bg-slate-800 border border-slate-700 rounded-2xl shadow-2xl"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="flex items-center justify-between p-4 border-b border-slate-700">
          <h2 className="text-lg font-bold text-white">
            {t("floor_plan.canvas_settings.title", "Configuración del Plano")}
          </h2>
          <button onClick={onClose} className="text-slate-400 hover:text-white transition-colors">
            <X size={20} />
          </button>
        </div>

        <form onSubmit={handleSubmit} className="p-4 space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-slate-300 mb-1">
                {t("floor_plan.canvas_settings.width", "Ancho (px)")}
              </label>
              <input
                type="number"
                min={400}
                max={10000}
                value={width}
                onChange={(e) => setWidth(e.target.value)}
                className={inputClass}
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-slate-300 mb-1">
                {t("floor_plan.canvas_settings.height", "Alto (px)")}
              </label>
              <input
                type="number"
                min={400}
                max={10000}
                value={height}
                onChange={(e) => setHeight(e.target.value)}
                className={inputClass}
              />
            </div>
          </div>

          <div>
            <label className="block text-sm font-medium text-slate-300 mb-1">
              {t("floor_plan.canvas_settings.grid_size", "Tamaño de celda (px)")}
            </label>
            <input
              type="number"
              min={5}
              max={200}
              value={gridSize}
              onChange={(e) => setGridSize(e.target.value)}
              className={inputClass}
            />
            <p className="text-xs text-slate-500 mt-1">
              {t("floor_plan.canvas_settings.grid_hint", "Las mesas se ajustarán a este tamaño de celda")}
            </p>
          </div>

          {error && (
            <div className="px-3 py-2 bg-red-900/40 border border-red-700 rounded-lg text-red-200 text-sm">
              {error}
            </div>
          )}

          <div className="flex items-center justify-end gap-2 pt-2">
            <button
              type="button"
              onClick={onClose}
              className="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg transition-colors"
            >
              {t("common.cancel", "Cancelar")}
            </button>
            <button
              type="submit"
              disabled={isSaving}
              className="flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 disabled:bg-slate-700 disabled:text-slate-500 text-white rounded-lg transition-colors"
            >
              {isSaving && <Loader2 size={16} className="animate-spin" />}
              {t("common.save", "Guardar")}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
