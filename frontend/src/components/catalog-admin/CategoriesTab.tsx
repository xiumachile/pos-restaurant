import { useState } from "react";
import {
  Plus,
  Pencil,
  Trash2,
  FolderTree,
  Loader2,
  AlertCircle,
} from "lucide-react";
import {
  useAdminCategories,
  useCreateCategory,
  useUpdateCategory,
  useDeleteCategory,
} from "@/hooks/useCatalogAdmin";
import type { Category } from "@/types/catalog";
import { getTranslatedName } from "@/types/catalog";

export function CategoriesTab() {
  const { data: categories = [], isLoading, error } = useAdminCategories();
  const [editingCategory, setEditingCategory] = useState<Category | null>(null);
  const [showCreateModal, setShowCreateModal] = useState(false);

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
        <p className="text-red-300">Error al cargar categorías</p>
      </div>
    );
  }

  return (
    <div>
      <div className="flex justify-between items-center mb-4">
        <h2 className="text-xl font-semibold">Categorías ({categories.length})</h2>
        <button
          onClick={() => setShowCreateModal(true)}
          className="flex items-center gap-2 px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg font-medium transition-colors"
        >
          <Plus size={18} />
          Nueva categoría
        </button>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        {categories.map((category) => (
          <div
            key={category.uuid}
            className="bg-slate-800/50 border border-slate-700 rounded-xl p-4 hover:border-orange-500/50 transition-all"
          >
            <div className="flex items-start justify-between gap-2 mb-2">
              <h3 className="font-semibold text-white">
                {getTranslatedName(category.name_translations)}
              </h3>
              <div className="flex gap-1">
                <button
                  onClick={() => setEditingCategory(category)}
                  className="p-1.5 text-slate-400 hover:text-white hover:bg-slate-700 rounded transition-colors"
                >
                  <Pencil size={14} />
                </button>
                <button
                  onClick={() => handleDelete(category)}
                  className="p-1.5 text-slate-400 hover:text-red-400 hover:bg-slate-700 rounded transition-colors"
                >
                  <Trash2 size={14} />
                </button>
              </div>
            </div>
            <p className="text-xs text-slate-500">
              Orden: {category.sort_order} ·{" "}
              {category.is_active ? (
                <span className="text-green-400">Activa</span>
              ) : (
                <span className="text-red-400">Inactiva</span>
              )}
            </p>
          </div>
        ))}
      </div>

      {categories.length === 0 && (
        <div className="bg-slate-800/50 border border-slate-700 rounded-lg p-12 text-center">
          <FolderTree className="mx-auto text-slate-500 mb-3" size={48} />
          <p className="text-slate-400">No hay categorías creadas</p>
        </div>
      )}

      {showCreateModal && (
        <CategoryFormModal
          onClose={() => setShowCreateModal(false)}
        />
      )}

      {editingCategory && (
        <CategoryFormModal
          category={editingCategory}
          onClose={() => setEditingCategory(null)}
        />
      )}
    </div>
  );
}

/* ─── Modal de formulario de categoría ─── */

interface CategoryFormModalProps {
  category?: Category;
  onClose: () => void;
}

function CategoryFormModal({ category, onClose }: CategoryFormModalProps) {
  const [nameEs, setNameEs] = useState(
    category?.name_translations?.es ?? ""
  );
  const [sortOrder, setSortOrder] = useState(category?.sort_order ?? 0);
  const [isActive, setIsActive] = useState(category?.is_active ?? true);

  const createMutation = useCreateCategory();
  const updateMutation = useUpdateCategory();

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    try {
      if (category) {
        await updateMutation.mutateAsync({
          uuid: category.uuid,
          payload: {
            name_translations: { es: nameEs },
            sort_order: sortOrder,
            is_active: isActive,
          },
        });
      } else {
        await createMutation.mutateAsync({
          name_translations: { es: nameEs },
          sort_order: sortOrder,
          is_active: isActive,
        });
      }
      onClose();
    } catch (err) {
      console.error("Error al guardar categoría:", err);
    }
  };

  return (
    <div className="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 flex items-center justify-center p-4">
      <div className="bg-slate-900 border border-slate-700 rounded-xl shadow-2xl max-w-md w-full p-6">
        <h2 className="text-xl font-bold text-white mb-4">
          {category ? "Editar categoría" : "Nueva categoría"}
        </h2>

        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="block text-sm text-slate-400 mb-1.5">
              Nombre (español)
            </label>
            <input
              type="text"
              value={nameEs}
              onChange={(e) => setNameEs(e.target.value)}
              required
              className="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-orange-500 [color-scheme:dark]"
            />
          </div>

          <div>
            <label className="block text-sm text-slate-400 mb-1.5">
              Orden de aparición
            </label>
            <input
              type="number"
              min="0"
              value={sortOrder}
              onChange={(e) => setSortOrder(parseInt(e.target.value) || 0)}
              className="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-orange-500 [color-scheme:dark]"
            />
          </div>

          <label className="flex items-center gap-2 cursor-pointer">
            <input
              type="checkbox"
              checked={isActive}
              onChange={(e) => setIsActive(e.target.checked)}
              className="w-4 h-4 accent-orange-500"
            />
            <span className="text-sm text-slate-300">Categoría activa</span>
          </label>

          <div className="flex gap-2 pt-2">
            <button
              type="submit"
              disabled={createMutation.isPending || updateMutation.isPending}
              className="flex-1 px-4 py-2 bg-orange-500 hover:bg-orange-600 disabled:bg-slate-700 text-white rounded-lg font-medium transition-colors"
            >
              {category ? "Guardar cambios" : "Crear categoría"}
            </button>
            <button
              type="button"
              onClick={onClose}
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

function handleDelete(category: Category) {
  if (confirm(`¿Eliminar la categoría "${getTranslatedName(category.name_translations)}"?`)) {
    // TODO: implementar deleteMutation
  }
}
