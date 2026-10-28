import { useState, useEffect } from "react";
import { useTranslation } from "react-i18next";
import { Map, Save, X, Edit3, Settings2 } from "lucide-react";
import type {
  FloorPlanObject,
  FloorPlanZone,
  CanvasConfig,
  SaveFloorPlanPayload,
  OperationalTableData,
  ObjectType,
  CreateDiningZonePayload,
  UpdateDiningZonePayload,
} from "@/types/floorPlan";
import { FloorPlanCanvas } from "@/components/floor-plan/FloorPlanCanvas";
import { AssetLibrary } from "@/components/floor-plan/AssetLibrary";
import { PropertiesPanel } from "@/components/floor-plan/panels/PropertiesPanel";
import { CanvasSettingsPanel } from "@/components/floor-plan/CanvasSettingsPanel";
import { CreateZoneModal } from "@/components/floor-plan/CreateZoneModal";
import { EditZoneModal } from "@/components/floor-plan/EditZoneModal";
import { DiningZonePanel } from "@/components/floor-plan/DiningZonePanel";
import { CreateOperationalTableModal } from "@/components/floor-plan/panels/CreateOperationalTableModal";
import { useFloorPlan, useSaveFloorPlan } from "@/hooks/useFloorPlan";
import { FURNITURE_CATALOG, calculateSeatLayout } from "@/components/floor-plan/catalog/furnitureCatalog";
import { apiClient } from "@/services/apiClient";

