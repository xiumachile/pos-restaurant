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
} from "lucide-react";
import {
  useMenus,
  useCreateMenu,
  useUpdateMenu,
  useDeleteMenu,
} from "@/hooks/useMenus";
import { usePriceLists } from "@/hooks/usePriceLists";
import type { Menu } from "@/services/menuAdminService";
import { getTranslatedName } from "@/types/catalog";

const CHANNEL_TYPES = [
  { value: "all", label: "🌐 Todos los canales" },
  { value: "dine_in", label: "🍽️ Comedor" },
  { value: "delivery", label: "🚗 Delivery" },
  { value: "uber_eats", label: "🛵 UberEats" },
  { value: "rappi", label: "📱 Rappi" },
];

export function MenusTab() {
  const { data: menus = [], isLoading, error } = useMenus();
  const { data: priceLists = [] } = usePriceLists();
  const [editingMenu, setEditingMenu] = useState<Menu | null>(null);
  const [showCreateModal, setShowCreateModal] = useState(false);

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
    </div>
  );
}

/* ─── Modal de formulario de menú ─── */

interface MenuFormModalProps {
  menu?: Menu;
  priceLists: any[];
  onClose: () => void;
}

function MenuFormModal({ menu, priceLists, onClose }: MenuFormModalProps) {
  const [name, setName] = useState(menu?.name ?? "");
  const [description, setDescription] = useState(menu?.description ?? "");
  const [priceListId, setPriceListId] = useState(
    menu?.price_list?.uuid ?? priceLists[0]?.uuid ?? ""
  );
  const [isDefault, setIsDefault] = useState(menu?.is_default ?? false);
  const [isActive, setIsActive] = useState(menu?.is_active ?? true);

  const createMutation = useCreateMenu();
  const updateMutation = useUpdateMenu();

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    try {
      if (menu) {
        await updateMutation.mutateAsync({
          uuid: menu.uuid,
          payload: {
            name,
            description: description || null,
            price_list_id: priceListId,
            is_default: isDefault,
            is_active: isActive,
          },
        });
      } else {
        await createMutation.mutateAsync({
          name,
          description: description || null,
          price_list_id: priceListId,
          is_default: isDefault,
          is_active: isActive,
        });
      }
      onClose();
    } catch (err) {
      console.error("Error al guardar menú:", err);
    }
  };

  const isSaving = createMutation.isPending || updateMutation.isPending;

  return (
    <div className="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 flex items-center justify-center p-4">
      <div className="bg-slate-900 border border-slate-700 rounded-xl shadow-2xl max-w-md w-full p-6">
        <h2 className="text-xl font-bold text-white mb-4">
          {menu ? "Editar menú" : "Nuevo menú"}
        </h2>

        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="block text-sm text-slate-400 mb-1.5">
              Nombre del menú *
            </label>
            <input
              type="text"
              value={name}
              onChange={(e) => setName(e.target.value)}
              required
              placeholder="Ej: Carta Comedor, Happy Hour"
              className="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-orange-500"
            />
          </div>

          <div>
            <label className="block text-sm text-slate-400 mb-1.5">Descripción</label>
            <textarea
              value={description}
              onChange={(e) => setDescription(e.target.value)}
              rows={2}
              placeholder="Descripción opcional..."
              className="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-orange-500 resize-none"
            />
          </div>

          <div>
            <label className="block text-sm text-slate-400 mb-1.5">
              📋 Lista de precios *
            </label>
            <select
              value={priceListId}
              onChange={(e) => setPriceListId(e.target.value)}
              required
              className="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-orange-500"
            >
              <option value="">Selecciona una lista...</option>
              {priceLists.map((list) => (
                <option key={list.uuid} value={list.uuid}>
                  {list.display_name} {list.is_default ? "⭐" : ""}
                </option>
              ))}
            </select>
            <p className="text-xs text-slate-500 mt-1">
              Determina qué precios se muestran en esta carta
            </p>
          </div>

          <div className="flex gap-4">
            <label className="flex items-center gap-2 cursor-pointer">
              <input
                type="checkbox"
                checked={isDefault}
                onChange={(e) => setIsDefault(e.target.checked)}
                className="w-4 h-4 accent-orange-500"
              />
              <span className="text-sm text-slate-300">⭐ Menú default</span>
            </label>
            <label className="flex items-center gap-2 cursor-pointer">
              <input
                type="checkbox"
                checked={isActive}
                onChange={(e) => setIsActive(e.target.checked)}
                className="w-4 h-4 accent-orange-500"
              />
              <span className="text-sm text-slate-300">Menú activo</span>
            </label>
          </div>

          <div className="flex gap-2 pt-2">
            <button
              type="submit"
              disabled={isSaving}
              className="flex-1 px-4 py-2 bg-orange-500 hover:bg-orange-600 disabled:bg-slate-700 text-white rounded-lg font-medium transition-colors"
            >
              {isSaving ? "Guardando..." : menu ? "Guardar cambios" : "Crear menú"}
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
