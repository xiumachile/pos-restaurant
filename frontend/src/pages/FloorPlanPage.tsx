import { useState, useMemo, useEffect } from "react";
import { useNavigate } from "react-router-dom";
import { useTranslation } from "react-i18next";
import { Map, Edit3, Save, Loader2, CheckCircle2, AlertTriangle } from "lucide-react";
import {
  useFloorPlan,
  useSaveFloorPlan,
  useCreateDiningZone,
  useUpdateDiningZone,
  useDeleteDiningZone,
} from "@/hooks/useFloorPlan";
import type {
  DiningZone,
  TablePosition,
  DecorationPosition,
  CreateDiningZonePayload,
  UpdateDiningZonePayload,
  FurnitureType,
} from "@/types/floorPlan";
import { FURNITURE_CATALOG } from "@/components/floor-plan/AssetLibrary";
import { FloorPlanCanvas } from "@/components/floor-plan/FloorPlanCanvas";
import { DiningZonePanel } from "@/components/floor-plan/DiningZonePanel";
import { CreateZoneModal } from "@/components/floor-plan/CreateZoneModal";
import { EditZoneModal } from "@/components/floor-plan/EditZoneModal";
import { AssetLibrary } from "@/components/floor-plan/AssetLibrary";
import { PropertiesPanel } from "@/components/floor-plan/PropertiesPanel";

type Toast = { type: "success" | "error"; message: string } | null;

