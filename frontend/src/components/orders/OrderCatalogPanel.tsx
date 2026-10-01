import { useState, useMemo } from "react";
import { useTranslation } from "react-i18next";
import { useCategories } from "@/hooks/useCatalog";
import { useActiveMenu } from "@/hooks/useActiveMenu";
import { useActiveChannelStore, CHANNEL_LABELS } from "@/stores/useActiveChannelStore";
import { getTranslatedName, formatPrice } from "@/types/catalog";
import type { Product } from "@/types/catalog";
import { Search, Plus, Package, Loader2, AlertCircle, BookOpen } from "lucide-react";
import { useToastStore } from "@/store/useToastStore";
import { ChannelSelector } from "./ChannelSelector";

interface OrderCatalogPanelProps {
  onAddProduct: (product: Product) => void;
}

/**
 * Catálogo compacto para toma de pedidos.
 * 
 * Usa la carta activa según el canal seleccionado (dine_in / delivery / takeout).
 * Los productos se filtran automáticamente según la carta resuelta por el backend.
 * Si no hay carta activa, muestra un mensaje de error claro.
 * 
 * 100% bilingüe: todos los textos usan t().
 */
export function OrderCatalogPanel({ onAddProduct }: OrderCatalogPanelProps) {
  const { t } = useTranslation();
  const addToast = useToastStore((s) => s.addToast);
  const channel = useActiveChannelStore((s) => s.channel);
  const [selectedCategoryId, setSelectedCategoryId] = useState<number | null>(null);
  const [searchQuery, setSearchQuery] = useState("");

  const { data: categories = [] } = useCategories();
  const { data: activeMenu, isLoading: loadingMenu, error: menuError } = useActiveMenu();

  // Construir "productos" a partir de los items de la carta activa
  // Enriquecemos con metadata de la categoría desde categories[]
  const products: Product[] = useMemo(() => {
    if (!activeMenu?.items) return [];
    return activeMenu.items.map((item) => {
      const category = categories.find((c) => c.id === item.category_id);
      return {
        id: 0, // No tenemos ID numérico real del backend aquí
        uuid: item.product_uuid,
        company_id: 0,
        branch_id: 0,
        category_id: item.category_id,
        sku: "",
        name_translations: { es: item.name },
        description_translations: null,
        base_price: String(item.price),
        tax_rate: "19",
        is_combo: false,
        kitchen_zone_id: null,
        is_active: true,
        created_at: "",
        updated_at: "",
        deleted_at: null,
        tax_id: null,
        category,
      } as Product;
    });
  }, [activeMenu, categories]);

  // Filtrar por categoría y búsqueda
  const filteredProducts = useMemo(() => {
    return products.filter((p) => {
      const matchesCategory =
        selectedCategoryId === null || p.category_id === selectedCategoryId;
      const name = getTranslatedName(p.name_translations).toLowerCase();
      const matchesSearch =
        !searchQuery || name.includes(searchQuery.toLowerCase());
      return matchesCategory && matchesSearch;
    });
  }, [products, selectedCategoryId, searchQuery]);

  // Conteo por categoría para badges
  const productsByCategory = useMemo(() => {
    const counts: Record<number, number> = {};
    for (const p of products) {
      counts[p.category_id] = (counts[p.category_id] || 0) + 1;
    }
    return counts;
  }, [products]);

  const channelLabel = CHANNEL_LABELS[channel];

  // ESTADO: cargando carta
  if (loadingMenu) {
    return (
      <div className="flex-1 flex items-center justify-center">
        <Loader2 className="animate-spin text-orange-500" size={40} />
      </div>
    );
  }

  // ESTADO: error de red al cargar carta
  if (menuError) {
    return (
      <div className="flex-1 flex items-center justify-center p-6">
        <div className="text-center">
          <AlertCircle className="mx-auto text-red-400 mb-3" size={48} />
          <p className="text-red-300 font-medium mb-2">
            {t("orders.menu_load_error")}
          </p>
          <p className="text-slate-400 text-sm">
            {t("orders.menu_load_error_hint")}
          </p>
        </div>
      </div>
    );
  }

  // ESTADO: no hay carta activa para este canal
  if (!activeMenu) {
    return (
      <div className="flex-1 flex flex-col overflow-hidden">
        <ChannelSelector />
        <div className="flex-1 flex items-center justify-center p-6">
          <div className="text-center max-w-md">
            <BookOpen className="mx-auto text-slate-500 mb-3" size={48} />
            <p className="text-slate-300 font-medium mb-2">
              {t("orders.no_active_menu", {
                icon: channelLabel.icon,
                channel: t(`orders.channel_${channel}`),
              })}
            </p>
            <p className="text-slate-400 text-sm">
              {t("orders.no_active_menu_hint")}
            </p>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="flex-1 flex flex-col overflow-hidden">
      {/* Selector de canal */}
      <ChannelSelector />

      {/* Info de carta activa */}
      <div className="mb-2 flex items-center justify-between text-xs">
        <span className="text-slate-400">
          📋 <span className="text-orange-400 font-medium">{activeMenu.menu.name}</span>
          {activeMenu.menu.price_list && (
            <span className="ml-2 text-slate-500">
              · {activeMenu.menu.price_list.display_name}
            </span>
          )}
        </span>
        <span className="text-slate-500">
          {filteredProducts.length} {t("orders.products_count")}
        </span>
      </div>

      {/* Búsqueda */}
      <div className="mb-3">
        <div className="relative">
          <Search size={18} className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
          <input
            type="text"
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            placeholder={t("orders.search_product")}
            className="w-full pl-10 pr-4 py-2.5 bg-slate-800 border border-slate-700 rounded-lg text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-orange-500"
          />
        </div>
      </div>

      {/* Tabs de categorías */}
      <div className="flex gap-2 mb-4 overflow-x-auto pb-1 flex-shrink-0">
        <button
          onClick={() => setSelectedCategoryId(null)}
          className={`px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition-colors flex items-center gap-1.5 ${
            selectedCategoryId === null
              ? "bg-orange-500 text-white"
              : "bg-slate-800 text-slate-300 hover:bg-slate-700"
          }`}
        >
          {t("orders.all_categories")}
          <span className="text-xs opacity-70">({products.length})</span>
        </button>
        {categories
          .filter((cat) => productsByCategory[cat.id] > 0)
          .map((cat) => (
            <button
              key={cat.id}
              onClick={() => setSelectedCategoryId(cat.id)}
              className={`px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition-colors flex items-center gap-1.5 ${
                selectedCategoryId === cat.id
                  ? "bg-orange-500 text-white"
                  : "bg-slate-800 text-slate-300 hover:bg-slate-700"
              }`}
            >
              {getTranslatedName(cat.name_translations)}
              <span className="text-xs opacity-70">
                ({productsByCategory[cat.id]})
              </span>
            </button>
          ))}
      </div>

      {/* Grid de productos */}
      <div className="flex-1 overflow-y-auto">
        {filteredProducts.length === 0 ? (
          <div className="text-center py-12 text-slate-400">
            <Package size={40} className="mx-auto mb-3 opacity-30" />
            <p>{t("orders.no_products_found")}</p>
          </div>
        ) : (
          <div className="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-3">
            {filteredProducts.map((product) => (
              <button
                key={product.uuid}
                onClick={() => {
                  onAddProduct(product);
                  addToast(
                    "success",
                    t("orders.product_added", {
                      name: getTranslatedName(product.name_translations),
                    })
                  );
                }}
                className="bg-slate-800/50 border border-slate-700 rounded-xl p-3 text-left hover:border-orange-500/60 hover:bg-slate-800 active:scale-95 active:border-orange-500 transition-all"
              >
                <div className="flex items-start justify-between gap-1 mb-1">
                  <h3 className="font-semibold text-white text-sm leading-tight flex-1">
                    {getTranslatedName(product.name_translations)}
                  </h3>
                  <span className="text-orange-400 flex-shrink-0 bg-orange-500/10 p-1.5 rounded-lg">
                    <Plus size={18} />
                  </span>
                </div>
                <p className="text-orange-400 font-bold text-base">
                  {formatPrice(product.base_price)}
                </p>
                {product.category && (
                  <p className="text-xs text-slate-500 mt-0.5 truncate">
                    {getTranslatedName(product.category.name_translations)}
                  </p>
                )}
              </button>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
