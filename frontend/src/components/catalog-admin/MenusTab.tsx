import { useTranslation } from 'react-i18next';
import { useConfirmStore } from '@/store/useConfirmStore';
import { useState } from "react";
import {
  Plus,
  Pencil,
  Trash2,
  BookOpen,
  Loader2,
  AlertCircle,
  Star,
  Settings,
  Package,
  Eye,
} from "lucide-react";
import {
  useMenus,
  useCreateMenu,
  useUpdateMenu,
  useDeleteMenu,
  useSetDefaultMenu,
  useToggleMenuActive,
} from "@/hooks/useMenus";
import { usePriceLists } from "@/hooks/usePriceLists";
import type { Menu } from "@/services/menuAdminService";
import { MenuFormModal } from "./MenuFormModal";
import { ActivationsModal, CHANNEL_TYPES } from "./MenuActivationsModal";
import { MenuProductsModal } from "./MenuProductsModal";
import { MenuPreviewModal } from "./MenuPreviewModal";

export function MenusTab() {
  const { t } = useTranslation();

  const { data: menus = [], isLoading, error } = useMenus();
  const { data: priceLists = [] } = usePriceLists();

  const [editingMenu, setEditingMenu] = useState<Menu | null>(null);
  const [showCreateModal, setShowCreateModal] = useState(false);
  const [managingActivationsMenu, setManagingActivationsMenu] = useState<Menu | null>(null);
  const [managingProductsMenu, setManagingProductsMenu] = useState<Menu | null>(null);
  const [previewMenu, setPreviewMenu] = useState<Menu | null>(null);

  const deleteMutation = useDeleteMenu();
  const setDefaultMutation = useSetDefaultMenu();
  const toggleActiveMutation = useToggleMenuActive();

  const handleDelete = async (menu: Menu) => {
    useConfirmStore.getState().open({
      title: t("common.confirm_delete"),
      message: t("catalog_admin.confirm_delete_menu", { name: menu.name }),
      variant: "danger",
      onConfirm: async () => {
        try {
          await deleteMutation.mutateAsync(menu.uuid);
        } catch (err) {
          console.error("Error al eliminar menú:", err);
        }
      },
    });
  };

  const handleSetDefault = async (menu: Menu) => {
    if (menu.is_default) return;
    useConfirmStore.getState().open({
      title: t("menus.set_default_title"),
      message: t("menus.set_default_confirm", { name: menu.name }),
      variant: "warning",
      onConfirm: async () => {
        try {
          await setDefaultMutation.mutateAsync(menu.uuid);
        } catch (err) {
          console.error("Error al marcar default:", err);
        }
      },
    });
  };

  const handleToggleActive = async (menu: Menu) => {
    try {
      await toggleActiveMutation.mutateAsync({
        uuid: menu.uuid,
        isActive: !menu.is_active,
      });
    } catch (err) {
      console.error("Error al cambiar estado:", err);
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
        <p className="text-red-300">{t("catalog_admin.error_loading")}</p>
      </div>
    );
  }

  return (
    <div>
      <div className="flex justify-between items-center mb-4">
        <div>
          <h2 className="text-xl font-semibold">
            {t("catalog_admin.menus_tab")} ({menus.length})
          </h2>
          <p className="text-sm text-slate-400 mt-1">
            {t("catalog_admin.menus_subtitle")}
          </p>
        </div>
        <button
          onClick={() => setShowCreateModal(true)}
          className="flex items-center gap-2 px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg font-medium transition-colors"
        >
          <Plus size={18} />
          {t("catalog_admin.new_menu")}
        </button>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        {menus.map((menu) => (
          <div
            key={menu.uuid}
            className={`bg-slate-800/50 border rounded-xl p-5 transition-all ${
              menu.is_default
                ? "border-amber-500/50"
                : "border-slate-700 hover:border-orange-500/50"
            }`}
          >
            <div className="flex items-start justify-between gap-2 mb-3">
              <div className="flex-1">
                <div className="flex items-center gap-2">
                  <h3 className="font-semibold text-white">{menu.name}</h3>
                  {menu.is_default && (
                    <Star size={14} className="text-amber-400" fill="currentColor" />
                  )}
                </div>
                {menu.description && (
                  <p className="text-xs text-slate-400 mt-1">{menu.description}</p>
                )}
              </div>
              <div className="flex gap-1">
                <button
                  onClick={() => setPreviewMenu(menu)}
                  className="p-1.5 text-slate-400 hover:text-green-400 hover:bg-slate-700 rounded transition-colors"
                  title={t("menus.preview")}
                >
                  <Eye size={14} />
                </button>
                <button
                  onClick={() => setManagingProductsMenu(menu)}
                  className="p-1.5 text-slate-400 hover:text-blue-400 hover:bg-slate-700 rounded transition-colors"
                  title={t("menus.manage_products")}
                >
                  <Package size={14} />
                </button>
                <button
                  onClick={() => setManagingActivationsMenu(menu)}
                  className="p-1.5 text-slate-400 hover:text-orange-400 hover:bg-slate-700 rounded transition-colors"
                  title={t("menus.activation_rules")}
                >
                  <Settings size={14} />
                </button>
                <button
                  onClick={() => setEditingMenu(menu)}
                  className="p-1.5 text-slate-400 hover:text-white hover:bg-slate-700 rounded transition-colors"
                  title={t("catalog_admin.edit")}
                >
                  <Pencil size={14} />
                </button>
                <button
                  onClick={() => handleDelete(menu)}
                  className="p-1.5 text-slate-400 hover:text-red-400 hover:bg-slate-700 rounded transition-colors"
                  title={t("catalog_admin.delete")}
                >
                  <Trash2 size={14} />
                </button>
              </div>
            </div>

            {/* Lista de precios asociada */}
            <div className="bg-slate-900/50 rounded-lg p-3 mb-3">
              <p className="text-xs text-slate-400 mb-1">{t("menus.price_list")}:</p>
              <p className="text-sm text-orange-400 font-semibold">
                {menu.price_list?.display_name ?? "N/A"}
              </p>
            </div>

            {/* Reglas de activación */}
            {menu.activations && menu.activations.length > 0 && (
              <div className="bg-slate-900/50 rounded-lg p-3 mb-3">
                <p className="text-xs text-slate-400 mb-2">
                  {t("menus.activation_rules")} ({menu.activations.length}):
                </p>
                <div className="space-y-1.5">
                  {menu.activations.slice(0, 2).map((act) => (
                    <div key={act.id} className="flex items-center gap-2 text-xs">
                      <span className="text-slate-300">
                        {CHANNEL_TYPES.find((c) => c.value === act.channel_type)?.label ??
                          act.channel_type}
                      </span>
                      {act.time_from && act.time_to && (
                        <span className="text-slate-400">
                          {act.time_from.slice(0, 5)} - {act.time_to.slice(0, 5)}
                        </span>
                      )}
                      <span className="text-slate-600">P:{act.priority}</span>
                    </div>
                  ))}
                  {menu.activations.length > 2 && (
                    <p className="text-xs text-slate-400 italic">
                      +{menu.activations.length - 2} {t("menus.more_rules")}
                    </p>
                  )}
                </div>
              </div>
            )}

            {/* Badges + acciones rápidas */}
            <div className="flex flex-wrap items-center gap-1.5 mb-3">
              {menu.is_default ? (
                <span className="px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-300 border border-amber-500/30 text-xs">
                  ⭐ {t("menus.default_badge")}
                </span>
              ) : (
                <button
                  onClick={() => handleSetDefault(menu)}
                  disabled={setDefaultMutation.isPending}
                  className="px-2 py-0.5 rounded-full bg-slate-700/50 text-slate-400 border border-slate-600 text-xs hover:bg-amber-500/20 hover:text-amber-300 hover:border-amber-500/30 transition-colors disabled:opacity-50"
                  title={t("menus.set_as_default")}
                >
                  ☆ {t("menus.set_as_default")}
                </button>
              )}
              {(menu.menu_products_count ?? 0) > 0 && (
                <span className="px-2 py-0.5 rounded-full bg-blue-500/20 text-blue-300 border border-blue-500/30 text-xs">
                  🍽️ {menu.menu_products_count} {t("menus.products_count")}
                </span>
              )}
              {(menu.menu_products_count ?? 0) === 0 && (
                <span className="px-2 py-0.5 rounded-full bg-red-500/20 text-red-300 border border-red-500/30 text-xs">
                  ⚠️ {t("menus.no_products")}
                </span>
              )}
            </div>

            {/* Toggle activo/inactivo */}
            <button
              onClick={() => handleToggleActive(menu)}
              disabled={toggleActiveMutation.isPending}
              className={`w-full flex items-center justify-between px-3 py-2 rounded-lg border text-sm font-medium transition-colors disabled:opacity-50 ${
                menu.is_active
                  ? "bg-green-500/10 border-green-500/30 text-green-300 hover:bg-green-500/20"
                  : "bg-red-500/10 border-red-500/30 text-red-300 hover:bg-red-500/20"
              }`}
            >
              <span>
                {menu.is_active ? t("menus.status_active") : t("menus.status_inactive")}
              </span>
              {toggleActiveMutation.isPending ? (
                <Loader2 size={14} className="animate-spin" />
              ) : (
                <span className="text-xs opacity-70">
                  {menu.is_active ? t("menus.click_to_deactivate") : t("menus.click_to_activate")}
                </span>
              )}
            </button>
          </div>
        ))}
      </div>

      {menus.length === 0 && (
        <div className="bg-slate-800/50 border border-slate-700 rounded-lg p-12 text-center">
          <BookOpen className="mx-auto text-slate-400 mb-3" size={48} />
          <p className="text-slate-400">{t("catalog_admin.no_menus_created")}</p>
        </div>
      )}

      {/* Info box */}
      <div className="mt-6 bg-blue-900/20 border border-blue-800/50 rounded-lg p-4">
        <p className="text-sm text-blue-300">{t("catalog_admin.menus_note")}</p>
      </div>

      {/* Modales */}
      {showCreateModal && (
        <MenuFormModal priceLists={priceLists} onClose={() => setShowCreateModal(false)} />
      )}

      {editingMenu && (
        <MenuFormModal
          menu={editingMenu}
          priceLists={priceLists}
          onClose={() => setEditingMenu(null)}
        />
      )}

      {managingActivationsMenu && (
        <ActivationsModal
          menu={managingActivationsMenu}
          onClose={() => setManagingActivationsMenu(null)}
        />
      )}

      {managingProductsMenu && (
        <MenuProductsModal
          menu={managingProductsMenu}
          onClose={() => setManagingProductsMenu(null)}
        />
      )}

      {previewMenu && (
        <MenuPreviewModal
          menuUuid={previewMenu.uuid}
          menuName={previewMenu.name}
          onClose={() => setPreviewMenu(null)}
        />
      )}
    </div>
  );
}
