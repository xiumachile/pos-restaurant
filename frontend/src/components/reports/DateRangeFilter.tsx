import { useState, useEffect } from "react";
import { useTranslation } from "react-i18next";
import { Calendar, ChevronDown, X } from "lucide-react";
import type { DateFilter } from "@/services/reportsService";

interface DateRangeFilterProps {
  value: DateFilter;
  onChange: (filter: DateFilter) => void;
}

const PRESETS: DateFilter["preset"][] = [
  "today",
  "yesterday",
  "last_7_days",
  "last_30_days",
  "this_month",
  "last_month",
  "this_year",
  "custom",
];

export function DateRangeFilter({ value, onChange }: DateRangeFilterProps) {
  const { t } = useTranslation();

  const [showCustom, setShowCustom] = useState(value.preset === "custom");
  const [customFrom, setCustomFrom] = useState(value.from_date ?? "");
  const [customTo, setCustomTo] = useState(value.to_date ?? "");

  useEffect(() => {
    if (value.preset === "custom") {
      setShowCustom(true);
      setCustomFrom(value.from_date ?? "");
      setCustomTo(value.to_date ?? "");
    }
  }, [value.preset]);

  const handlePresetClick = (preset: DateFilter["preset"]) => {
    if (preset === "custom") {
      setShowCustom(true);
      onChange({
        preset: "custom",
        from_date: customFrom || new Date().toISOString().split("T")[0],
        to_date: customTo || new Date().toISOString().split("T")[0],
      });
    } else {
      setShowCustom(false);
      onChange({ preset });
    }
  };

  const handleApplyCustom = () => {
    if (customFrom && customTo && customFrom <= customTo) {
      onChange({
        preset: "custom",
        from_date: customFrom,
        to_date: customTo,
      });
    }
  };

  const canApplyCustom = customFrom && customTo && customFrom <= customTo;

  return (
    <div className="bg-slate-800/30 border border-slate-700 rounded-lg p-4">
      <div className="flex items-center gap-2 mb-3">
        <Calendar size={18} className="text-orange-400" />
        <h3 className="font-semibold text-white">{t("reports.filters.title")}</h3>
      </div>

      {/* Botones de presets */}
      <div className="flex flex-wrap gap-2 mb-3">
        {PRESETS.map((preset) => (
          <button
            key={preset}
            onClick={() => handlePresetClick(preset)}
            className={`px-3 py-1.5 rounded-lg text-sm font-medium transition-colors ${
              value.preset === preset
                ? "bg-orange-500 text-white"
                : "bg-slate-700 text-slate-300 hover:bg-slate-600"
            }`}
          >
            {t(`reports.filters.presets.${preset}`)}
          </button>
        ))}
      </div>

      {/* Rango custom */}
      {showCustom && (
        <div className="flex flex-wrap items-center gap-3 pt-3 border-t border-slate-700">
          <div className="flex items-center gap-2">
            <label className="text-sm text-slate-400">
              {t("reports.filters.from")}:
            </label>
            <input
              type="date"
              value={customFrom}
              onChange={(e) => setCustomFrom(e.target.value)}
              max={customTo || undefined}
              className="px-3 py-1.5 bg-slate-800 border border-slate-600 rounded-lg text-white text-sm focus:outline-none focus:ring-2 focus:ring-orange-500"
            />
          </div>
          <div className="flex items-center gap-2">
            <label className="text-sm text-slate-400">
              {t("reports.filters.to")}:
            </label>
            <input
              type="date"
              value={customTo}
              onChange={(e) => setCustomTo(e.target.value)}
              min={customFrom || undefined}
              className="px-3 py-1.5 bg-slate-800 border border-slate-600 rounded-lg text-white text-sm focus:outline-none focus:ring-2 focus:ring-orange-500"
            />
          </div>
          <button
            onClick={handleApplyCustom}
            disabled={!canApplyCustom}
            className="px-4 py-1.5 bg-orange-500 hover:bg-orange-600 disabled:bg-slate-700 disabled:text-slate-500 disabled:cursor-not-allowed text-white rounded-lg text-sm font-medium transition-colors"
          >
            {t("reports.filters.apply")}
          </button>
        </div>
      )}
    </div>
  );
}
