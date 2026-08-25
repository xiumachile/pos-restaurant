import { useState, useEffect, useMemo } from "react";
import { Plus, Trash2, AlertTriangle, Loader2 } from "lucide-react";
import {
  useIngredients,
  useProductRecipe,
  useCreateRecipe,
  useUpdateRecipe,
  useDeleteRecipe,
} from "@/hooks/useRecipe";
import type { Product } from "@/types/catalog";
import type {
  RawIngredient,
  ProductRecipe,
  RecipeIngredientPayload,
} from "@/services/recipeService";
import { getTranslatedName } from "@/types/catalog";

/* ─── Constantes ─── */

const UNIT_LABELS: Record<string, string> = {
  g: "g",
  kg: "kg",
  ml: "ml",
  l: "l",
  unit: "un",
  units: "un",
};

function formatUnit(unit: string | null): string {
  if (!unit) return "";
  return UNIT_LABELS[unit] ?? unit;
}

function formatCurrency(amount: number): string {
  return new Intl.NumberFormat("es-CL", {
    style: "currency",
    currency: "CLP",
    minimumFractionDigits: 0,
  }).format(amount);
}

/* ─── Estado local de un ingrediente en edición ─── */

interface RecipeItemDraft {
  raw_ingredient_id: number | null;
  ingredient_uuid: string; // para lookup de metadata
  quantity_base_unit: number;
  waste_percentage: number;
}

interface RecipeSectionProps {
  product: Product | null;
  enabled: boolean;
  onSave?: (result: {
    saved: boolean;
    recipeUuid?: string;
  }) => void;
}

/**
 * Sección de receta (ficha técnica / BOM) para un producto.
 * Permite seleccionar ingredientes, cantidades y % de merma.
 * Muestra costo total, food cost % y margen bruto calculados en tiempo real.
 */
