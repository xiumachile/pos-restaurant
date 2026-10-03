import { AlertTriangle, CheckCircle2, Package } from "lucide-react";
import type { RawIngredient } from "@/services/recipeService";

interface IngredientStockCardProps {
  ingredient: RawIngredient;
  isSelected: boolean;
  onClick: () => void;
}

const UNIT_LABELS: Record<string, string> = {
  g: "g",
  kg: "kg",
  ml: "ml",
  l: "l",
  un: "un",
};

function formatUnit(unit: string | null): string {
  if (!unit) return "";
  return UNIT_LABELS[unit] ?? unit;
}

function formatStock(qty: number): string {
  if (qty >= 1000) {
    return qty.toLocaleString("es-CL", {
      minimumFractionDigits: 0,
      maximumFractionDigits: 1,
    });
  }
  return qty.toLocaleString("es-CL", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

function getStockStatus(ingredient: RawIngredient): "ok" | "low" | "out" {
  if ((ingredient.current_stock_base ?? 0) <= 0) return "out";
  if (ingredient.is_low_stock) return "low";
  return "ok";
}

export function IngredientStockCard({
  ingredient,
  isSelected,
  onClick,
}: IngredientStockCardProps) {
  const status = getStockStatus(ingredient);
  const translatedName =
    ingredient.name_translations?.es ??
    ingredient.name_translations?.["zh-CN"] ??
    ingredient.sku;

  const statusConfig = {
    ok: {
      bg: "bg-slate-800/50",
      border: "border-slate-700",
      selectedBorder: "border-orange-500",
      text: "text-slate-300",
      icon: <CheckCircle2 size={16} className="text-emerald-400" />,
    },
    low: {
      bg: "bg-yellow-900/10",
      border: "border-yellow-800/50",
      selectedBorder: "border-orange-500",
      text: "text-yellow-300",
      icon: <AlertTriangle size={16} className="text-yellow-400" />,
    },
    out: {
      bg: "bg-red-900/10",
      border: "border-red-800/50",
      selectedBorder: "border-orange-500",
      text: "text-red-300",
      icon: <AlertTriangle size={16} className="text-red-400" />,
    },
  };

  const cfg = statusConfig[status];

  return (
    <button
      onClick={onClick}
      className={`w-full text-left p-3 rounded-lg border transition-all ${cfg.bg} ${
        isSelected ? cfg.selectedBorder + " ring-2 ring-orange-500/30" : cfg.border
      } hover:border-slate-500`}
    >
      <div className="flex items-start justify-between gap-2 mb-1">
        <div className="flex-1 min-w-0">
          <p className="font-medium text-white truncate" title={translatedName}>
            {translatedName}
          </p>
          <p className="text-xs text-slate-500 font-mono">{ingredient.sku}</p>
        </div>
        {cfg.icon}
      </div>

      <div className="flex items-baseline justify-between mt-2">
        <span className={`font-mono font-bold ${cfg.text}`}>
          {formatStock(ingredient.current_stock_base ?? 0)}
        </span>
        <span className="text-xs text-slate-500">
          {formatUnit(ingredient.base_unit)}
        </span>
      </div>
    </button>
  );
}
