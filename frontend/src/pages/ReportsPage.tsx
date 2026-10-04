import { useState } from "react";
import { useTranslation } from "react-i18next";
import { BarChart3, TrendingUp, ShoppingCart } from "lucide-react";
import { DateRangeFilter } from "@/components/reports/DateRangeFilter";
import { ReportsSalesPage } from "./ReportsSalesPage";
import { ReportsPurchasesPage } from "./ReportsPurchasesPage";
import type { DateFilter } from "@/services/reportsService";

type ReportsTab = "sales" | "purchases";

export function ReportsPage() {
  const { t } = useTranslation();
  const [activeTab, setActiveTab] = useState<ReportsTab>("sales");
  const [dateFilter, setDateFilter] = useState<DateFilter>({
    preset: "last_7_days",
  });

  return (
    <div className="max-w-7xl mx-auto p-6 space-y-6">
      {/* Header */}
      <div className="flex items-center gap-3">
        <BarChart3 size={32} className="text-orange-400" />
        <div>
          <h1 className="text-3xl font-bold text-white">{t("reports.title")}</h1>
          <p className="text-slate-400">{t("reports.subtitle")}</p>
        </div>
      </div>

      {/* Tabs */}
      <div className="flex gap-2 border-b border-slate-700">
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
      <DateRangeFilter value={dateFilter} onChange={setDateFilter} />

      {/* Contenido del tab activo */}
      {activeTab === "sales" ? (
        <ReportsSalesPage dateFilter={dateFilter} />
      ) : (
        <ReportsPurchasesPage dateFilter={dateFilter} />
      )}
    </div>
  );
}
