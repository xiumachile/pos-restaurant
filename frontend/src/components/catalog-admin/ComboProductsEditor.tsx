import { useTranslation } from 'react-i18next';
import { useState, useMemo } from "react";
import { Search, Package, Plus, Trash2, AlertCircle } from "lucide-react";
import { useAdminProducts } from "@/hooks/useCatalogAdmin";
import type { Product } from "@/types/catalog";
import { getTranslatedName, formatPrice } from "@/types/catalog";

interface ComboProductsEditorProps {
  product: Product;
  comboProducts: Array<{ product_uuid: string; quantity: number }>;
  onChange: (products: Array<{ product_uuid: string; quantity: number }>) => void;
}

export function ComboProductsEditor({
  product,
  comboProducts,
  onChange,
}: ComboProductsEditorProps) {
  const { t } = useTranslation();
  const [searchQuery, setSearchQuery] = useState("");

  const { data: allProducts = [], isLoading } = useAdminProducts({});

  // Filtrar productos: excluir el producto actual y otros combos (no recursivo)
  const availableProducts = useMemo(() => {
    return allProducts.filter((p) => {
      const name = getTranslatedName(p.name_translations).toLowerCase();
      const matchesSearch = !searchQuery || name.includes(searchQuery.toLowerCase());
      const isNotSelf = p.uuid !== product.uuid;
      const isNotCombo = !p.is_combo; // Evitar combos recursivos
      return matchesSearch && isNotSelf && isNotCombo && p.is_active;
    });
  }, [allProducts, searchQuery, product.uuid]);

  const isInCombo = (uuid: string) =>
    comboProducts.some((cp) => cp.product_uuid === uuid);

  const addProduct = (productUuid: string) => {
    if (!isInCombo(productUuid)) {
      onChange([...comboProducts, { product_uuid: productUuid, quantity: 1 }]);
    }
  };

  const removeProduct = (productUuid: string) => {
    onChange(comboProducts.filter((cp) => cp.product_uuid !== productUuid));
  };

  const updateQuantity = (productUuid: string, quantity: number) => {
    if (quantity < 1) quantity = 1;
    onChange(
      comboProducts.map((cp) =>
        cp.product_uuid === productUuid ? { ...cp, quantity } : cp
      )
    );
  };

  // Calcular costo total del combo
  const totalCost = useMemo(() => {
    return comboProducts.reduce((sum, cp) => {
      const prod = allProducts.find((p) => p.uuid === cp.product_uuid);
      if (!prod) return sum;
      return sum + parseFloat(prod.base_price) * cp.quantity;
    }, 0);
  }, [comboProducts, allProducts]);

  const basePrice = parseFloat(product.base_price);
  const margin = basePrice - totalCost;
  const marginPercent = basePrice > 0 ? (margin / basePrice) * 100 : 0;

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h3 className="text-sm font-semibold text-white">
          {t("products.combo_products_title")}
        </h3>
        <span className="text-xs text-slate-400">
          {comboProducts.length} {t("products.products_in_combo")}
        </span>
      </div>

      {/* Dos columnas */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
        {/* Columna izquierda: productos disponibles */}
        <div className="space-y-2">
          <div className="relative">
            <Search
              size={16}
              className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-500"
            />
            <input
              type="text"
              placeholder={t("products.search_products")}
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="w-full pl-10 pr-4 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white placeholder-slate-500 focus:outline-none focus:border-orange-500 text-sm"
            />
          </div>

          <div className="max-h-80 overflow-y-auto space-y-2">
            {isLoading ? (
              <div className="text-center py-8 text-slate-400">
                {t("common.loading")}
              </div>
            ) : availableProducts.length === 0 ? (
              <div className="text-center py-8 text-slate-400">
                <Package className="mx-auto mb-2" size={32} />
                <p className="text-sm">{t("products.no_products_found")}</p>
              </div>
            ) : (
              availableProducts.map((prod) => {
                const inCombo = isInCombo(prod.uuid);
                return (
                  <div
                    key={prod.uuid}
                    onClick={() => !inCombo && addProduct(prod.uuid)}
                    className={`flex items-center gap-3 p-3 rounded-lg border cursor-pointer transition-all ${
                      inCombo
                        ? "bg-orange-500/10 border-orange-500/50 cursor-not-allowed"
                        : "bg-slate-800/50 border-slate-700 hover:border-orange-500/50"
                    }`}
                  >
                    <div className="flex-1 min-w-0">
                      <p className="text-sm font-medium text-white truncate">
                        {getTranslatedName(prod.name_translations)}
                      </p>
                      <p className="text-xs text-slate-400 truncate">
                        {prod.category &&
                          getTranslatedName(prod.category.name_translations)}
                      </p>
                    </div>
                    <div className="text-right flex-shrink-0">
                      <p className="text-sm font-semibold text-orange-400">
                        {formatPrice(prod.base_price)}
                      </p>
                      {inCombo && (
                        <p className="text-xs text-orange-300">
                          {t("products.added_to_combo")}
                        </p>
                      )}
                    </div>
                    {!inCombo && (
                      <Plus size={16} className="text-slate-400 flex-shrink-0" />
                    )}
                  </div>
                );
              })
            )}
          </div>
        </div>

        {/* Columna derecha: productos en el combo */}
        <div className="space-y-2">
          <div className="text-sm font-medium text-slate-300 mb-2">
            {t("products.combo_composition")}
          </div>

          <div className="max-h-80 overflow-y-auto space-y-2">
            {comboProducts.length === 0 ? (
              <div className="text-center py-8 text-slate-500 border border-dashed border-slate-700 rounded-lg">
                <AlertCircle className="mx-auto mb-2" size={32} />
                <p className="text-sm">
                  {t("products.select_products_for_combo")}
                </p>
              </div>
            ) : (
              comboProducts.map((cp) => {
                const prod = allProducts.find((p) => p.uuid === cp.product_uuid);
                if (!prod) return null;
                return (
                  <div
                    key={cp.product_uuid}
                    className="flex items-center gap-3 p-3 bg-slate-800 border border-slate-700 rounded-lg"
                  >
                    <div className="flex-1 min-w-0">
                      <p className="text-sm font-medium text-white truncate">
                        {getTranslatedName(prod.name_translations)}
                      </p>
                      <p className="text-xs text-slate-400">
                        {formatPrice(prod.base_price)} c/u
                      </p>
                    </div>

                    <div className="flex items-center gap-2 flex-shrink-0">
                      <button
                        onClick={() => updateQuantity(cp.product_uuid, cp.quantity - 1)}
                        className="w-8 h-8 flex items-center justify-center bg-slate-700 hover:bg-slate-600 rounded text-white"
                      >
                        -
                      </button>
                      <input
                        type="number"
                        min="1"
                        value={cp.quantity}
                        onChange={(e) =>
                          updateQuantity(
                            cp.product_uuid,
                            parseInt(e.target.value) || 1
                          )
                        }
                        className="w-16 px-2 py-1 bg-slate-900 border border-slate-700 rounded text-white text-center text-sm"
                      />
                      <button
                        onClick={() => updateQuantity(cp.product_uuid, cp.quantity + 1)}
                        className="w-8 h-8 flex items-center justify-center bg-slate-700 hover:bg-slate-600 rounded text-white"
                      >
                        +
                      </button>
                      <button
                        onClick={() => removeProduct(cp.product_uuid)}
                        className="w-8 h-8 flex items-center justify-center bg-red-500/20 hover:bg-red-500/30 text-red-300 rounded"
                      >
                        <Trash2 size={14} />
                      </button>
                    </div>
                  </div>
                );
              })
            )}
          </div>

          {/* Resumen de costos */}
          {comboProducts.length > 0 && (
            <div className="mt-4 p-3 bg-slate-900/50 border border-slate-700 rounded-lg space-y-2">
              <div className="flex justify-between text-sm">
                <span className="text-slate-400">
                  {t("products.combo_cost")}:
                </span>
                <span className="font-semibold text-white">
                  {formatPrice(totalCost)}
                </span>
              </div>
              <div className="flex justify-between text-sm">
                <span className="text-slate-400">
                  {t("products.combo_price")}:
                </span>
                <span className="font-semibold text-orange-400">
                  {formatPrice(basePrice)}
                </span>
              </div>
              <div className="flex justify-between text-sm pt-2 border-t border-slate-700">
                <span className="text-slate-400">{t("products.margin")}:</span>
                <span
                  className={`font-semibold ${
                    margin >= 0 ? "text-green-400" : "text-red-400"
                  }`}
                >
                  {formatPrice(margin)} ({marginPercent.toFixed(1)}%)
                </span>
              </div>
              {margin < 0 && (
                <div className="mt-2 p-2 bg-red-500/10 border border-red-500/30 rounded text-xs text-red-300">
                  ⚠️ {t("products.negative_margin_warning")}
                </div>
              )}
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
