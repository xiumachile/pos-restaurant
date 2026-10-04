import { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { Trash2, Copy, RotateCw, Link2, Info, Hash, Users, AlertTriangle } from 'lucide-react';
import { useFloorPlanStore } from '@/stores/floor-plan/floorPlanStore';
import { useRestaurantTables } from '@/hooks/floor-plan/useRestaurantTables';
import { useTableManagement } from '@/hooks/useTableManagement';
import { TableManagementModal } from '@/components/tables/TableManagementModal';
import { Plus, Edit2 } from 'lucide-react';
import type { FloorPlanObject, TableProperties, ChairPosition } from '@/types/floor-plan/floorPlan.types';

/**
 * Valida que un string sea un UUID válido
 */
function isValidUUID(uuid: string): boolean {
  const uuidRegex = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;
  return uuidRegex.test(uuid);
}

/**
 * Panel derecho: propiedades del objeto seleccionado
 */
export function FloorPlanProperties() {
  const { t } = useTranslation();
  const { objects, editor, updateObject, deleteObject, duplicateObject } = useFloorPlanStore();
  const { tables } = useRestaurantTables();
  
  const selectedObject = objects.find(obj => editor.selectedObjectIds.includes(obj.uuid));
  
  const [localLabel, setLocalLabel] = useState('');
  const [localReference, setLocalReference] = useState('');

  useEffect(() => {
    if (selectedObject) {
      const props = selectedObject.properties as any;
      setLocalLabel(props?.label || '');
      setLocalReference(props?.reference || '');
    }
  }, [selectedObject]);

  if (!selectedObject) {
    return null;
  }

  const isTable = selectedObject.object_type === 'table';
  const tableProps = isTable ? (selectedObject.properties as TableProperties) : null;

  const handleUpdate = (changes: Partial<FloorPlanObject>) => {
    updateObject(selectedObject.uuid, changes);
  };

  const handleUpdateTableProps = (propsChanges: Partial<TableProperties>) => {
    if (!tableProps) return;
    handleUpdate({
      properties: { ...tableProps, ...propsChanges },
    });
  };

  const handleDelete = () => {
    if (confirm(t('floor_plan.properties.delete_confirm', '¿Eliminar este objeto del plano?'))) {
      deleteObject(selectedObject.uuid);
    }
  };

  const handleDuplicate = () => {
    duplicateObject(selectedObject.uuid);
  };

  const handleRotate90 = () => {
    const newRotation = ((selectedObject.rotation ?? 0) + 90) % 360;
    handleUpdate({ rotation: newRotation });
  };

  const CHAIR_POSITIONS: ChairPosition[] = [
    'top', 'top-right', 'right', 'bottom-right',
    'bottom', 'bottom-left', 'left', 'top-left',
  ];

  const addChair = (position: ChairPosition) => {
    if (!tableProps) return;
    const newChairs = [...tableProps.chairs, { position }];
    handleUpdateTableProps({
      chairs: newChairs,
      capacity: newChairs.length,
    });
  };

  const removeChairAt = (index: number) => {
    if (!tableProps) return;
    const newChairs = tableProps.chairs.filter((_, i) => i !== index);
    handleUpdateTableProps({
      chairs: newChairs,
      capacity: newChairs.length,
    });
  };

  const PRESET_COLORS = [
    '#8B4513', '#A0522D', '#D2691E', '#BC8F8F',
    '#654321', '#2F4F4F', '#556B2F', '#8B0000',
  ];

  const handleLinkTable = (tableUuid: string | null) => {
    console.log('[FloorPlanProperties] Vinculando mesa:', { 
      objectUuid: selectedObject.uuid, 
      tableUuid,
      isValid: tableUuid ? isValidUUID(tableUuid) : 'null'
    });
    
    // VALIDACIÓN CRÍTICA: Si tableUuid no es null, debe ser un UUID válido
    if (tableUuid && !isValidUUID(tableUuid)) {
      console.error('[FloorPlanProperties] UUID inválido recibido:', tableUuid);
      alert(t('floor_plan.properties.invalid_uuid', 'Error: El identificador de mesa es inválido. Por favor selecciona una mesa válida del listado.'));
      return;
    }
    
    const selectedTable = tables.find(t => t.uuid === tableUuid);
    useFloorPlanStore.getState().linkTableToObject(
      selectedObject.uuid,
      tableUuid,
      selectedTable?.table_number
    );
  };

  return (
    <div className="w-80 bg-white dark:bg-slate-900 border-l border-gray-200 dark:border-slate-800 overflow-y-auto">
      {/* Header */}
      <div className="p-4 border-b border-gray-200 dark:border-slate-800 bg-gray-50 dark:bg-slate-800 sticky top-0 z-10">
        <h2 className="text-lg font-bold text-gray-800 dark:text-white">
          {t('floor_plan.properties.title', 'Propiedades')}
        </h2>
        <p className="text-xs text-gray-500 dark:text-slate-400 mt-1 capitalize">
          {t(`floor_plan.object_type.${selectedObject.object_type}`, selectedObject.object_type)}
          {' · '}{selectedObject.uuid.slice(0, 8)}
        </p>
      </div>

      <div className="p-4 space-y-5">
        {/* ===== ACCIONES RÁPIDAS ===== */}
        <section>
          <h3 className="text-sm font-semibold text-gray-700 dark:text-slate-300 mb-2 uppercase tracking-wide">
            {t('floor_plan.properties.actions', 'Acciones')}
          </h3>
          <div className="grid grid-cols-3 gap-2">
            <button
              onClick={handleRotate90}
              className="flex flex-col items-center gap-1 p-2 border border-gray-200 dark:border-slate-700 rounded hover:bg-gray-50 dark:hover:bg-slate-800 text-xs text-gray-700 dark:text-slate-300 transition-colors"
              title={t('floor_plan.properties.rotate_90', 'Rotar 90°')}
            >
              <RotateCw size={16} />
              <span>{t('floor_plan.properties.rotate', 'Rotar')}</span>
            </button>
            <button
              onClick={handleDuplicate}
              className="flex flex-col items-center gap-1 p-2 border border-gray-200 dark:border-slate-700 rounded hover:bg-gray-50 dark:hover:bg-slate-800 text-xs text-gray-700 dark:text-slate-300 transition-colors"
              title={t('floor_plan.properties.duplicate', 'Duplicar (Ctrl+D)')}
            >
              <Copy size={16} />
              <span>{t('floor_plan.properties.duplicate_short', 'Duplicar')}</span>
            </button>
            <button
              onClick={handleDelete}
              className="flex flex-col items-center gap-1 p-2 border border-red-200 dark:border-red-900 rounded hover:bg-red-50 dark:hover:bg-red-900/30 text-xs text-red-600 dark:text-red-400 transition-colors"
              title={t('floor_plan.properties.delete', 'Eliminar (Delete)')}
            >
              <Trash2 size={16} />
              <span>{t('floor_plan.properties.delete_short', 'Eliminar')}</span>
            </button>
          </div>
        </section>

        {/* ===== VINCULACIÓN CON MESA REAL (solo para mesas) ===== */}
        {isTable && (
          <section className="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-900 rounded-lg p-3">
            <h3 className="text-sm font-semibold text-blue-900 dark:text-blue-200 mb-2 flex items-center gap-2">
              <Link2 size={14} />
              {t('floor_plan.properties.link_table_title', 'Conectar con mesa real del restaurante')}
            </h3>
            <p className="text-xs text-blue-700 dark:text-blue-300 mb-3 flex items-start gap-1.5">
              <Info size={12} className="flex-shrink-0 mt-0.5" />
              <span>
                {t('floor_plan.properties.link_table_help',
                  'Asocia este dibujo con una mesa física del restaurante. Esto permite al garzón abrir pedidos directamente desde el plano.')}
              </span>
            </p>

            <label className="block text-xs font-medium text-blue-900 dark:text-blue-200 mb-1">
              {t('floor_plan.properties.select_real_table', 'Selecciona la mesa real:')}
            </label>
            <div className="space-y-2">
              <select
                value={selectedObject.object_key ?? ''}
                onChange={(e) => {
                  console.log('[FloorPlanProperties] Select onChange:', e.target.value);
                  handleLinkTable(e.target.value || null);
                }}
                className="w-full px-3 py-2 border border-blue-300 dark:border-blue-800 rounded text-sm bg-white dark:bg-slate-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500"
              >
                <option key="unassigned" value="">{t('floor_plan.properties.not_assigned', '— Aún no asignada —')}</option>
                {tables.map((table) => {
                  const isLinkedElsewhere = objects.some(
                    obj => obj.uuid !== selectedObject.uuid && obj.object_key === table.uuid
                  );
                  const isCurrentLinked = selectedObject.object_key === table.uuid;
                  return (
                    <option
                      key={`table-option-${table.uuid}`}
                      value={table.uuid}
                      disabled={isLinkedElsewhere}
                      className={isCurrentLinked ? 'font-bold' : ''}
                    >
                      Mesa {table.table_number} ({table.capacity}p) - {table.area_code || 'Sin zona'}
                      {isLinkedElsewhere ? ' [usada en otro dibujo]' : ''}
                      {isCurrentLinked ? ' ✓' : ''}
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

              
              {tables.length === 0 && (
                <p className="text-xs text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/30 p-2 rounded">
                  {t('floor_plan.properties.no_tables_available', 
                    'No hay mesas disponibles. Crea mesas primero en la sección de Mesas.')}
                </p>
              )}
              
              {selectedObject.object_key && isValidUUID(selectedObject.object_key) && (
                <button
                  onClick={() => handleLinkTable(null)}
                  className="w-full text-xs text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300 py-1 underline"
                >
                  {t('floor_plan.properties.unlink_table', 'Desconectar de mesa real')}
                </button>
              )}
              
              {/* Advertencia si object_key está corrupto */}
              {selectedObject.object_key && !isValidUUID(selectedObject.object_key) && (
                <div className="flex items-start gap-1.5 text-xs text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/30 p-2 rounded">
                  <AlertTriangle size={12} className="flex-shrink-0 mt-0.5" />
                  <span>
                    {t('floor_plan.properties.corrupted_link',
                      '⚠️ El vínculo con la mesa real está corrupto. Por favor selecciona nuevamente la mesa del listado.')}
                  </span>
                </div>
              )}
            </div>

            {selectedObject.object_key && isValidUUID(selectedObject.object_key) && (
              <div className="mt-2 flex items-center gap-1.5 text-xs text-emerald-700 dark:text-emerald-400 bg-emerald-100 dark:bg-emerald-900/30 px-2 py-1.5 rounded">
                <Info size={12} />
                <span className="font-medium">
                  {t('floor_plan.properties.connected_to', 'Conectada con mesa real')}
                </span>
              </div>
            )}

            {!selectedObject.object_key && (
              <div className="mt-2 flex items-center gap-1.5 text-xs text-amber-700 dark:text-amber-400 bg-amber-100 dark:bg-amber-900/30 px-2 py-1.5 rounded">
                <Info size={12} />
                <span>
                  {t('floor_plan.properties.not_connected_warning',
                    'Esta mesa no está conectada con una mesa real del restaurante.')}
                </span>
              </div>
            )}
          </section>
        )}

        {/* ===== IDENTIFICACIÓN VISUAL ===== */}
        {isTable && tableProps && (
          <section>
            <h3 className="text-sm font-semibold text-gray-700 dark:text-slate-300 mb-2 uppercase tracking-wide flex items-center gap-2">
              <Hash size={14} />
              {t('floor_plan.properties.visual_id', 'Identificación visual')}
            </h3>
            <div className="space-y-3">
              <div>
                <label className="block text-xs text-gray-600 dark:text-slate-400 mb-1">
                  {t('floor_plan.properties.display_number', 'Número visible en el plano')}
                </label>
                <input
                  type="text"
                  value={localLabel}
                  onChange={(e) => setLocalLabel(e.target.value)}
                  onBlur={() => handleUpdateTableProps({ label: localLabel })}
                  maxLength={10}
                  placeholder="Ej: 01, VIP, T5"
                  className="w-full px-3 py-1.5 border border-gray-300 dark:border-slate-700 rounded text-sm bg-white dark:bg-slate-800 text-gray-900 dark:text-white"
                />
                <p className="text-xs text-gray-500 dark:text-slate-500 mt-1">
                  {t('floor_plan.properties.display_number_help',
                    'Este es el número que ven los garzones en pantalla.')}
                </p>
              </div>

              <div>
                <label className="block text-xs text-gray-600 dark:text-slate-400 mb-1">
                  {t('floor_plan.properties.reference', 'Referencia física (opcional)')}
                </label>
                <input
                  type="text"
                  value={localReference}
                  onChange={(e) => setLocalReference(e.target.value)}
                  onBlur={() => handleUpdateTableProps({ reference: localReference })}
                  placeholder="Ej: Junto a la ventana, Frente a la barra"
                  className="w-full px-3 py-1.5 border border-gray-300 dark:border-slate-700 rounded text-sm bg-white dark:bg-slate-800 text-gray-900 dark:text-white"
                />
                <p className="text-xs text-gray-500 dark:text-slate-500 mt-1">
                  {t('floor_plan.properties.reference_help',
                    'Descripción que ayuda al garzón a ubicar la mesa física en el salón.')}
                </p>
              </div>
            </div>
          </section>
        )}

        {/* ===== CAPACIDAD ===== */}
        {isTable && tableProps && (
          <section>
            <h3 className="text-sm font-semibold text-gray-700 dark:text-slate-300 mb-2 uppercase tracking-wide flex items-center gap-2">
              <Users size={14} />
              {t('floor_plan.properties.capacity_title', 'Capacidad')}
            </h3>
            <div>
              <label className="block text-xs text-gray-600 dark:text-slate-400 mb-1">
                {t('floor_plan.properties.capacity_label', 'Número máximo de personas')}
              </label>
              <div className="flex items-center gap-2">
                <button
                  onClick={() => {
                    const newCapacity = Math.max(1, (tableProps.capacity ?? 2) - 1);
                    handleUpdateTableProps({ capacity: newCapacity });
                  }}
                  className="w-10 h-10 rounded bg-gray-100 dark:bg-slate-800 hover:bg-gray-200 dark:hover:bg-slate-700 text-gray-700 dark:text-slate-300 font-bold text-lg"
                  title={t('floor_plan.properties.decrease', 'Disminuir')}
                >
                  −
                </button>
                <input
                  type="number"
                  min={1}
                  max={20}
                  value={tableProps.capacity ?? 2}
                  onChange={(e) => {
                    const val = parseInt(e.target.value);
                    if (!isNaN(val) && val >= 1 && val <= 20) {
                      handleUpdateTableProps({ capacity: val });
                    }
                  }}
                  className="flex-1 px-3 py-2 border border-gray-300 dark:border-slate-700 rounded text-center text-lg font-bold bg-white dark:bg-slate-800 text-gray-900 dark:text-white"
                />
                <button
                  onClick={() => {
                    const newCapacity = Math.min(20, (tableProps.capacity ?? 2) + 1);
                    handleUpdateTableProps({ capacity: newCapacity });
                  }}
                  className="w-10 h-10 rounded bg-gray-100 dark:bg-slate-800 hover:bg-gray-200 dark:hover:bg-slate-700 text-gray-700 dark:text-slate-300 font-bold text-lg"
                  title={t('floor_plan.properties.increase', 'Aumentar')}
                >
                  +
                </button>
              </div>
              <p className="text-xs text-gray-500 dark:text-slate-500 mt-1">
                {t('floor_plan.properties.capacity_help', 'Define cuántas personas pueden sentarse en esta mesa.')}
              </p>
            </div>
          </section>
        )}

        {/* ===== POSICIÓN Y TAMAÑO ===== */}
        <section>
          <h3 className="text-sm font-semibold text-gray-700 dark:text-slate-300 mb-2 uppercase tracking-wide">
            {t('floor_plan.properties.position_size', 'Posición y tamaño')}
          </h3>
          <div className="grid grid-cols-2 gap-2">
            <div>
              <label className="text-xs text-gray-500 dark:text-slate-400">X</label>
              <input
                type="number"
                value={selectedObject.x}
                onChange={(e) => handleUpdate({ x: parseInt(e.target.value) || 0 })}
                className="w-full px-2 py-1 border border-gray-300 dark:border-slate-700 rounded text-sm bg-white dark:bg-slate-800 text-gray-900 dark:text-white"
              />
            </div>
            <div>
              <label className="text-xs text-gray-500 dark:text-slate-400">Y</label>
              <input
                type="number"
                value={selectedObject.y}
                onChange={(e) => handleUpdate({ y: parseInt(e.target.value) || 0 })}
                className="w-full px-2 py-1 border border-gray-300 dark:border-slate-700 rounded text-sm bg-white dark:bg-slate-800 text-gray-900 dark:text-white"
              />
            </div>
            <div>
              <label className="text-xs text-gray-500 dark:text-slate-400">
                {t('floor_plan.properties.width', 'Ancho')}
              </label>
              <input
                type="number"
                value={selectedObject.width ?? 0}
                onChange={(e) => handleUpdate({ width: parseInt(e.target.value) || 0 })}
                className="w-full px-2 py-1 border border-gray-300 dark:border-slate-700 rounded text-sm bg-white dark:bg-slate-800 text-gray-900 dark:text-white"
              />
            </div>
            <div>
              <label className="text-xs text-gray-500 dark:text-slate-400">
                {t('floor_plan.properties.height', 'Alto')}
              </label>
              <input
                type="number"
                value={selectedObject.height ?? 0}
                onChange={(e) => handleUpdate({ height: parseInt(e.target.value) || 0 })}
                className="w-full px-2 py-1 border border-gray-300 dark:border-slate-700 rounded text-sm bg-white dark:bg-slate-800 text-gray-900 dark:text-white"
              />
            </div>
            <div className="col-span-2">
              <label className="text-xs text-gray-500 dark:text-slate-400">
                {t('floor_plan.properties.rotation', 'Rotación (°)')}
              </label>
              <input
                type="number"
                value={selectedObject.rotation ?? 0}
                onChange={(e) => handleUpdate({ rotation: parseInt(e.target.value) || 0 })}
                className="w-full px-2 py-1 border border-gray-300 dark:border-slate-700 rounded text-sm bg-white dark:bg-slate-800 text-gray-900 dark:text-white"
              />
            </div>
          </div>
        </section>

        {/* ===== COLOR DE MESA ===== */}
        {isTable && tableProps && (
          <section>
            <h3 className="text-sm font-semibold text-gray-700 dark:text-slate-300 mb-2 uppercase tracking-wide">
              {t('floor_plan.properties.table_color', 'Color de la mesa')}
            </h3>
            <div className="flex gap-2 flex-wrap">
              {PRESET_COLORS.map((c) => (
                <button
                  key={c}
                  onClick={() => handleUpdateTableProps({ color: c })}
                  className={`w-8 h-8 rounded border-2 transition-transform hover:scale-110 ${
                    tableProps.color === c ? 'border-blue-500 ring-2 ring-blue-300' : 'border-gray-300 dark:border-slate-700'
                  }`}
                  style={{ backgroundColor: c }}
                  title={c}
                />
              ))}
              <input
                type="color"
                value={tableProps.color}
                onChange={(e) => handleUpdateTableProps({ color: e.target.value })}
                className="w-8 h-8 rounded cursor-pointer border border-gray-300 dark:border-slate-700"
                title={t('floor_plan.properties.custom_color', 'Color personalizado')}
              />
            </div>
          </section>
        )}

        {/* ===== SILLAS ===== */}
        {isTable && tableProps && (
          <section>
            <h3 className="text-sm font-semibold text-gray-700 dark:text-slate-300 mb-2 uppercase tracking-wide">
              {t('floor_plan.properties.chairs', 'Sillas')} ({tableProps.chairs.length})
            </h3>
            <p className="text-xs text-gray-500 dark:text-slate-400 mb-2">
              {t('floor_plan.properties.chairs_help',
                'Haz clic para agregar una silla en esa posición. Las sillas ayudan al garzón a reconocer la mesa física.')}
            </p>
            <div className="grid grid-cols-4 gap-1 mb-2">
              {CHAIR_POSITIONS.map((pos) => (
                <button
                  key={pos}
                  onClick={() => addChair(pos)}
                  className="text-xs px-1 py-1.5 border border-gray-200 dark:border-slate-700 rounded hover:bg-gray-50 dark:hover:bg-slate-800 capitalize text-gray-700 dark:text-slate-300 transition-colors"
                  title={t('floor_plan.properties.add_chair', 'Agregar silla {{position}}', { position: pos })}
                >
                  {t(`floor_plan.chair_position.${pos}`, pos.replace('-', ' '))}
                </button>
              ))}
            </div>
            {tableProps.chairs.length > 0 && (
              <div className="mt-2">
                <p className="text-xs text-gray-600 dark:text-slate-400 mb-1.5">
                  {t('floor_plan.properties.current_chairs', 'Sillas actuales:')}
                </p>
                <div className="grid grid-cols-2 gap-1.5">
                  {tableProps.chairs.map((chair, idx) => (
                    <div
                      key={idx}
                      className="flex items-center justify-between text-xs bg-gray-50 dark:bg-slate-800 px-2 py-1.5 rounded border border-gray-200 dark:border-slate-700"
                    >
                      <span className="capitalize text-gray-700 dark:text-slate-300 truncate pr-1">
                        {t(`floor_plan.chair_position.${chair.position}`, chair.position.replace('-', ' '))}
                      </span>
                      <button
                        onClick={() => removeChairAt(idx)}
                        className="flex-shrink-0 w-5 h-5 flex items-center justify-center rounded bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400 hover:bg-red-200 dark:hover:bg-red-900/60 transition-colors"
                        title={t('floor_plan.properties.remove_chair', 'Quitar silla')}
                      >
                        ✕
                      </button>
                    </div>
                  ))}
                </div>
                <button
                  onClick={() => {
                    if (confirm(t('floor_plan.properties.remove_all_chairs_confirm', '¿Quitar todas las sillas?'))) {
                      handleUpdateTableProps({ chairs: [], capacity: 0 });
                    }
                  }}
                  className="mt-2 w-full text-xs text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300 py-1"
                >
                  {t('floor_plan.properties.remove_all_chairs', 'Quitar todas las sillas')}
                </button>
              </div>
            )}
          </section>
        )}

        {/* ===== PROPIEDADES GENÉRICAS DE DECORACIÓN ===== */}
        {!isTable && (
          <section>
            <h3 className="text-sm font-semibold text-gray-700 dark:text-slate-300 mb-2 uppercase tracking-wide">
              {t('floor_plan.properties.identification', 'Identificación')}
            </h3>
            <div className="space-y-3">
              <div>
                <label className="text-xs text-gray-600 dark:text-slate-400">
                  {t('floor_plan.properties.label', 'Etiqueta')}
                </label>
                <input
                  type="text"
                  value={localLabel}
                  onChange={(e) => setLocalLabel(e.target.value)}
                  onBlur={() => {
                    const currentProps = (selectedObject.properties as any) ?? {};
                    handleUpdate({
                      properties: { ...currentProps, label: localLabel },
                    });
                  }}
                  placeholder={t('floor_plan.properties.label_placeholder', 'Ej: Planta del rincón')}
                  className="w-full px-3 py-1.5 border border-gray-300 dark:border-slate-700 rounded text-sm bg-white dark:bg-slate-800 text-gray-900 dark:text-white"
                />
              </div>
              <div>
                <label className="text-xs text-gray-600 dark:text-slate-400">
                  {t('floor_plan.properties.reference', 'Referencia (opcional)')}
                </label>
                <input
                  type="text"
                  value={localReference}
                  onChange={(e) => setLocalReference(e.target.value)}
                  onBlur={() => {
                    const currentProps = (selectedObject.properties as any) ?? {};
                    handleUpdate({
                      properties: { ...currentProps, reference: localReference },
                    });
                  }}
                  placeholder={t('floor_plan.properties.reference_placeholder', 'Ej: Junto a la puerta')}
                  className="w-full px-3 py-1.5 border border-gray-300 dark:border-slate-700 rounded text-sm bg-white dark:bg-slate-800 text-gray-900 dark:text-white"
                />
              </div>
            </div>
          </section>
        )}
      </div>
    </div>
  );
}
