import { useEffect, useState } from 'react';
import { X, Copy, Trash2, Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { useFloorPlanStore } from '@/stores/floor-plan/floorPlanStore';
import { useRestaurantTables } from '@/hooks/floor-plan/useRestaurantTables';
import { useTableManagement } from '@/hooks/useTableManagement';
import { TableManagementModal } from '@/components/tables/TableManagementModal';
import type { FloorPlanObject } from '@/types/floor-plan/floorPlan.types';

const CHAIR_POSITIONS = ['top', 'right', 'bottom', 'left', 'top-right', 'top-left', 'bottom-right', 'bottom-left'];

export function FloorPlanProperties() {
  const { t } = useTranslation();
  const { objects, editor, deleteObject, updateObject } = useFloorPlanStore();
  const { tables, isTableLinked } = useRestaurantTables();
  const tableManagement = useTableManagement();

  const selectedObject = objects.find((obj) => editor.selectedObjectIds.includes(obj.uuid));

  const [localProperties, setLocalProperties] = useState<Record<string, any>>({});

  useEffect(() => {
    if (selectedObject) {
      setLocalProperties(selectedObject.properties || {});
    } else {
      setLocalProperties({});
    }
  }, [selectedObject]);

  if (!selectedObject) {
    return null;
  }

  const isTable = selectedObject.object_type === 'table';
  const isChair = selectedObject.object_type === 'chair';

  const handlePropertyChange = (key: string, value: any) => {
    const newProperties = { ...localProperties, [key]: value };
    setLocalProperties(newProperties);
    updateObject(selectedObject.uuid, { properties: newProperties });
  };

  const handleDelete = () => {
    if (confirm(t('floor_plan.properties.confirm_delete', '¿Eliminar este objeto?'))) {
      deleteObject(selectedObject.uuid);
    }
  };

  const handleDuplicate = () => {
    const newObj: FloorPlanObject = {
      ...selectedObject,
      uuid: crypto.randomUUID(),
      x: selectedObject.x + 20,
      y: selectedObject.y + 20,
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
    };
    useFloorPlanStore.getState().addObject(newObj);
  };

  const handleLinkTable = (tableUuid: string | null) => {
    const selectedTable = tables.find((t) => t.uuid === tableUuid);
    useFloorPlanStore.getState().linkTableToObject(
      selectedObject.uuid,
      tableUuid,
      selectedTable?.table_number
    );
  };

  return (
    <div className="w-80 bg-white dark:bg-slate-800 border-l border-gray-200 dark:border-slate-700 overflow-y-auto">
      <div className="p-4 border-b border-gray-200 dark:border-slate-700">
        <div className="flex items-center justify-between">
          <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
            {t('floor_plan.properties.title', 'Propiedades')}
          </h3>
          <div className="flex gap-2">
            <button
              onClick={handleDuplicate}
              className="p-1.5 text-gray-600 hover:text-blue-600 dark:text-gray-400 dark:hover:text-blue-400"
              title={t('floor_plan.properties.duplicate', 'Duplicar')}
            >
              <Copy size={18} />
            </button>
            <button
              onClick={handleDelete}
              className="p-1.5 text-gray-600 hover:text-red-600 dark:text-gray-400 dark:hover:text-red-400"
              title={t('floor_plan.properties.delete', 'Eliminar')}
            >
              <Trash2 size={18} />
            </button>
          </div>
        </div>
        <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">
          {t(`floor_plan.object_type.${selectedObject.object_type}`, selectedObject.object_type)}
        </p>
      </div>

      <div className="p-4 space-y-4">
        {/* Label */}
        <div>
          <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {t('floor_plan.properties.label', 'Etiqueta')}
          </label>
          <input
            type="text"
            value={localProperties.label || ''}
            onChange={(e) => handlePropertyChange('label', e.target.value)}
            className="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-md bg-white dark:bg-slate-700 text-gray-900 dark:text-white"
            placeholder={t('floor_plan.properties.label_placeholder', 'Ej: Mesa 1')}
          />
        </div>

        {/* Reference */}
        <div>
          <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {t('floor_plan.properties.reference', 'Referencia')}
          </label>
          <input
            type="text"
            value={localProperties.reference || ''}
            onChange={(e) => handlePropertyChange('reference', e.target.value)}
            className="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-md bg-white dark:bg-slate-700 text-gray-900 dark:text-white"
            placeholder={t('floor_plan.properties.reference_placeholder', 'Ej: Junto a la ventana')}
          />
        </div>

        {/* Table-specific: Link to real table */}
        {isTable && (
          <div className="pt-4 border-t border-gray-200 dark:border-slate-700">
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              {t('floor_plan.properties.link_to_table', 'Vincular con mesa real')}
            </label>
            <select
              value={selectedObject.object_key || ''}
              onChange={(e) => handleLinkTable(e.target.value || null)}
              className="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-md bg-white dark:bg-slate-700 text-gray-900 dark:text-white"
            >
              <option key="unassigned" value="">
                {t('floor_plan.properties.not_linked', 'Sin vincular')}
              </option>
              {tables.map((table) => {
                const linked = isTableLinked(table.uuid);
                const isCurrent = selectedObject.object_key === table.uuid;
                return (
                  <option
                    key={`table-option-${table.uuid}`}
                    value={table.uuid}
                    disabled={linked && !isCurrent}
                  >
                    {table.table_number} - {t(`areas.${table.area_code ?? 'DEFAULT'}`, table.area_code ?? '')} ({table.capacity}p)
                    {linked && !isCurrent ? ' [vinculada]' : ''}
                  </option>
                );
              })}
            </select>
            <div className="mt-3 pt-3 border-t border-blue-200 dark:border-blue-800">
              <button
                onClick={() => tableManagement.openCreateModal()}
                className="w-full flex items-center justify-center gap-2 px-3 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded text-sm font-medium transition-colors"
              >
                <Plus size={16} />
                {t('floor_plan.properties.create_table', 'Crear nueva mesa real')}
              </button>
            </div>
            {selectedObject.object_key && (
              <p className="mt-2 text-xs text-green-600 dark:text-green-400">
                ✓ {t('floor_plan.properties.linked', 'Vinculada con mesa real')}
              </p>
            )}
          </div>
        )}

        {/* Color */}
        <div>
          <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {t('floor_plan.properties.color', 'Color')}
          </label>
          <input
            type="color"
            value={localProperties.color || '#8B4513'}
            onChange={(e) => handlePropertyChange('color', e.target.value)}
            className="w-full h-10 border border-gray-300 dark:border-slate-600 rounded-md cursor-pointer"
          />
        </div>

        {/* Chair-specific: Position */}
        {isChair && (
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {t('floor_plan.properties.chair_position', 'Posición de silla')}
            </label>
            <select
              value={localProperties.position || 'top'}
              onChange={(e) => handlePropertyChange('position', e.target.value)}
              className="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-md bg-white dark:bg-slate-700 text-gray-900 dark:text-white"
            >
              {CHAIR_POSITIONS.map((pos) => (
                <option key={pos} value={pos}>
                  {t(`floor_plan.chair_position.${pos}`, pos)}
                </option>
              ))}
            </select>
          </div>
        )}
      </div>

      {/* Modal de gestión de mesas */}
      <TableManagementModal
        isOpen={tableManagement.isModalOpen}
        editingTable={tableManagement.editingTable}
        onClose={tableManagement.closeModal}
        onCreate={tableManagement.handleCreate}
        onUpdate={tableManagement.handleUpdate}
        isLoading={tableManagement.isLoading}
      />
    </div>
  );
}
