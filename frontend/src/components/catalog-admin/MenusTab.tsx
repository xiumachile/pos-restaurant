import { useState } from "react";
import {
  Plus,
  Pencil,
  Trash2,
  BookOpen,
  Loader2,
  AlertCircle,
  Star,
  Clock,
  Calendar,
  Settings,
} from "lucide-react";
import {
  useMenus,
  useCreateMenu,
  useUpdateMenu,
  useDeleteMenu,
  useUpdateMenuActivations,
} from "@/hooks/useMenus";
import { usePriceLists } from "@/hooks/usePriceLists";
import type { Menu, MenuActivation, MenuActivationPayload } from "@/services/menuAdminService";
import { getTranslatedName } from "@/types/catalog";

export function MenusTab() {
  const { data: menus = [], isLoading, error } = useMenus();
  const { data: priceLists = [] } = usePriceLists();
  const [editingMenu, setEditingMenu] = useState<Menu | null>(null);
  const [showCreateModal, setShowCreateModal] = useState(false);
  const [managingActivationsMenu, setManagingActivationsMenu] = useState<Menu | null>(null);

  const deleteMutation = useDeleteMenu();

  const handleDelete = async (menu: Menu) => {
    if (confirm(`¿Eliminar el menú "${menu.name}"?`)) {
      try {
        await deleteMutation.mutateAsync(menu.uuid);
      } catch (err) {
        console.error("Error al eliminar menú:", err);
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
        <p className="text-red-300">Error al cargar menús</p>
      </div>
    );
  }

  return (
    <div>
      <div className="flex justify-between items-center mb-4">
        <div>
          <h2 className="text-xl font-semibold">Menús ({menus.length})</h2>
          <p className="text-sm text-slate-400 mt-1">
            El sistema resuelve automáticamente qué carta usar según el contexto.
            El mesero no selecciona la carta.
          </p>
        </div>
        <button
          onClick={() => setShowCreateModal(true)}
          className="flex items-center gap-2 px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg font-medium transition-colors"
        >
          <Plus size={18} />
          Nuevo menú
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
                  onClick={() => setManagingActivationsMenu(menu)}
                  className="p-1.5 text-slate-400 hover:text-orange-400 hover:bg-slate-700 rounded transition-colors"
                  title="Gestionar reglas de activación"
                >
                  <Settings size={14} />
                </button>
                <button
                  onClick={() => setEditingMenu(menu)}
                  className="p-1.5 text-slate-400 hover:text-white hover:bg-slate-700 rounded transition-colors"
                  title="Editar"
                >
                  <Pencil size={14} />
                </button>
                <button
                  onClick={() => handleDelete(menu)}
                  className="p-1.5 text-slate-400 hover:text-red-400 hover:bg-slate-700 rounded transition-colors"
                  title="Eliminar"
                >
                  <Trash2 size={14} />
                </button>
              </div>
            </div>

            {/* Lista de precios asociada */}
            <div className="bg-slate-900/50 rounded-lg p-3 mb-3">
              <p className="text-xs text-slate-500 mb-1">📋 Lista de precios:</p>
              <p className="text-sm text-orange-400 font-semibold">
                {menu.price_list?.display_name ?? "N/A"}
              </p>
            </div>

            {/* Reglas de activación (resumen) */}
            {menu.activations && menu.activations.length > 0 && (
              <div className="bg-slate-900/50 rounded-lg p-3 mb-3">
                <p className="text-xs text-slate-500 mb-2">⏰ Reglas de activación ({menu.activations.length}):</p>
                <div className="space-y-1.5">
                  {menu.activations.slice(0, 2).map((act) => (
                    <div key={act.id} className="flex items-center gap-2 text-xs">
                      <span className="text-slate-300">
                        {CHANNEL_TYPES.find((c) => c.value === act.channel_type)?.label ?? act.channel_type}
                      </span>
                      {act.time_from && act.time_to && (
                        <span className="text-slate-500">
                          {act.time_from.slice(0, 5)} - {act.time_to.slice(0, 5)}
                        </span>
                      )}
                      <span className="text-slate-600">P:{act.priority}</span>
                    </div>
                  ))}
                  {menu.activations.length > 2 && (
                    <p className="text-xs text-slate-500 italic">
                      +{menu.activations.length - 2} reglas más...
                    </p>
                  )}
                </div>
              </div>
            )}

            {/* Badges */}
            <div className="flex flex-wrap gap-1.5">
              {menu.is_default && (
                <span className="px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-300 border border-amber-500/30 text-xs">
                  ⭐ Default
                </span>
              )}
              {menu.is_active ? (
                <span className="px-2 py-0.5 rounded-full bg-green-500/20 text-green-300 border border-green-500/30 text-xs">
                  Activo
                </span>
              ) : (
                <span className="px-2 py-0.5 rounded-full bg-red-500/20 text-red-300 border border-red-500/30 text-xs">
                  Inactivo
                </span>
              )}
              {(menu.menu_products_count ?? 0) > 0 && (
                <span className="px-2 py-0.5 rounded-full bg-blue-500/20 text-blue-300 border border-blue-500/30 text-xs">
                  🍽️ {menu.menu_products_count} productos
                </span>
              )}
            </div>
          </div>
        ))}
      </div>

      {menus.length === 0 && (
        <div className="bg-slate-800/50 border border-slate-700 rounded-lg p-12 text-center">
          <BookOpen className="mx-auto text-slate-500 mb-3" size={48} />
          <p className="text-slate-400">No hay menús creados</p>
        </div>
      )}

      {/* Info box */}
      <div className="mt-6 bg-blue-900/20 border border-blue-800/50 rounded-lg p-4">
        <p className="text-sm text-blue-300">
          💡 <strong>Nota:</strong> La resolución automática considera el canal de venta,
          horario y día de la semana. Si ninguna regla matchea, se usa el menú default.
        </p>
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
    </div>
  );
}

/* ─── Modal de formulario de menú ─── */

import { MenuFormModal } from "./MenuFormModal";

/* ─── Modal de gestión de reglas de activación ─── */

import { ActivationsModal, CHANNEL_TYPES, DAYS_OF_WEEK } from "./MenuActivationsModal";
