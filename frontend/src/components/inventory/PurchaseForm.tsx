import { useState, useMemo } from "react";
import { useTranslation } from "react-i18next";
import { ShoppingCart, CheckCircle2, AlertTriangle, Loader2, Plus, FileText, X } from "lucide-react";
import { useIngredients } from "@/hooks/useRecipe";
import { useQueryClient, useMutation } from "@tanstack/react-query";
import apiClient from "@/services/apiClient";
import type { RawIngredient } from "@/services/recipeService";
import { NewIngredientForm } from "./NewIngredientForm";

interface PurchaseFormProps {
  onSuccess?: () => void;
}

// Unidades de compra por tipo, con claves i18n
const PURCHASE_UNIT_KEYS = {
  mass: ["kg", "g", "lb", "caja", "bolsa"] as const,
  volume: ["l", "ml", "galon", "botella", "bidon"] as const,
  unit: ["unidad", "docena", "caja", "pack"] as const,
};

const PURCHASE_UNIT_FACTORS: Record<string, number | null> = {
  kg: 1000,
  g: 1,
  lb: 453.592,
  l: 1000,
  ml: 1,
  galon: 3785.41,
  unidad: 1,
  docena: 12,
};

const DOCUMENT_TYPES = ["boleta", "factura", "factura_exenta", "nota_entrada", "otro"] as const;

