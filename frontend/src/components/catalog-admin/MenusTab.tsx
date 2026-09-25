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

const CHANNEL_TYPES = [
  { value: "all", label: "🌐 Todos los canales" },
  { value: "dine_in", label: "🍽️ Comedor" },
  { value: "delivery", label: "🚗 Delivery" },
  { value: "uber_eats", label: "🛵 UberEats" },
  { value: "rappi", label: "📱 Rappi" },
];

const DAYS_OF_WEEK = [
  { value: 1, label: "Lun" },
  { value: 2, label: "Mar" },
  { value: 3, label: "Mié" },
  { value: 4, label: "Jue" },
  { value: 5, label: "Vie" },
  { value: 6, label: "Sáb" },
  { value: 7, label: "Dom" },
];

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
              className="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-orange-500 [color-scheme:dark]"
            />
          </div>

          <div>
            <label className="block text-sm text-slate-400 mb-1.5">Descripción</label>
            <textarea
              value={description}
              onChange={(e) => setDescription(e.target.value)}
              rows={2}
              placeholder="Descripción opcional..."
              className="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-orange-500 resize-none [color-scheme:dark]"
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
              className="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-orange-500 [color-scheme:dark]"
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

/* ─── Modal de gestión de reglas de activación ─── */

interface ActivationsModalProps {
  menu: Menu;
  onClose: () => void;
}

