import { useState, useEffect } from 'react';
import { X, Plus, Pencil, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { useAreas, type Area } from '@/hooks/useAreas';

interface AreaManagementModalProps {
  isOpen: boolean;
  onClose: () => void;
}

export function AreaManagementModal({ isOpen, onClose }: AreaManagementModalProps) {
  const { t, i18n } = useTranslation();
  const currentLang = i18n.language.startsWith('zh') ? 'zh' : 'es';
  const { areas, createArea, updateArea, deleteArea, isCreating, isUpdating, isDeleting } = useAreas();

  const [editingArea, setEditingArea] = useState<Area | null>(null);
  const [formData, setFormData] = useState({
    code: '',
    name_es: '',
    name_zh: '',
    sort_order: 0,
  });

  useEffect(() => {
    if (editingArea) {
      setFormData({
        code: editingArea.code,
        name_es: editingArea.name_translations.es,
        name_zh: editingArea.name_translations.zh,
        sort_order: editingArea.sort_order,
      });
    } else {
      setFormData({ code: '', name_es: '', name_zh: '', sort_order: 0 });
    }
  }, [editingArea]);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    const payload = {
      code: formData.code.toUpperCase(),
      name_translations: {
        es: formData.name_es,
        zh: formData.name_zh,
      },
      sort_order: formData.sort_order,
    };

    if (editingArea) {
      updateArea(editingArea.uuid, payload);
    } else {
      createArea(payload as any);
    }

    setEditingArea(null);
    setFormData({ code: '', name_es: '', name_zh: '', sort_order: 0 });
  };

  const handleDelete = (area: Area) => {
    const name = area.name_translations[currentLang] || area.code;
    if (confirm(t('areas.delete_confirm', '¿Eliminar el área {name}?', { name }))) {
      deleteArea(area.uuid);
    }
  };

  const handleEdit = (area: Area) => {
    setEditingArea(area);
  };

  const handleCancel = () => {
    setEditingArea(null);
    setFormData({ code: '', name_es: '', name_zh: '', sort_order: 0 });
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
      <div className="bg-white dark:bg-slate-800 rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] flex flex-col">
        <div className="flex items-center justify-between p-4 border-b border-gray-200 dark:border-slate-700">
          <h2 className="text-lg font-semibold text-gray-900 dark:text-white">
            {t('areas.management_title', 'Gestión de Áreas del Restaurante')}
          </h2>
          <button onClick={onClose} className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
            <X size={20} />
          </button>
        </div>

        <div className="flex-1 overflow-y-auto p-4 space-y-6">
          {/* Formulario */}
          <div className="bg-gray-50 dark:bg-slate-700/50 rounded-lg p-4">
            <h3 className="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">
              {editingArea ? t('areas.edit_area', 'Editar Área') : t('areas.create_area', 'Crear Nueva Área')}
            </h3>
            <form onSubmit={handleSubmit} className="space-y-3">
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                    {t('areas.code', 'Código')} *
                  </label>
                  <input
                    type="text"
                    required
                    maxLength={50}
                    value={formData.code}
                    onChange={(e) => setFormData({ ...formData, code: e.target.value.toUpperCase() })}
                    className="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-md bg-white dark:bg-slate-700 text-gray-900 dark:text-white text-sm uppercase"
                    placeholder="MAIN, BAR, VIP"
                  />
                </div>
                <div>
                  <label className="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                    {t('areas.sort_order', 'Orden')}
                  </label>
                  <input
                    type="number"
                    min={0}
                    value={formData.sort_order}
                    onChange={(e) => setFormData({ ...formData, sort_order: parseInt(e.target.value) || 0 })}
                    className="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-md bg-white dark:bg-slate-700 text-gray-900 dark:text-white text-sm"
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                    {t('areas.name_es', 'Nombre (Español)')} *
                  </label>
                  <input
                    type="text"
                    required
                    maxLength={100}
                    value={formData.name_es}
                    onChange={(e) => setFormData({ ...formData, name_es: e.target.value })}
                    className="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-md bg-white dark:bg-slate-700 text-gray-900 dark:text-white text-sm"
                    placeholder="Salón Principal"
                  />
                </div>
                <div>
                  <label className="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                    {t('areas.name_zh', 'Nombre (Chino)')} *
                  </label>
                  <input
                    type="text"
                    required
                    maxLength={100}
                    value={formData.name_zh}
                    onChange={(e) => setFormData({ ...formData, name_zh: e.target.value })}
                    className="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-md bg-white dark:bg-slate-700 text-gray-900 dark:text-white text-sm"
                    placeholder="主厅"
                  />
                </div>
              </div>

              <div className="flex gap-2">
                <button
                  type="submit"
                  disabled={isCreating || isUpdating}
                  className="flex-1 px-4 py-2 bg-emerald-500 text-white rounded-md hover:bg-emerald-600 disabled:opacity-50 text-sm font-medium flex items-center justify-center gap-2"
                >
                  <Plus size={16} />
                  {editingArea ? t('common.update', 'Actualizar') : t('common.create', 'Crear')}
                </button>
                {editingArea && (
                  <button
                    type="button"
                    onClick={handleCancel}
                    className="px-4 py-2 border border-gray-300 dark:border-slate-600 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-50 dark:hover:bg-slate-700 text-sm"
                  >
                    {t('common.cancel', 'Cancelar')}
                  </button>
                )}
              </div>
            </form>
          </div>

          {/* Lista de áreas */}
          <div>
            <h3 className="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">
              {t('areas.existing_areas', 'Áreas Existentes')} ({areas.length})
            </h3>
            <div className="space-y-2">
              {areas.map((area) => (
                <div
                  key={area.uuid}
                  className="flex items-center justify-between p-3 bg-white dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-lg group"
                >
                  <div className="flex items-center gap-3 min-w-0">
                    <span className="font-mono text-sm font-bold text-gray-900 dark:text-white bg-gray-100 dark:bg-slate-600 px-2 py-1 rounded">
                      {area.code}
                    </span>
                    <div className="min-w-0">
                      <p className="text-sm font-medium text-gray-900 dark:text-white truncate">
                        {area.name_translations[currentLang]}
                      </p>
                      <p className="text-xs text-gray-500 dark:text-gray-400">
                        {currentLang === 'es' ? area.name_translations.zh : area.name_translations.es}
                      </p>
                    </div>
                  </div>
                  <div className="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                    <button
                      onClick={() => handleEdit(area)}
                      className="p-2 text-gray-500 hover:text-blue-600 dark:text-gray-400 dark:hover:text-blue-400 rounded hover:bg-blue-50 dark:hover:bg-blue-900/30"
                      title={t('areas.edit', 'Editar')}
                    >
                      <Pencil size={16} />
                    </button>
                    <button
                      onClick={() => handleDelete(area)}
                      disabled={isDeleting}
                      className="p-2 text-gray-500 hover:text-red-600 dark:text-gray-400 dark:hover:text-red-400 rounded hover:bg-red-50 dark:hover:bg-red-900/30 disabled:opacity-50"
                      title={t('areas.delete', 'Eliminar')}
                    >
                      <Trash2 size={16} />
                    </button>
                  </div>
                </div>
              ))}

              {areas.length === 0 && (
                <p className="text-sm text-gray-400 dark:text-gray-500 text-center py-8">
                  {t('areas.no_areas', 'No hay áreas creadas. Crea la primera área.')}
                </p>
              )}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
