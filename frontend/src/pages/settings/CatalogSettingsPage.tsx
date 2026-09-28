import { useTranslation } from 'react-i18next';
import { useState } from "react";
import {
  FolderTree,
  Package,
  Tags,
  BookOpen,
} from "lucide-react";
import { CategoriesTab } from "@/components/catalog-admin/CategoriesTab";
import { ProductsTab } from "@/components/catalog-admin/ProductsTab";
import { PriceListsTab } from "@/components/catalog-admin/PriceListsTab";
import { MenusTab } from "@/components/catalog-admin/MenusTab";

type TabId = "categories" | "products" | "price-lists" | "menus";

interface Tab {
  id: TabId;
  label: string;
  icon: React.ElementType;
  description: string;
}

const TABS: (Tab & { labelKey: string; descriptionKey: string })[] = [
  {
    id: "categories",
    labelKey: "catalog_admin.categories_tab",
    descriptionKey: "catalog_admin.categories_desc",
    icon: FolderTree,
    label: "",
    description: "",
  },
  {
    id: "products",
    labelKey: "catalog_admin.products_tab",
    descriptionKey: "catalog_admin.products_desc",
    icon: Package,
    label: "",
    description: "",
  },
  {
    id: "price-lists",
    labelKey: "catalog_admin.price_lists_tab",
    descriptionKey: "catalog_admin.price_lists_desc",
    icon: Tags,
    label: "",
    description: "",
  },
  {
    id: "menus",
    labelKey: "catalog_admin.menus_tab",
    descriptionKey: "catalog_admin.menus_desc",
    icon: BookOpen,
    label: "",
    description: "",
  },
];

/**
 * Página de administración completa de catálogo.
 * Permite gestionar categorías, productos, listas de precios y menús.
 */
export function CatalogSettingsPage() {
  const { t } = useTranslation();
  const [activeTab, setActiveTab] = useState<TabId>("categories");

  return (
    <div>
      <div className="mb-6">
        <h1 className="text-3xl font-bold flex items-center gap-3">
          <Package size={28} className="text-orange-400" />{t("catalog_admin.title")}</h1>
        <p className="text-slate-400 mt-1">{t("catalog_admin.subtitle")}</p>
      </div>

      {/* Tabs */}
      <div className="border-b border-slate-700 mb-6">
        <div className="flex gap-1">
          {TABS.map((tab) => {
            const Icon = tab.icon;
            const isActive = activeTab === tab.id;
            return (
              <button
                key={tab.id}
                onClick={() => setActiveTab(tab.id)}
                className={`flex items-center gap-2 px-4 py-3 border-b-2 transition-colors ${
                  isActive
                    ? "border-orange-500 text-orange-400"
                    : "border-transparent text-slate-400 hover:text-slate-300"
                }`}
              >
                <Icon size={18} />
                <span className="font-medium">{t(tab.labelKey)}</span>
              </button>
            );
          })}
        </div>
      </div>

      {/* Contenido del tab activo */}
      <div>
        {activeTab === "categories" && <CategoriesTab />}
        {activeTab === "products" && <ProductsTab />}
        {activeTab === "price-lists" && <PriceListsTab />}
        {activeTab === "menus" && <MenusTab />}
      </div>
    </div>
  );
}
