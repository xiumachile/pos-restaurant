import { useTranslation } from 'react-i18next';
import { useState, useEffect } from "react";
import { usePriceLists, useUpsertProductPrices } from "@/hooks/usePriceLists";
import { useProductPrices } from "@/hooks/useProductPrices";
import { useCreateProduct, useUpdateProduct } from "@/hooks/useCatalogAdmin";
import type { Product, Category } from "@/types/catalog";
import { getTranslatedName } from "@/types/catalog";
import { RecipeSection } from "./RecipeSection";
import { ComboProductsEditor } from "./ComboProductsEditor";

export const CHANNEL_LABELS: Record<string, string> = {
  dine_in: "🍽️ Comedor",
  delivery: "🚗 Delivery",
  uber_eats: "🛵 UberEats",
  rappi: "📱 Rappi",
  takeout: "🥡 Para llevar",
};

function formatDateTime(isoString: string): string {
  try {
    const date = new Date(isoString);
    return date.toLocaleString("es-CL", {
      day: "2-digit",
      month: "2-digit",
      year: "numeric",
      hour: "2-digit",
      minute: "2-digit",
    });
  } catch {
    return isoString;
  }
}

/* ─── Modal de formulario de producto ─── */

export interface ProductFormModalProps {
  product?: Product;
  categories: Category[];
  onClose: () => void;
}

/**
 * Estado de precios por lista.
 * Key: uuid de la price list
 * Value: { price: precio editado, updatedAt: fecha del último guardado }
 */
export type PriceEditorState = Record<
  string,
  { price: number | null; updatedAt: string | null }
>;