export function PurchaseForm({ onSuccess }: PurchaseFormProps) {
  const { t } = useTranslation();
  const { data: ingredients = [], refetch } = useIngredients();
  const queryClient = useQueryClient();

  const [showNewIngredient, setShowNewIngredient] = useState(false);
  const [selectedIngredientUuid, setSelectedIngredientUuid] = useState<string>("");
  const [purchaseUnitName, setPurchaseUnitName] = useState<string>("");
  const [purchaseQuantity, setPurchaseQuantity] = useState<number>(0);
  const [conversionFactor, setConversionFactor] = useState<number | null>(null);
  const [totalPurchaseCost, setTotalPurchaseCost] = useState<number>(0);
  
  // Documento contable
  const [documentType, setDocumentType] = useState<string>("");
  const [documentNumber, setDocumentNumber] = useState<string>("");
  const [supplierName, setSupplierName] = useState<string>("");
  const [supplierRut, setSupplierRut] = useState<string>("");
  
  const [successMessage, setSuccessMessage] = useState<string | null>(null);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const selectedIngredient: RawIngredient | undefined = ingredients.find(
    (i) => i.uuid === selectedIngredientUuid
  );

  const ingredientType = selectedIngredient?.dimension_type ?? "mass";
  const availableUnitKeys = PURCHASE_UNIT_KEYS[ingredientType as keyof typeof PURCHASE_UNIT_KEYS] || PURCHASE_UNIT_KEYS.mass;

  const effectiveConversionFactor = useMemo(() => {
    if (conversionFactor !== null) return conversionFactor;
    const predefined = PURCHASE_UNIT_FACTORS[purchaseUnitName];
    if (predefined !== undefined && predefined !== null) return predefined;
    return null;
  }, [conversionFactor, purchaseUnitName]);

  const baseQuantity = useMemo(() => {
    if (!effectiveConversionFactor || !purchaseQuantity) return 0;
    return purchaseQuantity * effectiveConversionFactor;
  }, [purchaseQuantity, effectiveConversionFactor]);

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
      document_type?: string;
      document_number?: string;
      supplier_name?: string;
      supplier_rut?: string;
    }) => {
      const body: any = {
        purchase_unit_name: payload.purchase_unit_name,
        purchase_quantity: payload.purchase_quantity,
        total_purchase_cost: Math.round(payload.total_purchase_cost),
      };
      if (payload.conversion_factor_to_base) body.conversion_factor_to_base = payload.conversion_factor_to_base;
      if (payload.document_type) body.document_type = payload.document_type;
      if (payload.document_number) body.document_number = payload.document_number;
      if (payload.supplier_name) body.supplier_name = payload.supplier_name;
      if (payload.supplier_rut) body.supplier_rut = payload.supplier_rut;

      const response = await apiClient.post(
        `/recipes/ingredients/${payload.ingredientUuid}/purchase`,
        body
      );
      return response.data;
    },
    onSuccess: (data: any) => {
      queryClient.invalidateQueries({ queryKey: ["recipes", "ingredients"] });
      queryClient.invalidateQueries({ queryKey: ["inventory", "movements"] });

      const addedQty = data?.data?.total_base_quantity_added ?? baseQuantity;
      const newStock = data?.data?.new_stock_base ?? 0;

      const ingName =
        selectedIngredient?.name_translations?.es ??
        selectedIngredient?.name_translations?.["zh-CN"] ??
        selectedIngredient?.sku ??
        "";
      const ingUnit = selectedIngredient?.base_unit ?? "";

      setSuccessMessage(
        t("inventory.purchase.success_message", {
          added: addedQty.toFixed(2),
          unit: ingUnit,
          ingredient: ingName,
          stock: newStock.toFixed(2),
        })
      );
      setErrorMessage(null);

      // Limpiar formulario
      setPurchaseQuantity(0);
      setTotalPurchaseCost(0);
      setPurchaseUnitName("");
      setConversionFactor(null);
      setDocumentType("");
      setDocumentNumber("");
      setSupplierName("");
      setSupplierRut("");

      setTimeout(() => setSuccessMessage(null), 6000);

      if (onSuccess) onSuccess();
    },
    onError: (error: any) => {
      const message =
        error?.response?.data?.message ??
        error?.response?.data?.error ??
        "Unknown error";
      setErrorMessage(`${t("inventory.purchase.error_prefix")}: ${message}`);
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
      document_type: documentType || undefined,
      document_number: documentNumber.trim() || undefined,
      supplier_name: supplierName.trim() || undefined,
      supplier_rut: supplierRut.trim() || undefined,
    });
  };

  const handleIngredientCreated = (newUuid: string) => {
    setShowNewIngredient(false);
    refetch().then(() => {
      setSelectedIngredientUuid(newUuid);
    });
  };

  const selectedUnitLabel = purchaseUnitName
    ? t(`inventory.purchase.units.${ingredientType}.${purchaseUnitName}`, purchaseUnitName)
    : "";

  const requiresManualFactor = purchaseUnitName && PURCHASE_UNIT_FACTORS[purchaseUnitName] === undefined;

  // Modal para crear insumo nuevo
  if (showNewIngredient) {
    return (
      <div className="max-w-3xl mx-auto">
        <div className="bg-slate-800/30 border border-slate-700 rounded-lg p-6">
          <div className="flex items-center justify-between mb-4">
            <h2 className="text-2xl font-bold text-white">{t("inventory.purchase.new_ingredient_title", "Crear Nuevo Insumo")}</h2>
            <button
              onClick={() => setShowNewIngredient(false)}
              className="p-2 text-slate-400 hover:text-white hover:bg-slate-700 rounded-lg transition-colors"
            >
              <X size={20} />
            </button>
          </div>
          <NewIngredientForm onCreated={handleIngredientCreated} onCancel={() => setShowNewIngredient(false)} />
        </div>
      </div>
    );
  }

  return (
    <div className="max-w-3xl mx-auto">
      <div className="bg-slate-800/30 border border-slate-700 rounded-lg p-6">
        <div className="flex items-center justify-between mb-6">
          <div className="flex items-center gap-2">
            <ShoppingCart size={24} className="text-orange-400" />
            <h2 className="text-2xl font-bold text-white">{t("inventory.purchase.title")}</h2>
          </div>
          <button
            onClick={() => setShowNewIngredient(true)}
            className="flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition-colors"
          >
            <Plus size={16} />
            <span>{t("inventory.purchase.new_ingredient_btn", "Nuevo Insumo")}</span>
          </button>
        </div>

        <form onSubmit={handleSubmit} className="space-y-5">
          {/* Selector de insumo */}
          <div>
            <label className="block text-sm font-medium text-slate-300 mb-2">
              {t("inventory.purchase.select_ingredient")}
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
              <option value="">{t("inventory.purchase.select_placeholder")}</option>
              {ingredients.map((ing) => {
                const name = ing.name_translations?.es ?? ing.name_translations?.["zh-CN"] ?? ing.sku;
                return (
                  <option key={ing.uuid} value={ing.uuid}>
                    {name} ({ing.sku})
                  </option>
                );
              })}
            </select>
          </div>

          {/* Info del insumo */}
          {selectedIngredient && (
            <div className="bg-slate-800/50 border border-slate-700 rounded-lg p-4 space-y-2">
              <div className="flex items-center justify-between">
                <span className="text-sm text-slate-400">{t("inventory.purchase.current_stock")}:</span>
                <span className="font-mono font-bold text-white">
                  {selectedIngredient.current_stock_base.toLocaleString("es-CL", {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                  })}{" "}
                  {selectedIngredient.base_unit}
                </span>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-sm text-slate-400">{t("inventory.purchase.min_stock")}:</span>
                <span className="font-mono text-slate-300">
                  {selectedIngredient.minimum_stock_base.toLocaleString("es-CL", {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                  })}{" "}
                  {selectedIngredient.base_unit}
                </span>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-sm text-slate-400">{t("inventory.purchase.current_cost")}:</span>
                <span className="font-mono text-slate-300">
                  {selectedIngredient.cost_per_base_unit.toLocaleString("es-CL", {
                    style: "currency",
                    currency: "CLP",
                    minimumFractionDigits: 2,
                  })}{" "}
                  {t("inventory.purchase.per_unit", { unit: selectedIngredient.base_unit })}
                </span>
              </div>
            </div>
          )}

          {/* ═══════════════════════════════════════════════════════
              SECCIÓN: Documento Contable (SII Chile)
              ═══════════════════════════════════════════════════════ */}
          <div className="bg-slate-800/50 border border-slate-700 rounded-lg p-4">
            <div className="flex items-center gap-2 mb-4">
              <FileText size={18} className="text-blue-400" />
              <h3 className="font-semibold text-white">{t("inventory.purchase.document_section", "Documento Contable")}</h3>
              <span className="text-xs text-slate-500 ml-auto">{t("inventory.purchase.document_optional", "Opcional pero recomendado")}</span>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              {/* Tipo de documento */}
              <div>
                <label className="block text-sm font-medium text-slate-300 mb-1">
                  {t("inventory.purchase.document_type", "Tipo de documento")}
                </label>
                <select
                  value={documentType}
                  onChange={(e) => setDocumentType(e.target.value)}
                  className="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-orange-500"
                >
                  <option value="">— {t("inventory.purchase.document_select", "Seleccionar")} —</option>
                  {DOCUMENT_TYPES.map((type) => (
                    <option key={type} value={type}>
                      {t(`inventory.purchase.doc_types.${type}`, type)}
                    </option>
                  ))}
                </select>
              </div>

              {/* Número de documento */}
              <div>
                <label className="block text-sm font-medium text-slate-300 mb-1">
                  {t("inventory.purchase.document_number", "Número de documento")}
                </label>
                <input
                  type="text"
                  value={documentNumber}
                  onChange={(e) => setDocumentNumber(e.target.value)}
                  maxLength={50}
                  placeholder="Ej: F-12345"
                  className="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-orange-500 font-mono"
                />
              </div>

              {/* Nombre del proveedor */}
              <div>
                <label className="block text-sm font-medium text-slate-300 mb-1">
                  {t("inventory.purchase.supplier_name", "Proveedor")}
                </label>
                <input
                  type="text"
                  value={supplierName}
                  onChange={(e) => setSupplierName(e.target.value)}
                  maxLength={150}
                  placeholder="Ej: Distribuidora Central"
                  className="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-orange-500"
                />
              </div>

              {/* RUT del proveedor */}
              <div>
                <label className="block text-sm font-medium text-slate-300 mb-1">
                  {t("inventory.purchase.supplier_rut", "RUT Proveedor")}
                </label>
                <input
                  type="text"
                  value={supplierRut}
                  onChange={(e) => setSupplierRut(e.target.value)}
                  maxLength={20}
                  placeholder="Ej: 76.123.456-7"
                  className="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-orange-500 font-mono"
                />
              </div>
            </div>
          </div>

          {/* Unidad de compra */}
          <div>
            <label className="block text-sm font-medium text-slate-300 mb-2">
              {t("inventory.purchase.unit_label")}
            </label>
            <select
              value={purchaseUnitName}
              onChange={(e) => {
                setPurchaseUnitName(e.target.value);
                setConversionFactor(null);
              }}
              className="w-full px-4 py-2.5 bg-slate-800 border border-slate-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-orange-500"
            >
              <option value="">{t("inventory.purchase.unit_placeholder")}</option>
              {availableUnitKeys.map((unitKey) => {
                const factor = PURCHASE_UNIT_FACTORS[unitKey];
                const label = t(`inventory.purchase.units.${ingredientType}.${unitKey}`, unitKey);
                return (
                  <option key={unitKey} value={unitKey}>
                    {label}
                    {factor && selectedIngredient
                      ? ` (1 ${unitKey} = ${factor} ${selectedIngredient.base_unit})`
                      : ""}
                  </option>
                );
              })}
            </select>
          </div>

          {/* Factor de conversión manual */}
          {requiresManualFactor && selectedIngredient && (
            <div>
              <label className="block text-sm font-medium text-slate-300 mb-2">
                {t("inventory.purchase.conversion_factor_label", {
                  unit: selectedUnitLabel,
                  base_unit: selectedIngredient.base_unit,
                })}
              </label>
              <input
                type="number"
                min={0.01}
                step={0.01}
                value={conversionFactor ?? ""}
                onChange={(e) => setConversionFactor(parseFloat(e.target.value) || null)}
                placeholder={t("inventory.purchase.conversion_factor_placeholder_generic")}
                className="w-full px-4 py-2.5 bg-slate-800 border border-slate-700 rounded-lg text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-orange-500 font-mono"
              />
            </div>
          )}

          {/* Cantidad comprada */}
          <div>
            <label className="block text-sm font-medium text-slate-300 mb-2">
              {t("inventory.purchase.quantity_label", {
                unit: selectedUnitLabel || t("inventory.purchase.unit_placeholder"),
              })}
            </label>
            <input
              type="number"
              min={0.01}
              step={0.01}
              value={purchaseQuantity || ""}
              onChange={(e) => setPurchaseQuantity(parseFloat(e.target.value) || 0)}
              placeholder={t("inventory.purchase.quantity_placeholder")}
              className="w-full px-4 py-2.5 bg-slate-800 border border-slate-700 rounded-lg text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-orange-500 font-mono"
            />
          </div>

          {/* Preview de conversión */}
          {baseQuantity > 0 && selectedIngredient && (
            <div className="bg-blue-900/20 border border-blue-700/50 rounded-lg p-3 flex items-center justify-between">
              <span className="text-sm text-blue-300">{t("inventory.purchase.will_add_to_stock")}:</span>
              <span className="font-mono font-bold text-blue-200">
                +{baseQuantity.toFixed(2)} {selectedIngredient.base_unit}
              </span>
            </div>
          )}

          {/* Costo total */}
          <div>
            <label className="block text-sm font-medium text-slate-300 mb-2">
              {t("inventory.purchase.total_cost_label")}
            </label>
            <input
              type="number"
              min={0}
              step={1}
              value={totalPurchaseCost || ""}
              onChange={(e) => setTotalPurchaseCost(parseInt(e.target.value) || 0)}
              placeholder={t("inventory.purchase.total_cost_placeholder")}
              className="w-full px-4 py-2.5 bg-slate-800 border border-slate-700 rounded-lg text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-orange-500 font-mono"
            />
          </div>

          {/* Preview costo unitario */}
          {costPerBaseUnit > 0 && selectedIngredient && (
            <div className="bg-emerald-900/20 border border-emerald-700/50 rounded-lg p-3 flex items-center justify-between">
              <span className="text-sm text-emerald-300">
                {t("inventory.purchase.cost_per_unit_label", {
                  unit: selectedIngredient.base_unit,
                })}:
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
                {t("inventory.purchase.submitting")}
              </>
            ) : (
              <>
                <ShoppingCart size={18} />
                {t("inventory.purchase.submit")}
              </>
            )}
          </button>
        </form>
      </div>
    </div>
  );
}
