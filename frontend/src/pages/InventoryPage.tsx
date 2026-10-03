import { useState, useMemo, useEffect } from "react";
import { useTranslation } from "react-i18next";
import { Search, Package, AlertTriangle, Loader2, History, Factory } from "lucide-react";
import { useIngredients } from "@/hooks/useRecipe";
import { IngredientStockCard } from "@/components/inventory/IngredientStockCard";
import { MovementHistoryTable } from "@/components/inventory/MovementHistoryTable";
import { ProductionBatchForm } from "@/components/inventory/ProductionBatchForm";
import type { RawIngredient } from "@/services/recipeService";

export function InventoryPage() {
  const { t } = useTranslation();
  const { data: ingredients = [], isLoading, error } = useIngredients();
  const [selectedUuid, setSelectedUuid] = useState<string | null>(null);
  const [searchQuery, setSearchQuery] = useState("");
  const [showOnlyLowStock, setShowOnlyLowStock] = useState(false);
  const [activeTab, setActiveTab] = useState<"stock" | "production">("stock");

  const filteredIngredients = useMemo(() => {
    let result = ingredients;

    if (showOnlyLowStock) {
      result = result.filter(
        (ing) => ing.is_low_stock || (ing.current_stock_base ?? 0) <= 0
      );
    }

    if (searchQuery.trim()) {
      const q = searchQuery.toLowerCase();
      result = result.filter((ing) => {
        const name =
          ing.name_translations?.es?.toLowerCase() ??
          ing.name_translations?.["zh-CN"]?.toLowerCase() ??
          "";
        const sku = ing.sku.toLowerCase();
        return name.includes(q) || sku.includes(q);
      });
    }

    return result.sort((a, b) => {
      // Low stock primero
      const aLow = a.is_low_stock || (a.current_stock_base ?? 0) <= 0 ? 0 : 1;
      const bLow = b.is_low_stock || (b.current_stock_base ?? 0) <= 0 ? 0 : 1;
      if (aLow !== bLow) return aLow - bLow;
      return a.sku.localeCompare(b.sku);
    });
  }, [ingredients, searchQuery, showOnlyLowStock]);

  const selectedIngredient: RawIngredient | undefined = ingredients.find(
    (i) => i.uuid === selectedUuid
  );

  // Auto-seleccionar el primer insumo si no hay selección
  useEffect(() => {
    if (!selectedUuid && filteredIngredients.length > 0) {
      setSelectedUuid(filteredIngredients[0].uuid);
    }
  }, [selectedUuid, filteredIngredients]);

  const stats = useMemo(() => {
    const total = ingredients.length;
    const lowStock = ingredients.filter((i) => i.is_low_stock).length;
    const outOfStock = ingredients.filter(
      (i) => (i.current_stock_base ?? 0) <= 0
    ).length;
    return { total, lowStock, outOfStock };
  }, [ingredients]);

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-64">
        <Loader2 className="animate-spin text-orange-500" size={48} />
      </div>
    );
  }

  if (error) {
    return (
      <div className="bg-red-900/30 border border-red-800 rounded-lg p-8 text-center">
        <AlertTriangle className="mx-auto text-red-400 mb-3" size={48} />
        <p className="text-red-300">Error al cargar insumos: {error.message}</p>
      </div>
    );
  }

  return (
    <div className="max-w-7xl mx-auto">
      {/* Header con stats */}
      <div className="mb-6">
        <div className="flex items-center justify-between mb-4">
          <div>
            <h1 className="text-3xl font-bold text-white flex items-center gap-2">
              <Package className="text-orange-400" size={32} />
              Inventario
            </h1>
            <p className="text-slate-400 mt-1">
              Stock y movimientos de insumos
            </p>
          </div>

          <div className="flex items-center gap-3">
            <StatPill
              label="Total"
              value={stats.total}
              color="text-slate-300"
            />
            <StatPill
              label="Stock bajo"
              value={stats.lowStock}
              color="text-yellow-400"
            />
            <StatPill
              label="Sin stock"
              value={stats.outOfStock}
              color="text-red-400"
            />
          </div>
        </div>

        {/* Tabs */}
        <div className="flex gap-2 mb-4 border-b border-slate-700">
          <button
            onClick={() => setActiveTab("stock")}
            className={`flex items-center gap-2 px-4 py-2 font-medium transition-colors border-b-2 ${
              activeTab === "stock"
                ? "border-orange-500 text-orange-400"
                : "border-transparent text-slate-400 hover:text-white"
            }`}
          >
            <History size={18} />
            <span>Stock & Movimientos</span>
          </button>
          <button
            onClick={() => setActiveTab("production")}
            className={`flex items-center gap-2 px-4 py-2 font-medium transition-colors border-b-2 ${
              activeTab === "production"
                ? "border-orange-500 text-orange-400"
                : "border-transparent text-slate-400 hover:text-white"
            }`}
          >
            <Factory size={18} />
            <span>Producción</span>
          </button>
        </div>

        {/* Barra de búsqueda y filtros (solo en tab stock) */}
        {activeTab === "stock" && (
          <div className="flex items-center gap-3">
            <div className="relative flex-1 max-w-md">
              <Search
                size={18}
                className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"
              />
              <input
                type="text"
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                placeholder="Buscar por nombre o SKU..."
                className="w-full pl-10 pr-4 py-2.5 bg-slate-800 border border-slate-700 rounded-lg text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-orange-500"
              />
            </div>

            <label className="flex items-center gap-2 cursor-pointer">
              <input
                type="checkbox"
                checked={showOnlyLowStock}
                onChange={(e) => setShowOnlyLowStock(e.target.checked)}
                className="w-4 h-4 rounded border-slate-600 bg-slate-700 text-orange-500 focus:ring-orange-500"
              />
              <span className="text-sm text-slate-300">
                Solo stock bajo / sin stock
              </span>
            </label>
          </div>
        )}
      </div>

      {/* Contenido según tab activo */}
      {activeTab === "production" ? (
        <ProductionBatchForm />
      ) : (
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {/* Lista de insumos */}
          <div className="lg:col-span-1">
            <div className="bg-slate-800/30 border border-slate-700 rounded-lg p-3 max-h-[calc(100vh-280px)] overflow-y-auto">
              {filteredIngredients.length === 0 ? (
                <div className="text-center py-8">
                  <Package className="mx-auto text-slate-500 mb-2" size={32} />
                  <p className="text-sm text-slate-400">
                    {searchQuery || showOnlyLowStock
                      ? "Sin resultados"
                      : "No hay insumos registrados"}
                  </p>
                </div>
              ) : (
                <div className="space-y-2">
                  {filteredIngredients.map((ing) => (
                    <IngredientStockCard
                      key={ing.uuid}
                      ingredient={ing}
                      isSelected={selectedUuid === ing.uuid}
                      onClick={() => setSelectedUuid(ing.uuid)}
                    />
                  ))}
                </div>
              )}
            </div>
          </div>

          {/* Panel derecho: historial de movimientos */}
          <div className="lg:col-span-2">
            <MovementHistoryTable
              ingredientUuid={selectedUuid}
              ingredientName={
                selectedIngredient?.name_translations?.es ??
                selectedIngredient?.name_translations?.["zh-CN"] ??
                selectedIngredient?.sku ??
                ""
              }
            />
          </div>
        </div>
      )}
    </div>
  );
}

function StatPill({
  label,
  value,
  color,
}: {
  label: string;
  value: number;
  color: string;
}) {
  return (
    <div className="bg-slate-800/50 border border-slate-700 rounded-lg px-4 py-2">
      <p className="text-xs text-slate-400">{label}</p>
      <p className={`text-lg font-bold font-mono ${color}`}>{value}</p>
    </div>
  );
}
