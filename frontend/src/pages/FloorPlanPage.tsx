// src/pages/FloorPlanPage.tsx
import { useState, useMemo, useEffect } from "react";
import { useTranslation } from "react-i18next";
import { Map, Save, X, Edit3, Settings2 } from "lucide-react";
import type { 
  FloorPlanObject, 
  FloorPlanZone, 
  CanvasConfig, 
  ObjectType, 
  SaveFloorPlanPayload,
  OperationalTableData
} from "@/types/floorPlan";
import { FloorPlanCanvas } from "@/components/floor-plan/FloorPlanCanvas";
import { AssetLibrary } from "@/components/floor-plan/AssetLibrary";
import { PropertiesPanel } from "@/components/floor-plan/PropertiesPanel";
import { CanvasSettingsPanel } from "@/components/floor-plan/CanvasSettingsPanel";
import { useFloorPlan, useSaveFloorPlan } from "@/hooks/useFloorPlan";

export function FloorPlanPage() {
  const { t } = useTranslation();
  
  const [isEditMode, setIsEditMode] = useState(false);
  const [selectedObjectUuid, setSelectedObjectUuid] = useState<string | null>(null);
  const [localObjects, setLocalObjects] = useState<FloorPlanObject[]>([]);
  const [localZones, setLocalZones] = useState<FloorPlanZone[]>([]);
  const [availableTables, setAvailableTables] = useState<OperationalTableData[]>([]);
  const [canvasConfig, setCanvasConfig] = useState<CanvasConfig>({ 
    width: 2000, 
    height: 1500, 
    grid_size: 40, 
    unit: 'px', 
    version: 1 
  });
  
  const [showCanvasSettings, setShowCanvasSettings] = useState(false);
  const [hasChanges, setHasChanges] = useState(false);

  // Polling cada 15s en modo operacional, 0 en modo edición
  const { data, isLoading, refetch } = useFloorPlan(true, isEditMode ? 0 : 15000);
  const saveMutation = useSaveFloorPlan();

  // Sincronizar estado local con datos del backend
  useEffect(() => {
    if (data && !hasChanges) {
      setLocalObjects(data.objects || []);
      setLocalZones(data.zones || []);
      setAvailableTables(data.available_tables || []);
      if (data.plan) {
        setCanvasConfig(prev => ({ ...prev, ...data.plan }));
      }
    }
  }, [data, hasChanges]);

  // --- Manejadores de Acciones ---

  const handleAddObjectAt = (objectType: ObjectType, x: number, y: number) => {
    if (objectType === "table") {
      // ⚠️ REGLA FUNDAMENTAL: Buscar una mesa operativa real disponible
      const availableTable = availableTables.find(
        t => !localObjects.some(obj => obj.table_uuid === t.uuid)
      );

      if (!availableTable) {
        alert(t("floor_plan.errors.no_available_tables", "No hay mesas operativas disponibles para colocar en el plano."));
        return;
      }

      const newObject: FloorPlanObject = {
        uuid: crypto.randomUUID(), // UUID del objeto gráfico (no de la mesa)
        object_type: "table",
        table_uuid: availableTable.uuid, // ✅ Vinculación estable
        zone_uuid: null, // ✅ AGREGADO
        position_x: x,
        position_y: y,
        width: 80,
        height: 80,
        rotation: 0,
        z_index: 10,
        properties: { shape: "square" },
        operational_data: {
          uuid: availableTable.uuid,
          table_number: availableTable.table_number,
          capacity: availableTable.capacity,
          status: availableTable.status,
          has_active_order: availableTable.has_active_order,
          current_order_id: availableTable.current_order_id,
        }
      };
      
      setLocalObjects(prev => [...prev, newObject]);
      setSelectedObjectUuid(newObject.uuid);
    } else {
      // Es un objeto decorativo (muro, planta, booth, etc.)
      const newObject: FloorPlanObject = {
        uuid: crypto.randomUUID(),
        object_type: objectType,
        table_uuid: null,
        zone_uuid: null, // ✅ AGREGADO
        position_x: x,
        position_y: y,
        width: objectType === "wall" ? 120 : 60,
        height: objectType === "wall" ? 20 : 60,
        rotation: 0,
        z_index: 5,
        properties: {}
      };
      setLocalObjects(prev => [...prev, newObject]);
      setSelectedObjectUuid(newObject.uuid);
    }
    setHasChanges(true);
  };

    const handleAddObjectFromLibrary = (objectType: ObjectType) => {
    // Al hacer clic en la biblioteca, se agrega en una posición por defecto
    handleAddObjectAt(objectType, 100, 100); 
  };

  const handleObjectPositionChange = (uuid: string, x: number, y: number) => {
    setLocalObjects(prev => prev.map(obj => 
      obj.uuid === uuid ? { ...obj, position_x: x, position_y: y } : obj
    ));
    setHasChanges(true);
  };

  const handleObjectClick = (obj: FloorPlanObject) => {
    if (!isEditMode && obj.object_type === "table" && obj.table_uuid) {
      // Aquí iría la navegación al POS operacional:
      // navigate(`/pos/order?table_uuid=${obj.table_uuid}`);
      console.log("Abrir mesa operativa:", obj.table_uuid);
    }
  };

  const handleUpdateObject = (uuid: string, updates: Partial<FloorPlanObject>) => {
    setLocalObjects(prev => prev.map(obj => 
      obj.uuid === uuid ? { ...obj, ...updates } : obj
    ));
    setHasChanges(true);
  };

  const handleDeleteObject = (uuid: string) => {
    setLocalObjects(prev => prev.filter(obj => obj.uuid !== uuid));
    if (selectedObjectUuid === uuid) setSelectedObjectUuid(null);
    setHasChanges(true);
  };

    const handleSave = async () => {
    try {
      // Filtrar y sanitizar objetos para evitar nulls en campos requeridos
      const validObjects = localObjects
        .filter(obj => {
          // No enviar mesas gráficas sin una mesa operativa real vinculada
          if (obj.object_type === 'table' && !obj.table_uuid) return false;
          return true;
        })
        .map(obj => ({
          uuid: obj.uuid,
          object_type: obj.object_type,
          table_uuid: obj.table_uuid || null,
          zone_uuid: obj.zone_uuid || null,
          position_x: Math.round(obj.position_x) || 0,
          position_y: Math.round(obj.position_y) || 0,
          width: obj.width || (obj.object_type === 'table' ? 80 : 60), // ✅ Fallback a número
          height: obj.height || (obj.object_type === 'table' ? 80 : 60), // ✅ Fallback a número
          rotation: obj.rotation || 0,
          z_index: obj.z_index || 0,
          properties: obj.properties || {},
        }));

      const payload: SaveFloorPlanPayload = {
        plan_version: canvasConfig.version,
        plan: {
          width: canvasConfig.width || 2400,
          height: canvasConfig.height || 1800,
          grid_size: canvasConfig.grid_size || 40,
          unit: canvasConfig.unit || 'px',
        },
        zones: localZones.map(z => ({
          uuid: z.uuid,
          name: z.name || z.code,
          code: z.code,
          type: z.type || 'other',
          color: z.color || '#cccccc',
          name_translations: z.name_translations || {},
          position_x: z.position_x || 0,
          position_y: z.position_y || 0,
          width: z.width || 400,
          height: z.height || 300,
          rotation: z.rotation || 0,
          properties: z.properties || {},
        })),
        objects: validObjects,
      };

      await saveMutation.mutateAsync(payload);
      
      setHasChanges(false);
      setIsEditMode(false);
      setSelectedObjectUuid(null);
      refetch();
    } catch (error: any) {
      console.error("Error detallado al guardar:", error.response?.data || error);
      alert(t("floor_plan.errors.save_failed", "Error al guardar. Revisa la consola para más detalles."));
    }
  };
  const handleCancel = () => {
    if (hasChanges && !window.confirm(t("floor_plan.messages.discard_changes", "¿Descartar cambios no guardados?"))) {
      return;
    }
    setHasChanges(false);
    setIsEditMode(false);
    setSelectedObjectUuid(null);
    refetch(); // Revertir a datos del servidor
  };

  const handleCanvasConfigSave = (newConfig: CanvasConfig) => {
    setCanvasConfig(prev => ({ ...prev, ...newConfig }));
    setHasChanges(true);
    setShowCanvasSettings(false);
  };

  const selectedObject = localObjects.find(obj => obj.uuid === selectedObjectUuid) || null;

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-full text-slate-400">
        {t("floor_plan.messages.loading", "Cargando plano...")}
      </div>
    );
  }

  return (
    <div className="flex flex-col h-full bg-slate-900 text-white">
      {/* Header */}
      <div className="flex items-center justify-between p-4 border-b border-slate-700 bg-slate-800/50 shrink-0">
        <div className="flex items-center gap-3">
          <Map size={28} className="text-orange-400" />
          <div>
            <h1 className="text-xl font-bold text-white">{t("floor_plan.title", "Plano del Restaurante")}</h1>
            <p className="text-xs text-slate-400">{t("floor_plan.subtitle", "Organiza las mesas y zonas")}</p>
          </div>
        </div>
        
        <div className="flex items-center gap-2">
          <button
            onClick={() => setShowCanvasSettings(true)}
            className="flex items-center gap-2 px-3 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg transition-colors text-sm border border-slate-600"
          >
            <Settings2 size={16} />
            <span className="hidden sm:inline">{canvasConfig.width}×{canvasConfig.height}</span>
          </button>

          {isEditMode ? (
            <>
              <button
                onClick={handleCancel}
                className="flex items-center gap-2 px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg transition-colors text-sm"
              >
                <X size={16} />
                {t("floor_plan.actions.cancel", "Cancelar")}
              </button>
              <button
                onClick={handleSave}
                disabled={!hasChanges || saveMutation.isPending}
                className="flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 disabled:bg-slate-700 disabled:text-slate-500 text-white rounded-lg transition-colors text-sm font-medium"
              >
                <Save size={16} />
                {saveMutation.isPending ? t("floor_plan.actions.saving", "Guardando...") : t("floor_plan.actions.save", "Guardar cambios")}
              </button>
            </>
          ) : (
            <button
              onClick={() => setIsEditMode(true)}
              className="flex items-center gap-2 px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white rounded-lg transition-colors text-sm font-medium"
            >
              <Edit3 size={16} />
              {t("floor_plan.actions.edit", "Editar plano")}
            </button>
          )}
        </div>
      </div>

      {/* Área de Trabajo Principal */}
      <div className="flex flex-1 overflow-hidden">
        {/* Panel Izquierdo: Biblioteca de Activos */}
                {isEditMode && (
          <AssetLibrary onAddObject={handleAddObjectFromLibrary} isEditMode={isEditMode} />
        )}
        
        {/* Centro: Lienzo del Plano */}
        <div className="flex-1 p-4 overflow-hidden bg-slate-950">
          <FloorPlanCanvas
  zones={localZones}
  objects={localObjects}  // ✅ Pasar objects, no tables/decorations
  isEditMode={isEditMode}
  selectedObjectUuid={selectedObjectUuid}
  canvasConfig={canvasConfig}
  onObjectPositionChange={handleObjectPositionChange}
  onAddObjectAt={handleAddObjectAt}
  onObjectClick={handleObjectClick}
  onObjectSelect={setSelectedObjectUuid}
/>
        </div>

        {/* Panel Derecho: Propiedades */}
        {isEditMode && (
          <PropertiesPanel
            selectedObject={selectedObject}
            zones={localZones}
            availableTables={availableTables}
            onUpdateObject={handleUpdateObject}
            onDeleteObject={handleDeleteObject}
            isEditMode={isEditMode}
          />
        )}
      </div>

      {/* Modal de Configuración del Canvas */}
      <CanvasSettingsPanel
        canvasConfig={canvasConfig}
        onSave={handleCanvasConfigSave}
        onClose={() => setShowCanvasSettings(false)}
        isOpen={showCanvasSettings}
      />
    </div>
  );
}
