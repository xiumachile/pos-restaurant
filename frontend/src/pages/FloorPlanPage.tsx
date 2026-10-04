import { useState, useMemo } from "react";
import { useNavigate } from "react-router-dom";
import { useTranslation } from "react-i18next";
import { Map, Eye, Edit3, Save, Loader2, CheckCircle2, AlertTriangle } from "lucide-react";
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
  CreateDiningZonePayload,
  UpdateDiningZonePayload,
} from "@/types/floorPlan";
import { FloorPlanCanvas } from "@/components/floor-plan/FloorPlanCanvas";
import { DiningZonePanel } from "@/components/floor-plan/DiningZonePanel";
import { CreateZoneModal } from "@/components/floor-plan/CreateZoneModal";
import { EditZoneModal } from "@/components/floor-plan/EditZoneModal";

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
  const [hasChanges, setHasChanges] = useState(false);
  const [showCreateZone, setShowCreateZone] = useState(false);
  const [editingZone, setEditingZone] = useState<DiningZone | null>(null);
  const [toast, setToast] = useState<Toast>(null);

  // Sincronizar localTables con data cuando llegue del servidor
  useMemo(() => {
    if (data && !hasChanges) {
      setLocalTables(data.tables);
    }
  }, [data, hasChanges]);

  const showToast = (type: "success" | "error", message: string) => {
    setToast({ type, message });
    setTimeout(() => setToast(null), 4000);
  };

  const handleToggleEdit = () => {
    if (isEditMode && hasChanges) {
      // Preguntar si descartar cambios
      if (
        !window.confirm(t("floor_plan.messages.discard_changes_confirm"))
      ) {
        return;
      }
      // Descartar cambios
      if (data) setLocalTables(data.tables);
      setHasChanges(false);
    }
    setIsEditMode(!isEditMode);
  };

  const handleTablePositionChange = (uuid: string, x: number, y: number) => {
    setLocalTables((prev) =>
      prev.map((t) => (t.uuid === uuid ? { ...t, position_x: x, position_y: y } : t))
    );
    setHasChanges(true);
  };

  const handleTableClick = (table: TablePosition) => {
    if (!isEditMode) {
      // En modo ver, navegar a toma de pedidos
      navigate(`/tables/${table.uuid}`);
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
        })),
      };
      await saveMutation.mutateAsync(payload);
      showToast("success", t("floor_plan.messages.saved"));
      setHasChanges(false);
      setIsEditMode(false);
    } catch (err: any) {
      showToast(
        "error",
        err?.response?.data?.message ?? t("floor_plan.messages.error_save")
      );
    }
  };

  const handleCancel = () => {
    if (data) setLocalTables(data.tables);
    setHasChanges(false);
    setIsEditMode(false);
  };

  const handleCreateZone = async (payload: CreateDiningZonePayload) => {
    await createZoneMutation.mutateAsync(payload);
    showToast("success", t("floor_plan.messages.zone_created"));
  };

  const handleUpdateZone = async (
    uuid: string,
    payload: UpdateDiningZonePayload
  ) => {
    await updateZoneMutation.mutateAsync({ uuid, payload });
    showToast("success", t("floor_plan.messages.zone_updated"));
  };

  const handleDeleteZone = async (zone: DiningZone) => {
    try {
      await deleteZoneMutation.mutateAsync(zone.uuid);
      showToast("success", t("floor_plan.messages.zone_deleted"));
    } catch (err: any) {
      showToast(
        "error",
        err?.response?.data?.message ?? t("floor_plan.messages.zone_delete_error")
      );
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
        <p>{t("floor_plan.messages.error_load")}</p>
      </div>
    );
  }

  const zones = data?.zones ?? [];
  const tables = localTables;

  return (
    <div className="flex flex-col h-full">
      {/* Header + Toolbar */}
      <div className="flex items-center justify-between p-4 border-b border-slate-700 bg-slate-800/50">
        <div className="flex items-center gap-3">
          <Map size={28} className="text-orange-400" />
          <div>
            <h1 className="text-2xl font-bold text-white">
              {t("floor_plan.title")}
            </h1>
            <p className="text-sm text-slate-400">
              {t("floor_plan.subtitle")}
            </p>
          </div>
        </div>

        <div className="flex items-center gap-2">
          {isEditMode ? (
            <>
              <button
                onClick={handleCancel}
                className="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg transition-colors"
              >
                {t("floor_plan.actions.cancel")}
              </button>
              <button
                onClick={handleSave}
                disabled={!hasChanges || saveMutation.isPending}
                className="flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 disabled:bg-slate-700 disabled:text-slate-500 text-white rounded-lg transition-colors"
              >
                {saveMutation.isPending ? (
                  <Loader2 size={16} className="animate-spin" />
                ) : (
                  <Save size={16} />
                )}
                {t("floor_plan.actions.save")}
                {hasChanges && (
                  <span className="w-2 h-2 bg-orange-400 rounded-full animate-pulse" />
                )}
              </button>
            </>
          ) : (
            <button
              onClick={handleToggleEdit}
              className="flex items-center gap-2 px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg transition-colors"
            >
              <Edit3 size={16} />
              {t("floor_plan.actions.edit")}
            </button>
          )}
        </div>
      </div>

      {/* Contenido: panel lateral + canvas */}
      <div className="flex flex-1 overflow-hidden">
        {isEditMode && (
          <DiningZonePanel
            zones={zones}
            onCreateZone={() => setShowCreateZone(true)}
            onEditZone={(zone) => setEditingZone(zone)}
            onDeleteZone={handleDeleteZone}
          />
        )}

        <div className="flex-1 p-4 overflow-hidden">
          <FloorPlanCanvas
            zones={zones}
            tables={tables}
            isEditMode={isEditMode}
            onTablePositionChange={handleTablePositionChange}
            onTableClick={handleTableClick}
          />
        </div>
      </div>

      {/* Modales */}
      <CreateZoneModal
        isOpen={showCreateZone}
        onClose={() => setShowCreateZone(false)}
        onSubmit={handleCreateZone}
      />

      <EditZoneModal
        isOpen={!!editingZone}
        zone={editingZone}
        onClose={() => setEditingZone(null)}
        onSubmit={handleUpdateZone}
      />

      {/* Toast */}
      {toast && (
        <div
          className={`fixed bottom-6 right-6 flex items-center gap-3 px-4 py-3 rounded-lg shadow-2xl z-50 ${
            toast.type === "success"
              ? "bg-emerald-900/90 border border-emerald-700 text-emerald-100"
              : "bg-red-900/90 border border-red-700 text-red-100"
          }`}
        >
          {toast.type === "success" ? (
            <CheckCircle2 size={20} className="text-emerald-400" />
          ) : (
            <AlertTriangle size={20} className="text-red-400" />
          )}
          <span className="text-sm font-medium">{toast.message}</span>
        </div>
      )}
    </div>
  );
}
