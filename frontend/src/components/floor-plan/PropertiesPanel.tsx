// src/components/floor-plan/PropertiesPanel.tsx
import { useTranslation } from "react-i18next";
import { Trash2, Copy, RotateCcw, RotateCw, Users, AlertCircle } from "lucide-react";
import type { FloorPlanObject, FloorPlanZone, OperationalTableData, ObjectType } from "@/types/floorPlan";
import { cn } from "@/lib/utils";

interface Props {
  selectedObject: FloorPlanObject | null;
  zones: FloorPlanZone[];
  availableTables: OperationalTableData[];
  onUpdateObject: (uuid: string, updates: Partial<FloorPlanObject>) => void;
  onDeleteObject: (uuid: string) => void;
  isEditMode: boolean;
}

const STATUS_STYLE: Record<string, string> = {
  available: "bg-emerald-500/15 text-emerald-300 border-emerald-500/40",
  occupied: "bg-red-500/15 text-red-300 border-red-500/40",
  billing: "bg-amber-500/15 text-amber-300 border-amber-500/40",
  maintenance: "bg-slate-500/15 text-slate-300 border-slate-500/40",
};

const inputCls = "w-full px-3 py-2 bg-slate-700 border border-slate-600 rounded-lg text-white text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 transition-all";
const labelCls = "text-xs font-semibold text-slate-400 uppercase mb-1.5 block";

