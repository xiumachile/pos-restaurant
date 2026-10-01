import { useTranslation } from 'react-i18next';
import { useConfirmStore } from '@/store/useConfirmStore';
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


/* ─── Badge de precios múltiples (on-demand) ─── */

function ProductPriceBadge({ productUuid }: { productUuid: string }) {
  const { t } = useTranslation();
  const { data: prices, isLoading } = useProductPrices(productUuid);

  if (isLoading) {
    return (
      <span className="px-2 py-0.5 rounded-full bg-slate-500/20 text-slate-400 border border-slate-500/30 text-xs">
        ...
      </span>
    );
  }

  if (!prices || prices.length === 0) return null;

  // Solo mostrar badge si hay más de 1 precio (o 1 pero distinto a base_price)
  const activePrices = prices.filter((p) => p.price_list?.is_active);
  if (activePrices.length === 0) return null;

  return (
    <span
      className="px-2 py-0.5 rounded-full bg-blue-500/20 text-blue-300 border border-blue-500/30 text-xs cursor-help"
      title={activePrices
        .map(
          (p) =>
            `${p.price_list?.display_name ?? p.price_list?.name}: ${formatPrice(p.price)}`
        )
        .join("\n")}
    >
      💰 {activePrices.length} {t("products.multiple_prices")}
    </span>
  );
}


export function ProductsTab() {
  const { t } = useTranslation();

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
    useConfirmStore.getState().open({
      title: t("common.confirm_delete"),
      message: t("catalog_admin.confirm_delete_product", { name }),
      variant: "danger",
      onConfirm: async () => {
        try {
          await deleteMutation.mutateAsync(product.uuid);
        } catch (err) {
          console.error("Error al eliminar producto:", err);
        }
      }
    });
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
        <h2 className="text-xl font-semibold">{t("catalog_admin.products_tab")} ({products.length})</h2>

        <div className="flex flex-col sm:flex-row gap-2 w-full md:w-auto">
          {/* Búsqueda */}
          <div className="relative">
            <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
            <input
              type="text"
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              placeholder={t("catalog_admin.search_placeholder")}
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
            <Plus size={18} />{t("catalog_admin.new_product")}</button>
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
                <p className="text-xs text-slate-400 mt-0.5">{product.sku}</p>
              </div>
              <div className="flex gap-1">
                <button
                  onClick={() => setEditingProduct(product)}
                  className="p-1.5 text-slate-400 hover:text-white hover:bg-slate-700 rounded transition-colors"
                  title={t("catalog_admin.edit")}
                >
                  <Pencil size={14} />
                </button>
                <button
                  onClick={() => handleDelete(product)}
                  className="p-1.5 text-slate-400 hover:text-red-400 hover:bg-slate-700 rounded transition-colors"
                  title={t("catalog_admin.delete")}
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
                <ProductPriceBadge productUuid={product.uuid} />
                {product.is_active ? (
                  <span className="px-2 py-0.5 rounded-full bg-green-500/20 text-green-300 border border-green-500/30 text-xs">{t("catalog_admin.active")}</span>
                ) : (
                  <span className="px-2 py-0.5 rounded-full bg-red-500/20 text-red-300 border border-red-500/30 text-xs">{t("products.status_inactive")}</span>
                )}
              </div>
            </div>
          </div>
        ))}
      </div>

      {products.length === 0 && (
        <div className="bg-slate-800/50 border border-slate-700 rounded-lg p-12 text-center">
          <Package className="mx-auto text-slate-400 mb-3" size={48} />
          <p className="text-slate-400">
            {searchQuery || selectedCategoryId
              ? t("products.no_products_filtered")
              : t("products.no_products_created")}
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

import { ProductFormModal } from "./ProductFormModal";