export function FloorPlanPage() {
  const { t } = useTranslation();

  const [isEditMode, setIsEditMode] = useState(false);
  const [selectedObjectUuid, setSelectedObjectUuid] = useState<string | null>(null);
  const [localObjects, setLocalObjects] = useState<FloorPlanObject[]>([]);
  const [localZones, setLocalZones] = useState<FloorPlanZone[]>([]);
  const [availableTables, setAvailableTables] = useState<OperationalTableData[]>([]);
  const [canvasConfig, setCanvasConfig] = useState<CanvasConfig>({
    width: 2400,
    height: 1800,
    grid_size: 40,
    unit: "px",
    version: 1,
  });

  const [showCanvasSettings, setShowCanvasSettings] = useState(false);
  const [showCreateZone, setShowCreateZone] = useState(false);
  const [editingZone, setEditingZone] = useState<FloorPlanZone | null>(null);
  const [showZonePanel, setShowZonePanel] = useState(false);
  const [hasChanges, setHasChanges] = useState(false);
  const [isCreatingTable, setIsCreatingTable] = useState(false);
  const [targetObjectUuid, setTargetObjectUuid] = useState<string | null>(null);

  const { data, isLoading, refetch } = useFloorPlan(true, isEditMode ? 0 : 15000);
  const saveMutation = useSaveFloorPlan();

  useEffect(() => {
    if (data && !hasChanges) {
      setLocalObjects(data.objects || []);
      setLocalZones(data.zones || []);
      setAvailableTables(data.available_tables || []);
      if (data.plan) {
        setCanvasConfig((prev) => ({ ...prev, ...data.plan }));
      }
    }
  }, [data, hasChanges]);

  const deduceZoneByPosition = (x: number, y: number): string | null => {
    const zone = localZones.find((zone) => {
      const zx = zone.position_x || 0;
      const zy = zone.position_y || 0;
      const zw = zone.width || 400;
      const zh = zone.height || 300;
      return x >= zx && x <= zx + zw && y >= zy && y <= zy + zh;
    });
    return zone?.uuid || null;
  };

  const handleAddObjectAt = (objectType: ObjectType, x: number, y: number) => {
    const definition = (FURNITURE_CATALOG as any)[objectType];
    if (!definition) {
      console.error(`Tipo de objeto no encontrado: ${objectType}`);
      return;
    }

    const isTable = objectType.includes("table");
    const zoneUuid = deduceZoneByPosition(x, y);

    if (isTable) {
      const availableTable = availableTables.find(
        (t) => !localObjects.some((obj) => obj.table_uuid === t.uuid)
      );

      if (!availableTable) {
        alert(t("floor_plan.errors.no_available_tables", "No hay mesas operativas disponibles."));
        return;
      }

      const capacity = availableTable.capacity || definition.defaultCapacity;
      const seatLayout = calculateSeatLayout(capacity, definition.defaultShape);

      const newObject: FloorPlanObject = {
        uuid: crypto.randomUUID(),
        object_type: objectType,
        table_uuid: availableTable.uuid,
        zone_uuid: zoneUuid,
        position_x: x,
        position_y: y,
        width: definition.defaultWidth,
        height: definition.defaultHeight,
        rotation: 0,
        z_index: 10,
        properties: {
          shape: definition.defaultShape,
          capacity: capacity,
          seat_layout: seatLayout,
        },
        operational_data: {
          uuid: availableTable.uuid,
          table_number: availableTable.table_number,
          capacity: capacity,
          status: availableTable.status,
          has_active_order: availableTable.has_active_order,
          current_order_id: availableTable.current_order_id,
        },
      };

      setLocalObjects((prev) => [...prev, newObject]);
      setSelectedObjectUuid(newObject.uuid);
    } else {
      const newObject: FloorPlanObject = {
        uuid: crypto.randomUUID(),
        object_type: objectType,
        table_uuid: null,
        zone_uuid: zoneUuid,
        position_x: x,
        position_y: y,
        width: definition.defaultWidth,
        height: definition.defaultHeight,
        rotation: 0,
        z_index: 5,
        properties: {
          shape: definition.defaultShape,
          swing_direction: "right",
        },
      };

      setLocalObjects((prev) => [...prev, newObject]);
      setSelectedObjectUuid(newObject.uuid);
    }

    setHasChanges(true);
  };

  const handleAddObjectFromLibrary = (objectType: ObjectType) => {
    const offsetX = Math.floor(Math.random() * 40);
    const offsetY = Math.floor(Math.random() * 40);
    handleAddObjectAt(objectType, 100 + offsetX, 100 + offsetY);
  };

  const handleObjectPositionChange = (uuid: string, x: number, y: number) => {
    const newZoneUuid = deduceZoneByPosition(x, y);
    setLocalObjects((prev) =>
      prev.map((obj) =>
        obj.uuid === uuid ? { ...obj, position_x: x, position_y: y, zone_uuid: newZoneUuid } : obj
      )
    );
    setHasChanges(true);
  };

  const handleObjectClick = (obj: FloorPlanObject) => {
    if (!isEditMode && obj.object_type.includes("table") && obj.table_uuid) {
      console.log("Abrir mesa operativa:", obj.table_uuid);
    }
  };

  const handleUpdateObject = (uuid: string, updates: Partial<FloorPlanObject>) => {
    setLocalObjects((prev) =>
      prev.map((obj) => (obj.uuid === uuid ? { ...obj, ...updates } : obj))
    );
    setHasChanges(true);
  };

  const handleDeleteObject = (uuid: string) => {
    setLocalObjects((prev) => prev.filter((obj) => obj.uuid !== uuid));
    if (selectedObjectUuid === uuid) setSelectedObjectUuid(null);
    setHasChanges(true);
  };

  const handleCreateQuickTable = async (data: { table_number: string; capacity: number }) => {
    if (!targetObjectUuid) return;
    setIsCreatingTable(true);
    try {
      const targetObj = localObjects.find((obj) => obj.uuid === targetObjectUuid);
      const zone = targetObj?.zone_uuid
        ? localZones.find((z) => z.uuid === targetObj.zone_uuid)
        : null;

      const areaCode = zone?.code || "MAIN";
      const areaNameEs = zone?.name_translations?.es || zone?.name || "Salón Principal";
      const areaNameZh = zone?.name_translations?.zh || "主厅";

      console.log("📤 Creando mesa operativa:", data.table_number, "en área:", areaCode);

      const response = await apiClient.post("/tables", {
        area_code: areaCode,
        area_name_translations: {
          es: areaNameEs,
          zh: areaNameZh,
        },
        table_number: data.table_number,
        capacity: data.capacity,
      });

      const newTable = response.data?.data || response.data;
      console.log("📥 Mesa creada:", newTable);

      if (!newTable.uuid) {
        throw new Error("La respuesta del backend no contiene el UUID de la mesa creada");
      }

      setLocalObjects((prev) =>
        prev.map((obj) => {
          if (obj.uuid === targetObjectUuid) {
            return {
              ...obj,
              table_uuid: newTable.uuid,
              operational_data: {
                uuid: newTable.uuid,
                table_number: newTable.table_number,
                capacity: newTable.capacity,
                status: newTable.status || "available",
                has_active_order: false,
                current_order_id: null,
              },
              properties: {
                ...obj.properties,
                capacity: newTable.capacity,
                seat_layout: calculateSeatLayout(
                  newTable.capacity,
                  obj.properties?.shape || "square"
                ),
              },
            };
          }
          return obj;
        })
      );

      setAvailableTables((prev) => prev.filter((t) => t.uuid !== newTable.uuid));

      setHasChanges(true);
      setTargetObjectUuid(null);
    } catch (error: any) {
      const errorMsg = error.response?.data?.message || error.message || "Error desconocido";
      console.error("❌ Error al crear mesa:", error.response?.data || error);
      alert("Error al crear la mesa: " + errorMsg);
    } finally {
      setIsCreatingTable(false);
    }
  };

  const handleSave = async () => {
    try {
      const ALLOWED_BACKEND_TYPES: ObjectType[] = [
        "table_round",
        "table_square",
        "table_rectangle",
        "chair",
        "stool",
        "booth",
        "sofa",
        "wall",
        "column",
        "door_single",
        "door_double",
        "window",
        "plant",
        "counter",
        "service_station",
      ];

      const validObjects = localObjects
        .filter((obj) => {
          if (obj.object_type.includes("table") && !obj.table_uuid) return false;
          return true;
        })
        .map((obj) => {
          let backendType: ObjectType = obj.object_type;

          if (!ALLOWED_BACKEND_TYPES.includes(backendType)) {
            console.warn(`Tipo '${backendType}' no reconocido, normalizando a 'table_round'`);
            backendType = "table_round";
          }

          return {
            uuid: obj.uuid,
            object_type: backendType,
            table_uuid: obj.table_uuid || null,
            zone_uuid: obj.zone_uuid || null,
            position_x: Math.round(obj.position_x) || 0,
            position_y: Math.round(obj.position_y) || 0,
            width: obj.width || 80,
            height: obj.height || 80,
            rotation: obj.rotation || 0,
            z_index: obj.z_index || 0,
            properties: obj.properties || {},
          };
        });

      const payload: SaveFloorPlanPayload = {
        plan_version: canvasConfig.version,
        plan: {
          width: canvasConfig.width || 2400,
          height: canvasConfig.height || 1800,
          grid_size: canvasConfig.grid_size || 40,
          unit: canvasConfig.unit || "px",
        },
        zones: localZones.map((z) => ({
          uuid: z.uuid,
          name: z.name || z.code,
          code: z.code,
          type: z.type || "other",
          color: z.color || "#cccccc",
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
      console.error("Error al guardar:", error);
      const validationErrors = error.response?.data?.errors;
      const errorMessage = error.response?.data?.message || "Error desconocido";

      if (validationErrors) {
        alert("Error de validación:\n\n" + JSON.stringify(validationErrors, null, 2));
      } else {
        alert(t("floor_plan.errors.save_failed", "Error al guardar: ") + errorMessage);
      }
    }
  };

  const handleCancel = () => {
    if (
      hasChanges &&
      !window.confirm(t("floor_plan.messages.discard_changes", "¿Descartar cambios?"))
    ) {
      return;
    }
    setHasChanges(false);
    setIsEditMode(false);
    setSelectedObjectUuid(null);
    refetch();
  };

  const handleCanvasConfigSave = (newConfig: CanvasConfig) => {
    setCanvasConfig((prev) => ({ ...prev, ...newConfig }));
    setHasChanges(true);
    setShowCanvasSettings(false);
  };

  const handleCreateZone = async (payload: CreateDiningZonePayload) => {
    const newZone: FloorPlanZone = {
      uuid: crypto.randomUUID(),
      code: payload.code,
      name: payload.name_translations.es || payload.code,
      type: "other",
      color: payload.color,
      name_translations: payload.name_translations,
      position_x: payload.position_x || 100,
      position_y: payload.position_y || 100,
      width: payload.width || 400,
      height: payload.height || 300,
      rotation: 0,
      properties: {},
    };
    setLocalZones((prev) => [...prev, newZone]);
    setHasChanges(true);
  };

  const handleUpdateZone = async (uuid: string, payload: UpdateDiningZonePayload) => {
    setLocalZones((prev) =>
      prev.map((z) =>
        z.uuid === uuid
          ? {
              ...z,
              name_translations: payload.name_translations || z.name_translations,
              color: payload.color || z.color,
              position_x: payload.position_x ?? z.position_x,
              position_y: payload.position_y ?? z.position_y,
              width: payload.width ?? z.width,
              height: payload.height ?? z.height,
              rotation: payload.rotation ?? z.rotation,
            }
          : z
      )
    );
    setHasChanges(true);
  };

  const handleDeleteZone = (zone: FloorPlanZone) => {
    setLocalZones((prev) => prev.filter((z) => z.uuid !== zone.uuid));
    setLocalObjects((prev) =>
      prev.map((obj) => (obj.zone_uuid === zone.uuid ? { ...obj, zone_uuid: null } : obj))
    );
    setHasChanges(true);
  };

  const selectedObject = localObjects.find((obj) => obj.uuid === selectedObjectUuid) || null;

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-full text-slate-400 bg-slate-900">
        <div className="flex flex-col items-center gap-3">
          <div className="w-8 h-8 border-4 border-orange-500 border-t-transparent rounded-full animate-spin"></div>
          <span>{t("floor_plan.messages.loading", "Cargando plano...")}</span>
        </div>
      </div>
    );
  }

  return (
    <div className="flex flex-col h-full bg-slate-900 text-white">
      <div className="flex items-center justify-between p-4 border-b border-slate-700 bg-slate-800/50 shrink-0">
        <div className="flex items-center gap-3">
          <Map size={28} className="text-orange-400" />
          <div>
            <h1 className="text-xl font-bold text-white">
              {t("floor_plan.title", "Plano del Restaurante")}
            </h1>
            <p className="text-xs text-slate-400">
              {t("floor_plan.subtitle", "Organiza las mesas y zonas")}
            </p>
          </div>
        </div>
        <div className="flex items-center gap-2">
          <button
            onClick={() => setShowZonePanel(true)}
            className="flex items-center gap-2 px-3 py-2 bg-purple-700 hover:bg-purple-600 text-white rounded-lg transition-colors text-sm border border-purple-600"
            title="Gestionar Zonas"
          >
            🗺️ Zonas
          </button>
          <button
            onClick={() => setShowCanvasSettings(true)}
            className="flex items-center gap-2 px-3 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg transition-colors text-sm border border-slate-600"
            title="Configurar lienzo"
          >
            <Settings2 size={16} />
            <span className="hidden sm:inline">
              {canvasConfig.width} × {canvasConfig.height}
            </span>
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
                className="flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 disabled:bg-slate-700 disabled:text-slate-500 disabled:cursor-not-allowed text-white rounded-lg transition-colors text-sm font-medium"
              >
                <Save size={16} />
                {saveMutation.isPending
                  ? t("floor_plan.actions.saving", "Guardando...")
                  : t("floor_plan.actions.save", "Guardar cambios")}
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

      <div className="flex flex-1 overflow-hidden">
        {isEditMode && (
          <AssetLibrary onAddObject={handleAddObjectFromLibrary} isEditMode={isEditMode} />
        )}
        <div className="flex-1 p-4 overflow-hidden bg-slate-950">
          <FloorPlanCanvas
            zones={localZones}
            objects={localObjects}
            isEditMode={isEditMode}
            selectedObjectUuid={selectedObjectUuid}
            canvasConfig={canvasConfig}
            onObjectPositionChange={handleObjectPositionChange}
            onAddObjectAt={handleAddObjectAt}
            onObjectClick={handleObjectClick}
            onObjectSelect={setSelectedObjectUuid}
          />
        </div>
        {isEditMode && (
          <PropertiesPanel
            selectedObject={selectedObject}
            zones={localZones}
            availableTables={availableTables}
            onUpdateObject={handleUpdateObject}
            onDeleteObject={handleDeleteObject}
            onRequestCreateTable={(uuid) => setTargetObjectUuid(uuid)}
            isEditMode={isEditMode}
          />
        )}
      </div>

      {showZonePanel && (
        <DiningZonePanel
          zones={localZones}
          onCreateZone={() => {
            setShowZonePanel(false);
            setShowCreateZone(true);
          }}
          onEditZone={(zone) => {
            setShowZonePanel(false);
            setEditingZone(zone);
          }}
          onDeleteZone={(zone) => {
            handleDeleteZone(zone);
            setShowZonePanel(false);
          }}
        />
      )}

      <CreateZoneModal
        isOpen={showCreateZone}
        onClose={() => setShowCreateZone(false)}
        onSubmit={async (payload) => {
          await handleCreateZone(payload);
          setShowCreateZone(false);
        }}
      />
      <EditZoneModal
        zone={editingZone}
        isOpen={!!editingZone}
        onClose={() => setEditingZone(null)}
        onSubmit={async (uuid, payload) => {
          await handleUpdateZone(uuid, payload);
          setEditingZone(null);
        }}
      />

      <CreateOperationalTableModal
        isOpen={!!targetObjectUuid}
        onClose={() => setTargetObjectUuid(null)}
        onCreate={handleCreateQuickTable}
        isSubmitting={isCreatingTable}
      />

      <CanvasSettingsPanel
        canvasConfig={canvasConfig}
        onSave={handleCanvasConfigSave}
        onClose={() => setShowCanvasSettings(false)}
        isOpen={showCanvasSettings}
      />
    </div>
  );
}
