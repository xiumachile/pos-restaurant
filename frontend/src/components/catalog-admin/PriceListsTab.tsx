import { useState } from "react";
import {
  Plus,
  Pencil,
  Trash2,
  Tags,
  Loader2,
  AlertCircle,
  Star,
} from "lucide-react";
import {
  usePriceLists,
  useCreatePriceList,
  useUpdatePriceList,
  useDeletePriceList,
} from "@/hooks/usePriceLists";
import type { PriceList } from "@/services/priceListService";

const CHANNEL_TYPES = [
  { value: "", label: "Sin canal (general)" },
  { value: "dine_in", label: "🍽️ Comedor" },
  { value: "delivery", label: "🚗 Delivery" },
  { value: "uber_eats", label: "🛵 UberEats" },
  { value: "rappi", label: "📱 Rappi" },
  { value: "takeout", label: "🥡 Para llevar" },
];

export function PriceListsTab() {
  const { data: priceLists = [], isLoading, error } = usePriceLists();
  const [editingList, setEditingList] = useState<PriceList | null>(null);
  const [showCreateModal, setShowCreateModal] = useState(false);

  const deleteMutation = useDeletePriceList();

  const handleDelete = async (list: PriceList) => {
    if (confirm(`¿Eliminar la lista "${list.display_name}"?`)) {
      try {
        await deleteMutation.mutateAsync(list.uuid);
      } catch (err: any) {
        const message = err?.response?.data?.error || "Error al eliminar";
        alert(message);
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
        <p className="text-red-300">Error al cargar listas de precios</p>
      </div>
    );
  }

  return (
    <div>
      <div className="flex justify-between items-center mb-4">
        <div>
          <h2 className="text-xl font-semibold">
            Listas de precios ({priceLists.length})
          </h2>
          <p className="text-sm text-slate-400 mt-1">
            Configura N listas según tus canales de venta. Los menús seleccionan cuál usar.
          </p>
        </div>
        <button
          onClick={() => setShowCreateModal(true)}
          className="flex items-center gap-2 px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg font-medium transition-colors"
        >
          <Plus size={18} />
          Nueva lista
        </button>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        {priceLists.map((list) => (
          <div
            key={list.uuid}
            className={`bg-slate-800/50 border rounded-xl p-4 transition-all ${
              list.is_default
                ? "border-amber-500/50"
                : "border-slate-700 hover:border-orange-500/50"
            }`}
          >
            <div className="flex items-start justify-between gap-2 mb-2">
              <div className="flex-1">
                <div className="flex items-center gap-2">
                  <h3 className="font-semibold text-white">{list.display_name}</h3>
                  {list.is_default && (
                    <Star size={14} className="text-amber-400" fill="currentColor" />
                  )}
                </div>
                <p className="text-xs text-slate-500 font-mono mt-0.5">{list.name}</p>
              </div>
              <div className="flex gap-1">
                <button
                  onClick={() => setEditingList(list)}
                  className="p-1.5 text-slate-400 hover:text-white hover:bg-slate-700 rounded transition-colors"
                  title="Editar"
                >
                  <Pencil size={14} />
                </button>
                <button
                  onClick={() => handleDelete(list)}
                  className="p-1.5 text-slate-400 hover:text-red-400 hover:bg-slate-700 rounded transition-colors"
                  title="Eliminar"
                >
                  <Trash2 size={14} />
                </button>
              </div>
            </div>

            <div className="flex flex-wrap gap-1.5 mt-2">
              {list.channel_type && (
                <span className="px-2 py-0.5 rounded-full bg-blue-500/20 text-blue-300 border border-blue-500/30 text-xs">
                  {CHANNEL_TYPES.find((c) => c.value === list.channel_type)?.label ||
                    list.channel_type}
                </span>
              )}
              <span className="px-2 py-0.5 rounded-full bg-slate-500/20 text-slate-300 border border-slate-500/30 text-xs">
                {list.currency}
              </span>
              {list.is_default && (
                <span className="px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-300 border border-amber-500/30 text-xs">
                  Default
                </span>
              )}
              {list.is_active ? (
                <span className="px-2 py-0.5 rounded-full bg-green-500/20 text-green-300 border border-green-500/30 text-xs">
                  Activa
                </span>
              ) : (
                <span className="px-2 py-0.5 rounded-full bg-red-500/20 text-red-300 border border-red-500/30 text-xs">
                  Inactiva
                </span>
              )}
            </div>
          </div>
        ))}
      </div>

      {priceLists.length === 0 && (
        <div className="bg-slate-800/50 border border-slate-700 rounded-lg p-12 text-center">
          <Tags className="mx-auto text-slate-500 mb-3" size={48} />
          <p className="text-slate-400">No hay listas de precios creadas</p>
        </div>
      )}

      {/* Info box */}
      <div className="mt-6 bg-blue-900/20 border border-blue-800/50 rounded-lg p-4">
        <p className="text-sm text-blue-300">
          💡 <strong>Nota:</strong> Los precios por producto se configuran desde la pestaña
          de productos (editor de precios múltiples). Cada menú selecciona una lista de
          precios mediante un desplegable.
        </p>
      </div>

      {/* Modales */}
      {showCreateModal && (
        <PriceListFormModal onClose={() => setShowCreateModal(false)} />
      )}

      {editingList && (
        <PriceListFormModal list={editingList} onClose={() => setEditingList(null)} />
      )}
    </div>
  );
}

/* ─── Modal de formulario de lista de precios ─── */

interface PriceListFormModalProps {
  list?: PriceList;
  onClose: () => void;
}

function PriceListFormModal({ list, onClose }: PriceListFormModalProps) {
  const [name, setName] = useState(list?.name ?? "");
  const [displayName, setDisplayName] = useState(list?.display_name ?? "");
  const [channelType, setChannelType] = useState(list?.channel_type ?? "");
  const [isDefault, setIsDefault] = useState(list?.is_default ?? false);
  const [isActive, setIsActive] = useState(list?.is_active ?? true);

  const createMutation = useCreatePriceList();
  const updateMutation = useUpdatePriceList();

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    // Auto-generar name desde displayName si no se especifica
    const finalName =
      name.trim() ||
      displayName
        .toLowerCase()
        .replace(/\s+/g, "_")
        .replace(/[^\w_]/g, "");

    try {
      if (list) {
        await updateMutation.mutateAsync({
          uuid: list.uuid,
          payload: {
            name: finalName,
            display_name: displayName,
            channel_type: channelType || null,
            is_default: isDefault,
            is_active: isActive,
          },
        });
      } else {
        await createMutation.mutateAsync({
          name: finalName,
          display_name: displayName,
          channel_type: channelType || null,
          is_default: isDefault,
          is_active: isActive,
        });
      }
      onClose();
    } catch (err) {
      console.error("Error al guardar lista de precios:", err);
    }
  };

  const isSaving = createMutation.isPending || updateMutation.isPending;

  return (
    <div className="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 flex items-center justify-center p-4">
      <div className="bg-slate-900 border border-slate-700 rounded-xl shadow-2xl max-w-md w-full p-6">
        <h2 className="text-xl font-bold text-white mb-4">
          {list ? "Editar lista de precios" : "Nueva lista de precios"}
        </h2>

        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="block text-sm text-slate-400 mb-1.5">
              Nombre visible *
            </label>
            <input
              type="text"
              value={displayName}
              onChange={(e) => setDisplayName(e.target.value)}
              required
              placeholder="Ej: Precio Comedor"
              className="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-orange-500 [color-scheme:dark]"
            />
          </div>

          <div>
            <label className="block text-sm text-slate-400 mb-1.5">
              Identificador interno
            </label>
            <input
              type="text"
              value={name}
              onChange={(e) => setName(e.target.value)}
              placeholder="Se genera automáticamente si se deja vacío"
              className="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 font-mono [color-scheme:dark]"
            />
            <p className="text-xs text-slate-500 mt-1">
              Ej: precio_comedor, precio_delivery
            </p>
          </div>

          <div>
            <label className="block text-sm text-slate-400 mb-1.5">
              Canal de venta
            </label>
            <select
              value={channelType}
              onChange={(e) => setChannelType(e.target.value)}
              className="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-orange-500 [color-scheme:dark]"
            >
              {CHANNEL_TYPES.map((channel) => (
                <option key={channel.value} value={channel.value}>
                  {channel.label}
                </option>
              ))}
            </select>
          </div>

          <div className="flex gap-4">
            <label className="flex items-center gap-2 cursor-pointer">
              <input
                type="checkbox"
                checked={isDefault}
                onChange={(e) => setIsDefault(e.target.checked)}
                className="w-4 h-4 accent-orange-500"
              />
              <span className="text-sm text-slate-300">⭐ Lista default</span>
            </label>
            <label className="flex items-center gap-2 cursor-pointer">
              <input
                type="checkbox"
                checked={isActive}
                onChange={(e) => setIsActive(e.target.checked)}
                className="w-4 h-4 accent-orange-500"
              />
              <span className="text-sm text-slate-300">Lista activa</span>
            </label>
          </div>

          <div className="flex gap-2 pt-2">
            <button
              type="submit"
              disabled={isSaving}
              className="flex-1 px-4 py-2 bg-orange-500 hover:bg-orange-600 disabled:bg-slate-700 text-white rounded-lg font-medium transition-colors"
            >
              {isSaving ? "Guardando..." : list ? "Guardar cambios" : "Crear lista"}
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
