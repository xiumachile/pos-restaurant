import { useState, useEffect } from 'react';
import { X } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { useAreas } from '@/hooks/useAreas';

interface TableManagementModalProps {
  isOpen: boolean;
  editingTable: any;
  onClose: () => void;
  onCreate: (payload: any) => void;
  onUpdate: (uuid: string, payload: any) => void;
  isLoading: boolean;
}



export function TableManagementModal({
  isOpen,
  editingTable,
  onClose,
  onCreate,
  onUpdate,
  isLoading,
}: TableManagementModalProps) {
  const { t, i18n } = useTranslation();
  const { areas } = useAreas();
  const currentLang = i18n.language.startsWith('zh') ? 'zh' : 'es';
  
  const [formData, setFormData] = useState({
    table_number: '',
    area_code: 'MAIN',
    area_name_es: 'Salón Principal',
    area_name_zh: '主厅',
    capacity: 4,
  });

  useEffect(() => {
    if (editingTable) {
      setFormData({
        table_number: editingTable.table_number || '',
        area_code: editingTable.area_code || 'MAIN',
        area_name_es: editingTable.area_name_translations?.es || t('areas.MAIN'),
        area_name_zh: editingTable.area_name_translations?.zh || '主厅',
        capacity: editingTable.capacity || 4,
      });
    } else {
      setFormData({
        table_number: '',
        area_code: 'MAIN',
        area_name_es: t('areas.MAIN'),
        area_name_zh: '主厅',
        capacity: 4,
      });
    }
  }, [editingTable, isOpen, t]);

  const handleAreaChange = (areaCode: string) => {
    setFormData({
      ...formData,
      area_code: areaCode,
      area_name_es: t(`areas.${areaCode}`),
      area_name_zh: t(`areas.${areaCode}`, { lng: 'zh' }) || areaCode,
    });
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    const payload = {
      table_number: formData.table_number,
      area_code: formData.area_code,
      area_name_translations: {
        es: formData.area_name_es,
        zh: formData.area_name_zh,
      },
      capacity: formData.capacity,
    };

    if (editingTable) {
      onUpdate(editingTable.uuid, payload);
    } else {
      onCreate(payload);
    }
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
      <div className="bg-white dark:bg-slate-800 rounded-lg shadow-xl max-w-md w-full mx-4">
        <div className="flex items-center justify-between p-4 border-b border-gray-200 dark:border-slate-700">
          <h2 className="text-lg font-semibold text-gray-900 dark:text-white">
            {editingTable ? t('table_management.edit_table') : t('table_management.create_table')}
          </h2>
          <button
            onClick={onClose}
            className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
          >
            <X size={20} />
          </button>
        </div>

        <form onSubmit={handleSubmit} className="p-4 space-y-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {t('table_management.table_number')} *
            </label>
            <input
              type="text"
              required
              maxLength={20}
              value={formData.table_number}
              onChange={(e) => setFormData({ ...formData, table_number: e.target.value })}
              className="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-md bg-white dark:bg-slate-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-orange-500"
              placeholder={t('table_management.table_number_placeholder')}
            />
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {t('table_management.area')} *
            </label>
            <select
              value={formData.area_code}
              onChange={(e) => handleAreaChange(e.target.value)}
              className="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-md bg-white dark:bg-slate-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-orange-500"
            >
              {areas.map((area) => (
                <option key={area.uuid} value={area.code}>
                  {area.name_translations[currentLang]} ({area.code})
                </option>
              ))}
              {areas.length === 0 && (
                <option disabled>{t('areas.no_areas_select', 'No hay áreas - créalas primero')}</option>
              )}
            </select>
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {t('table_management.capacity')} *
            </label>
            <input
              type="number"
              required
              min={1}
              max={50}
              value={formData.capacity}
              onChange={(e) => setFormData({ ...formData, capacity: parseInt(e.target.value) || 1 })}
              className="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-md bg-white dark:bg-slate-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-orange-500"
            />
          </div>

          <div className="flex gap-3 pt-4">
            <button
              type="button"
              onClick={onClose}
              disabled={isLoading}
              className="flex-1 px-4 py-2 border border-gray-300 dark:border-slate-600 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-50 dark:hover:bg-slate-700 disabled:opacity-50"
            >
              {t('table_management.cancel')}
            </button>
            <button
              type="submit"
              disabled={isLoading}
              className="flex-1 px-4 py-2 bg-orange-500 text-white rounded-md hover:bg-orange-600 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {isLoading ? t('table_management.saving') : t('table_management.save')}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