export function RecipeSection({ product, enabled, onSave }: RecipeSectionProps) {
  const { data: ingredients = [], isLoading: loadingIngredients } = useIngredients();
  const { data: existingRecipe, isLoading: loadingRecipe } = useProductRecipe(
    enabled && product ? product.uuid : null
  );
  const createMutation = useCreateRecipe();
  const updateMutation = useUpdateRecipe();
  const deleteMutation = useDeleteRecipe();

  const [description, setDescription] = useState("");
  const [yieldServings, setYieldServings] = useState(1);
  const [items, setItems] = useState<RecipeItemDraft[]>([]);
  const [initialized, setInitialized] = useState(false);

  // Construir mapa de ingredientes por uuid/id para lookup rápido
  const ingredientById = useMemo(() => {
    const map = new Map<number, RawIngredient>();
    for (const ing of ingredients) {
      // El backend usa id numérico, pero el resource solo expone uuid
      // Necesitamos un mapa por uuid también
      map.set((ing as any).id ?? 0, ing);
    }
    return map;
  }, [ingredients]);

  const ingredientByUuid = useMemo(() => {
    const map = new Map<string, RawIngredient>();
    for (const ing of ingredients) {
      map.set(ing.uuid, ing);
    }
    return map;
  }, [ingredients]);

  // Inicializar desde receta existente
  useEffect(() => {
    if (!initialized && ingredients.length > 0) {
      if (existingRecipe && existingRecipe.items && existingRecipe.items.length > 0) {
        const drafts: RecipeItemDraft[] = existingRecipe.items.map((item) => ({
          raw_ingredient_id: ingredientByUuid.get(item.ingredient_uuid)
            ? (ingredientByUuid.get(item.ingredient_uuid) as any).id ?? null
            : null,
          ingredient_uuid: item.ingredient_uuid,
          quantity_base_unit: item.quantity_base_unit,
          waste_percentage: item.waste_percentage,
        }));
        setItems(drafts);
        setDescription(existingRecipe.description ?? "");
        setYieldServings(existingRecipe.yield_servings);
      }
      setInitialized(true);
    }
  }, [existingRecipe, ingredients, ingredientByUuid, initialized]);

  const addItem = () => {
    setItems((prev) => [
      ...prev,
      {
        raw_ingredient_id: null,
        ingredient_uuid: "",
        quantity_base_unit: 0,
        waste_percentage: 0,
      },
    ]);
  };

  const removeItem = (index: number) => {
    setItems((prev) => prev.filter((_, i) => i !== index));
  };

  const updateItem = (index: number, patch: Partial<RecipeItemDraft>) => {
    setItems((prev) =>
      prev.map((item, i) => (i === index ? { ...item, ...patch } : item))
    );
  };

  // Calcular costos en tiempo real
  const computedItems = useMemo(() => {
    return items.map((draft) => {
      const ingredient = draft.ingredient_uuid
        ? ingredientByUuid.get(draft.ingredient_uuid)
        : null;

      if (!ingredient) {
        return { ...draft, cost: 0, effective: 0, ingredient: null };
      }

      const effective =
        draft.quantity_base_unit * (1 + draft.waste_percentage / 100);
      const cost = effective * ingredient.cost_per_base_unit;

      return { ...draft, cost, effective, ingredient };
    });
  }, [items, ingredientByUuid]);

  const totalCost = computedItems.reduce((sum, it) => sum + it.cost, 0);
  const basePrice = product ? parseFloat(product.base_price) : 0;
  const foodCostPct = basePrice > 0 ? (totalCost / basePrice) * 100 : 0;
  const grossMargin = Math.max(0, basePrice - totalCost);

  /**
   * Guarda la receta (create o update).
   */
  const saveRecipe = async (): Promise<{ saved: boolean; recipeUuid?: string }> => {
    if (!product) return { saved: false };

    const validItems = computedItems.filter(
      (it) => it.raw_ingredient_id && it.quantity_base_unit > 0
    );

    if (validItems.length === 0) {
      // Si no hay ingredientes, eliminar receta si existe
      if (existingRecipe) {
        try {
          await deleteMutation.mutateAsync(existingRecipe.uuid);
          return { saved: true };
        } catch (err) {
          console.error("Error al eliminar receta:", err);
          return { saved: false };
        }
      }
      return { saved: true };
    }

    const payloadItems: RecipeIngredientPayload[] = validItems.map((it) => ({
      raw_ingredient_id: it.raw_ingredient_id!,
      quantity_base_unit: it.quantity_base_unit,
      waste_percentage: it.waste_percentage,
    }));

    try {
      if (existingRecipe) {
        const result = await updateMutation.mutateAsync({
          recipeUuid: existingRecipe.uuid,
          payload: {
            description: description || null,
            yield_servings: yieldServings,
            ingredients: payloadItems,
          },
        });
        return { saved: true, recipeUuid: result.uuid };
      } else {
        const result = await createMutation.mutateAsync({
          product_uuid: product.uuid,
          description: description || null,
          yield_servings: yieldServings,
          ingredients: payloadItems,
        });
        return { saved: true, recipeUuid: result.uuid };
      }
    } catch (err) {
      console.error("Error al guardar receta:", err);
      return { saved: false };
    }
  };

  // Exponer saveRecipe al padre a través de onSave cuando se monta
  useEffect(() => {
    // El padre debe manejar el guardado coordinado
    // Guardamos referencia en window temporalmente para debug
    (window as any).__saveRecipe = saveRecipe;
  });

  if (!enabled) {
    return null;
  }

  if (loadingIngredients || loadingRecipe) {
    return (
      <div className="flex items-center justify-center py-6">
        <Loader2 className="animate-spin text-orange-500" size={24} />
      </div>
    );
  }

  return (
    <div className="border border-slate-700 rounded-lg p-4 bg-gradient-to-br from-emerald-900/10 to-slate-900/50">
      <div className="flex items-center justify-between mb-3">
        <div>
          <h3 className="text-sm font-semibold text-white flex items-center gap-2">
            🧾 Receta (Ficha técnica)
          </h3>
          <p className="text-xs text-slate-500 mt-0.5">
            Ingredientes necesarios y su costo
          </p>
        </div>
      </div>

      {/* Descripción y porciones */}
      <div className="grid grid-cols-2 gap-3 mb-3">
        <div>
          <label className="block text-xs text-slate-400 mb-1">Descripción</label>
          <input
            type="text"
            value={description}
            onChange={(e) => setDescription(e.target.value)}
            placeholder="Ej: Chaufán de verduras salteado"
            className="w-full px-2.5 py-1.5 bg-slate-800 border border-slate-700 rounded text-white text-sm focus:outline-none focus:ring-2 focus:ring-orange-500"
          />
        </div>
        <div>
          <label className="block text-xs text-slate-400 mb-1">
            Porciones que rinde
          </label>
          <input
            type="number"
            min="1"
            max="100"
            value={yieldServings}
            onChange={(e) => setYieldServings(parseInt(e.target.value) || 1)}
            className="w-full px-2.5 py-1.5 bg-slate-800 border border-slate-700 rounded text-white text-sm focus:outline-none focus:ring-2 focus:ring-orange-500"
          />
        </div>
      </div>

      {/* Lista de ingredientes */}
      <div className="space-y-2 mb-3">
        {computedItems.map((item, index) => (
          <div
            key={index}
            className="bg-slate-900/50 border border-slate-700/50 rounded-lg p-2.5"
          >
            <div className="flex gap-2 items-start">
              {/* Selector de ingrediente */}
              <div className="flex-1 min-w-0">
                <select
                  value={item.ingredient_uuid}
                  onChange={(e) => {
                    const uuid = e.target.value;
                    const ing = ingredientByUuid.get(uuid);
                    updateItem(index, {
                      ingredient_uuid: uuid,
                      raw_ingredient_id: ing ? ((ing as any).id ?? null) : null,
                    });
                  }}
                  className="w-full px-2 py-1.5 bg-slate-800 border border-slate-700 rounded text-white text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 [color-scheme:dark]"
                >
                  <option value="">Seleccionar ingrediente...</option>
                  {ingredients.map((ing) => (
                    <option key={ing.uuid} value={ing.uuid}>
                      {getTranslatedName(ing.name_translations)} ({ing.sku}) ·{" "}
                      {formatUnit(ing.base_unit)}
                    </option>
                  ))}
                </select>
              </div>

              {/* Cantidad */}
              <div className="w-24">
                <input
                  type="number"
                  min="0"
                  step="0.01"
                  value={item.quantity_base_unit || ""}
                  onChange={(e) =>
                    updateItem(index, {
                      quantity_base_unit: parseFloat(e.target.value) || 0,
                    })
                  }
                  placeholder="Cant."
                  className="w-full px-2 py-1.5 bg-slate-800 border border-slate-700 rounded text-white text-sm focus:outline-none focus:ring-2 focus:ring-orange-500"
                />
              </div>

              {/* % merma */}
              <div className="w-16">
                <input
                  type="number"
                  min="0"
                  max="100"
                  step="1"
                  value={item.waste_percentage || ""}
                  onChange={(e) =>
                    updateItem(index, {
                      waste_percentage: parseFloat(e.target.value) || 0,
                    })
                  }
                  placeholder="%"
                  className="w-full px-2 py-1.5 bg-slate-800 border border-slate-700 rounded text-white text-sm focus:outline-none focus:ring-2 focus:ring-orange-500"
                  title="% de merma"
                />
              </div>

              {/* Costo calculado */}
              <div className="w-20 text-right">
                <span className="text-xs text-orange-400 font-medium">
                  {item.ingredient ? formatCurrency(item.cost) : "—"}
                </span>
              </div>

              {/* Eliminar */}
              <button
                type="button"
                onClick={() => removeItem(index)}
                className="p-1.5 text-slate-400 hover:text-red-400 hover:bg-slate-700 rounded transition-colors flex-shrink-0"
                title="Quitar ingrediente"
              >
                <Trash2 size={14} />
              </button>
            </div>

            {/* Info adicional del ingrediente */}
            {item.ingredient && (
              <div className="mt-1.5 flex items-center gap-3 text-xs text-slate-500">
                <span>
                  Stock: {item.ingredient.current_stock_base.toFixed(2)}{" "}
                  {formatUnit(item.ingredient.base_unit)}
                </span>
                {item.ingredient.is_low_stock && (
                  <span className="flex items-center gap-0.5 text-amber-400">
                    <AlertTriangle size={11} />
                    Stock bajo
                  </span>
                )}
                <span>
                  Costo unit: {formatCurrency(item.ingredient.cost_per_base_unit)}/
                  {formatUnit(item.ingredient.base_unit)}
                </span>
                {item.waste_percentage > 0 && (
                  <span>
                    Efectivo: {item.effective.toFixed(2)}{" "}
                    {formatUnit(item.ingredient.base_unit)}
                  </span>
                )}
              </div>
            )}
          </div>
        ))}

        {items.length === 0 && (
          <p className="text-xs text-slate-500 text-center py-3">
            No hay ingredientes agregados
          </p>
        )}
      </div>

      {/* Botón agregar */}
      <button
        type="button"
        onClick={addItem}
        className="flex items-center gap-1.5 px-3 py-1.5 bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-300 rounded-lg text-xs font-medium transition-colors"
      >
        <Plus size={12} />
        Agregar ingrediente
      </button>

      {/* Resumen de costos */}
      {items.length > 0 && (
        <div className="mt-4 pt-3 border-t border-slate-700 space-y-1.5">
          <div className="flex justify-between text-sm">
            <span className="text-slate-400">Costo total receta:</span>
            <span className="text-white font-semibold">
              {formatCurrency(totalCost)}
            </span>
          </div>
          <div className="flex justify-between text-sm">
            <span className="text-slate-400">Precio de venta:</span>
            <span className="text-white">{formatCurrency(basePrice)}</span>
          </div>
          <div className="flex justify-between text-sm">
            <span className="text-slate-400">Food Cost %:</span>
            <span
              className={`font-semibold ${
                foodCostPct <= 30
                  ? "text-green-400"
                  : foodCostPct <= 40
                  ? "text-amber-400"
                  : "text-red-400"
              }`}
            >
              {foodCostPct.toFixed(1)}%
              {foodCostPct > 40 && " ⚠️"}
            </span>
          </div>
          <div className="flex justify-between text-sm">
            <span className="text-slate-400">Margen bruto:</span>
            <span
              className={`font-semibold ${
                grossMargin > 0 ? "text-green-400" : "text-red-400"
              }`}
            >
              {formatCurrency(grossMargin)}
            </span>
          </div>
        </div>
      )}
    </div>
  );
}
