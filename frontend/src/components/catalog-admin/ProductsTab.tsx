import { useState, useEffect } from "react";
import {
  Plus,
  Pencil,
  Trash2,
  Package,
  Search,
  Loader2,
  AlertCircle,
} from "lucide-react";
import {
  useAdminProducts,
  useAdminCategories,
  useCreateProduct,
  useUpdateProduct,
  useDeleteProduct,
} from "@/hooks/useCatalogAdmin";
import { usePriceLists, useUpsertProductPrices } from "@/hooks/usePriceLists";
import { useProductPrices } from "@/hooks/useProductPrices";
import type { Product, Category } from "@/types/catalog";
import type { PriceList, ProductPrice } from "@/services/priceListService";
import { getTranslatedName, formatPrice } from "@/types/catalog";
import { RecipeSection } from "./RecipeSection";

export function ProductsTab() {
  const [searchQuery, setSearchQuery] = useState("");
  const [selectedCategoryId, setSelectedCategoryId] = useState<number | null>(null);
  const [editingProduct, setEditingProduct] = useState<Product | null>(null);
  const [showCreateModal, setShowCreateModal] = useState(false);

  const { data: categories = [] } = useAdminCategories();
  const { data: products = [], isLoading, error } = useAdminProducts({
    categoryId: selectedCategoryId ?? undefined,
    search: searchQuery,
  });

  const deleteMutation = useDeleteProduct();

  const handleDelete = async (product: Product) => {
    const name = getTranslatedName(product.name_translations);
    if (confirm(`¿Eliminar el producto "${name}"?`)) {
      try {
        await deleteMutation.mutateAsync(product.uuid);
      } catch (err) {
        console.error("Error al eliminar producto:", err);
      }
    }
  };

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-64">
        <Loader2 className="animate-spin text-orange-500" size={48} />
      </div>
    );
  }

  if (error) {
    return (
      <div className="bg-red-900/30 border border-red-800 rounded-lg p-6 text-center">
        <AlertCircle className="mx-auto text-red-400 mb-3" size={32} />
        <p className="text-red-300">Error al cargar productos</p>
      </div>
    );
  }

  return (
    <div>
      {/* Header con búsqueda y filtros */}
      <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-4">
        <h2 className="text-xl font-semibold">
          Productos ({products.length})
        </h2>

        <div className="flex flex-col sm:flex-row gap-2 w-full md:w-auto">
          {/* Búsqueda */}
          <div className="relative">
            <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
            <input
              type="text"
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              placeholder="Buscar por nombre o SKU..."
              className="pl-9 pr-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-orange-500 w-full sm:w-64"
            />
          </div>

          {/* Filtro por categoría */}
          <select
            value={selectedCategoryId ?? ""}
            onChange={(e) =>
              setSelectedCategoryId(e.target.value ? parseInt(e.target.value) : null)
            }
            className="px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-2 focus:ring-orange-500"
          >
            <option value="">Todas las categorías</option>
            {categories.map((cat) => (
              <option key={cat.uuid} value={cat.id}>
                {getTranslatedName(cat.name_translations)}
              </option>
            ))}
          </select>

          {/* Botón crear */}
          <button
            onClick={() => setShowCreateModal(true)}
            className="flex items-center gap-2 px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg font-medium transition-colors"
          >
            <Plus size={18} />
            Nuevo producto
          </button>
        </div>
      </div>

      {/* Lista de productos */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        {products.map((product) => (
          <div
            key={product.uuid}
            className="bg-slate-800/50 border border-slate-700 rounded-xl p-4 hover:border-orange-500/50 transition-all"
          >
            <div className="flex items-start justify-between gap-2 mb-2">
              <div className="flex-1">
                <h3 className="font-semibold text-white leading-tight">
                  {getTranslatedName(product.name_translations)}
                </h3>
                <p className="text-xs text-slate-500 mt-0.5">{product.sku}</p>
              </div>
              <div className="flex gap-1">
                <button
                  onClick={() => setEditingProduct(product)}
                  className="p-1.5 text-slate-400 hover:text-white hover:bg-slate-700 rounded transition-colors"
                  title="Editar"
                >
                  <Pencil size={14} />
                </button>
                <button
                  onClick={() => handleDelete(product)}
                  className="p-1.5 text-slate-400 hover:text-red-400 hover:bg-slate-700 rounded transition-colors"
                  title="Eliminar"
                >
                  <Trash2 size={14} />
                </button>
              </div>
            </div>

            {product.category && (
              <p className="text-xs text-slate-400 mb-2">
                📂 {getTranslatedName(product.category.name_translations)}
              </p>
            )}

            <div className="flex items-center justify-between gap-2 pt-2 border-t border-slate-700">
              <span className="text-lg font-bold text-orange-400">
                {formatPrice(product.base_price)}
              </span>
              <div className="flex gap-1.5">
                {product.is_combo && (
                  <span className="px-2 py-0.5 rounded-full bg-purple-500/20 text-purple-300 border border-purple-500/30 text-xs">
                    Combo
                  </span>
                )}
                {product.is_active ? (
                  <span className="px-2 py-0.5 rounded-full bg-green-500/20 text-green-300 border border-green-500/30 text-xs">
                    Activo
                  </span>
                ) : (
                  <span className="px-2 py-0.5 rounded-full bg-red-500/20 text-red-300 border border-red-500/30 text-xs">
                    Inactivo
                  </span>
                )}
              </div>
            </div>
          </div>
        ))}
      </div>

      {products.length === 0 && (
        <div className="bg-slate-800/50 border border-slate-700 rounded-lg p-12 text-center">
          <Package className="mx-auto text-slate-500 mb-3" size={48} />
          <p className="text-slate-400">
            {searchQuery || selectedCategoryId
              ? "No hay productos con los filtros aplicados"
              : "No hay productos creados"}
          </p>
        </div>
      )}

      {/* Modales */}
      {showCreateModal && (
        <ProductFormModal categories={categories} onClose={() => setShowCreateModal(false)} />
      )}

      {editingProduct && (
        <ProductFormModal
          product={editingProduct}
          categories={categories}
          onClose={() => setEditingProduct(null)}
        />
      )}
    </div>
  );
}

