import { useState } from "react";
import { useTranslation } from "react-i18next";
import { Loader2, Filter, History } from "lucide-react";
import { useIngredientMovements } from "@/hooks/useInventory";
import {
  MOVEMENT_TYPE_CONFIG,
  type MovementType,
  type RawIngredientMovement,
} from "@/services/inventoryService";

interface MovementHistoryTableProps {
  ingredientUuid: string | null;
  ingredientName: string;
}

const LIMIT_OPTIONS = [25, 50, 100, 200];

function formatQuantity(qty: number, type: MovementType): string {
  const prefix =
    type === "in_purchase" || type === "in_production" ? "+" : "";
  return `${prefix}${qty.toLocaleString("es-CL", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })}`;
}

function formatDateTime(iso: string, t: (key: string, opts?: any) => string): string {
  const date = new Date(iso);
  const now = new Date();
  const diffMs = now.getTime() - date.getTime();
  const diffMins = Math.floor(diffMs / 60000);
  const diffHours = Math.floor(diffMins / 60);
  const diffDays = Math.floor(diffHours / 24);

  if (diffMins < 1) return t("inventory.movements.time.just_now");
  if (diffMins < 60) return t("inventory.movements.time.minutes_ago", { count: diffMins });
  if (diffHours < 24) return t("inventory.movements.time.hours_ago", { count: diffHours });
  if (diffDays < 7) return t("inventory.movements.time.days_ago", { count: diffDays });

  return date.toLocaleDateString("es-CL", {
    day: "2-digit",
    month: "short",
    year: date.getFullYear() !== now.getFullYear() ? "numeric" : undefined,
  });
}

