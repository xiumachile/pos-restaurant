import { useTranslation } from 'react-i18next';
import { useState } from "react";
import { X, Loader2, AlertCircle, BookOpen } from "lucide-react";
import { useQuery } from "@tanstack/react-query";
import { apiClient } from "@/services/apiClient";
import { CHANNEL_TYPES } from "./MenuActivationsModal";
import { getTranslatedName, formatPrice } from "@/types/catalog";

interface MenuPreviewModalProps {
  menuUuid: string;
  menuName: string;
  onClose: () => void;
}

/* ─── Tipos del preview ─── */

interface PreviewCategory {
  name_translations: Record<string, string>;
}

interface PreviewProduct {
  uuid: string;
  name_translations: Record<string, string>;
  description_translations?: Record<string, string> | null;
  base_price: string;
  category?: PreviewCategory | null;
}

interface PreviewItem {
  position: number;
  is_available: boolean;
  product?: PreviewProduct | null;
}

interface MenuPreviewData {
  menu: {
    uuid: string;
    name: string;
    description?: string | null;
  };
  items: PreviewItem[];
}

export function MenuPreviewModal({ menuUuid, menuName, onClose }: MenuPreviewModalProps) {
  const { t } = useTranslation();
  const [channel, setChannel] = useState("dine_in");

  const { data, isLoading, error } = useQuery<MenuPreviewData>({
    queryKey: ["menu-preview", menuUuid, channel],
    queryFn: async () => {
      const res = await apiClient.get(
        `/catalog/menus/${menuUuid}?channel_type=${channel}`
      );
      return (res.data as any)?.data as MenuPreviewData;
    },
  });

  const items: PreviewItem[] = data?.items ?? [];

  // Agrupar items por categoría (con tipos explícitos)
  const itemsByCategory: Record<string, PreviewItem[]> = {};
  for (const item of items) {
    const cat = item.product?.category
      ? getTranslatedName(item.product.category.name_translations)
      : "Otros";
    if (!itemsByCategory[cat]) {
      itemsByCategory[cat] = [];
    }
    itemsByCategory[cat].push(item);
  }

  const categoryEntries: Array<[string, PreviewItem[]]> = Object.entries(itemsByCategory);

  return (
    <div className="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 flex items-center justify-center p-4">
      <div className="bg-slate-900 rounded-xl shadow-2xl w-full max-w-3xl max-h-[90vh] flex flex-col border border-slate-700">
        <div className="p-5 border-b border-slate-700 flex items-center justify-between">
          <div>
            <h2 className="text-xl font-bold text-white">
              {t("menus.preview_title")}
            </h2>
            <p className="text-sm text-slate-400 mt-1">{menuName}</p>
          </div>
          <button
            onClick={onClose}
            className="p-2 text-slate-400 hover:text-white hover:bg-slate-800 rounded-lg transition-colors"
          >
            <X size={20} />
          </button>
        </div>

        {/* Selector de canal */}
        <div className="p-4 border-b border-slate-700 bg-slate-950/50">
          <label className="text-xs text-slate-400 block mb-2">
            {t("menus.simulate_channel")}
          </label>
          <div className="flex flex-wrap gap-2">
            {CHANNEL_TYPES.filter((c) => c.value !== "all").map((ch) => (
              <button
                key={ch.value}
                onClick={() => setChannel(ch.value)}
                className={`px-3 py-1.5 rounded-lg text-sm font-medium transition-colors ${
                  channel === ch.value
                    ? "bg-orange-500 text-white"
                    : "bg-slate-800 text-slate-300 hover:bg-slate-700"
                }`}
              >
                {ch.label}
              </button>
            ))}
          </div>
        </div>

        {/* Contenido */}
        <div className="flex-1 overflow-y-auto p-5">
          {isLoading ? (
            <div className="flex items-center justify-center h-64">
              <Loader2 className="animate-spin text-orange-500" size={48} />
            </div>
          ) : error ? (
            <div className="text-center py-12 text-red-400">
              <AlertCircle className="mx-auto mb-3" size={48} />
              <p>{t("menus.preview_error")}</p>
            </div>
          ) : items.length === 0 ? (
            <div className="text-center py-12 text-slate-500">
              <BookOpen className="mx-auto mb-3" size={48} />
              <p>{t("menus.no_products_in_menu")}</p>
            </div>
          ) : (
            <div className="space-y-6">
              {categoryEntries.map(([category, categoryItems]) => (
                <div key={category}>
                  <h3 className="text-lg font-bold text-orange-400 mb-3 border-b border-orange-500/30 pb-1">
                    {category}
                  </h3>
                  <div className="space-y-2">
                    {categoryItems.map((item, idx) => {
                      const product = item.product;
                      if (!product) return null;
                      return (
                        <div
                          key={idx}
                          className={`flex items-center justify-between p-3 rounded-lg border ${
                            item.is_available
                              ? "bg-slate-800/50 border-slate-700"
                              : "bg-red-900/10 border-red-800/30 opacity-60"
                          }`}
                        >
                          <div className="flex-1">
                            <p className="font-medium text-white">
                              {getTranslatedName(product.name_translations)}
                            </p>
                            {product.description_translations && (
                              <p className="text-xs text-slate-400 mt-0.5">
                                {getTranslatedName(product.description_translations)}
                              </p>
                            )}
                          </div>
                          <div className="text-right">
                            <p className="font-bold text-orange-400">
                              {formatPrice(product.base_price)}
                            </p>
                            {!item.is_available && (
                              <p className="text-xs text-red-400">
                                {t("menus.unavailable")}
                              </p>
                            )}
                          </div>
                        </div>
                      );
                    })}
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>

        <div className="p-4 border-t border-slate-700">
          <button
            onClick={onClose}
            className="w-full px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg font-medium transition-colors"
          >
            {t("common.close")}
          </button>
        </div>
      </div>
    </div>
  );
}