function ActivationsModal({ menu, onClose }: ActivationsModalProps) {
  const [activations, setActivations] = useState<MenuActivation[]>(menu.activations ?? []);
  const [showAddForm, setShowAddForm] = useState(false);
  const [newActivation, setNewActivation] = useState<MenuActivationPayload>({
    channel_type: "all",
    days_of_week: null,
    time_from: null,
    time_to: null,
    priority: 1,
    is_active: true,
  });

  const updateMutation = useUpdateMenuActivations();

  const handleAddActivation = () => {
    setActivations([
      ...activations,
      {
        id: Date.now(), // Temp ID
        uuid: `temp-${Date.now()}`,
        menu_id: menu.id,
        ...newActivation,
      } as MenuActivation,
    ]);
    setNewActivation({
      channel_type: "all",
      days_of_week: null,
      time_from: null,
      time_to: null,
      priority: 1,
      is_active: true,
    });
    setShowAddForm(false);
  };

  const handleRemoveActivation = (id: number) => {
    setActivations(activations.filter((a) => a.id !== id));
  };

  const handleSave = async () => {
    try {
      await updateMutation.mutateAsync({
        menuUuid: menu.uuid,
        activations: activations.map((a) => ({
          channel_type: a.channel_type,
          days_of_week: a.days_of_week,
          time_from: a.time_from?.slice(0, 5) ?? null,
          time_to: a.time_to?.slice(0, 5) ?? null,
          priority: a.priority,
          is_active: a.is_active,
        })),
      });
      onClose();
    } catch (err) {
      console.error("Error al guardar activaciones:", err);
    }
  };

  const isSaving = updateMutation.isPending;

  return (
    <div className="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 flex items-center justify-center p-4">
      <div className="bg-slate-900 border border-slate-700 rounded-xl shadow-2xl max-w-2xl w-full p-6 max-h-[90vh] overflow-y-auto">
        <h2 className="text-xl font-bold text-white mb-2">
          Reglas de activación: {menu.name}
        </h2>
        <p className="text-sm text-slate-400 mb-4">
          Configura cuándo se usa automáticamente esta carta según canal, horario y día.
        </p>

        {/* Lista de reglas existentes */}
        <div className="space-y-2 mb-4">
          {activations.length === 0 && !showAddForm && (
            <div className="bg-slate-800/50 border border-slate-700 rounded-lg p-6 text-center">
              <Calendar className="mx-auto text-slate-500 mb-2" size={32} />
              <p className="text-slate-400 text-sm">No hay reglas configuradas</p>
              <p className="text-slate-500 text-xs mt-1">
                Esta carta se usará solo si es la default de la sucursal
              </p>
            </div>
          )}

          {activations.map((act) => (
            <div
              key={act.id}
              className="bg-slate-800/50 border border-slate-700 rounded-lg p-3 flex items-center justify-between"
            >
              <div className="flex-1">
                <div className="flex items-center gap-2 mb-1">
                  <span className="text-sm text-white font-medium">
                    {CHANNEL_TYPES.find((c) => c.value === act.channel_type)?.label ?? act.channel_type}
                  </span>
                  <span className="text-xs text-slate-500">Prioridad: {act.priority}</span>
                </div>
                <div className="flex items-center gap-3 text-xs text-slate-400">
                  {act.time_from && act.time_to && (
                    <span className="flex items-center gap-1">
                      <Clock size={12} />
                      {act.time_from.slice(0, 5)} - {act.time_to.slice(0, 5)}
                    </span>
                  )}
                  {act.days_of_week && act.days_of_week.length > 0 && (
                    <span className="flex items-center gap-1">
                      <Calendar size={12} />
                      {act.days_of_week.map((d) => DAYS_OF_WEEK.find((dw) => dw.value === d)?.label).join(", ")}
                    </span>
                  )}
                  {!act.days_of_week && <span>Todos los días</span>}
                </div>
              </div>
              <button
                onClick={() => handleRemoveActivation(act.id)}
                className="p-1.5 text-slate-400 hover:text-red-400 hover:bg-slate-700 rounded transition-colors"
                title="Eliminar regla"
              >
                <Trash2 size={14} />
              </button>
            </div>
          ))}
        </div>

        {/* Formulario para agregar nueva regla */}
        {showAddForm && (
          <div className="bg-slate-800/50 border border-orange-500/30 rounded-lg p-4 mb-4">
            <h3 className="text-sm font-semibold text-white mb-3">Nueva regla</h3>
            <div className="space-y-3">
              <div>
                <label className="block text-xs text-slate-400 mb-1">Canal</label>
                <select
                  value={newActivation.channel_type}
                  onChange={(e) => setNewActivation({ ...newActivation, channel_type: e.target.value })}
                  className="w-full px-2 py-1.5 bg-slate-900 border border-slate-700 rounded text-sm text-white [color-scheme:dark]"
                >
                  {CHANNEL_TYPES.map((c) => (
                    <option key={c.value} value={c.value}>
                      {c.label}
                    </option>
                  ))}
                </select>
              </div>

              <div className="grid grid-cols-2 gap-2">
                <div>
                  <label className="block text-xs text-slate-400 mb-1">Desde</label>
                  <input
                    type="time"
                    value={newActivation.time_from ?? ""}
                    onChange={(e) => setNewActivation({ ...newActivation, time_from: e.target.value || null })}
                    className="w-full px-2 py-1.5 bg-slate-900 border border-slate-700 rounded text-sm text-white [color-scheme:dark]"
                  />
                </div>
                <div>
                  <label className="block text-xs text-slate-400 mb-1">Hasta</label>
                  <input
                    type="time"
                    value={newActivation.time_to ?? ""}
                    onChange={(e) => setNewActivation({ ...newActivation, time_to: e.target.value || null })}
                    className="w-full px-2 py-1.5 bg-slate-900 border border-slate-700 rounded text-sm text-white [color-scheme:dark]"
                  />
                </div>
              </div>

              <div>
                <label className="block text-xs text-slate-400 mb-1">Días de la semana</label>
                <div className="flex flex-wrap gap-1">
                  {DAYS_OF_WEEK.map((day) => (
                    <button
                      key={day.value}
                      type="button"
                      onClick={() => {
                        const current = newActivation.days_of_week ?? [];
                        const updated = current.includes(day.value)
                          ? current.filter((d) => d !== day.value)
                          : [...current, day.value];
                        setNewActivation({ ...newActivation, days_of_week: updated.length > 0 ? updated : null });
                      }}
                      className={`px-2 py-1 rounded text-xs transition-colors ${
                        (newActivation.days_of_week ?? []).includes(day.value)
                          ? "bg-orange-500 text-white"
                          : "bg-slate-700 text-slate-400 hover:bg-slate-600"
                      }`}
                    >
                      {day.label}
                    </button>
                  ))}
                </div>
                <p className="text-xs text-slate-500 mt-1">
                  {(newActivation.days_of_week ?? []).length === 0 && "Todos los días"}
                </p>
              </div>

              <div>
                <label className="block text-xs text-slate-400 mb-1">Prioridad</label>
                <input
                  type="number"
                  min="1"
                  value={newActivation.priority ?? 1}
                  onChange={(e) => setNewActivation({ ...newActivation, priority: parseInt(e.target.value) })}
                  className="w-full px-2 py-1.5 bg-slate-900 border border-slate-700 rounded text-sm text-white [color-scheme:dark]"
                />
                <p className="text-xs text-slate-500 mt-1">
                  Reglas con mayor prioridad se usan primero
                </p>
              </div>

              <div className="flex gap-2">
                <button
                  onClick={handleAddActivation}
                  className="flex-1 px-3 py-1.5 bg-orange-500 hover:bg-orange-600 text-white rounded text-sm font-medium transition-colors"
                >
                  Agregar regla
                </button>
                <button
                  onClick={() => setShowAddForm(false)}
                  className="px-3 py-1.5 bg-slate-700 hover:bg-slate-600 text-white rounded text-sm font-medium transition-colors"
                >
                  Cancelar
                </button>
              </div>
            </div>
          </div>
        )}

        {/* Botones de acción */}
        <div className="flex gap-2 pt-2 border-t border-slate-700">
          {!showAddForm && (
            <button
              onClick={() => setShowAddForm(true)}
              className="flex items-center gap-2 px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white rounded-lg text-sm font-medium transition-colors"
            >
              <Plus size={16} />
              Agregar regla
            </button>
          )}
          <button
            onClick={handleSave}
            disabled={isSaving}
            className="flex-1 px-4 py-2 bg-orange-500 hover:bg-orange-600 disabled:bg-slate-700 text-white rounded-lg font-medium transition-colors"
          >
            {isSaving ? "Guardando..." : "Guardar cambios"}
          </button>
          <button
            onClick={onClose}
            disabled={isSaving}
            className="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white rounded-lg font-medium transition-colors"
          >
            Cancelar
          </button>
        </div>
      </div>
    </div>
  );
}
