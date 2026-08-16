import { useState } from "react";
import { useCategories, useProducts } from "@/hooks/useCatalog";
import { getTranslatedName, formatPrice } from "@/types/catalog";
import type { Product } from "@/types/catalog";
import { Search, Plus, Package, Loader2 } from "lucide-react";

interface OrderCatalogPanelProps {
  onAddProduct: (product: Product) => void;
}

/**
 * Catálogo compacto para toma de pedidos:
 * búsqueda + tabs horizontales de categorías + grid de productos.
 */
export function OrderCatalogPanel({ onAddProduct }: OrderCatalogPanelProps) {
  const [selectedCategoryId, setSelectedCategoryId] = useState<number | null>(null);
  const [searchQuery, setSearchQuery] = useState("");

  const { data: categories = [], isLoading: loadingCategories } = useCategories();
  const { data: products = [], isLoading: loadingProducts } = useProducts({
    categoryId: selectedCategoryId,
    search: searchQuery,
  });

  const isLoading = loadingCategories || loadingProducts;

  return (
    <div className="flex-1 flex flex-col overflow-hidden">
      {/* Búsqueda */}
      <div className="mb-3">
        <div className="relative">
          <Search size={18} className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
          <input
            type="text"
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            placeholder="Buscar producto o SKU..."
            className="w-full pl-10 pr-4 py-2.5 bg-slate-800 border border-slate-700 rounded-lg text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-orange-500"
          />
        </div>
      </div>

      {/* Tabs de categorías (scroll horizontal) */}
      <div className="flex gap-2 mb-4 overflow-x-auto pb-1 flex-shrink-0">
        <button
          onClick={() => setSelectedCategoryId(null)}
          className={`px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition-colors ${
            selectedCategoryId === null
              ? "bg-orange-500 text-white"
              : "bg-slate-800 text-slate-300 hover:bg-slate-700"
          }`}
        >
          Todos
        </button>
        {categories.map((cat) => (
          <button
            key={cat.id}
            onClick={() => setSelectedCategoryId(cat.id)}
            className={`px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition-colors ${
              selectedCategoryId === cat.id
                ? "bg-orange-500 text-white"
                : "bg-slate-800 text-slate-300 hover:bg-slate-700"
            }`}
          >
            {getTranslatedName(cat.name_translations)}
          </button>
        ))}
      </div>

      {/* Grid de productos */}
      <div className="flex-1 overflow-y-auto">
        {isLoading ? (
          <div className="flex items-center justify-center h-64">
            <Loader2 className="animate-spin text-orange-500" size={40} />
          </div>
        ) : products.length === 0 ? (
          <div className="text-center py-12 text-slate-500">
            <Package size={40} className="mx-auto mb-3 opacity-30" />
            <p>No se encontraron productos</p>
          </div>
        ) : (
          <div className="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-3">
            {products.map((product) => (
              <button
                key={product.id}
                onClick={() => onAddProduct(product)}
                className="bg-slate-800/50 border border-slate-700 rounded-xl p-3 text-left hover:border-orange-500/60 hover:bg-slate-800 active:scale-95 transition-all group"
              >
                <div className="flex items-start justify-between gap-1 mb-1">
                  <h3 className="font-semibold text-white text-sm leading-tight">
                    {getTranslatedName(product.name_translations)}
                  </h3>
                  <span className="opacity-0 group-hover:opacity-100 transition-opacity text-orange-400 flex-shrink-0">
                    <Plus size={16} />
                  </span>
                </div>
                <p className="text-xs text-slate-500 mb-2">{product.sku}</p>
                <p className="text-base font-bold text-orange-400">
                  {formatPrice(product.base_price)}
                </p>
              </button>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
