import { useTranslation } from "react-i18next";
import { Square, Armchair, Flower, RectangleHorizontal, Sofa, X } from "lucide-react";

export const DECORATION_TYPES = [
  { id: "wall" as const, label: "Muro", icon: Square, color: "#475569", width: 120, height: 20 },
  { id: "chair" as const, label: "Silla", icon: Armchair, color: "#94a3b8", width: 40, height: 40 },
  { id: "plant" as const, label: "Planta / Flor", icon: Flower, color: "#22c55e", width: 50, height: 50 },
  { id: "counter" as const, label: "Barra / Mostrador", icon: RectangleHorizontal, color: "#d97706", width: 160, height: 40 },
  { id: "sofa" as const, label: "Sofá", icon: Sofa, color: "#6366f1", width: 100, height: 50 },
];

export type DecorationType = typeof DECORATION_TYPES[number]["id"];

interface Props {
  onAddDecoration: (type: DecorationType) => void;
  onClose: () => void;
}

export function DecorationPalette({ onAddDecoration, onClose }: Props) {
  const { t } = useTranslation();
  return (
    <div className="absolute top-4 right-4 bg-slate-800 border border-slate-700 rounded-xl shadow-2xl p-4 z-50 w-64">
      <div className="flex items-center justify-between mb-3">
        <h3 className="text-sm font-bold text-white">{t("floor_plan.decorations.title")}</h3>
        <button onClick={onClose} className="text-slate-400 hover:text-white"><X size={16} /></button>
      </div>
      <div className="grid grid-cols-2 gap-2">
        {DECORATION_TYPES.map((item) => {
          const Icon = item.icon;
          return (
            <button
              key={item.id}
              onClick={() => onAddDecoration(item.id)}
              className="flex flex-col items-center gap-2 p-3 bg-slate-700/50 hover:bg-slate-700 border border-slate-600 hover:border-slate-500 rounded-lg transition-all group"
            >
              <Icon size={24} className="text-slate-300 group-hover:text-white" style={{ color: item.color }} />
              <span className="text-xs font-medium text-slate-200">{t(`floor_plan.decorations.${item.id}`, item.label)}</span>
            </button>
          );
        })}
      </div>
      <p className="text-xs text-slate-500 mt-3 text-center">{t("floor_plan.decorations.hint")}</p>
    </div>
  );
}
