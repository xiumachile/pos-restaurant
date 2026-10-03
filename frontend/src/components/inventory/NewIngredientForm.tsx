import { useState } from "react";
import { useTranslation } from "react-i18next";
import { Loader2, CheckCircle2, AlertTriangle } from "lucide-react";
import { useQueryClient, useMutation } from "@tanstack/react-query";
import apiClient from "@/services/apiClient";

interface NewIngredientFormProps {
  onCreated: (uuid: string) => void;
  onCancel: () => void;
}

const DIMENSION_TYPES = ["mass", "volume", "count"] as const;

const BASE_UNITS = {
  mass: [
    { value: "g", labelKey: "inventory.purchase.units_names.g" },
    { value: "kg", labelKey: "inventory.purchase.units_names.kg" },
    { value: "lb", labelKey: "inventory.purchase.units_names.lb" },
  ],
  volume: [
    { value: "ml", labelKey: "inventory.purchase.units_names.ml" },
    { value: "l", labelKey: "inventory.purchase.units_names.l" },
  ],
  count: [
    { value: "un", labelKey: "inventory.purchase.units_names.un" },
    { value: "doc", labelKey: "inventory.purchase.units_names.doc" },
    { value: "pack", labelKey: "inventory.purchase.units_names.pack" },
  ],
};

