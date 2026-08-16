import { useState } from "react";
import { useCategories, useProducts } from "@/hooks/useCatalog";
import { CategoryList } from "@/components/catalog/CategoryList";
import { ProductCard } from "@/components/catalog/ProductCard";
import { CartDrawer } from "@/components/cart/CartDrawer";
import { SelectTableModal } from "@/components/cart/SelectTableModal";
import { useCartStore } from "@/stores/useCartStore";
import type { Product } from "@/types/catalog";
import { ShoppingCart, Search, Package, Loader2, AlertCircle } from "lucide-react";

export function CatalogPage() {
  const [selectedCategoryId, setSelectedCategoryId] = useState<number | null>(null);
  const [searchQuery, setSearchQuery] = useState("");
  const [isCartOpen, setIsCartOpen] = useState(false);
  const [isSelectTableOpen, setIsSelectTableOpen] = useState(false);

  const { tableNumber, addItem, getTotals, tableId } = useCartStore();
  const totals = getTotals();

  const { data: categories = [], isLoading: loadingCategories } = useCategories();

  const { data: products = [], isLoading: loadingProducts, error } = useProducts({
    categoryId: selectedCategoryId,
    search: searchQuery,
  });

  const productsByCategory = products.reduce((acc, p) => {
    acc[p.category_id] = (acc[p.category_id] || 0) + 1;
    return acc;
  }, {} as Record<number, number>);

  const handleAddProduct = (product: Product) => {
    if (!tableId) {
      setIsSelectTableOpen(true);
      return;
    }
    addItem(product);
    setIsCartOpen(true);
  };

  const handleTableSelected = (tableId: string, tableNumber: string) => {
    useCartStore.getState().setTable(tableId, tableNumber);
    setIsSelectTableOpen(false);
    setIsCartOpen(true);
  };

  const isLoading = loadingCategories || loadingProducts;

  return (
    <div className="flex h-full">
      <div className="flex-1 flex flex-col overflow-hidden">
        <div className="flex items-center justify-between p-4 border-b border-slate-700">
          <div>
            <h1 className="text-2xl font-bold">Catálogo</h1>
            <p className="text-sm text-slate-400">
              {products.length} productos disponibles
              {tableNumber && (
                <span className="ml-2 px-2 py-0.5 bg-orange-500/20 text-orange-400 rounded">
                  Mesa {tableNumber}
                </span>
              )}
            </p>
          </div>

          <div className="flex items-center gap-3">
            <div className="relative">
              <Search size={18} className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
              <input
                type="text"
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                placeholder="Buscar..."
                className="w-64 pl-10 pr-4 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-orange-500"
              />
            </div>

            <button
              onClick={() => setIsCartOpen(true)}
              className="relative px-4 py-2 bg-orange-500 hover:bg-orange-600 rounded-lg transition-colors flex items-center gap-2"
            >
              <ShoppingCart size={18} />
              Carrito
              {totals.itemCount > 0 && (
                <span className="absolute -top-2 -right-2 bg-red-500 text-white text-xs font-bold rounded-full w-5 h-5 flex items-center justify-center">
                  {totals.itemCount}
                </span>
              )}
            </button>
          </div>
        </div>

        <div className="flex-1 flex overflow-hidden">
          {!loadingCategories && (
            <CategoryList
              categories={categories}
              selectedId={selectedCategoryId}
              productsByCategory={productsByCategory}
              totalProducts={products.length}
              onSelect={setSelectedCategoryId}
            />
          )}

          <div className="flex-1 overflow-y-auto p-4">
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
                  {searchQuery ? `No se encontraron productos para "${searchQuery}"` : "No hay productos"}
                </p>
              </div>
            ) : (
              <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
                {products.map((product) => (
                  <ProductCard key={product.id} product={product} onAdd={handleAddProduct} />
                ))}
              </div>
            )}
          </div>
        </div>
      </div>

      <CartDrawer isOpen={isCartOpen} onClose={() => setIsCartOpen(false)} />
      <SelectTableModal
        isOpen={isSelectTableOpen}
        onClose={() => setIsSelectTableOpen(false)}
        onSelect={handleTableSelected}
      />
    </div>
  );
}
