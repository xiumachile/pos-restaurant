// src/components/floor-plan/panels/PropertiesPanel.tsx
import { useTranslation } from "react-i18next";
import { Trash2, Copy, Unlink, Plus } from "lucide-react";
import type { FloorPlanObject, FloorPlanZone, OperationalTableData } from "@/types/floorPlan";
import { RotationControl } from "./RotationControl";
import { ObjectRenderer } from "../renderers/ObjectRenderer";
import { calculateSeatLayout } from "../catalog/furnitureCatalog";

interface Props {
  selectedObject: FloorPlanObject | null;
  zones: FloorPlanZone[];
  availableTables: OperationalTableData[];
  onUpdateObject: (uuid: string, updates: Partial<FloorPlanObject>) => void;
  onDeleteObject: (uuid: string) => void;
  onRequestCreateTable: (objectUuid: string) => void;
  isEditMode: boolean;
}

export function PropertiesPanel({
  selectedObject,
  zones,
  availableTables,
  onUpdateObject,
  onDeleteObject,
  onRequestCreateTable,
  isEditMode,
}: Props) {
  const { t } = useTranslation();

  if (!selectedObject || !isEditMode) {
    return (
      <div className="w-80 bg-slate-800 border-l border-slate-700 p-6 flex items-center justify-center">
        <p className="text-sm text-slate-400 text-center">
          {t("floor_plan.properties.no_selection", "Selecciona un objeto para editar sus propiedades")}
        </p>
      </div>
    );
  }

  const handlePositionChange = (axis: "x" | "y", value: number) => {
    onUpdateObject(selectedObject.uuid, { [`position_${axis}`]: Math.max(0, value) });
  };

  const handleSizeChange = (dimension: "width" | "height", value: number) => {
    onUpdateObject(selectedObject.uuid, { [dimension]: Math.max(20, value) });
  };

  const handleRotationChange = (degrees: number) => {
    onUpdateObject(selectedObject.uuid, { rotation: degrees });
  };

  const handleCapacityChange = (capacity: number) => {
    const shape = selectedObject.properties?.shape || "square";
    const seatLayout = calculateSeatLayout(capacity, shape as any);
    onUpdateObject(selectedObject.uuid, {
      properties: { ...selectedObject.properties, capacity, seat_layout: seatLayout },
    });
  };

  const handleTableReassign = (tableUuid: string) => {
    const newTable = availableTables.find(t => t.uuid === tableUuid);
    if (newTable) {
      onUpdateObject(selectedObject.uuid, {
        table_uuid: newTable.uuid,
        operational_data: {
          uuid: newTable.uuid,
          table_number: newTable.table_number,
          capacity: newTable.capacity,
          status: newTable.status,
          has_active_order: newTable.has_active_order,
          current_order_id: newTable.current_order_id,
        },
        properties: {
          ...selectedObject.properties,
          capacity: newTable.capacity,
          seat_layout: calculateSeatLayout(newTable.capacity, selectedObject.properties?.shape || "square"),
        }
      });
    }
  };

  const handleUnlinkTable = () => {
    if (window.confirm("¿Desvincular esta mesa del plano? El objeto gráfico se eliminará, pero la mesa operativa se mantendrá en el sistema.")) {
      onDeleteObject(selectedObject.uuid);
    }
  };

  const isTable = selectedObject.object_type.toLowerCase().includes("table");
  const capacity = selectedObject.properties?.capacity || selectedObject.operational_data?.capacity || 4;
  const shape = selectedObject.properties?.shape || "square";

  return (
    <div className="w-80 bg-slate-800 border-l border-slate-700 overflow-y-auto shrink-0">
      <div className="p-4 space-y-4">
        {/* Header */}
        <div className="flex items-center justify-between border-b border-slate-700 pb-3">
          <h3 className="text-sm font-bold text-white">{t("floor_plan.properties.title", "Propiedades")}</h3>
          <div className="flex gap-2">
            <button className="p-1.5 bg-slate-700 hover:bg-slate-600 rounded transition-colors" title="Duplicar">
              <Copy size={14} className="text-slate-300" />
            </button>
            <button onClick={() => onDeleteObject(selectedObject.uuid)} className="p-1.5 bg-red-600 hover:bg-red-700 rounded transition-colors" title="Eliminar">
              <Trash2 size={14} className="text-white" />
            </button>
          </div>
        </div>

        {/* Previsualización */}
        <div className="bg-slate-900 p-4 rounded-lg border border-slate-700">
          <label className="text-xs font-semibold text-slate-400 uppercase block mb-2 text-center">Vista Previa</label>
          <div className="flex items-center justify-center h-32 bg-slate-950 rounded">
            <ObjectRenderer object={selectedObject} statusColor="#10b981" showChairs={true} />
          </div>
          {isTable && <p className="text-xs text-slate-400 text-center mt-2">{capacity} sillas · {shape}</p>}
        </div>

        {/* Tipo */}
        <div className="bg-slate-700/50 p-3 rounded-lg border border-slate-600">
          <label className="text-xs font-semibold text-slate-400 uppercase block mb-1">Tipo de Objeto</label>
          <p className="text-sm text-white font-medium capitalize">{selectedObject.object_type.replace(/_/g, " ")}</p>
        </div>

        {/* ✅ GESTIÓN DE MESA OPERATIVA (Siempre visible con opción de crear) */}
        {isTable && (
          <div className="bg-slate-700/50 p-3 rounded-lg border border-slate-600 space-y-3">
            <div className="flex items-center justify-between">
              <label className="text-xs font-semibold text-slate-400 uppercase">Mesa Operativa</label>
              {selectedObject.operational_data && (
                <span className="text-[10px] px-1.5 py-0.5 bg-emerald-600/20 text-emerald-400 rounded border border-emerald-600/30 font-medium">
                  Vinculada
                </span>
              )}
            </div>
            
            {selectedObject.operational_data ? (
              <>
                <div className="text-xs text-white bg-slate-800 p-2.5 rounded border border-slate-600 space-y-1.5">
                  <div className="flex justify-between">
                    <span className="text-slate-400">Número:</span> 
                    <span className="font-bold text-orange-400">{selectedObject.operational_data.table_number}</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-slate-400">Capacidad:</span> 
                    <span>{selectedObject.operational_data.capacity} pax</span>
                  </div>
                </div>
                
                <div className="space-y-2">
                  <label className="text-[10px] text-slate-500 block">Reasignar a otra existente:</label>
                  <select 
                    value={selectedObject.table_uuid || ""}
                    onChange={(e) => {
                      if (e.target.value) handleTableReassign(e.target.value);
                    }}
                    className="w-full px-2 py-1.5 bg-slate-700 border border-slate-600 rounded text-sm text-white focus:ring-1 focus:ring-orange-500 outline-none"
                  >
                    <option value={selectedObject.table_uuid || ""}>
                      Actual: Mesa {selectedObject.operational_data.table_number}
                    </option>
                    {availableTables.map(t => (
                      <option key={t.uuid} value={t.uuid}>
                        Mesa {t.table_number} ({t.capacity} pax)
                      </option>
                    ))}
                  </select>
                </div>

                <button 
                  onClick={handleUnlinkTable} 
                  className="w-full flex items-center justify-center gap-2 px-3 py-2 bg-red-600/10 hover:bg-red-600/20 text-red-400 rounded text-xs border border-red-600/30 transition-colors"
                >
                  <Unlink size={12} /> Desvincular del plano
                </button>
              </>
            ) : (
              <div className="p-2 bg-amber-500/10 border border-amber-500/30 rounded">
                <p className="text-[10px] text-amber-400 text-center font-medium">
                  ⚠️ Sin mesa operativa asignada
                </p>
              </div>
            )}

            {/* ✅ BOTÓN DE CREAR SIEMPRE VISIBLE */}
            <div className="pt-2 border-t border-slate-600/50">
              <button 
                onClick={() => onRequestCreateTable(selectedObject.uuid)}
                className="w-full flex items-center justify-center gap-2 px-3 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded text-xs font-bold transition-colors shadow-sm border border-emerald-500"
              >
                <Plus size={14} /> Crear y asignar nueva mesa operativa
              </button>
              <p className="text-[10px] text-slate-500 mt-1.5 text-center leading-tight">
                Esto creará la mesa en el sistema y la vinculará a este objeto automáticamente.
              </p>
            </div>
          </div>
        )}

        {/* Capacidad */}
        {isTable && (
          <div className="bg-slate-700/50 p-3 rounded-lg border border-slate-600">
            <label className="text-xs font-semibold text-slate-400 uppercase block mb-2">Capacidad de sillas</label>
            <div className="flex items-center gap-3">
              <button onClick={() => handleCapacityChange(Math.max(1, capacity - 1))} className="w-8 h-8 bg-slate-600 hover:bg-slate-500 rounded flex items-center justify-center text-white font-bold">-</button>
              <input type="number" min="1" max="20" value={capacity} onChange={(e) => handleCapacityChange(parseInt(e.target.value) || 1)} className="flex-1 px-2 py-1.5 bg-slate-700 border border-slate-600 rounded text-sm text-white text-center" />
              <button onClick={() => handleCapacityChange(Math.min(20, capacity + 1))} className="w-8 h-8 bg-slate-600 hover:bg-slate-500 rounded flex items-center justify-center text-white font-bold">+</button>
            </div>
            <p className="text-[10px] text-slate-500 mt-1 text-center">Las sillas se redistribuyen automáticamente</p>
          </div>
        )}

        {/* Posición */}
        <div className="space-y-3">
          <label className="text-xs font-semibold text-slate-400 uppercase block">Posición (px)</label>
          <div className="grid grid-cols-2 gap-2">
            <div><label className="text-[10px] text-slate-500 block mb-1">X</label><input type="number" value={Math.round(selectedObject.position_x || 0)} onChange={(e) => handlePositionChange("x", parseInt(e.target.value) || 0)} className="w-full px-2 py-1.5 bg-slate-700 border border-slate-600 rounded text-sm text-white" /></div>
            <div><label className="text-[10px] text-slate-500 block mb-1">Y</label><input type="number" value={Math.round(selectedObject.position_y || 0)} onChange={(e) => handlePositionChange("y", parseInt(e.target.value) || 0)} className="w-full px-2 py-1.5 bg-slate-700 border border-slate-600 rounded text-sm text-white" /></div>
          </div>
        </div>

        {/* Rotación */}
        <div className="bg-slate-700/50 p-3 rounded-lg border border-slate-600">
          <RotationControl value={selectedObject.rotation || 0} onChange={handleRotationChange} step={1} />
        </div>

        {/* Zona */}
        <div className="space-y-2">
          <label className="text-xs font-semibold text-slate-400 uppercase block">Zona</label>
          <select value={selectedObject.zone_uuid || ""} onChange={(e) => onUpdateObject(selectedObject.uuid, { zone_uuid: e.target.value || null })} className="w-full px-2 py-1.5 bg-slate-700 border border-slate-600 rounded text-sm text-white">
            <option value="">Sin zona</option>
            {zones.map((zone) => (<option key={zone.uuid} value={zone.uuid}>{zone.code} - {zone.name_translations?.es || zone.code}</option>))}
          </select>
        </div>
      </div>
    </div>
  );
}
