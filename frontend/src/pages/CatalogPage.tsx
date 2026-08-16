import { useState } from "react";
import { useCategories, useProducts } from "@/hooks/useCatalog";
import { CategoryList } from "@/components/catalog/CategoryList";
import { ProductCard } from "@/components/catalog/ProductCard";
import { Search, Loader2, AlertCircle, Package } from "lucide-react";

/**
 * Vista de solo lectura del catálogo (gestión/administración).
 * El flujo de pedidos se hace desde las mesas (OrderTakingPage).
 */
export function CatalogPage() {
  const [selectedCategoryId, setSelectedCategoryId] = useState<number | null>(null);
  const [searchQuery, setSearchQuery] = useState("");

  const { data: categories = [], isLoading: loadingCategories } = useCategories();
  const { data: products = [], isLoading: loadingProducts, error } = useProducts({
    categoryId: selectedCategoryId,
    search: searchQuery,
  });

  const productsByCategory = products.reduce((acc, p) => {
    acc[p.category_id] = (acc[p.category_id] || 0) + 1;
    return acc;
  }, {} as Record<number, number>);

  const isLoading = loadingCategories || loadingProducts;

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-3xl font-bold">Catálogo</h1>
          <p className="text-slate-400 mt-1">
            {products.length} productos · Vista de administración
          </p>
        </div>

        <div className="relative w-96">
          <Search size={18} className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
          <input
            type="text"
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            placeholder="Buscar por nombre o SKU..."
            className="w-full pl-10 pr-4 py-2.5 bg-slate-800 border border-slate-700 rounded-lg text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-orange-500"
          />
        </div>
      </div>

      <div className="flex gap-6">
        {!loadingCategories && (
          <CategoryList
            categories={categories}
            selectedId={selectedCategoryId}
            productsByCategory={productsByCategory}
            totalProducts={products.length}
            onSelect={setSelectedCategoryId}
          />
        )}

        <div className="flex-1">
          {isLoading ? (
            <div className="flex items-center justify-center h-64">
              <Loader2 className="animate-spin text-orange-500" size={48} />
            </div>
          ) : error ? (
            <div className="bg-red-900/30 border border-red-800 rounded-lg p-6 text-center">
              <AlertCircle className="mx-auto text-red-400 mb-3" size={32} />
              <p className="text-red-300">Error al cargar productos</p>
            </div>
          ) : products.length === 0 ? (
            <div className="bg-slate-800/50 border border-slate-700 rounded-lg p-12 text-center">
              <Package className="mx-auto text-slate-500 mb-3" size={48} />
              <p className="text-slate-400">
                {searchQuery ? `Sin resultados para "${searchQuery}"` : "No hay productos"}
              </p>
            </div>
          ) : (
            <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
              {products.map((product) => (
                <ProductCard key={product.id} product={product} />
              ))}
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
