import { useState } from "react";
import { useCreateMenu, useUpdateMenu } from "@/hooks/useMenus";
import type { Menu } from "@/services/menuAdminService";
import type { PriceList } from "@/services/priceListService";

export interface MenuFormModalProps {
  menu?: Menu;
  priceLists: PriceList[];
  onClose: () => void;
}

export function MenuFormModal({ menu, priceLists, onClose }: MenuFormModalProps) {
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