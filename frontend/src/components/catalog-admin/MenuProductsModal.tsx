import { useTranslation } from 'react-i18next';
import { useState, useMemo, useEffect } from "react";
import { X, Search, Package, GripVertical, Check, AlertCircle, Loader2 } from "lucide-react";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useProducts } from "@/hooks/useProducts";
import { useMenu } from "@/hooks/useMenus";
import { menuAdminService } from "@/services/menuAdminService";
import type { Menu } from "@/services/menuAdminService";
import { getTranslatedName, formatPrice } from "@/types/catalog";

interface MenuProductsModalProps {
  menu: Menu;
  onClose: () => void;
}

interface AssignedProduct {
  product_uuid: string;
  position: number;
  is_available: boolean;
}

export function MenuProductsModal({ menu, onClose }: MenuProductsModalProps) {
  const { t } = useTranslation();
  const queryClient = useQueryClient();

  const { data: allProducts = [], isLoading: loadingProducts } = useProducts();
  const { data: menuDetail, isLoading: loadingMenu } = useMenu(menu.uuid);

  const [searchQuery, setSearchQuery] = useState("");
  const [assigned, setAssigned] = useState<AssignedProduct[]>([]);
  const [initialized, setInitialized] = useState(false);

  useEffect(() => {
    if (menuDetail?.items && !initialized && allProducts.length > 0) {
      const items = (menuDetail.items as any[]) || [];
      const current = items
        .map((item: any, idx: number) => ({
          product_uuid: item.product_uuid ?? item.product?.uuid,
          position: item.position ?? idx,
          is_available: item.is_available ?? true,
        }))
        .filter((a) => a.product_uuid);
      setAssigned(current);
      setInitialized(true);
    }
  }, [menuDetail, initialized, allProducts]);

  const saveMutation = useMutation({
    mutationFn: () =>
      menuAdminService.assignProducts(menu.uuid, { products: assigned }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["admin", "menus"] });
      queryClient.invalidateQueries({ queryKey: ["admin", "menus", menu.uuid] });
      onClose();
    },
  });

  const filteredProducts = useMemo(() => {
    return allProducts.filter((p) => {
      const name = getTranslatedName(p.name_translations).toLowerCase();
      const matchesSearch = !searchQuery || name.includes(searchQuery.toLowerCase());
      return matchesSearch && p.is_active;
    });
  }, [allProducts, searchQuery]);

  const isAssigned = (uuid: string) => assigned.some((a) => a.product_uuid === uuid);

  const toggleProduct = (productUuid: string) => {
    if (isAssigned(productUuid)) {
      setAssigned(assigned.filter((a) => a.product_uuid !== productUuid));
    } else {
      setAssigned([
        ...assigned,
        {
          product_uuid: productUuid,
          position: assigned.length,
          is_available: true,
        },
      ]);
    }
  };

  const toggleAvailability = (uuid: string) => {
    setAssigned(
      assigned.map((a) =>
        a.product_uuid === uuid ? { ...a, is_available: !a.is_available } : a
      )
    );
  };

  const moveUp = (idx: number) => {
    if (idx === 0) return;
    const next = [...assigned];
    [next[idx - 1], next[idx]] = [next[idx], next[idx - 1]];
    setAssigned(next.map((a, i) => ({ ...a, position: i })));
  };

  const moveDown = (idx: number) => {
    if (idx === assigned.length - 1) return;
    const next = [...assigned];
    [next[idx], next[idx + 1]] = [next[idx + 1], next[idx]];
    setAssigned(next.map((a, i) => ({ ...a, position: i })));
  };

  const loading = loadingProducts || loadingMenu;

  return (
    <div className="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 flex items-center justify-center p-4">
      <div className="bg-slate-900 rounded-xl shadow-2xl w-full max-w-5xl max-h-[90vh] flex flex-col border border-slate-700">
        {/* Header */}
        <div className="p-5 border-b border-slate-700 flex items-center justify-between">
          <div>
            <h2 className="text-xl font-bold text-white">
              {t("menus.assign_products_title")}
            </h2>
            <p className="text-sm text-slate-400 mt-1">
              <strong className="text-orange-400">{menu.name}</strong> ·{" "}
              {assigned.length} {t("menus.products_selected")}
            </p>
          </div>
          <button
            onClick={onClose}
            className="p-2 text-slate-400 hover:text-white hover:bg-slate-800 rounded-lg transition-colors"
          >
            <X size={20} />
          </button>
        </div>

        {/* Dos columnas */}
        <div className="flex-1 grid grid-cols-1 lg:grid-cols-2 overflow-hidden">
          {/* Catálogo disponible */}
          <div className="flex flex-col border-r border-slate-700 overflow-hidden">
            <div className="p-4 border-b border-slate-700">
              <div className="relative">
                <Search
                  size={16}
                  className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-500"
                />
                <input
                  type="text"
                  placeholder={t("menus.search_products")}
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  className="w-full pl-10 pr-4 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white placeholder-slate-500 focus:outline-none focus:border-orange-500"
                />
              </div>
            </div>

            <div className="flex-1 overflow-y-auto p-4 space-y-2">
              {loading ? (
                <div className="flex items-center justify-center h-32">
                  <Loader2 className="animate-spin text-orange-500" size={32} />
                </div>
              ) : filteredProducts.length === 0 ? (
                <div className="text-center py-12 text-slate-400">
                  <Package className="mx-auto mb-2" size={32} />
                  <p>{t("menus.no_products_found")}</p>
                </div>
              ) : (
                filteredProducts.map((product) => {
                  const assignedProduct = isAssigned(product.uuid);
                  return (
                    <div
                      key={product.uuid}
                      onClick={() => toggleProduct(product.uuid)}
                      className={`flex items-center gap-3 p-3 rounded-lg border cursor-pointer transition-all ${
                        assignedProduct
                          ? "bg-orange-500/10 border-orange-500/50"
                          : "bg-slate-800/50 border-slate-700 hover:border-slate-500"
                      }`}
                    >
                      <div
                        className={`w-5 h-5 rounded border-2 flex items-center justify-center flex-shrink-0 ${
                          assignedProduct
                            ? "bg-orange-500 border-orange-500"
                            : "border-slate-600"
                        }`}
                      >
                        {assignedProduct && <Check size={12} className="text-white" />}
                      </div>
                      <div className="flex-1 min-w-0">
                        <p className="text-sm font-medium text-white truncate">
                          {getTranslatedName(product.name_translations)}
                        </p>
                        <p className="text-xs text-slate-400 truncate">
                          {product.category &&
                            getTranslatedName(product.category.name_translations)}
                        </p>
                      </div>
                      <span className="text-sm font-semibold text-orange-400">
                        {formatPrice(product.base_price)}
                      </span>
                    </div>
                  );
                })
              )}
            </div>
          </div>

          {/* Productos asignados */}
          <div className="flex flex-col overflow-hidden bg-slate-950/50">
            <div className="p-4 border-b border-slate-700">
              <h3 className="text-sm font-semibold text-slate-300 mb-1">
                {t("menus.assigned_order")}
              </h3>
              <p className="text-xs text-slate-500">{t("menus.drag_hint")}</p>
            </div>

            <div className="flex-1 overflow-y-auto p-4 space-y-2">
              {assigned.length === 0 ? (
                <div className="text-center py-12 text-slate-500">
                  <AlertCircle className="mx-auto mb-2" size={32} />
                  <p className="text-sm">{t("menus.select_from_left")}</p>
                </div>
              ) : (
                assigned.map((a, idx) => {
                  const product = allProducts.find(
                    (p) => p.uuid === a.product_uuid
                  );
                  if (!product) return null;
                  return (
                    <div
                      key={a.product_uuid}
                      className={`flex items-center gap-2 p-3 rounded-lg border ${
                        a.is_available
                          ? "bg-slate-800 border-slate-700"
                          : "bg-slate-800/30 border-slate-800 opacity-60"
                      }`}
                    >
                      <GripVertical size={14} className="text-slate-600 flex-shrink-0" />
                      <div className="flex-1 min-w-0">
                        <p className="text-sm font-medium text-white truncate">
                          {idx + 1}. {getTranslatedName(product.name_translations)}
                        </p>
                        <p className="text-xs text-slate-400">
                          {formatPrice(product.base_price)}
                        </p>
                      </div>
                      <button
                        onClick={() => toggleAvailability(a.product_uuid)}
                        className={`px-2 py-1 rounded text-xs font-medium transition-colors ${
                          a.is_available
                            ? "bg-green-500/20 text-green-300 hover:bg-green-500/30"
                            : "bg-red-500/20 text-red-300 hover:bg-red-500/30"
                        }`}
                        title={
                          a.is_available
                            ? t("menus.mark_unavailable")
                            : t("menus.mark_available")
                        }
                      >
                        {a.is_available ? "✓" : "✗"}
                      </button>
                      <div className="flex flex-col gap-0.5">
                        <button
                          onClick={() => moveUp(idx)}
                          disabled={idx === 0}
                          className="p-1 text-slate-400 hover:text-white disabled:opacity-30 disabled:cursor-not-allowed"
                        >
                          ▲
                        </button>
                        <button
                          onClick={() => moveDown(idx)}
                          disabled={idx === assigned.length - 1}
                          className="p-1 text-slate-400 hover:text-white disabled:opacity-30 disabled:cursor-not-allowed"
                        >
                          ▼
                        </button>
                      </div>
                    </div>
                  );
                })
              )}
            </div>
          </div>
        </div>

        {/* Footer */}
        <div className="p-4 border-t border-slate-700 flex items-center justify-between bg-slate-900">
          <p className="text-sm text-slate-400">
            {assigned.filter((a) => a.is_available).length}{" "}
            {t("menus.available")} / {assigned.length} {t("menus.total")}
          </p>
          <div className="flex gap-2">
            <button
              onClick={onClose}
              disabled={saveMutation.isPending}
              className="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg font-medium transition-colors disabled:opacity-50"
            >
              {t("common.cancel")}
            </button>
            <button
              onClick={() => saveMutation.mutate()}
              disabled={saveMutation.isPending}
              className="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg font-medium transition-colors disabled:opacity-50 flex items-center gap-2"
            >
              {saveMutation.isPending && <Loader2 size={14} className="animate-spin" />}
              {t("common.save")}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
