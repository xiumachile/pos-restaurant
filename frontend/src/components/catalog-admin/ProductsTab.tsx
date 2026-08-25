import { useState } from "react";
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
import type { Product, Category } from "@/types/catalog";
import { getTranslatedName, formatPrice } from "@/types/catalog";

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

/* ─── Modal de formulario de producto ─── */

interface ProductFormModalProps {
  product?: Product;
  categories: Category[];
  onClose: () => void;
}

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

  const createMutation = useCreateProduct();
  const updateMutation = useUpdateProduct();

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    try {
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
        await createMutation.mutateAsync({
          sku,
          name_translations: { es: nameEs },
          category_id: categoryId,
          base_price: basePrice,
          tax_rate: taxRate,
          is_combo: isCombo,
          is_active: isActive,
        });
      }
      onClose();
    } catch (err) {
      console.error("Error al guardar producto:", err);
    }
  };

  const isSaving = createMutation.isPending || updateMutation.isPending;

  return (
    <div className="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 flex items-center justify-center p-4">
      <div className="bg-slate-900 border border-slate-700 rounded-xl shadow-2xl max-w-lg w-full p-6 max-h-[90vh] overflow-y-auto">
        <h2 className="text-xl font-bold text-white mb-4">
          {product ? "Editar producto" : "Nuevo producto"}
        </h2>

        <form onSubmit={handleSubmit} className="space-y-4">
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
                className="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-2 focus:ring-orange-500"
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

          <div className="flex gap-4">
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
          </div>

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
