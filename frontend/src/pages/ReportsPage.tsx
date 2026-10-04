import { useState, useMemo } from "react";
import { useTranslation } from "react-i18next";
import { BarChart3, TrendingUp, ShoppingCart } from "lucide-react";
import { DateRangeFilter } from "@/components/reports/DateRangeFilter";
import { ReportsSalesPage } from "./ReportsSalesPage";
import { ReportsPurchasesPage } from "./ReportsPurchasesPage";
import type { DateFilter } from "@/services/reportsService";
import { resolveDateFilter } from "@/services/reportsService";

type ReportsTab = "sales" | "purchases";

/**
 * Formatea un DateFilter a un label legible para el usuario.
 * Se usa tanto en la UI como en el header de impresión.
 */
function formatDateFilterLabel(filter: DateFilter, t: (key: string) => string): string {
  if (filter.preset === "custom" && filter.from_date && filter.to_date) {
    return `${filter.from_date} → ${filter.to_date}`;
  }

  const presetLabels: Record<DateFilter["preset"], string> = {
    today: t("reports.filters.presets.today"),
    yesterday: t("reports.filters.presets.yesterday"),
    last_7_days: t("reports.filters.presets.last_7_days"),
    last_30_days: t("reports.filters.presets.last_30_days"),
    this_month: t("reports.filters.presets.this_month"),
    last_month: t("reports.filters.presets.last_month"),
    this_year: t("reports.filters.presets.this_year"),
    custom: t("reports.filters.presets.custom"),
  };

  const label = presetLabels[filter.preset];

  // Agregar fechas concretas para más claridad
  if (filter.preset !== "custom") {
    const { from_date, to_date } = resolveDateFilter(filter);
    return `${label} (${from_date} → ${to_date})`;
  }

  return label;
}

export function ReportsPage() {
  const { t } = useTranslation();
  const [activeTab, setActiveTab] = useState<ReportsTab>("sales");
  const [dateFilter, setDateFilter] = useState<DateFilter>({
    preset: "last_7_days",
  });

  const dateRangeLabel = useMemo(
    () => formatDateFilterLabel(dateFilter, t),
    [dateFilter, t]
  );

  return (
    <div className="max-w-7xl mx-auto p-6 space-y-6">
      {/* Header */}
      <div className="flex items-center gap-3 no-print" data-print-hide="true">
        <BarChart3 size={32} className="text-orange-400" />
        <div>
          <h1 className="text-3xl font-bold text-white">{t("reports.title")}</h1>
          <p className="text-slate-400">{t("reports.subtitle")}</p>
        </div>
      </div>

      {/* Tabs */}
      <div className="flex gap-2 border-b border-slate-700 no-print" data-print-hide="true">
        <button
          onClick={() => setActiveTab("sales")}
          className={`flex items-center gap-2 px-4 py-2.5 font-medium transition-colors border-b-2 ${
            activeTab === "sales"
              ? "border-orange-500 text-orange-400"
              : "border-transparent text-slate-400 hover:text-white"
          }`}
        >
          <TrendingUp size={18} />
          <span>{t("reports.tabs.sales")}</span>
        </button>
        <button
          onClick={() => setActiveTab("purchases")}
          className={`flex items-center gap-2 px-4 py-2.5 font-medium transition-colors border-b-2 ${
            activeTab === "purchases"
              ? "border-orange-500 text-orange-400"
              : "border-transparent text-slate-400 hover:text-white"
          }`}
        >
          <ShoppingCart size={18} />
          <span>{t("reports.tabs.purchases")}</span>
        </button>
      </div>

      {/* Filtro de fechas (compartido entre tabs) */}
      <div className="no-print" data-print-hide="true">
        <DateRangeFilter value={dateFilter} onChange={setDateFilter} />
      </div>

      {/* Contenido del tab activo */}
      {activeTab === "sales" ? (
        <ReportsSalesPage dateFilter={dateFilter} dateRangeLabel={dateRangeLabel} />
      ) : (
        <ReportsPurchasesPage dateFilter={dateFilter} dateRangeLabel={dateRangeLabel} />
      )}
    </div>
  );
}
