import { useTranslation } from 'react-i18next';
import { useState } from "react";
import { useUpdateMenuActivations } from "@/hooks/useMenus";
import type { Menu, MenuActivation, MenuActivationPayload } from "@/services/menuAdminService";
import { Trash2, Plus, Clock, Calendar } from "lucide-react";

export const CHANNEL_TYPES = [
  { value: "all", label: "🌐 Todos los canales" },
  { value: "dine_in", label: "🍽️ Comedor" },
  { value: "delivery", label: "🚗 Delivery" },
  { value: "uber_eats", label: "🛵 UberEats" },
  { value: "rappi", label: "📱 Rappi" },
];

export const DAYS_OF_WEEK = [
  { value: 1, label: "Lun" },
  { value: 2, label: "Mar" },
  { value: 3, label: "Mié" },
  { value: 4, label: "Jue" },
  { value: 5, label: "Vie" },
  { value: 6, label: "Sáb" },
  { value: 7, label: "Dom" },
];

export interface ActivationsModalProps {
  menu: Menu;
  onClose: () => void;
}

export function ActivationsModal({ menu, onClose }: ActivationsModalProps) {
  const { t } = useTranslation();

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
          {t("menus.activation_rules")}: {menu.name}
        </h2>
        <p className="text-sm text-slate-400 mb-4">
          {t("menus.activation_rules_subtitle")}
        </p>

        {/* Lista de reglas existentes */}
        <div className="space-y-2 mb-4">
          {activations.length === 0 && !showAddForm && (
            <div className="bg-slate-800/50 border border-slate-700 rounded-lg p-6 text-center">
              <Calendar className="mx-auto text-slate-400 mb-2" size={32} />
              <p className="text-slate-400 text-sm">{t("menus.no_rules_configured")}</p>
              <p className="text-slate-400 text-xs mt-1">
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
                  <span className="text-xs text-slate-400">Prioridad: {act.priority}</span>
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
                  {!act.days_of_week && <span>{t("menus.all_days")}</span>}
                </div>
              </div>
              <button
                onClick={() => handleRemoveActivation(act.id)}
                className="p-1.5 text-slate-400 hover:text-red-400 hover:bg-slate-700 rounded transition-colors"
                title={t("menus.delete_rule")}
              >
                <Trash2 size={14} />
              </button>
            </div>
          ))}
        </div>

        {/* Formulario para agregar nueva regla */}
        {showAddForm && (
          <div className="bg-slate-800/50 border border-orange-500/30 rounded-lg p-4 mb-4">
            <h3 className="text-sm font-semibold text-white mb-3">{t("menus.new_rule")}</h3>
            <div className="space-y-3">
              <div>
                <label className="block text-xs text-slate-400 mb-1">{t("menus.channel")}</label>
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
                  <label className="block text-xs text-slate-400 mb-1">{t("menus.from")}</label>
                  <input
                    type="time"
                    value={newActivation.time_from ?? ""}
                    onChange={(e) => setNewActivation({ ...newActivation, time_from: e.target.value || null })}
                    className="w-full px-2 py-1.5 bg-slate-900 border border-slate-700 rounded text-sm text-white [color-scheme:dark]"
                  />
                </div>
                <div>
                  <label className="block text-xs text-slate-400 mb-1">{t("menus.to")}</label>
                  <input
                    type="time"
                    value={newActivation.time_to ?? ""}
                    onChange={(e) => setNewActivation({ ...newActivation, time_to: e.target.value || null })}
                    className="w-full px-2 py-1.5 bg-slate-900 border border-slate-700 rounded text-sm text-white [color-scheme:dark]"
                  />
                </div>
              </div>

              <div>
                <label className="block text-xs text-slate-400 mb-1">{t("menus.days_of_week")}</label>
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
                <p className="text-xs text-slate-400 mt-1">
                  {(newActivation.days_of_week ?? []).length === 0 && t("menus.all_days")}
                </p>
              </div>

              <div>
                <label className="block text-xs text-slate-400 mb-1">{t("menus.priority")}</label>
                <input
                  type="number"
                  min="1"
                  value={newActivation.priority ?? 1}
                  onChange={(e) => setNewActivation({ ...newActivation, priority: parseInt(e.target.value) })}
                  className="w-full px-2 py-1.5 bg-slate-900 border border-slate-700 rounded text-sm text-white [color-scheme:dark]"
                />
                <p className="text-xs text-slate-400 mt-1">
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
            {isSaving ? t("menus.saving") : t("menus.save_changes")}
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