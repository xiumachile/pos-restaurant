import { useState, useEffect } from 'react';
import { X } from 'lucide-react';
import { useTranslation } from 'react-i18next';

interface TableManagementModalProps {
  isOpen: boolean;
  editingTable: any;
  onClose: () => void;
  onCreate: (payload: any) => void;
  onUpdate: (uuid: string, payload: any) => void;
  isLoading: boolean;
}

const AREA_OPTIONS = [
  { code: 'MAIN', es: 'Salón Principal', zh: '主厅' },
  { code: 'TERRAZA', es: 'Terraza', zh: '露台' },
  { code: 'BAR', es: 'Bar', zh: '酒吧' },
  { code: 'VIP', es: 'VIP', zh: '贵宾' },
  { code: 'PATIO', es: 'Patio', zh: '庭院' },
];

export function TableManagementModal({
  isOpen,
  editingTable,
  onClose,
  onCreate,
  onUpdate,
  isLoading,
}: TableManagementModalProps) {
  const { t } = useTranslation();
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
        area_name_es: editingTable.area_name_translations?.es || 'Salón Principal',
        area_name_zh: editingTable.area_name_translations?.zh || '主厅',
        capacity: editingTable.capacity || 4,
      });
    } else {
      setFormData({
        table_number: '',
        area_code: 'MAIN',
        area_name_es: 'Salón Principal',
        area_name_zh: '主厅',
        capacity: 4,
      });
    }
  }, [editingTable, isOpen]);

  const handleAreaChange = (areaCode: string) => {
    const area = AREA_OPTIONS.find(a => a.code === areaCode);
    if (area) {
      setFormData({
        ...formData,
        area_code: area.code,
        area_name_es: area.es,
        area_name_zh: area.zh,
      });
    }
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
        {/* Header */}
        <div className="flex items-center justify-between p-4 border-b border-gray-200 dark:border-slate-700">
          <h2 className="text-lg font-semibold text-gray-900 dark:text-white">
            {editingTable ? t('tables.edit_table', 'Editar Mesa') : t('tables.create_table', 'Crear Mesa')}
          </h2>
          <button
            onClick={onClose}
            className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
          >
            <X size={20} />
          </button>
        </div>

        {/* Form */}
        <form onSubmit={handleSubmit} className="p-4 space-y-4">
          {/* Número de mesa */}
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {t('tables.table_number', 'Número de Mesa')} *
            </label>
            <input
              type="text"
              required
              maxLength={20}
              value={formData.table_number}
              onChange={(e) => setFormData({ ...formData, table_number: e.target.value })}
              className="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-md bg-white dark:bg-slate-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-orange-500"
              placeholder="Ej: M-01, VIP-1, T-03"
            />
          </div>

          {/* Área */}
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {t('tables.area', 'Área / Zona')} *
            </label>
            <select
              value={formData.area_code}
              onChange={(e) => handleAreaChange(e.target.value)}
              className="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-md bg-white dark:bg-slate-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-orange-500"
            >
              {AREA_OPTIONS.map((area) => (
                <option key={area.code} value={area.code}>
                  {area.es} / {area.zh}
                </option>
              ))}
            </select>
          </div>

          {/* Capacidad */}
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {t('tables.capacity', 'Capacidad (personas)')} *
            </label>
            <input
              type="number"
              required
              min={1}
              max={50}
              value={formData.capacity}
              onChange={(e) => setFormData({ ...formData, capacity: parseInt(e.target.value) })}
              className="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-md bg-white dark:bg-slate-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-orange-500"
            />
          </div>

          {/* Acciones */}
          <div className="flex gap-3 pt-4">
            <button
              type="button"
              onClick={onClose}
              disabled={isLoading}
              className="flex-1 px-4 py-2 border border-gray-300 dark:border-slate-600 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-50 dark:hover:bg-slate-700 disabled:opacity-50"
            >
              {t('common.cancel', 'Cancelar')}
            </button>
            <button
              type="submit"
              disabled={isLoading}
              className="flex-1 px-4 py-2 bg-orange-500 text-white rounded-md hover:bg-orange-600 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {isLoading ? t('common.saving', 'Guardando...') : t('common.save', 'Guardar')}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