export function FloorPlanPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();

  const { data, isLoading, error } = useFloorPlan();
  const saveMutation = useSaveFloorPlan();
  const createZoneMutation = useCreateDiningZone();
  const updateZoneMutation = useUpdateDiningZone();
  const deleteZoneMutation = useDeleteDiningZone();

  const [isEditMode, setIsEditMode] = useState(false);
  const [localTables, setLocalTables] = useState<TablePosition[]>([]);
  const [localDecorations, setLocalDecorations] = useState<DecorationPosition[]>([]);
  const [hasChanges, setHasChanges] = useState(false);
  const [showCreateZone, setShowCreateZone] = useState(false);
  const [editingZone, setEditingZone] = useState<DiningZone | null>(null);
  const [selectedTableUuid, setSelectedTableUuid] = useState<string | null>(null);
  const [toast, setToast] = useState<Toast>(null);

  // ✅ Sincronizar solo cuando data cambia desde el servidor
  useEffect(() => {
    if (data && !hasChanges) {
      setLocalTables(data.tables);
      setLocalDecorations((data as any).decorations || []);
    }
  }, [data]);

  const showToast = (type: "success" | "error", message: string) => {
    setToast({ type, message });
    setTimeout(() => setToast(null), 4000);
  };

  const handleToggleEdit = () => {
    if (isEditMode && hasChanges) {
      if (!window.confirm(t("floor_plan.messages.discard_changes_confirm", "¿Descartar cambios no guardados?"))) {
        return;
      }
      if (data) {
        setLocalTables(data.tables);
        setLocalDecorations((data as any).decorations || []);
      }
      setHasChanges(false);
    }
    setIsEditMode(!isEditMode);
    setSelectedTableUuid(null);
  };

  const handleAddFurniture = (type: FurnitureType) => {
    const config = FURNITURE_CATALOG.find((f) => f.id === type);
    if (!config) return;

    if (config.capacity > 0) {
      const newTable: TablePosition = {
        uuid: crypto.randomUUID(),
        table_number: `Mesa ${localTables.length + 1}`,
        capacity: config.capacity,
        position_x: 200,
        position_y: 200,
        rotation: 0,
        shape: config.id.includes("round") ? "round" : config.id.includes("rectangle") ? "rectangle" : "square",
        width: config.width,
        height: config.height,
        status: "available",
        zone_id: null,
        zone_uuid: null,
        has_active_order: false,
        current_order_id: null,
        furniture_type: type,
      } as TablePosition;
      setLocalTables((prev) => [...prev, newTable]);
      setSelectedTableUuid(newTable.uuid);
    } else {
      const newDecoration: DecorationPosition = {
        uuid: crypto.randomUUID(),
        type,
        position_x: 200,
        position_y: 200,
        rotation: 0,
        width: config.width,
        height: config.height,
      };
      setLocalDecorations((prev) => [...prev, newDecoration]);
    }
    setHasChanges(true);
  };

  const handleUpdateTable = (uuid: string, updates: Partial<TablePosition>) => {
    setLocalTables((prev) => prev.map((t) => (t.uuid === uuid ? { ...t, ...updates } : t)));
    setHasChanges(true);
  };

  const handleDeleteTable = (uuid: string) => {
    setLocalTables((prev) => prev.filter((t) => t.uuid !== uuid));
    setHasChanges(true);
    setSelectedTableUuid(null);
  };

  const handleDeleteDecoration = (uuid: string) => {
    setLocalDecorations((prev) => prev.filter((d) => d.uuid !== uuid));
    setHasChanges(true);
  };

  const handleDuplicateTable = (table: TablePosition) => {
    const newTable: TablePosition = {
      ...table,
      uuid: crypto.randomUUID(),
      table_number: `${table.table_number} (copia)`,
      position_x: table.position_x + 50,
      position_y: table.position_y + 50,
    };
    setLocalTables((prev) => [...prev, newTable]);
    setHasChanges(true);
    setSelectedTableUuid(newTable.uuid);
  };

  const handleTablePositionChange = (uuid: string, x: number, y: number) => {
    setLocalTables((prev) => prev.map((t) => (t.uuid === uuid ? { ...t, position_x: x, position_y: y } : t)));
    setHasChanges(true);
  };

  const handleDecorationPositionChange = (uuid: string, x: number, y: number) => {
    setLocalDecorations((prev) => prev.map((d) => (d.uuid === uuid ? { ...d, position_x: x, position_y: y } : d)));
    setHasChanges(true);
  };

  const handleTableClick = (table: TablePosition) => {
    if (!isEditMode) {
      navigate(`/tables/${table.uuid}`);
    } else {
      setSelectedTableUuid(table.uuid);
    }
  };

  const handleSave = async () => {
    try {
      const payload = {
        tables: localTables.map((t) => ({
          uuid: t.uuid,
          position_x: t.position_x,
          position_y: t.position_y,
          rotation: t.rotation,
          shape: t.shape,
          width: t.width,
          height: t.height,
          furniture_type: t.furniture_type, // ✅ Crucial para que el backend sepa qué renderizar
        })),
        decorations: localDecorations.map((d) => ({
          uuid: d.uuid,
          type: d.type,
          position_x: d.position_x,
          position_y: d.position_y,
          rotation: d.rotation,
          width: d.width,
          height: d.height,
        })),
      };
      await saveMutation.mutateAsync(payload);
      showToast("success", t("floor_plan.messages.saved", "Plano guardado correctamente"));
      setHasChanges(false);
      setIsEditMode(false);
    } catch (err: any) {
      showToast("error", err?.response?.data?.message ?? t("floor_plan.messages.error_save", "Error al guardar"));
    }
  };

  const handleCancel = () => {
    if (data) {
      setLocalTables(data.tables);
      setLocalDecorations((data as any).decorations || []);
    }
    setHasChanges(false);
    setIsEditMode(false);
    setSelectedTableUuid(null);
  };

  const handleCreateZone = async (payload: CreateDiningZonePayload) => {
    await createZoneMutation.mutateAsync(payload);
    showToast("success", t("floor_plan.messages.zone_created", "Zona creada"));
  };

  const handleUpdateZone = async (uuid: string, payload: UpdateDiningZonePayload) => {
    await updateZoneMutation.mutateAsync({ uuid, payload });
    showToast("success", t("floor_plan.messages.zone_updated", "Zona actualizada"));
  };

  const handleDeleteZone = async (zone: DiningZone) => {
    try {
      await deleteZoneMutation.mutateAsync(zone.uuid);
      showToast("success", t("floor_plan.messages.zone_deleted", "Zona eliminada"));
    } catch (err: any) {
      showToast("error", err?.response?.data?.message ?? t("floor_plan.messages.zone_delete_error", "Error al eliminar"));
    }
  };

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-full min-h-[600px]">
        <Loader2 className="animate-spin text-orange-500" size={48} />
      </div>
    );
  }

  if (error) {
    return (
      <div className="flex items-center justify-center h-full min-h-[600px] text-red-400">
        <p>{t("floor_plan.messages.error_load", "Error al cargar el plano")}</p>
      </div>
    );
  }

  const zones = data?.zones ?? [];
  const selectedTable = localTables.find((t) => t.uuid === selectedTableUuid) || null;

  return (
    <div className="flex flex-col h-full">
      <div className="flex items-center justify-between p-4 border-b border-slate-700 bg-slate-800/50">
        <div className="flex items-center gap-3">
          <Map size={28} className="text-orange-400" />
          <div>
            <h1 className="text-2xl font-bold text-white">{t("floor_plan.title", "Plano del Restaurante")}</h1>
            <p className="text-sm text-slate-400">{t("floor_plan.subtitle", "Organiza las mesas y zonas")}</p>
          </div>
        </div>
        <div className="flex items-center gap-2">
          {isEditMode ? (
            <>
              <button onClick={handleCancel} className="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg transition-colors">
                {t("floor_plan.actions.cancel", "Cancelar")}
              </button>
              <button
                onClick={handleSave}
                disabled={!hasChanges || saveMutation.isPending}
                className="flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 disabled:bg-slate-700 disabled:text-slate-500 text-white rounded-lg transition-colors"
              >
                {saveMutation.isPending ? <Loader2 size={16} className="animate-spin" /> : <Save size={16} />}
                {t("floor_plan.actions.save", "Guardar")}
                {hasChanges && <span className="w-2 h-2 bg-orange-400 rounded-full animate-pulse" />}
              </button>
            </>
          ) : (
            <button onClick={handleToggleEdit} className="flex items-center gap-2 px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg transition-colors">
              <Edit3 size={16} />
              {t("floor_plan.actions.edit", "Editar")}
            </button>
          )}
        </div>
      </div>

      <div className="flex flex-1 overflow-hidden">
        {isEditMode && <AssetLibrary onAddFurniture={handleAddFurniture} isEditMode={isEditMode} />}
        <div className="flex-1 p-4 overflow-hidden">
          <FloorPlanCanvas
            zones={zones}
            tables={localTables}
            decorations={localDecorations}
            isEditMode={isEditMode}
            selectedTableUuid={selectedTableUuid}
            onTablePositionChange={handleTablePositionChange}
            onDecorationPositionChange={handleDecorationPositionChange}
            onTableClick={handleTableClick}
            onTableSelect={setSelectedTableUuid}
          />
        </div>
        {isEditMode && (
          <PropertiesPanel
            selectedTable={selectedTable}
            onUpdateTable={handleUpdateTable}
            onDeleteTable={handleDeleteTable}
            onDeleteDecoration={handleDeleteDecoration}
            onDuplicateTable={handleDuplicateTable}
            isEditMode={isEditMode}
          />
        )}
      </div>

      <CreateZoneModal isOpen={showCreateZone} onClose={() => setShowCreateZone(false)} onSubmit={handleCreateZone} />
      <EditZoneModal isOpen={!!editingZone} zone={editingZone} onClose={() => setEditingZone(null)} onSubmit={handleUpdateZone} />

      {toast && (
        <div className={`fixed bottom-6 right-6 flex items-center gap-3 px-4 py-3 rounded-lg shadow-2xl z-50 ${
          toast.type === "success" ? "bg-emerald-900/90 border border-emerald-700 text-emerald-100" : "bg-red-900/90 border border-red-700 text-red-100"
        }`}>
          {toast.type === "success" ? <CheckCircle2 size={20} className="text-emerald-400" /> : <AlertTriangle size={20} className="text-red-400" />}
          <span className="text-sm font-medium">{toast.message}</span>
        </div>
      )}
    </div>
  );
}
