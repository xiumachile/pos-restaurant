import { useState, useMemo } from "react";
import { useTranslation } from "react-i18next";
import { ShoppingCart, CheckCircle2, AlertTriangle, Loader2 } from "lucide-react";
import { useIngredients } from "@/hooks/useRecipe";
import { useQueryClient, useMutation } from "@tanstack/react-query";
import apiClient from "@/services/apiClient";
import type { RawIngredient } from "@/services/recipeService";

interface PurchaseFormProps {
  onSuccess?: () => void;
}

// Unidades de compra comunes y su factor de conversión por tipo de insumo
const PURCHASE_UNITS = {
  mass: [
    { value: "kg", label: "Kilogramo (kg)", factor: 1000 }, // 1 kg = 1000 g
    { value: "g", label: "Gramo (g)", factor: 1 },
    { value: "lb", label: "Libra (lb)", factor: 453.592 },
    { value: "caja", label: "Caja", factor: null }, // Requiere input manual
    { value: "bolsa", label: "Bolsa", factor: null },
  ],
  volume: [
    { value: "l", label: "Litro (l)", factor: 1000 }, // 1 l = 1000 ml
    { value: "ml", label: "Mililitro (ml)", factor: 1 },
    { value: "galon", label: "Galón", factor: 3785.41 },
    { value: "botella", label: "Botella", factor: null },
    { value: "bidon", label: "Bidón", factor: null },
  ],
  unit: [
    { value: "unidad", label: "Unidad", factor: 1 },
    { value: "docena", label: "Docena", factor: 12 },
    { value: "caja", label: "Caja", factor: null },
    { value: "pack", label: "Pack", factor: null },
  ],
};

