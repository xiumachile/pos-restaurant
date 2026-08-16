import { useState, useMemo } from "react";
import { useCategories, useProducts } from "@/hooks/useCatalog";
import { CategoryList } from "@/components/catalog/CategoryList";
import { ProductCard } from "@/components/catalog/ProductCard";
import type { Product } from "@/types/catalog";
import { Search, Loader2, AlertCircle, Package } from "lucide-react";

export function CatalogPage() {
  const [selectedCategoryId, setSelectedCategoryId] = useState<number | null>(null);
  const [searchQuery, setSearchQuery] = useState("");

  const {
    data: categories = [],
    isLoading: loadingCategories,
  } = useCategories();

  const {
    data: products = [],
    isLoading: loadingProducts,
    error,
  } = useProducts({
    categoryId: selectedCategoryId,
    search: searchQuery,
  });

  // Contar productos por categoría (usando todos los productos sin filtro para el contador)
  const productsByCategory = useMemo(() => {
    const counts: Record<number, number> = {};
    products.forEach((p) => {
      counts[p.category_id] = (counts[p.category_id] || 0) + 1;
    });
    return counts;
  }, [products]);

  const handleAddProduct = (product: Product) => {
    // TODO: Conectar con el store de carrito en F13.5
    console.log("Agregar al carrito:", product);
    alert(
      `Agregar al carrito:\n` +
      `• ${product.sku}\n` +
      `• Precio: $${product.base_price}\n\n` +
      `Funcionalidad a implementar en F13.5`
    );
  };

  const isLoading = loadingCategories || loadingProducts;

  return (
    <div>
      {/* Header */}
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-3xl font-bold">Catálogo</h1>
          <p className="text-slate-400 mt-1">
            {products.length} productos disponibles
          </p>
        </div>

        {/* Búsqueda */}
        <div className="relative w-96">
          <Search
            size={18}
            className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"
          />
          <input
            type="text"
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            placeholder="Buscar por nombre o SKU..."
            className="w-full pl-10 pr-4 py-2.5 bg-slate-800 border border-slate-700 rounded-lg text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent"
          />
        </div>
      </div>

      {/* Contenido: Sidebar + Grid */}
      <div className="flex gap-6">
        {/* Sidebar de categorías */}
        {!loadingCategories && (
          <CategoryList
            categories={categories}
            selectedId={selectedCategoryId}
            productsByCategory={productsByCategory}
            totalProducts={products.length}
            onSelect={setSelectedCategoryId}
          />
        )}

        {/* Grid de productos */}
        <div className="flex-1">
          {isLoading ? (
            <div className="flex items-center justify-center h-64">
              <Loader2 className="animate-spin text-orange-500" size={48} />
            </div>
          ) : error ? (
            <div className="bg-red-900/30 border border-red-800 rounded-lg p-6 text-center">
              <AlertCircle className="mx-auto text-red-400 mb-3" size={32} />
              <p className="text-red-300">Error al cargar productos</p>
              <p className="text-sm text-red-400 mt-2">
                {(error as Error).message}
              </p>
            </div>
          ) : products.length === 0 ? (
            <div className="bg-slate-800/50 border border-slate-700 rounded-lg p-12 text-center">
              <Package className="mx-auto text-slate-500 mb-3" size={48} />
              <p className="text-slate-400">
                {searchQuery
                  ? `No se encontraron productos para "${searchQuery}"`
                  : "No hay productos en esta categoría"}
              </p>
            </div>
          ) : (
            <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
              {products.map((product) => (
                <ProductCard
                  key={product.id}
                  product={product}
                  onAdd={handleAddProduct}
                />
              ))}
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