export function ProductFormModal({ product, categories, onClose }: ProductFormModalProps) {
  const { t } = useTranslation();

  const [sku, setSku] = useState(product?.sku ?? "");
  const [nameEs, setNameEs] = useState(product?.name_translations?.es ?? "");
  const [categoryId, setCategoryId] = useState(
    product?.category?.uuid ?? categories[0]?.uuid ?? ""
  );
  const [basePrice, setBasePrice] = useState(
    product ? parseFloat(product.base_price) : 0
  );
  const [taxRate, setTaxRate] = useState(
    product ? parseFloat(product.tax_rate) : 19
  );
  const [isCombo, setIsCombo] = useState(product?.is_combo ?? false);
  const [isActive, setIsActive] = useState(product?.is_active ?? true);
  const [hasRecipe, setHasRecipe] = useState(false);

  // Estado para precios por lista de precios (solo en modo edición)
  const [priceEditorState, setPriceEditorState] = useState<PriceEditorState>({});
  const [comboProducts, setComboProducts] = useState<Array<{product_uuid: string; quantity: number}>>([]);
  const [saveStatus, setSaveStatus] = useState<string | null>(null);

  // Datos de backend
  const { data: priceLists = [] } = usePriceLists();
  const { data: existingPrices, isLoading: loadingPrices } = useProductPrices(
    product?.uuid ?? null
  );

  const createMutation = useCreateProduct();
  const updateMutation = useUpdateProduct();
  const upsertPricesMutation = useUpsertProductPrices();

  /**
   * Un solo effect que sincroniza el estado del editor de precios.
   * Se ejecuta cuando cambian priceLists O existingPrices.
   * Construye el estado completo: para cada lista activa, usa el precio
   * existente si lo hay, o null si no.
   */
  useEffect(() => {
    if (priceLists.length === 0) return;

    const newState: PriceEditorState = {};

    // Construir mapa de precios existentes por list uuid
    const existingMap = new Map<string, { price: number; updatedAt: string }>();
    if (existingPrices && existingPrices.length > 0) {
      for (const pp of existingPrices) {
        const listUuid = pp.price_list?.uuid;
        if (listUuid) {
          existingMap.set(listUuid, {
            price: parseFloat(pp.price),
            updatedAt: pp.updated_at,
          });
        }
      }
    }

    // Para cada lista activa, crear entrada en el estado
    for (const list of priceLists) {
      if (!list.is_active) continue;
      const existing = existingMap.get(list.uuid);
      if (existing) {
        newState[list.uuid] = {
          price: existing.price,
          updatedAt: existing.updatedAt,
        };
      } else {
        // Mantener el valor editado por el usuario si ya existe en el estado previo
        newState[list.uuid] = priceEditorState[list.uuid] ?? {
          price: null,
          updatedAt: null,
        };
      }
    }

    setPriceEditorState(newState);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [priceLists, existingPrices]);

  const updatePriceForList = (listUuid: string, price: number | null) => {
    setPriceEditorState((prev) => ({
      ...prev,
      [listUuid]: {
        price,
        updatedAt: prev[listUuid]?.updatedAt ?? null,
      },
    }));
  };

  /**
   * Detecta qué precios cambiaron comparando con el estado original cargado.
   * Solo envía al backend los que efectivamente se modificaron.
   */
  const getChangedPrices = (): Array<{ price_list_id: string; price: number }> => {
    if (!product) {
      // Modo creación: enviar todos los que tengan valor
      return Object.entries(priceEditorState)
        .filter(([_, state]) => state.price !== null && state.price > 0)
        .map(([listUuid, state]) => ({
          price_list_id: listUuid,
          price: state.price!,
        }));
    }

    // Modo edición: comparar con precios originales
    const originalMap = new Map<string, number>();
    if (existingPrices) {
      for (const pp of existingPrices) {
        if (pp.price_list?.uuid) {
          originalMap.set(pp.price_list.uuid, parseFloat(pp.price));
        }
      }
    }

    const changes: Array<{ price_list_id: string; price: number }> = [];
    for (const [listUuid, state] of Object.entries(priceEditorState)) {
      const originalPrice = originalMap.get(listUuid);
      const newPrice = state.price;

      // Incluir si:
      // - antes no existía y ahora tiene valor
      // - antes existía y el valor cambió
      if (newPrice !== null && newPrice > 0) {
        if (originalPrice === undefined || Math.abs(originalPrice - newPrice) > 0.01) {
          changes.push({ price_list_id: listUuid, price: newPrice });
        }
      }
    }
    return changes;
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSaveStatus(null);

    try {
      let productUuid = product?.uuid;

      // 1. Guardar producto (create o update)
      if (product) {
        await updateMutation.mutateAsync({
          uuid: product.uuid,
          payload: {
            sku,
            name_translations: { es: nameEs },
            category_id: categoryId,
            base_price: basePrice,
            tax_rate: taxRate,
            is_combo: isCombo,
            is_active: isActive,
          },
        });
      } else {
        const result = await createMutation.mutateAsync({
          sku,
          name_translations: { es: nameEs },
          category_id: categoryId,
          base_price: basePrice,
          tax_rate: taxRate,
          is_combo: isCombo,
          is_active: isActive,
        });
        productUuid = (result as any)?.uuid;
      }

      // 2. Actualizar precios si hay cambios y tenemos el UUID del producto
      if (productUuid) {
        const changedPrices = getChangedPrices();
        if (changedPrices.length > 0) {
          setSaveStatus("Actualizando precios...");
          await upsertPricesMutation.mutateAsync({
            productUuid,
            payload: { prices: changedPrices },
          });
          setSaveStatus(`✅ ${changedPrices.length} precio(s) actualizado(s)`);
        }

        // 3. Guardar receta si está habilitada
        if (hasRecipe && typeof (window as any).__saveRecipe === "function") {
          setSaveStatus("Guardando receta...");
          const recipeResult = await (window as any).__saveRecipe();
          if (!recipeResult.saved) {
            setSaveStatus(t("catalog_admin.product_saved_recipe_error"));
          } else {
            setSaveStatus(t("catalog_admin.product_recipe_saved"));
          }
        }
      }

      // Cerrar después de breve delay si hubo actualización de precios (para ver el mensaje)
      if (saveStatus) {
        setTimeout(onClose, 800);
      } else {
        onClose();
      }
    } catch (err) {
      console.error("Error al guardar producto:", err);
      setSaveStatus("❌ Error al guardar");
    }
  };

  const isSaving =
    createMutation.isPending ||
    updateMutation.isPending ||
    upsertPricesMutation.isPending;

  // CSS class para selects: incluye color-scheme dark para que el dropdown del SO sea oscuro
  const selectClass =
    "w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 [color-scheme:dark]";

  return (
    <div className="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 flex items-center justify-center p-4">
      <div className="bg-slate-900 border border-slate-700 rounded-xl shadow-2xl max-w-2xl w-full p-6 max-h-[90vh] overflow-y-auto">
        <h2 className="text-xl font-bold text-white mb-4">
          {product ? t("catalog_admin.edit_product") : t("catalog_admin.new_product")}
        </h2>

        <form onSubmit={handleSubmit} className="space-y-4">
          {/* Sección 1: Info básica */}
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="block text-sm text-slate-400 mb-1.5">{t("products.sku")}</label>
              <input
                type="text"
                value={sku}
                onChange={(e) => setSku(e.target.value)}
                required
                placeholder={t("products.sku_placeholder")}
                className="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-2 focus:ring-orange-500"
              />
            </div>
            <div>
              <label className="block text-sm text-slate-400 mb-1.5">{t("catalog_admin.category_required")}</label>
              <select
                value={categoryId}
                onChange={(e) => setCategoryId(e.target.value)}
                required
                className={selectClass}
              >
                <option value="">{t("products.select")}</option>
                {categories.map((cat) => (
                  <option key={cat.uuid} value={cat.uuid}>
                    {getTranslatedName(cat.name_translations)}
                  </option>
                ))}
              </select>
            </div>
          </div>

          <div>
            <label className="block text-sm text-slate-400 mb-1.5">{t("catalog_admin.name_es_required")}</label>
            <input
              type="text"
              value={nameEs}
              onChange={(e) => setNameEs(e.target.value)}
              required
              className="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-orange-500"
            />
          </div>

          {/* Sección 2: Precio base e IVA */}
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="block text-sm text-slate-400 mb-1.5">{t("products.base_price")}</label>
              <input
                type="number"
                min="0"
                step="10"
                value={basePrice || ""}
                onChange={(e) => setBasePrice(parseFloat(e.target.value) || 0)}
                required
                className="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-orange-500"
              />
              <p className="text-xs text-slate-400 mt-1">{t("products.fallback_help")}</p>
            </div>
            <div>
              <label className="block text-sm text-slate-400 mb-1.5">{t("products.iva_rate")}</label>
              <input
                type="number"
                min="0"
                max="100"
                step="0.01"
                value={taxRate || ""}
                onChange={(e) => setTaxRate(parseFloat(e.target.value) || 0)}
                className="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-orange-500"
              />
            </div>
          </div>

          {/* Sección 3: Precios por lista (solo en modo edición) */}
          {product && priceLists.length > 0 && (
            <div className="border border-slate-700 rounded-lg p-4 bg-slate-800/30">
              <div className="flex items-center justify-between mb-3">
                <div>
                  <h3 className="text-sm font-semibold text-white">{t("products.prices_by_list_title")}</h3>
                  <p className="text-xs text-slate-400 mt-0.5">
                    Define el precio específico para cada canal de venta
                  </p>
                </div>
                {loadingPrices && (
                  <span className="text-xs text-slate-400">Cargando...</span>
                )}
              </div>

              <div className="space-y-2">
                {priceLists
                  .filter((list) => list.is_active)
                  .map((list) => {
                    const state = priceEditorState[list.uuid];
                    return (
                      <div
                        key={list.uuid}
                        className="flex items-center gap-3 p-2.5 bg-slate-900/50 rounded-lg border border-slate-700/50"
                      >
                        <div className="flex-1 min-w-0">
                          <div className="flex items-center gap-2 flex-wrap">
                            <span className="text-sm font-medium text-white truncate">
                              {list.display_name}
                            </span>
                            {list.channel_type && (
                              <span className="text-xs px-1.5 py-0.5 rounded bg-blue-500/20 text-blue-300 border border-blue-500/30">
                                {CHANNEL_LABELS[list.channel_type] ?? list.channel_type}
                              </span>
                            )}
                            {list.is_default && (
                              <span className="text-xs px-1.5 py-0.5 rounded bg-amber-500/20 text-amber-300 border border-amber-500/30">
                                ⭐ Default
                              </span>
                            )}
                          </div>
                          {state?.updatedAt && (
                            <p className="text-xs text-slate-400 mt-0.5">
                              Actualizado: {formatDateTime(state.updatedAt)}
                            </p>
                          )}
                        </div>
                        <div className="flex items-center gap-1.5 flex-shrink-0">
                          <span className="text-sm text-slate-400">$</span>
                          <input
                            type="number"
                            min="0"
                            step="10"
                            value={state?.price ?? ""}
                            onChange={(e) => {
                              const val = e.target.value === "" ? null : parseFloat(e.target.value);
                              updatePriceForList(list.uuid, val);
                            }}
                            placeholder={basePrice ? String(basePrice) : "—"}
                            className="w-24 px-2 py-1.5 bg-slate-800 border border-slate-700 rounded text-white text-sm focus:outline-none focus:ring-2 focus:ring-orange-500"
                          />
                        </div>
                      </div>
                    );
                  })}

                {priceLists.filter((l) => l.is_active).length === 0 && (
                  <p className="text-xs text-slate-400 text-center py-2">
                    No hay listas de precios activas. Crea una desde la pestaña "Listas de Precios".
                  </p>
                )}
              </div>
            </div>
          )}

          {product && priceLists.length === 0 && (
            <p className="text-xs text-amber-400 bg-amber-500/10 border border-amber-500/30 rounded-lg p-3">
              ⚠️ No hay listas de precios configuradas. Ve a la pestaña "Listas de Precios" para crearlas.
            </p>
          )}

          {!product && priceLists.length > 0 && (
            <p className="text-xs text-slate-400 bg-slate-800/50 border border-slate-700 rounded-lg p-3">
              💡 Los precios por lista se configuran después de crear el producto.
            </p>
          )}

          {/* Sección 4: Flags */}
          <div className="flex gap-4 flex-wrap">
            {/* Wizard de tipo de producto: Simple / Compuesto / Combo */}
            <div className="grid grid-cols-3 gap-2 col-span-full">
              <button
                type="button"
                onClick={() => {
                  setIsCombo(false);
                  setHasRecipe(false);
                }}
                className={`p-3 rounded-lg border-2 transition-all ${
                  !isCombo && !hasRecipe
                    ? "border-orange-500 bg-orange-500/10"
                    : "border-slate-700 hover:border-slate-600"
                }`}
              >
                <div className="text-2xl mb-1">🍽️</div>
                <div className="text-sm font-medium text-white">
                  {t("products.type_simple")}
                </div>
                <div className="text-xs text-slate-400 mt-1">
                  {t("products.type_simple_desc")}
                </div>
              </button>

              <button
                type="button"
                onClick={() => {
                  setIsCombo(false);
                  setHasRecipe(true);
                }}
                className={`p-3 rounded-lg border-2 transition-all ${
                  !isCombo && hasRecipe
                    ? "border-orange-500 bg-orange-500/10"
                    : "border-slate-700 hover:border-slate-600"
                }`}
              >
                <div className="text-2xl mb-1">🍳</div>
                <div className="text-sm font-medium text-white">
                  {t("products.type_compound")}
                </div>
                <div className="text-xs text-slate-400 mt-1">
                  {t("products.type_compound_desc")}
                </div>
              </button>

              <button
                type="button"
                onClick={() => {
                  setIsCombo(true);
                  setHasRecipe(false);
                }}
                className={`p-3 rounded-lg border-2 transition-all ${
                  isCombo && !hasRecipe
                    ? "border-orange-500 bg-orange-500/10"
                    : "border-slate-700 hover:border-slate-600"
                }`}
              >
                <div className="text-2xl mb-1">📦</div>
                <div className="text-sm font-medium text-white">
                  {t("products.type_combo")}
                </div>
                <div className="text-xs text-slate-400 mt-1">
                  {t("products.type_combo_desc")}
                </div>
              </button>
            </div>
            <label className="flex items-center gap-2 cursor-pointer">
              <input
                type="checkbox"
                checked={isActive}
                onChange={(e) => setIsActive(e.target.checked)}
                className="w-4 h-4 accent-orange-500"
              />
              <span className="text-sm text-slate-300">{t("catalog_admin.active_product")}</span>
            </label>

          </div>

          {/* Sección 5: Receta (ingredientes y costos) — solo en modo edición */}
          {product && hasRecipe && (
            <RecipeSection
              product={product}
              enabled={hasRecipe}
              onSave={() => {}}
            />
          )}

          {/* Sección 6: Productos del combo — solo si es combo */}
          {product && isCombo && (
            <ComboProductsEditor
              product={product}
              comboProducts={comboProducts}
              onChange={setComboProducts}
            />
          )}

          {/* Status */}
          {saveStatus && (
            <p className="text-sm text-center text-slate-300">{saveStatus}</p>
          )}

          {/* Botones */}
          <div className="flex gap-2 pt-2">
            <button
              type="submit"
              disabled={isSaving}
              className="flex-1 px-4 py-2 bg-orange-500 hover:bg-orange-600 disabled:bg-slate-700 text-white rounded-lg font-medium transition-colors"
            >
              {isSaving
                ? "Guardando..."
                : product
                ? t("catalog_admin.save_changes")
                : t("catalog_admin.create_product")}
            </button>
            <button
              type="button"
              onClick={onClose}
              disabled={isSaving}
              className="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white rounded-lg font-medium transition-colors"
            >
              {t("common.cancel")}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}