function MovementRow({
  movement,
  t,
}: {
  movement: RawIngredientMovement;
  t: (key: string, opts?: any) => string;
}) {
  const config = MOVEMENT_TYPE_CONFIG[movement.type];
  const isPositive =
    movement.type === "in_purchase" ||
    movement.type === "in_production" ||
    (movement.type === "adjustment" && movement.quantity_base > 0);

  // Traducir referencia
  let referenceText: React.ReactNode;
  if (movement.reference_type) {
    if (movement.reference_type === "order") {
      referenceText = t("inventory.movements.reference.order", { id: movement.reference_id });
    } else if (movement.reference_type === "production_batch") {
      referenceText = t("inventory.movements.reference.production_batch");
    } else if (movement.reference_type === "purchase") {
      referenceText = t("inventory.movements.reference.purchase");
    } else {
      referenceText = movement.reference_type;
    }
  }

  // Traducir tipo
  const typeLabel = t(`inventory.movements.types.${movement.type}`);

  return (
    <tr className="border-b border-slate-800 hover:bg-slate-800/30 transition-colors">
      <td className="py-3 px-4 text-sm text-slate-300">
        <div className="flex flex-col">
          <span className="font-medium">{formatDateTime(movement.created_at, t)}</span>
          <span className="text-xs text-slate-500">
            {new Date(movement.created_at).toLocaleTimeString("es-CL", {
              hour: "2-digit",
              minute: "2-digit",
            })}
          </span>
        </div>
      </td>
      <td className="py-3 px-4">
        <span
          className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-full border text-xs ${config.color}`}
        >
          <span>{config.icon}</span>
          <span>{typeLabel}</span>
        </span>
      </td>
      <td className="py-3 px-4 text-sm font-mono">
        <span className={isPositive ? "text-emerald-400" : "text-red-400"}>
          {formatQuantity(movement.quantity_base, movement.type)}
        </span>
      </td>
      <td className="py-3 px-4 text-sm font-mono text-slate-300">
        {movement.balance_after.toLocaleString("es-CL", {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2,
        })}
      </td>
      <td className="py-3 px-4 text-sm text-slate-400 max-w-xs">
        {referenceText ? (
          <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-slate-700/50 text-xs">
            <span>🔗</span>
            <span>{referenceText}</span>
          </span>
        ) : (
          <span className="text-slate-500 text-xs">—</span>
        )}
      </td>
      <td className="py-3 px-4 text-sm text-slate-400 max-w-xs truncate" title={movement.reason ?? ""}>
        {movement.reason ?? <span className="text-slate-500 text-xs">—</span>}
      </td>
    </tr>
  );
}

export function MovementHistoryTable({
  ingredientUuid,
  ingredientName,
}: MovementHistoryTableProps) {
  const { t } = useTranslation();
  const [typeFilter, setTypeFilter] = useState<MovementType | "all">("all");
  const [limit, setLimit] = useState<number>(100);

  const { data: movements = [], isLoading, error } = useIngredientMovements(
    ingredientUuid,
    { type: typeFilter, limit }
  );

  const TYPE_OPTIONS: Array<{ value: MovementType | "all"; label: string }> = [
    { value: "all", label: t("inventory.movements.types.all") },
    { value: "in_purchase", label: t("inventory.movements.types.in_purchase") },
    { value: "in_production", label: t("inventory.movements.types.in_production") },
    { value: "out_consumption", label: t("inventory.movements.types.out_consumption") },
    { value: "out_waste", label: t("inventory.movements.types.out_waste") },
    { value: "adjustment", label: t("inventory.movements.types.adjustment") },
  ];

  if (!ingredientUuid) {
    return (
      <div className="bg-slate-800/50 border border-slate-700 rounded-lg p-12 text-center">
        <History className="mx-auto text-slate-500 mb-3" size={48} />
        <p className="text-slate-400">
          {t("inventory.movements.select_ingredient")}
        </p>
      </div>
    );
  }

  return (
    <div className="bg-slate-800/30 border border-slate-700 rounded-lg overflow-hidden">
      {/* Header con filtros */}
      <div className="p-4 border-b border-slate-700 bg-slate-800/50">
        <div className="flex items-center justify-between mb-3">
          <div className="flex items-center gap-2">
            <History size={18} className="text-orange-400" />
            <h3 className="font-bold text-white">{t("inventory.movements.title")}</h3>
          </div>
          <span className="text-sm text-slate-400">
            {ingredientName} · {movements.length}
          </span>
        </div>

        <div className="flex flex-wrap items-center gap-3">
          <div className="flex items-center gap-2">
            <Filter size={14} className="text-slate-400" />
            <select
              value={typeFilter}
              onChange={(e) => setTypeFilter(e.target.value as MovementType | "all")}
              className="bg-slate-700 border border-slate-600 rounded px-3 py-1.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-orange-500"
            >
              {TYPE_OPTIONS.map((opt) => (
                <option key={opt.value} value={opt.value}>
                  {opt.label}
                </option>
              ))}
            </select>
          </div>

          <div className="flex items-center gap-2">
            <span className="text-sm text-slate-400">{t("inventory.movements.filter.limit")}:</span>
            <select
              value={limit}
              onChange={(e) => setLimit(Number(e.target.value))}
              className="bg-slate-700 border border-slate-600 rounded px-3 py-1.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-orange-500"
            >
              {LIMIT_OPTIONS.map((l) => (
                <option key={l} value={l}>
                  {l}
                </option>
              ))}
            </select>
          </div>
        </div>
      </div>

      {/* Contenido */}
      {isLoading ? (
        <div className="flex items-center justify-center py-16">
          <Loader2 className="animate-spin text-orange-500" size={32} />
        </div>
      ) : error ? (
        <div className="p-8 text-center text-red-400">
          {t("inventory.movements.error")}: {error.message}
        </div>
      ) : movements.length === 0 ? (
        <div className="p-12 text-center">
          <History className="mx-auto text-slate-500 mb-3" size={32} />
          <p className="text-slate-400 text-sm">
            {t("inventory.movements.empty")}
          </p>
        </div>
      ) : (
        <div className="overflow-x-auto">
          <table className="w-full">
            <thead className="bg-slate-800/70">
              <tr className="text-xs text-slate-400 uppercase tracking-wider">
                <th className="py-2 px-4 text-left">{t("inventory.movements.columns.date")}</th>
                <th className="py-2 px-4 text-left">{t("inventory.movements.columns.type")}</th>
                <th className="py-2 px-4 text-right">{t("inventory.movements.columns.quantity")}</th>
                <th className="py-2 px-4 text-right">{t("inventory.movements.columns.balance")}</th>
                <th className="py-2 px-4 text-left">{t("inventory.movements.columns.reference")}</th>
                <th className="py-2 px-4 text-left">{t("inventory.movements.columns.reason")}</th>
              </tr>
            </thead>
            <tbody>
              {movements.map((m) => (
                <MovementRow key={m.uuid} movement={m} t={t} />
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