export function PropertiesPanel({ 
  selectedObject, 
  zones, 
  availableTables, 
  onUpdateObject, 
  onDeleteObject, 
  isEditMode 
}: Props) {
  const { t } = useTranslation();

  if (!isEditMode || !selectedObject) {
    return (
      <div className="w-72 bg-slate-800 border-l border-slate-700 p-4 shrink-0 flex items-center justify-center h-full">
        <p className="text-sm text-slate-400 text-center">
          {t("floor_plan.properties.no_selection", "Selecciona un elemento para editar sus propiedades")}
        </p>
      </div>
    );
  }

  const isTable = selectedObject.object_type === "table";
  const set = (updates: Partial<FloorPlanObject>) => onUpdateObject(selectedObject.uuid, updates);

  // Función para manejar el cambio de vinculación de mesa operativa
  const handleTableLinkChange = (newTableUuid: string) => {
    const linkedTable = availableTables.find(t => t.uuid === newTableUuid);
    if (linkedTable) {
      set({
        table_uuid: linkedTable.uuid,
        operational_data: {
          uuid: linkedTable.uuid,
          table_number: linkedTable.table_number,
          capacity: linkedTable.capacity,
          status: linkedTable.status,
          has_active_order: linkedTable.has_active_order,
          current_order_id: linkedTable.current_order_id,
        }
      });
    }
  };

  return (
    <div className="w-72 bg-slate-800 border-l border-slate-700 overflow-y-auto shrink-0 h-full">
      <div className="p-4 space-y-5">
        
        {/* Encabezado */}
        <div className="flex items-center justify-between pb-3 border-b border-slate-700">
          <h3 className="text-sm font-bold text-white">{t("floor_plan.properties.title", "Propiedades")}</h3>
          {isTable && selectedObject.operational_data && (
            <span className={cn("text-[10px] font-bold px-2 py-0.5 rounded-full border", STATUS_STYLE[selectedObject.operational_data.status])}>
              {t(`floor_plan.status.${selectedObject.operational_data.status}`, selectedObject.operational_data.status)}
            </span>
          )}
        </div>

        {/* Tipo de objeto (Solo lectura) */}
        <div className="text-[11px] text-slate-400 bg-slate-900/50 rounded-lg px-3 py-2.5 border border-slate-700">
          <span className="text-slate-500">{t("floor_plan.properties.object_type", "Tipo")}:</span>{" "}
          <span className="text-slate-200 font-medium capitalize">
            {t(`floor_plan.objects.${selectedObject.object_type}`, selectedObject.object_type)}
          </span>
        </div>

        {/* --- SECCIÓN ESPECÍFICA PARA MESAS --- */}
        {isTable && (
          <div className="space-y-4 p-3 bg-slate-900/30 rounded-lg border border-slate-700/50">
            <h4 className="text-xs font-semibold text-orange-400 uppercase flex items-center gap-1.5">
              <Users size={12} /> {t("floor_plan.properties.operational_data", "Datos Operativos")}
            </h4>
            
            {/* Selector de Mesa Operativa */}
            <div>
              <label className={labelCls}>{t("floor_plan.properties.linked_table", "Mesa Operativa")}</label>
              <select 
                className={inputCls} 
                value={selectedObject.table_uuid || ""}
                onChange={(e) => handleTableLinkChange(e.target.value)}
              >
                <option value="">{t("floor_plan.properties.select_table", "Seleccionar mesa...")}</option>
                {/* Mostrar la mesa actualmente vinculada aunque ya no esté en availableTables (por si acaso) */}
                {selectedObject.table_uuid && !availableTables.find(t => t.uuid === selectedObject.table_uuid) && (
                  <option value={selectedObject.table_uuid}>
                    {selectedObject.operational_data?.table_number || "Mesa vinculada"}
                  </option>
                )}
                {availableTables
                  .filter(t => t.uuid !== selectedObject.table_uuid) // No mostrar duplicados en la lista
                  .map(t => (
                    <option key={t.uuid} value={t.uuid}>
                      {t.table_number} ({t.capacity}p) - {t.status}
                    </option>
                  ))}
              </select>
            </div>

            {/* Info de solo lectura si está vinculada */}
            {selectedObject.operational_data && (
              <div className="grid grid-cols-2 gap-2 text-xs">
                <div className="bg-slate-800 p-2 rounded border border-slate-700">
                  <span className="text-slate-500 block">Número</span>
                  <span className="text-white font-bold">{selectedObject.operational_data.table_number}</span>
                </div>
                <div className="bg-slate-800 p-2 rounded border border-slate-700">
                  <span className="text-slate-500 block">Capacidad</span>
                  <span className="text-white font-bold">{selectedObject.operational_data.capacity} pax</span>
                </div>
              </div>
            )}
            
            {!selectedObject.table_uuid && (
              <div className="flex items-start gap-2 text-[11px] text-amber-400 bg-amber-900/20 p-2 rounded border border-amber-700/30">
                <AlertCircle size={14} className="shrink-0 mt-0.5" />
                <span>Esta representación gráfica no está vinculada a una mesa operativa real.</span>
              </div>
            )}
          </div>
        )}

        {/* --- PROPIEDADES GEOMÉTRICAS (Comunes a todos) --- */}
        <div className="space-y-4">
          <h4 className="text-xs font-semibold text-slate-400 uppercase border-b border-slate-700 pb-1">
            {t("floor_plan.properties.geometry", "Geometría")}
          </h4>

          {/* Posición */}
          <div>
            <label className={labelCls}>{t("floor_plan.properties.position", "Posición (px)")}</label>
            <div className="grid grid-cols-2 gap-2">
              <div>
                <span className="text-[10px] text-slate-500 mb-1 block">X</span>
                <input 
                  type="number" 
                  step={10} 
                  className={inputCls}
                  value={Math.round(selectedObject.position_x)}
                  onChange={(e) => set({ position_x: Math.max(0, Number(e.target.value) || 0) })} 
                />
              </div>
              <div>
                <span className="text-[10px] text-slate-500 mb-1 block">Y</span>
                <input 
                  type="number" 
                  step={10} 
                  className={inputCls}
                  value={Math.round(selectedObject.position_y)}
                  onChange={(e) => set({ position_y: Math.max(0, Number(e.target.value) || 0) })} 
                />
              </div>
            </div>
          </div>

          {/* Dimensiones */}
          <div>
            <label className={labelCls}>{t("floor_plan.properties.dimensions", "Dimensiones (px)")}</label>
            <div className="grid grid-cols-2 gap-2">
              <div>
                <span className="text-[10px] text-slate-500 mb-1 block">Ancho</span>
                <input 
                  type="number" 
                  min={20} 
                  step={10} 
                  className={inputCls}
                  value={selectedObject.width}
                  onChange={(e) => set({ width: Math.max(20, Number(e.target.value) || 20) })} 
                />
              </div>
              <div>
                <span className="text-[10px] text-slate-500 mb-1 block">Alto</span>
                <input 
                  type="number" 
                  min={20} 
                  step={10} 
                  className={inputCls}
                  value={selectedObject.height}
                  onChange={(e) => set({ height: Math.max(20, Number(e.target.value) || 20) })} 
                />
              </div>
            </div>
          </div>

          {/* Rotación */}
          <div>
            <label className={labelCls}>{t("floor_plan.properties.rotation", "Rotación")}</label>
            <div className="flex items-center gap-1">
              <button 
                onClick={() => set({ rotation: ((selectedObject.rotation + 270) % 360) as 0 | 90 | 180 | 270 })}
                className="p-2 bg-slate-700 hover:bg-slate-600 rounded-lg text-slate-200 transition-colors" 
                title="-90°"
              >
                <RotateCcw size={14} />
              </button>
              {[0, 90, 180, 270].map((r) => (
                <button 
                  key={r} 
                  onClick={() => set({ rotation: r as 0 | 90 | 180 | 270 })}
                  className={cn(
                    "flex-1 px-1 py-2 rounded text-xs font-medium transition-all",
                    selectedObject.rotation === r 
                      ? "bg-orange-500 text-white shadow-lg shadow-orange-500/20" 
                      : "bg-slate-700 text-slate-300 hover:bg-slate-600"
                  )}
                >
                  {r}°
                </button>
              ))}
              <button 
                onClick={() => set({ rotation: ((selectedObject.rotation + 90) % 360) as 0 | 90 | 180 | 270 })}
                className="p-2 bg-slate-700 hover:bg-slate-600 rounded-lg text-slate-200 transition-colors" 
                title="+90°"
              >
                <RotateCw size={14} />
              </button>
            </div>
          </div>

          {/* Zona */}
          <div>
            <label className={labelCls}>{t("floor_plan.properties.zone", "Zona")}</label>
            <select 
              className={inputCls} 
              value={selectedObject.zone_uuid || ""}
              onChange={(e) => set({ zone_uuid: e.target.value || null })}
            >
              <option value="">{t("floor_plan.properties.no_zone", "Sin zona")}</option>
              {zones.map((z) => (
                <option key={z.uuid} value={z.uuid}>{z.name || z.code}</option>
              ))}
            </select>
          </div>
        </div>

        {/* --- ACCIONES --- */}
        <div className="grid grid-cols-2 gap-2 pt-4 border-t border-slate-700">
          {/* Duplicar: SOLO permitido para decoraciones, NUNCA para mesas operativas */}
          {!isTable && (
            <button 
              onClick={() => {
                // La lógica de duplicación real se manejaría en el padre, 
                // aquí podrías emitir un evento o llamar a una prop onDuplicateObject
                // Por ahora, es un placeholder visual o se maneja en FloorPlanPage
              }}
              className="flex items-center justify-center gap-1.5 px-3 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-medium transition-colors"
            >
              <Copy size={14} /> {t("floor_plan.properties.duplicate", "Duplicar")}
            </button>
          )}
          
          <button 
            onClick={() => onDeleteObject(selectedObject.uuid)}
            className={cn(
              "flex items-center justify-center gap-1.5 px-3 py-2.5 text-white rounded-lg text-xs font-medium transition-colors",
              isTable ? "col-span-2 bg-red-600/90 hover:bg-red-700" : "bg-red-600/90 hover:bg-red-700"
            )}
          >
            <Trash2 size={14} /> {t("floor_plan.properties.delete", "Eliminar")}
          </button>
        </div>

      </div>
    </div>
  );
}
