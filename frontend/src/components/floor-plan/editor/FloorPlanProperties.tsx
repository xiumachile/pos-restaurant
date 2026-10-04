import { useState, useEffect } from 'react';
import { useFloorPlanStore } from '@/stores/floor-plan/floorPlanStore';
import type { FloorPlanObject, TableProperties, ChairPosition } from '@/types/floor-plan/floorPlan.types';
import { Trash2, Copy, Lock, Unlock, RotateCw } from 'lucide-react';

/**
 * Panel derecho: propiedades del objeto seleccionado.
 * Sección 4.1 de la especificación técnica.
 */
export function FloorPlanProperties() {
  const {
    objects,
    editor,
    updateObject,
    deleteObject,
    deleteObjects,
    pushHistory,
  } = useFloorPlanStore();

  const selectedId = editor.selectedObjectIds[0];
  const selectedObject = objects.find((o) => o.uuid === selectedId) ?? null;
  const [localLabel, setLocalLabel] = useState('');
  const [localReference, setLocalReference] = useState('');

  useEffect(() => {
    if (selectedObject) {
      const props = selectedObject.properties as any;
      setLocalLabel(props?.label ?? '');
      setLocalReference(props?.reference ?? '');
    }
  }, [selectedObject]);

  if (!selectedObject) {
    return (
      <div className="w-80 bg-white border-l border-gray-200 overflow-y-auto p-4">
        <h2 className="text-lg font-bold text-gray-800 mb-4">Propiedades</h2>
        <p className="text-sm text-gray-500">
          Selecciona un objeto en el plano para ver y editar sus propiedades.
        </p>
      </div>
    );
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
    pushHistory({ type: 'DELETE_OBJECT', objectId: selectedObject.uuid, object: selectedObject });
    deleteObject(selectedObject.uuid);
  };

  const handleRotate90 = () => {
    const newRotation = ((selectedObject.rotation ?? 0) + 90) % 360;
    pushHistory({
      type: 'UPDATE_OBJECT',
      objectId: selectedObject.uuid,
      changes: { rotation: selectedObject.rotation ?? 0 },
    });
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

  return (
    <div className="w-80 bg-white border-l border-gray-200 overflow-y-auto">
      {/* Header */}
      <div className="p-4 border-b border-gray-200 bg-gray-50">
        <h2 className="text-lg font-bold text-gray-800">Propiedades</h2>
        <p className="text-xs text-gray-500 mt-1 capitalize">
          {selectedObject.object_type} · {selectedObject.uuid.slice(0, 8)}
        </p>
      </div>

      <div className="p-4 space-y-4">
        {/* ===== ACCIONES RÁPIDAS ===== */}
        <section>
          <h3 className="text-sm font-semibold text-gray-700 mb-2">Acciones</h3>
          <div className="grid grid-cols-3 gap-2">
            <button
              onClick={handleRotate90}
              className="flex flex-col items-center gap-1 p-2 border border-gray-200 rounded hover:bg-gray-50 text-xs"
              title="Rotar 90°"
            >
              <RotateCw size={16} />
              <span>Rotar 90°</span>
            </button>
            <button
              onClick={() => {
                // TODO: duplicar (implementar en store)
                alert('Duplicar: Ctrl+D');
              }}
              className="flex flex-col items-center gap-1 p-2 border border-gray-200 rounded hover:bg-gray-50 text-xs"
            >
              <Copy size={16} />
              <span>Duplicar</span>
            </button>
            <button
              onClick={handleDelete}
              className="flex flex-col items-center gap-1 p-2 border border-red-200 rounded hover:bg-red-50 text-xs text-red-600"
            >
              <Trash2 size={16} />
              <span>Eliminar</span>
            </button>
          </div>
        </section>

        {/* ===== POSICIÓN Y TAMAÑO ===== */}
        <section>
          <h3 className="text-sm font-semibold text-gray-700 mb-2">Posición y tamaño</h3>
          <div className="grid grid-cols-2 gap-2">
            <div>
              <label className="text-xs text-gray-500">X</label>
              <input
                type="number"
                value={selectedObject.x}
                onChange={(e) => handleUpdate({ x: parseInt(e.target.value) || 0 })}
                className="w-full px-2 py-1 border border-gray-300 rounded text-sm"
              />
            </div>
            <div>
              <label className="text-xs text-gray-500">Y</label>
              <input
                type="number"
                value={selectedObject.y}
                onChange={(e) => handleUpdate({ y: parseInt(e.target.value) || 0 })}
                className="w-full px-2 py-1 border border-gray-300 rounded text-sm"
              />
            </div>
            <div>
              <label className="text-xs text-gray-500">Ancho</label>
              <input
                type="number"
                value={selectedObject.width ?? 0}
                onChange={(e) => handleUpdate({ width: parseInt(e.target.value) || 0 })}
                className="w-full px-2 py-1 border border-gray-300 rounded text-sm"
              />
            </div>
            <div>
              <label className="text-xs text-gray-500">Alto</label>
              <input
                type="number"
                value={selectedObject.height ?? 0}
                onChange={(e) => handleUpdate({ height: parseInt(e.target.value) || 0 })}
                className="w-full px-2 py-1 border border-gray-300 rounded text-sm"
              />
            </div>
            <div className="col-span-2">
              <label className="text-xs text-gray-500">Rotación (°)</label>
              <input
                type="number"
                value={selectedObject.rotation ?? 0}
                onChange={(e) => handleUpdate({ rotation: parseInt(e.target.value) || 0 })}
                className="w-full px-2 py-1 border border-gray-300 rounded text-sm"
              />
            </div>
          </div>
        </section>

        {/* ===== PROPIEDADES DE MESA ===== */}
        {isTable && tableProps && (
          <>
            <section>
              <h3 className="text-sm font-semibold text-gray-700 mb-2">Identificación</h3>
              <div className="space-y-2">
                <div>
                  <label className="text-xs text-gray-500">Número / Etiqueta</label>
                  <input
                    type="text"
                    value={localLabel}
                    onChange={(e) => setLocalLabel(e.target.value)}
                    onBlur={() => handleUpdateTableProps({ label: localLabel })}
                    maxLength={10}
                    className="w-full px-2 py-1 border border-gray-300 rounded text-sm"
                  />
                </div>
                <div>
                  <label className="text-xs text-gray-500">Referencia</label>
                  <input
                    type="text"
                    value={localReference}
                    onChange={(e) => setLocalReference(e.target.value)}
                    onBlur={() => handleUpdateTableProps({ reference: localReference })}
                    placeholder="Junto a la ventana"
                    className="w-full px-2 py-1 border border-gray-300 rounded text-sm"
                  />
                </div>
              </div>
            </section>

            <section>
              <h3 className="text-sm font-semibold text-gray-700 mb-2">Color de superficie</h3>
              <div className="flex gap-2 flex-wrap">
                {PRESET_COLORS.map((c) => (
                  <button
                    key={c}
                    onClick={() => handleUpdateTableProps({ color: c })}
                    className={`w-8 h-8 rounded border-2 ${
                      tableProps.color === c ? 'border-blue-500' : 'border-gray-300'
                    }`}
                    style={{ backgroundColor: c }}
                  />
                ))}
                <input
                  type="color"
                  value={tableProps.color}
                  onChange={(e) => handleUpdateTableProps({ color: e.target.value })}
                  className="w-8 h-8 rounded cursor-pointer"
                />
              </div>
            </section>

            <section>
              <h3 className="text-sm font-semibold text-gray-700 mb-2">
                Sillas ({tableProps.chairs.length})
              </h3>
              <div className="grid grid-cols-4 gap-1 mb-2">
                {CHAIR_POSITIONS.map((pos) => (
                  <button
                    key={pos}
                    onClick={() => addChair(pos)}
                    className="text-xs px-1 py-1 border border-gray-200 rounded hover:bg-gray-50 capitalize"
                    title={`Agregar silla ${pos}`}
                  >
                    {pos.replace('-', ' ')}
                  </button>
                ))}
              </div>
              {tableProps.chairs.length > 0 && (
                <div className="space-y-1 max-h-40 overflow-y-auto">
                  {tableProps.chairs.map((chair, idx) => (
                    <div
                      key={idx}
                      className="flex items-center justify-between text-xs bg-gray-50 px-2 py-1 rounded"
                    >
                      <span className="capitalize">{chair.position.replace('-', ' ')}</span>
                      <button
                        onClick={() => removeChairAt(idx)}
                        className="text-red-500 hover:text-red-700"
                      >
                        ✕
                      </button>
                    </div>
                  ))}
                </div>
              )}
            </section>
          </>
        )}
      </div>
    </div>
  );
}
