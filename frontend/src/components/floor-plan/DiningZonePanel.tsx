import { useState } from "react";
import { useTranslation } from "react-i18next";
import { Plus, Pencil, Trash2, AlertCircle } from "lucide-react";
import type { DiningZone } from "@/types/floorPlan";
import { getTranslation } from "@/utils/floorPlanHelpers";

interface Props {
  zones: DiningZone[];
  onCreateZone: () => void;
  onEditZone: (zone: DiningZone) => void;
  onDeleteZone: (zone: DiningZone) => void;
}

export function DiningZonePanel({
  zones,
  onCreateZone,
  onEditZone,
  onDeleteZone,
}: Props) {
  const { t } = useTranslation();
  const [confirmingDelete, setConfirmingDelete] = useState<string | null>(null);

  const handleDeleteClick = (zone: DiningZone) => {
    if (confirmingDelete === zone.uuid) {
      onDeleteZone(zone);
      setConfirmingDelete(null);
    } else {
      setConfirmingDelete(zone.uuid);
      // Auto-cancelar después de 3 segundos
      setTimeout(() => setConfirmingDelete(null), 3000);
    }
  };

  return (
    <div className="w-80 bg-slate-800/50 border-r border-slate-700 p-4 flex flex-col h-full">
      <div className="flex items-center justify-between mb-4">
        <h2 className="text-lg font-bold text-white">
          {t("floor_plan.zone_panel.title")}
        </h2>
        <button
          onClick={onCreateZone}
          className="flex items-center gap-1.5 px-3 py-1.5 bg-orange-500 hover:bg-orange-600 text-white rounded-lg text-sm font-medium transition-colors"
        >
          <Plus size={14} />
          {t("floor_plan.actions.add_zone")}
        </button>
      </div>

      <div className="space-y-2 flex-1 overflow-y-auto">
        {zones.length === 0 ? (
          <div className="text-center py-8 text-slate-400 text-sm">
            {t("floor_plan.zone_panel.empty")}
          </div>
        ) : (
          zones.map((zone) => {
            const isConfirming = confirmingDelete === zone.uuid;
            const hasTables = (zone.tables_count ?? 0) > 0;

            return (
              <div
                key={zone.uuid}
                className="bg-slate-700/50 rounded-lg p-3 border border-slate-600"
              >
                <div className="flex items-start gap-2">
                  <div
                    className="w-4 h-4 rounded mt-1 flex-shrink-0"
                    style={{ backgroundColor: zone.color }}
                  />
                  <div className="flex-1 min-w-0">
                    <p className="font-medium text-white truncate">
                      {getTranslation(zone.name_translations, zone.code)}
                    </p>
                    <p className="text-xs text-slate-400 font-mono">
                      {zone.code} · {zone.tables_count ?? 0}{" "}
                      {t("floor_plan.zone_panel.tables")}
                    </p>
                  </div>
                </div>

                <div className="flex gap-1 mt-2">
                  <button
                    onClick={() => onEditZone(zone)}
                    className="flex-1 flex items-center justify-center gap-1 px-2 py-1 bg-slate-600 hover:bg-slate-500 text-white rounded text-xs transition-colors"
                  >
                    <Pencil size={12} />
                    {t("floor_plan.actions.edit")}
                  </button>
                  <button
                    onClick={() => handleDeleteClick(zone)}
                    disabled={hasTables}
                    className={`flex-1 flex items-center justify-center gap-1 px-2 py-1 rounded text-xs transition-colors ${
                      hasTables
                        ? "bg-slate-700 text-slate-500 cursor-not-allowed"
                        : isConfirming
                          ? "bg-red-600 hover:bg-red-700 text-white"
                          : "bg-slate-600 hover:bg-red-600 text-white"
                    }`}
                    title={
                      hasTables
                        ? t("floor_plan.zone_panel.cannot_delete_has_tables")
                        : ""
                    }
                  >
                    {isConfirming ? (
                      <>
                        <AlertCircle size={12} />
                        {t("floor_plan.actions.confirm")}
                      </>
                    ) : (
                      <>
                        <Trash2 size={12} />
                        {t("floor_plan.actions.delete")}
                      </>
                    )}
                  </button>
                </div>
              </div>
            );
          })
        )}
      </div>
    </div>
  );
}
