import { useTranslation } from "react-i18next";
import { RotateCw, Trash2, Copy } from "lucide-react";
import type { TablePosition, TableRotation } from "@/types/floorPlan";

interface Props {
  selectedTable: TablePosition | null;
  onUpdateTable: (uuid: string, updates: Partial<TablePosition>) => void;
  onDeleteTable: (uuid: string) => void;
  onDuplicateTable: (table: TablePosition) => void;
  isEditMode: boolean;
}

export function PropertiesPanel({
  selectedTable,
  onUpdateTable,
  onDeleteTable,
  onDuplicateTable,
  isEditMode,
}: Props) {
  const { t } = useTranslation();

  if (!isEditMode || !selectedTable) {
    return (
      <div className="w-64 bg-slate-800 border-l border-slate-700 p-4">
        <p className="text-sm text-slate-400 text-center">
          {t("floor_plan.properties.no_selection", "Selecciona un elemento para editar sus propiedades")}
        </p>
      </div>
    );
  }

  const handleRotationChange = (rotation: TableRotation) => {
    onUpdateTable(selectedTable.uuid, { rotation });
  };

  const handleTableNumberChange = (table_number: string) => {
    onUpdateTable(selectedTable.uuid, { table_number });
  };

  const handleCapacityChange = (capacity: number) => {
    onUpdateTable(selectedTable.uuid, { capacity });
  };

  return (
    <div className="w-64 bg-slate-800 border-l border-slate-700 overflow-y-auto">
      <div className="p-4">
        <h3 className="text-sm font-bold text-white mb-4">
          {t("floor_plan.properties.title", "Propiedades")}
        </h3>

        {/* Número de Mesa */}
        <div className="mb-4">
          <label className="text-xs font-semibold text-slate-400 uppercase mb-1 block">
            {t("floor_plan.properties.table_number", "Número de Mesa")}
          </label>
          <input
            type="text"
            value={selectedTable.table_number}
            onChange={(e) => handleTableNumberChange(e.target.value)}
            className="w-full px-3 py-2 bg-slate-700 border border-slate-600 rounded-lg text-white text-sm focus:outline-none focus:ring-2 focus:ring-orange-500"
          />
        </div>

        {/* Capacidad */}
        <div className="mb-4">
          <label className="text-xs font-semibold text-slate-400 uppercase mb-1 block">
            {t("floor_plan.properties.capacity", "Capacidad (personas)")}
          </label>
          <input
            type="number"
            value={selectedTable.capacity}
            onChange={(e) => handleCapacityChange(parseInt(e.target.value) || 0)}
            min="0"
            className="w-full px-3 py-2 bg-slate-700 border border-slate-600 rounded-lg text-white text-sm focus:outline-none focus:ring-2 focus:ring-orange-500"
          />
        </div>

        {/* Rotación */}
        <div className="mb-4">
          <label className="text-xs font-semibold text-slate-400 uppercase mb-1 block">
            {t("floor_plan.properties.rotation", "Rotación")}
          </label>
          <div className="grid grid-cols-4 gap-1">
            {[0, 90, 180, 270].map((rot) => (
              <button
                key={rot}
                onClick={() => handleRotationChange(rot as TableRotation)}
                className={`px-2 py-1 rounded text-xs font-medium transition-colors ${
                  selectedTable.rotation === rot
                    ? "bg-orange-500 text-white"
                    : "bg-slate-700 text-slate-300 hover:bg-slate-600"
                }`}
              >
                {rot}°
              </button>
            ))}
          </div>
        </div>

        {/* Posición */}
        <div className="mb-4">
          <label className="text-xs font-semibold text-slate-400 uppercase mb-1 block">
            {t("floor_plan.properties.position", "Posición")}
          </label>
          <div className="grid grid-cols-2 gap-2">
            <div>
              <span className="text-xs text-slate-500">X</span>
              <input
                type="number"
                value={selectedTable.position_x}
                readOnly
                className="w-full px-2 py-1 bg-slate-700 border border-slate-600 rounded text-white text-xs"
              />
            </div>
            <div>
              <span className="text-xs text-slate-500">Y</span>
              <input
                type="number"
                value={selectedTable.position_y}
                readOnly
                className="w-full px-2 py-1 bg-slate-700 border border-slate-600 rounded text-white text-xs"
              />
            </div>
          </div>
        </div>

        {/* Acciones */}
        <div className="border-t border-slate-700 pt-4 mt-4">
          <div className="grid grid-cols-2 gap-2">
            <button
              onClick={() => onDuplicateTable(selectedTable)}
              className="flex items-center justify-center gap-1 px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs transition-colors"
            >
              <Copy size={12} />
              {t("floor_plan.properties.duplicate", "Duplicar")}
            </button>
            <button
              onClick={() => onDeleteTable(selectedTable.uuid)}
              className="flex items-center justify-center gap-1 px-3 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-xs transition-colors"
            >
              <Trash2 size={12} />
              {t("floor_plan.properties.delete", "Eliminar")}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