export function NewIngredientForm({ onCreated, onCancel }: NewIngredientFormProps) {
  const { t } = useTranslation();
  const queryClient = useQueryClient();

  const [sku, setSku] = useState("");
  const [nameEs, setNameEs] = useState("");
  const [nameZh, setNameZh] = useState("");
  const [dimensionType, setDimensionType] = useState<string>("mass");
  const [baseUnit, setBaseUnit] = useState<string>("g");
  const [minimumStock, setMinimumStock] = useState<number>(0);
  const [initialCost, setInitialCost] = useState<number>(0);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const createMutation = useMutation({
    mutationFn: async (payload: any) => {
      const response = await apiClient.post("/recipes/ingredients", payload);
      return response.data;
    },
    onSuccess: (data: any) => {
      queryClient.invalidateQueries({ queryKey: ["recipes", "ingredients"] });
      const uuid = data?.data?.uuid ?? data?.uuid;
      if (uuid) {
        setSuccessMessage(`✅ ${t("inventory.purchase.new_ingredient.success_prefix")}: ${nameEs}`);
        setTimeout(() => onCreated(uuid), 800);
      }
    },
    onError: (error: any) => {
      const message =
        error?.response?.data?.message ??
        error?.response?.data?.error ??
        "Unknown error";
      setErrorMessage(`Error: ${message}`);
      setSuccessMessage(null);
    },
  });

  const canSubmit =
    sku.trim() !== "" &&
    nameEs.trim() !== "" &&
    !createMutation.isPending;

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!canSubmit) return;

    const nameTranslations: Record<string, string> = { es: nameEs.trim() };
    if (nameZh.trim()) nameTranslations["zh"] = nameZh.trim();

    createMutation.mutate({
      sku: sku.trim().toUpperCase(),
      name_translations: nameTranslations,
      dimension_type: dimensionType,
      base_unit: baseUnit,
      minimum_stock_base: minimumStock,
      initial_cost_per_base_unit: initialCost,
    });
  };

  const availableUnits = BASE_UNITS[dimensionType as keyof typeof BASE_UNITS] || BASE_UNITS.mass;

  const dimensionLabel = (dim: string) => {
    if (dim === "mass") return t("inventory.purchase.new_ingredient.dimension_mass");
    if (dim === "volume") return t("inventory.purchase.new_ingredient.dimension_volume");
    return t("inventory.purchase.new_ingredient.dimension_count");
  };

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      {/* SKU */}
      <div>
        <label className="block text-sm font-medium text-slate-300 mb-1">
          {t("inventory.purchase.new_ingredient.sku_label")}{" "}
          <span className="text-red-400">{t("inventory.purchase.new_ingredient.sku_required")}</span>
        </label>
        <input
          type="text"
          value={sku}
          onChange={(e) => setSku(e.target.value.toUpperCase())}
          placeholder={t("inventory.purchase.new_ingredient.sku_placeholder")}
          className="w-full px-4 py-2.5 bg-slate-800 border border-slate-700 rounded-lg text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-orange-500 font-mono"
        />
      </div>

      {/* Nombre español */}
      <div>
        <label className="block text-sm font-medium text-slate-300 mb-1">
          {t("inventory.purchase.new_ingredient.name_es_label")}{" "}
          <span className="text-red-400">{t("inventory.purchase.new_ingredient.sku_required")}</span>
        </label>
        <input
          type="text"
          value={nameEs}
          onChange={(e) => setNameEs(e.target.value)}
          placeholder={t("inventory.purchase.new_ingredient.name_es_placeholder")}
          className="w-full px-4 py-2.5 bg-slate-800 border border-slate-700 rounded-lg text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-orange-500"
        />
      </div>

      {/* Nombre chino (opcional) */}
      <div>
        <label className="block text-sm font-medium text-slate-300 mb-1">
          {t("inventory.purchase.new_ingredient.name_zh_label")}{" "}
          <span className="text-slate-500 text-xs">({t("inventory.purchase.new_ingredient.name_zh_optional")})</span>
        </label>
        <input
          type="text"
          value={nameZh}
          onChange={(e) => setNameZh(e.target.value)}
          placeholder={t("inventory.purchase.new_ingredient.name_zh_placeholder")}
          className="w-full px-4 py-2.5 bg-slate-800 border border-slate-700 rounded-lg text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-orange-500"
        />
      </div>

      {/* Tipo de dimensión */}
      <div>
        <label className="block text-sm font-medium text-slate-300 mb-1">
          {t("inventory.purchase.new_ingredient.dimension_label")}
        </label>
        <select
          value={dimensionType}
          onChange={(e) => {
            setDimensionType(e.target.value);
            const units = BASE_UNITS[e.target.value as keyof typeof BASE_UNITS] || [];
            setBaseUnit(units[0]?.value ?? "");
          }}
          className="w-full px-4 py-2.5 bg-slate-800 border border-slate-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-orange-500"
        >
          {DIMENSION_TYPES.map((dim) => (
            <option key={dim} value={dim}>
              {dimensionLabel(dim)}
            </option>
          ))}
        </select>
      </div>

      {/* Unidad base */}
      <div>
        <label className="block text-sm font-medium text-slate-300 mb-1">
          {t("inventory.purchase.new_ingredient.base_unit_label")}
        </label>
        <select
          value={baseUnit}
          onChange={(e) => setBaseUnit(e.target.value)}
          className="w-full px-4 py-2.5 bg-slate-800 border border-slate-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-orange-500"
        >
          {availableUnits.map((u) => (
            <option key={u.value} value={u.value}>
              {t(u.labelKey)}
            </option>
          ))}
        </select>
      </div>

      {/* Stock mínimo */}
      <div>
        <label className="block text-sm font-medium text-slate-300 mb-1">
          {t("inventory.purchase.new_ingredient.min_stock_label")}
        </label>
        <input
          type="number"
          min={0}
          step={0.01}
          value={minimumStock || ""}
          onChange={(e) => setMinimumStock(parseFloat(e.target.value) || 0)}
          placeholder={t("inventory.purchase.new_ingredient.min_stock_placeholder")}
          className="w-full px-4 py-2.5 bg-slate-800 border border-slate-700 rounded-lg text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-orange-500 font-mono"
        />
      </div>

      {/* Costo inicial */}
      <div>
        <label className="block text-sm font-medium text-slate-300 mb-1">
          {t("inventory.purchase.new_ingredient.initial_cost_label")}
        </label>
        <input
          type="number"
          min={0}
          step={0.01}
          value={initialCost || ""}
          onChange={(e) => setInitialCost(parseFloat(e.target.value) || 0)}
          placeholder={t("inventory.purchase.new_ingredient.initial_cost_placeholder")}
          className="w-full px-4 py-2.5 bg-slate-800 border border-slate-700 rounded-lg text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-orange-500 font-mono"
        />
      </div>

      {/* Mensajes */}
      {successMessage && (
        <div className="bg-emerald-900/30 border border-emerald-700 rounded-lg p-3 flex items-start gap-2">
          <CheckCircle2 size={16} className="text-emerald-400 mt-0.5" />
          <p className="text-sm text-emerald-300">{successMessage}</p>
        </div>
      )}

      {errorMessage && (
        <div className="bg-red-900/30 border border-red-700 rounded-lg p-3 flex items-start gap-2">
          <AlertTriangle size={16} className="text-red-400 mt-0.5" />
          <p className="text-sm text-red-300">{errorMessage}</p>
        </div>
      )}

      {/* Botones */}
      <div className="flex gap-3 pt-2">
        <button
          type="button"
          onClick={onCancel}
          className="flex-1 py-2.5 bg-slate-700 hover:bg-slate-600 text-white font-semibold rounded-lg transition-colors"
        >
          {t("inventory.purchase.new_ingredient.cancel")}
        </button>
        <button
          type="submit"
          disabled={!canSubmit}
          className="flex-1 py-2.5 bg-orange-500 hover:bg-orange-600 disabled:bg-slate-700 disabled:text-slate-500 text-white font-semibold rounded-lg transition-colors flex items-center justify-center gap-2"
        >
          {createMutation.isPending ? (
            <>
              <Loader2 className="animate-spin" size={16} />
              {t("inventory.purchase.new_ingredient.submitting")}
            </>
          ) : (
            <>{t("inventory.purchase.new_ingredient.submit")}</>
          )}
        </button>
      </div>
    </form>
  );
}
