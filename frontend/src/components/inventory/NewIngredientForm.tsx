import { useState } from "react";
import { useTranslation } from "react-i18next";
import { Loader2, CheckCircle2, AlertTriangle } from "lucide-react";
import { useQueryClient, useMutation } from "@tanstack/react-query";
import apiClient from "@/services/apiClient";

interface NewIngredientFormProps {
  onCreated: (uuid: string) => void;
  onCancel: () => void;
}

const DIMENSION_TYPES = ["mass", "volume", "unit"] as const;

const BASE_UNITS = {
  mass: [
    { value: "gram", label: "Gramos (g)" },
    { value: "kilogram", label: "Kilogramos (kg)" },
  ],
  volume: [
    { value: "milliliter", label: "Mililitros (ml)" },
    { value: "liter", label: "Litros (l)" },
  ],
  unit: [
    { value: "unit", label: "Unidades" },
  ],
};

export function NewIngredientForm({ onCreated, onCancel }: NewIngredientFormProps) {
  const { t } = useTranslation();
  const queryClient = useQueryClient();

  const [sku, setSku] = useState("");
  const [nameEs, setNameEs] = useState("");
  const [nameZh, setNameZh] = useState("");
  const [dimensionType, setDimensionType] = useState<string>("mass");
  const [baseUnit, setBaseUnit] = useState<string>("gram");
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
        setSuccessMessage(`✅ Insumo creado: ${nameEs}`);
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
    if (nameZh.trim()) nameTranslations["zh-CN"] = nameZh.trim();

    createMutation.mutate({
      sku: sku.trim().toUpperCase(),
      name_translations: nameTranslations,
      dimension_type: dimensionType,
      base_unit: baseUnit,
      minimum_stock_base: minimumStock,
      cost_per_base_unit: initialCost,
    });
  };

  const availableUnits = BASE_UNITS[dimensionType as keyof typeof BASE_UNITS] || BASE_UNITS.mass;

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      {/* SKU */}
      <div>
        <label className="block text-sm font-medium text-slate-300 mb-1">
          SKU (código único) *
        </label>
        <input
          type="text"
          value={sku}
          onChange={(e) => setSku(e.target.value.toUpperCase())}
          placeholder="Ej: HARINA-001"
          className="w-full px-4 py-2.5 bg-slate-800 border border-slate-700 rounded-lg text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-orange-500 font-mono"
        />
      </div>

      {/* Nombre español */}
      <div>
        <label className="block text-sm font-medium text-slate-300 mb-1">
          Nombre (español) *
        </label>
        <input
          type="text"
          value={nameEs}
          onChange={(e) => setNameEs(e.target.value)}
          placeholder="Ej: Harina"
          className="w-full px-4 py-2.5 bg-slate-800 border border-slate-700 rounded-lg text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-orange-500"
        />
      </div>

      {/* Nombre chino (opcional) */}
      <div>
        <label className="block text-sm font-medium text-slate-300 mb-1">
          Nombre (中文) — opcional
        </label>
        <input
          type="text"
          value={nameZh}
          onChange={(e) => setNameZh(e.target.value)}
          placeholder="Ej: 面粉"
          className="w-full px-4 py-2.5 bg-slate-800 border border-slate-700 rounded-lg text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-orange-500"
        />
      </div>

      {/* Tipo de dimensión */}
      <div>
        <label className="block text-sm font-medium text-slate-300 mb-1">
          Tipo de medición
        </label>
        <select
          value={dimensionType}
          onChange={(e) => {
            setDimensionType(e.target.value);
            // Reset unit al cambiar dimensión
            const units = BASE_UNITS[e.target.value as keyof typeof BASE_UNITS] || [];
            setBaseUnit(units[0]?.value ?? "");
          }}
          className="w-full px-4 py-2.5 bg-slate-800 border border-slate-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-orange-500"
        >
          {DIMENSION_TYPES.map((dim) => (
            <option key={dim} value={dim}>
              {dim === "mass" ? "Peso (masa)" : dim === "volume" ? "Volumen" : "Unidades"}
            </option>
          ))}
        </select>
      </div>

      {/* Unidad base */}
      <div>
        <label className="block text-sm font-medium text-slate-300 mb-1">
          Unidad base
        </label>
        <select
          value={baseUnit}
          onChange={(e) => setBaseUnit(e.target.value)}
          className="w-full px-4 py-2.5 bg-slate-800 border border-slate-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-orange-500"
        >
          {availableUnits.map((u) => (
            <option key={u.value} value={u.value}>
              {u.label}
            </option>
          ))}
        </select>
      </div>

      {/* Stock mínimo */}
      <div>
        <label className="block text-sm font-medium text-slate-300 mb-1">
          Stock mínimo (alerta)
        </label>
        <input
          type="number"
          min={0}
          step={0.01}
          value={minimumStock || ""}
          onChange={(e) => setMinimumStock(parseFloat(e.target.value) || 0)}
          placeholder="Ej: 1000"
          className="w-full px-4 py-2.5 bg-slate-800 border border-slate-700 rounded-lg text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-orange-500 font-mono"
        />
      </div>

      {/* Costo inicial */}
      <div>
        <label className="block text-sm font-medium text-slate-300 mb-1">
          Costo inicial por unidad (CLP)
        </label>
        <input
          type="number"
          min={0}
          step={0.01}
          value={initialCost || ""}
          onChange={(e) => setInitialCost(parseFloat(e.target.value) || 0)}
          placeholder="Ej: 2.5"
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
          Cancelar
        </button>
        <button
          type="submit"
          disabled={!canSubmit}
          className="flex-1 py-2.5 bg-orange-500 hover:bg-orange-600 disabled:bg-slate-700 disabled:text-slate-500 text-white font-semibold rounded-lg transition-colors flex items-center justify-center gap-2"
        >
          {createMutation.isPending ? (
            <>
              <Loader2 className="animate-spin" size={16} />
              Creando...
            </>
          ) : (
            <>Crear Insumo</>
          )}
        </button>
      </div>
    </form>
  );
}