/* ─── Constantes para canales ─── */

const CHANNEL_LABELS: Record<string, string> = {
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

interface ProductFormModalProps {
  product?: Product;
  categories: Category[];
  onClose: () => void;
}

/**
 * Estado de precios por lista.
 * Key: uuid de la price list
 * Value: { price: precio editado, updatedAt: fecha del último guardado }
 */
type PriceEditorState = Record<
  string,
  { price: number | null; updatedAt: string | null }
>;

function ProductFormModal({ product, categories, onClose }: ProductFormModalProps) {
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
            setSaveStatus("⚠️ Producto guardado, pero error al guardar receta");
          } else {
            setSaveStatus("✅ Producto y receta guardados");
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
          {product ? "Editar producto" : "Nuevo producto"}
        </h2>

        <form onSubmit={handleSubmit} className="space-y-4">
          {/* Sección 1: Info básica */}
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="block text-sm text-slate-400 mb-1.5">SKU *</label>
              <input
                type="text"
                value={sku}
                onChange={(e) => setSku(e.target.value)}
                required
                placeholder="Ej: BEB-001"
                className="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-2 focus:ring-orange-500"
              />
            </div>
            <div>
              <label className="block text-sm text-slate-400 mb-1.5">Categoría *</label>
              <select
                value={categoryId}
                onChange={(e) => setCategoryId(e.target.value)}
                required
                className={selectClass}
              >
                <option value="">Selecciona...</option>
                {categories.map((cat) => (
                  <option key={cat.uuid} value={cat.uuid}>
                    {getTranslatedName(cat.name_translations)}
                  </option>
                ))}
              </select>
            </div>
          </div>

          <div>
            <label className="block text-sm text-slate-400 mb-1.5">
              Nombre (español) *
            </label>
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
              <label className="block text-sm text-slate-400 mb-1.5">
                Precio base (CLP) *
              </label>
              <input
                type="number"
                min="0"
                step="10"
                value={basePrice || ""}
                onChange={(e) => setBasePrice(parseFloat(e.target.value) || 0)}
                required
                className="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-orange-500"
              />
              <p className="text-xs text-slate-500 mt-1">Fallback si la lista no tiene precio</p>
            </div>
            <div>
              <label className="block text-sm text-slate-400 mb-1.5">
                Tasa de IVA (%)
              </label>
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
                  <h3 className="text-sm font-semibold text-white">
                    📋 Precios por lista
                  </h3>
                  <p className="text-xs text-slate-500 mt-0.5">
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
                            <p className="text-xs text-slate-500 mt-0.5">
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
                  <p className="text-xs text-slate-500 text-center py-2">
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
            <p className="text-xs text-slate-500 bg-slate-800/50 border border-slate-700 rounded-lg p-3">
              💡 Los precios por lista se configuran después de crear el producto.
            </p>
          )}

          {/* Sección 4: Flags */}
          <div className="flex gap-4 flex-wrap">
            <label className="flex items-center gap-2 cursor-pointer">
              <input
                type="checkbox"
                checked={isCombo}
                onChange={(e) => setIsCombo(e.target.checked)}
                className="w-4 h-4 accent-orange-500"
              />
              <span className="text-sm text-slate-300">📦 Es combo</span>
            </label>
            <label className="flex items-center gap-2 cursor-pointer">
              <input
                type="checkbox"
                checked={isActive}
                onChange={(e) => setIsActive(e.target.checked)}
                className="w-4 h-4 accent-orange-500"
              />
              <span className="text-sm text-slate-300">Producto activo</span>
            </label>
            {product && (
              <label className="flex items-center gap-2 cursor-pointer">
                <input
                  type="checkbox"
                  checked={hasRecipe}
                  onChange={(e) => setHasRecipe(e.target.checked)}
                  className="w-4 h-4 accent-orange-500"
                />
                <span className="text-sm text-slate-300">🧾 Tiene receta</span>
              </label>
            )}
          </div>

          {/* Sección 5: Receta (ingredientes y costos) — solo en modo edición */}
          {product && hasRecipe && (
            <RecipeSection
              product={product}
              enabled={hasRecipe}
              onSave={() => {}}
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
                ? "Guardar cambios"
                : "Crear producto"}
            </button>
            <button
              type="button"
              onClick={onClose}
              disabled={isSaving}
              className="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white rounded-lg font-medium transition-colors"
            >
              Cancelar
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