export function PurchaseForm({ onSuccess }: PurchaseFormProps) {
  const { t } = useTranslation();
  const { data: ingredients = [] } = useIngredients();
  const queryClient = useQueryClient();

  const [selectedIngredientUuid, setSelectedIngredientUuid] = useState<string>("");
  const [purchaseUnitName, setPurchaseUnitName] = useState<string>("");
  const [purchaseQuantity, setPurchaseQuantity] = useState<number>(0);
  const [conversionFactor, setConversionFactor] = useState<number | null>(null);
  const [totalPurchaseCost, setTotalPurchaseCost] = useState<number>(0);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const selectedIngredient: RawIngredient | undefined = ingredients.find(
    (i) => i.uuid === selectedIngredientUuid
  );

  // Determinar tipo de insumo para mostrar unidades apropiadas
  const ingredientType = selectedIngredient?.dimension_type ?? "mass";
  const availableUnits = PURCHASE_UNITS[ingredientType as keyof typeof PURCHASE_UNITS] || PURCHASE_UNITS.mass;

  // Calcular factor de conversión automáticamente o manual
  const selectedUnitInfo = availableUnits.find((u) => u.value === purchaseUnitName);
  const effectiveConversionFactor = useMemo(() => {
    if (conversionFactor !== null) return conversionFactor;
    if (selectedUnitInfo?.factor !== null && selectedUnitInfo?.factor !== undefined) {
      return selectedUnitInfo.factor;
    }
    return null;
  }, [conversionFactor, selectedUnitInfo]);

  // Calcular cantidad en unidades base
  const baseQuantity = useMemo(() => {
    if (!effectiveConversionFactor || !purchaseQuantity) return 0;
    return purchaseQuantity * effectiveConversionFactor;
  }, [purchaseQuantity, effectiveConversionFactor]);

  // Calcular costo por unidad base
  const costPerBaseUnit = useMemo(() => {
    if (!baseQuantity || !totalPurchaseCost) return 0;
    return totalPurchaseCost / baseQuantity;
  }, [baseQuantity, totalPurchaseCost]);

  const purchaseMutation = useMutation({
    mutationFn: async (payload: {
      ingredientUuid: string;
      purchase_unit_name: string;
      purchase_quantity: number;
      total_purchase_cost: number;
      conversion_factor_to_base?: number;
    }) => {
      const response = await apiClient.post(
        `/recipes/ingredients/${payload.ingredientUuid}/purchase`,
        {
          purchase_unit_name: payload.purchase_unit_name,
          purchase_quantity: payload.purchase_quantity,
          total_purchase_cost: Math.round(payload.total_purchase_cost), // CLP entero
          conversion_factor_to_base: payload.conversion_factor_to_base,
        }
      );
      return response.data;
    },
    onSuccess: (data: any) => {
      // Invalidar queries de ingredientes y movimientos
      queryClient.invalidateQueries({ queryKey: ["recipes", "ingredients"] });
      queryClient.invalidateQueries({ queryKey: ["inventory", "movements"] });

      const addedQty = data?.data?.total_base_quantity_added ?? baseQuantity;
      const newStock = data?.data?.new_stock_base ?? 0;

      setSuccessMessage(
        `✅ Compra registrada: +${addedQty.toFixed(2)} ${selectedIngredient?.base_unit ?? ""} de ${selectedIngredient?.name_translations?.es ?? selectedIngredient?.sku}\n` +
        `Nuevo stock: ${newStock.toFixed(2)} ${selectedIngredient?.base_unit}`
      );
      setErrorMessage(null);

      // Limpiar formulario
      setPurchaseQuantity(0);
      setTotalPurchaseCost(0);
      setPurchaseUnitName("");
      setConversionFactor(null);

      setTimeout(() => setSuccessMessage(null), 6000);

      if (onSuccess) onSuccess();
    },
    onError: (error: any) => {
      const message =
        error?.response?.data?.message ??
        error?.response?.data?.error ??
        "Error al registrar compra";
      setErrorMessage(`Error: ${message}`);
      setSuccessMessage(null);
    },
  });

  const canSubmit =
    selectedIngredientUuid.trim() !== "" &&
    purchaseUnitName.trim() !== "" &&
    purchaseQuantity > 0 &&
    totalPurchaseCost >= 0 &&
    effectiveConversionFactor !== null &&
    effectiveConversionFactor > 0 &&
    !purchaseMutation.isPending;

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!canSubmit) return;

    purchaseMutation.mutate({
      ingredientUuid: selectedIngredientUuid,
      purchase_unit_name: purchaseUnitName,
      purchase_quantity: purchaseQuantity,
      total_purchase_cost: totalPurchaseCost,
      conversion_factor_to_base:
        effectiveConversionFactor !== 1 ? effectiveConversionFactor ?? undefined : undefined,
    });
  };

  return (
    <div className="max-w-3xl mx-auto">
      <div className="bg-slate-800/30 border border-slate-700 rounded-lg p-6">
        <div className="flex items-center gap-2 mb-6">
          <ShoppingCart size={24} className="text-orange-400" />
          <h2 className="text-2xl font-bold text-white">Registrar Compra de Insumo</h2>
        </div>

        <form onSubmit={handleSubmit} className="space-y-5">
          {/* Selector de insumo */}
          <div>
            <label className="block text-sm font-medium text-slate-300 mb-2">
              Insumo a comprar
            </label>
            <select
              value={selectedIngredientUuid}
              onChange={(e) => {
                setSelectedIngredientUuid(e.target.value);
                setPurchaseUnitName("");
                setConversionFactor(null);
              }}
              className="w-full px-4 py-2.5 bg-slate-800 border border-slate-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-orange-500"
            >
              <option value="">Selecciona un insumo...</option>
              {ingredients.map((ing) => (
                <option key={ing.uuid} value={ing.uuid}>
                  {ing.name_translations?.es ?? ing.sku} ({ing.sku})
                </option>
              ))}
            </select>
          </div>

          {/* Info del insumo seleccionado */}
          {selectedIngredient && (
            <div className="bg-slate-800/50 border border-slate-700 rounded-lg p-4 space-y-2">
              <div className="flex items-center justify-between">
                <span className="text-sm text-slate-400">Stock actual:</span>
                <span className="font-mono font-bold text-white">
                  {selectedIngredient.current_stock_base.toLocaleString("es-CL", {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                  })}{" "}
                  {selectedIngredient.base_unit}
                </span>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-sm text-slate-400">Stock mínimo:</span>
                <span className="font-mono text-slate-300">
                  {selectedIngredient.minimum_stock_base.toLocaleString("es-CL", {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                  })}{" "}
                  {selectedIngredient.base_unit}
                </span>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-sm text-slate-400">Costo actual:</span>
                <span className="font-mono text-slate-300">
                  {selectedIngredient.cost_per_base_unit.toLocaleString("es-CL", {
                    style: "currency",
                    currency: "CLP",
                    minimumFractionDigits: 2,
                  })}{" "}
                  / {selectedIngredient.base_unit}
                </span>
              </div>
            </div>
          )}

          {/* Unidad de compra */}
          <div>
            <label className="block text-sm font-medium text-slate-300 mb-2">
              Unidad de compra
            </label>
            <select
              value={purchaseUnitName}
              onChange={(e) => {
                setPurchaseUnitName(e.target.value);
                setConversionFactor(null); // Reset manual factor
              }}
              className="w-full px-4 py-2.5 bg-slate-800 border border-slate-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-orange-500"
            >
              <option value="">Selecciona unidad...</option>
              {availableUnits.map((unit) => (
                <option key={unit.value} value={unit.value}>
                  {unit.label}
                  {unit.factor && ` (1 ${unit.value} = ${unit.factor} ${selectedIngredient?.base_unit})`}
                </option>
              ))}
            </select>
          </div>

          {/* Factor de conversión manual (solo si la unidad no tiene factor predefinido) */}
          {purchaseUnitName && selectedUnitInfo?.factor === null && (
            <div>
              <label className="block text-sm font-medium text-slate-300 mb-2">
                Factor de conversión: 1 {purchaseUnitName} = ¿cuántas {selectedIngredient?.base_unit}?
              </label>
              <input
                type="number"
                min={0.01}
                step={0.01}
                value={conversionFactor ?? ""}
                onChange={(e) => setConversionFactor(parseFloat(e.target.value) || null)}
                placeholder={`Ej: ${selectedIngredient?.base_unit === "gram" ? "1000 (1 kg = 1000 g)" : "12 (1 docena = 12 unidades)"}`}
                className="w-full px-4 py-2.5 bg-slate-800 border border-slate-700 rounded-lg text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-orange-500 font-mono"
              />
            </div>
          )}

          {/* Cantidad comprada */}
          <div>
            <label className="block text-sm font-medium text-slate-300 mb-2">
              Cantidad comprada ({purchaseUnitName || "unidad"})
            </label>
            <input
              type="number"
              min={0.01}
              step={0.01}
              value={purchaseQuantity || ""}
              onChange={(e) => setPurchaseQuantity(parseFloat(e.target.value) || 0)}
              placeholder={`Ej: 5`}
              className="w-full px-4 py-2.5 bg-slate-800 border border-slate-700 rounded-lg text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-orange-500 font-mono"
            />
          </div>

          {/* Preview de conversión */}
          {baseQuantity > 0 && selectedIngredient && (
            <div className="bg-blue-900/20 border border-blue-700/50 rounded-lg p-3 flex items-center justify-between">
              <span className="text-sm text-blue-300">Se agregará al stock:</span>
              <span className="font-mono font-bold text-blue-200">
                +{baseQuantity.toFixed(2)} {selectedIngredient.base_unit}
              </span>
            </div>
          )}

          {/* Costo total */}
          <div>
            <label className="block text-sm font-medium text-slate-300 mb-2">
              Costo total de la compra (CLP)
            </label>
            <input
              type="number"
              min={0}
              step={1}
              value={totalPurchaseCost || ""}
              onChange={(e) => setTotalPurchaseCost(parseInt(e.target.value) || 0)}
              placeholder="Ej: 25000"
              className="w-full px-4 py-2.5 bg-slate-800 border border-slate-700 rounded-lg text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-orange-500 font-mono"
            />
          </div>

          {/* Preview costo unitario */}
          {costPerBaseUnit > 0 && selectedIngredient && (
            <div className="bg-emerald-900/20 border border-emerald-700/50 rounded-lg p-3 flex items-center justify-between">
              <span className="text-sm text-emerald-300">
                Costo por {selectedIngredient.base_unit}:
              </span>
              <span className="font-mono font-bold text-emerald-200">
                {costPerBaseUnit.toLocaleString("es-CL", {
                  style: "currency",
                  currency: "CLP",
                  minimumFractionDigits: 2,
                  maximumFractionDigits: 2,
                })}
              </span>
            </div>
          )}

          {/* Mensajes */}
          {successMessage && (
            <div className="bg-emerald-900/30 border border-emerald-700 rounded-lg p-3 flex items-start gap-2">
              <CheckCircle2 size={16} className="text-emerald-400 mt-0.5 flex-shrink-0" />
              <p className="text-sm text-emerald-300 whitespace-pre-line">{successMessage}</p>
            </div>
          )}

          {errorMessage && (
            <div className="bg-red-900/30 border border-red-700 rounded-lg p-3 flex items-start gap-2">
              <AlertTriangle size={16} className="text-red-400 mt-0.5 flex-shrink-0" />
              <p className="text-sm text-red-300">{errorMessage}</p>
            </div>
          )}

          {/* Submit */}
          <button
            type="submit"
            disabled={!canSubmit}
            className="w-full py-3 bg-orange-500 hover:bg-orange-600 disabled:bg-slate-700 disabled:text-slate-500 disabled:cursor-not-allowed text-white font-semibold rounded-lg transition-colors flex items-center justify-center gap-2"
          >
            {purchaseMutation.isPending ? (
              <>
                <Loader2 className="animate-spin" size={18} />
                Registrando compra...
              </>
            ) : (
              <>
                <ShoppingCart size={18} />
                Registrar Compra
              </>
            )}
          </button>
        </form>
      </div>
    </div>
  );
}
