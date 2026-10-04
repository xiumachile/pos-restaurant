import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Plus, Pencil, Trash2, X, ChevronDown, ChevronRight } from 'lucide-react';
import { useRestaurantTables } from '@/hooks/floor-plan/useRestaurantTables';
import { useTableManagement } from '@/hooks/useTableManagement';
import { TableManagementModal } from './TableManagementModal';
import { useToastStore } from '@/store/useToastStore';

/**
 * Panel de gestión de mesas reales (crear/editar/eliminar).
 * Se muestra en el sidebar del editor de plano.
 */
export function TableManagementPanel() {
  const { t } = useTranslation();
  const { tables, reload } = useRestaurantTables();
  const tableManagement = useTableManagement();
  const addToast = useToastStore((s) => s.addToast);
  const [expanded, setExpanded] = useState(false);

  // Agrupar mesas por área
  const tablesByArea = tables.reduce<Record<string, typeof tables>>((acc, table) => {
    const area = table.area_code || 'SIN_ZONA';
    if (!acc[area]) acc[area] = [];
    acc[area].push(table);
    return acc;
  }, {});

  const handleDelete = (uuid: string, tableNumber: string) => {
    if (confirm(t('table_management.delete_confirm', '¿Eliminar la mesa {number}?', { number: tableNumber }))) {
      tableManagement.handleDelete(uuid, tableNumber);
      // Recargar después de un pequeño delay para dar tiempo al backend
      setTimeout(() => reload(), 500);
    }
  };

  return (
    <div className="border-t border-gray-200 dark:border-slate-700 mt-2">
      {/* Header colapsable */}
      <button
        onClick={() => setExpanded(!expanded)}
        className="w-full flex items-center justify-between px-2 py-2 text-sm font-semibold text-gray-700 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-800 rounded transition-colors"
      >
        <span className="flex items-center gap-2">
          🪑 {t('table_management.title', 'Mesas del restaurante')}
          <span className="text-xs text-gray-400 bg-gray-100 dark:bg-slate-700 px-1.5 py-0.5 rounded-full">
            {tables.length}
          </span>
        </span>
        {expanded ? <ChevronDown size={14} /> : <ChevronRight size={14} />}
      </button>

      {expanded && (
        <div className="px-2 pb-2 space-y-2">
          {/* Botón crear */}
          <button
            onClick={() => tableManagement.openCreateModal()}
            className="w-full flex items-center justify-center gap-2 px-3 py-2 bg-emerald-500 hover:bg-emerald-600 text-white rounded text-sm font-medium transition-colors"
          >
            <Plus size={16} />
            {t('table_management.create_table', 'Crear nueva mesa')}
          </button>

          {/* Lista de mesas por área */}
          <div className="max-h-60 overflow-y-auto space-y-1">
            {Object.entries(tablesByArea).map(([area, areaTables]) => (
              <div key={area}>
                <p className="text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wide px-1 py-1">
                  {t(`areas.${area}`, area)} ({areaTables.length})
                </p>
                {areaTables.map((table) => (
                  <div
                    key={table.uuid}
                    className="flex items-center justify-between px-2 py-1.5 bg-gray-50 dark:bg-slate-800 rounded text-sm group"
                  >
                    <div className="flex items-center gap-2 min-w-0">
                      <span className="font-medium text-gray-800 dark:text-slate-200 truncate">
                        {table.table_number}
                      </span>
                      <span className="text-xs text-gray-400">
                        {table.capacity}p
                      </span>
                      <span className={`w-2 h-2 rounded-full flex-shrink-0 ${
                        table.status === 'available' ? 'bg-emerald-500' :
                        table.status === 'occupied' ? 'bg-red-500' :
                        table.status === 'reserved' ? 'bg-amber-500' :
                        'bg-gray-400'
                      }`} />
                    </div>
                    <div className="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                      <button
                        onClick={() => tableManagement.openEditModal(table)}
                        className="p-1 text-gray-500 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400 rounded hover:bg-blue-50 dark:hover:bg-blue-900/30"
                        title={t('table_management.edit_table', 'Editar mesa')}
                      >
                        <Pencil size={13} />
                      </button>
                      <button
                        onClick={() => handleDelete(table.uuid, table.table_number)}
                        className="p-1 text-gray-500 hover:text-red-600 dark:text-slate-400 dark:hover:text-red-400 rounded hover:bg-red-50 dark:hover:bg-red-900/30"
                        title={t('table_management.delete_table', 'Eliminar mesa')}
                      >
                        <Trash2 size={13} />
                      </button>
                    </div>
                  </div>
                ))}
              </div>
            ))}

            {tables.length === 0 && (
              <p className="text-xs text-gray-400 dark:text-slate-500 text-center py-3">
                {t('table_management.no_tables', 'No hay mesas creadas. Crea la primera mesa.')}
              </p>
            )}
          </div>
        </div>
      )}

      {/* Modal de gestión */}
      <TableManagementModal
        isOpen={tableManagement.isModalOpen}
        editingTable={tableManagement.editingTable}
        onClose={tableManagement.closeModal}
        onCreate={(payload) => {
          tableManagement.handleCreate(payload);
          setTimeout(() => reload(), 500);
        }}
        onUpdate={(uuid, payload) => {
          tableManagement.handleUpdate(uuid, payload);
          setTimeout(() => reload(), 500);
        }}
        isLoading={tableManagement.isLoading}
      />
    </div>
  );
}
