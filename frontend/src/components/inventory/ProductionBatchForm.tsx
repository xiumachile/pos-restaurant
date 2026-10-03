import { useState, useMemo } from "react";
import { useTranslation } from "react-i18next";
import { Factory, CheckCircle2, AlertTriangle, Loader2 } from "lucide-react";
import { useProductRecipe } from "@/hooks/useRecipe";
import { useCreateProductionBatch } from "@/hooks/useInventory";

export function ProductionBatchForm() {
  const { t } = useTranslation();
  const createBatch = useCreateProductionBatch();

  const [productUuid, setProductUuid] = useState<string>("");
  const [quantity, setQuantity] = useState<number>(1);
  const [batchNotes, setBatchNotes] = useState<string>("");
  const [successMessage, setSuccessMessage] = useState<string | null>(null);

  const { data: recipe } = useProductRecipe(productUuid || null);

  const preview = useMemo(() => {
    if (!recipe || !recipe.items) return [];
    return recipe.items.map((item) => ({
      ...item,
      totalQuantity: item.effective_discount_base_quantity * quantity,
    }));
  }, [recipe, quantity]);

  const totalCost = useMemo(() => {
    return preview.reduce((acc, item) => acc + item.calculated_item_cost * quantity, 0);
  }, [preview, quantity]);

  const canSubmit =
    productUuid.trim() !== "" &&
    recipe &&
    recipe.items.length > 0 &&
    quantity >= 1 &&
    !createBatch.isPending;

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!canSubmit) return;

    try {
      const result = await createBatch.mutateAsync({
        product_uuid: productUuid,
        quantity,
        batch_notes: batchNotes.trim() || undefined,
      });

      setSuccessMessage(
        t("inventory.production.success", {
          movements: result.movements_count,
          quantity: result.quantity,
          product: result.product_name,
        })
      );

      setQuantity(1);
      setBatchNotes("");

      setTimeout(() => setSuccessMessage(null), 5000);
    } catch (error: any) {
      const message =
        error?.response?.data?.message ??
        error?.response?.data?.error ??
        "Unknown error";
      alert(`${t("inventory.production.error_prefix")}: ${message}`);
    }
  };

  return (
    <div className="max-w-3xl mx-auto">
      <div className="bg-slate-800/30 border border-slate-700 rounded-lg p-6">
        <div className="flex items-center gap-2 mb-6">
          <Factory size={24} className="text-orange-400" />
          <h2 className="text-2xl font-bold text-white">{t("inventory.production.title")}</h2>
        </div>

        <form onSubmit={handleSubmit} className="space-y-5">
          {/* Product UUID */}
          <div>
            <label className="block text-sm font-medium text-slate-300 mb-2">
              {t("inventory.production.product_uuid")}
            </label>
            <input
              type="text"
              value={productUuid}
              onChange={(e) => setProductUuid(e.target.value)}
              placeholder={t("inventory.production.product_uuid_placeholder")}
              className="w-full px-4 py-2.5 bg-slate-800 border border-slate-700 rounded-lg text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-orange-500 font-mono text-sm"
            />
            <p className="text-xs text-slate-500 mt-1">
              {t("inventory.production.product_uuid_hint")}
            </p>
          </div>

          {/* Preview de receta */}
          {recipe && recipe.items.length > 0 && (
            <div className="bg-slate-800/50 border border-slate-700 rounded-lg p-4">
              <h3 className="text-sm font-semibold text-slate-300 mb-3">
                {t("inventory.production.recipe_label")}: {t("inventory.production.recipe_items", { count: recipe.items.length })}
              </h3>
              <div className="space-y-2">
                {preview.map((item) => (
                  <div
                    key={item.uuid}
                    className="flex items-center justify-between text-sm"
                  >
                    <span className="text-slate-300">
                      {item.ingredient_name} ({item.ingredient_sku})
                    </span>
                    <span className="font-mono text-orange-300">
                      {item.totalQuantity.toFixed(2)} {item.ingredient_sku}
                    </span>
                  </div>
                ))}
                <div className="pt-2 mt-2 border-t border-slate-700 flex justify-between font-semibold">
                  <span className="text-slate-300">{t("inventory.production.total_cost")}:</span>
                  <span className="text-white">
                    {totalCost.toLocaleString("es-CL", {
                      style: "currency",
                      currency: "CLP",
                      minimumFractionDigits: 0,
                    })}
                  </span>
                </div>
              </div>
            </div>
          )}

          {productUuid && !recipe && (
            <div className="bg-yellow-900/20 border border-yellow-800/50 rounded-lg p-3 flex items-start gap-2">
              <AlertTriangle size={16} className="text-yellow-400 mt-0.5" />
              <p className="text-sm text-yellow-300">
                {t("inventory.production.no_recipe_warning")}
              </p>
            </div>
          )}

          {/* Cantidad */}
          <div>
            <label className="block text-sm font-medium text-slate-300 mb-2">
              {t("inventory.production.quantity_label")}
            </label>
            <input
              type="number"
              min={1}
              max={10000}
              value={quantity}
              onChange={(e) => setQuantity(Math.max(1, parseInt(e.target.value) || 1))}
              className="w-full px-4 py-2.5 bg-slate-800 border border-slate-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-orange-500 font-mono"
            />
          </div>

          {/* Notas del lote */}
          <div>
            <label className="block text-sm font-medium text-slate-300 mb-2">
              {t("inventory.production.notes_label")}
            </label>
            <textarea
              value={batchNotes}
              onChange={(e) => setBatchNotes(e.target.value)}
              maxLength={500}
              rows={2}
              placeholder={t("inventory.production.notes_placeholder")}
              className="w-full px-4 py-2.5 bg-slate-800 border border-slate-700 rounded-lg text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-orange-500 resize-none"
            />
            <p className="text-xs text-slate-500 mt-1 text-right">
              {batchNotes.length}/500
            </p>
          </div>

          {/* Mensaje de éxito */}
          {successMessage && (
            <div className="bg-emerald-900/30 border border-emerald-700 rounded-lg p-3 flex items-start gap-2">
              <CheckCircle2 size={16} className="text-emerald-400 mt-0.5 flex-shrink-0" />
              <p className="text-sm text-emerald-300">{successMessage}</p>
            </div>
          )}

          {/* Submit */}
          <button
            type="submit"
            disabled={!canSubmit}
            className="w-full py-3 bg-orange-500 hover:bg-orange-600 disabled:bg-slate-700 disabled:text-slate-500 disabled:cursor-not-allowed text-white font-semibold rounded-lg transition-colors flex items-center justify-center gap-2"
          >
            {createBatch.isPending ? (
              <>
                <Loader2 className="animate-spin" size={18} />
                {t("inventory.production.submitting")}
              </>
            ) : (
              <>
                <Factory size={18} />
                {t("inventory.production.submit")}
              </>
            )}
          </button>
        </form>
      </div>
    </div>
  );
}
