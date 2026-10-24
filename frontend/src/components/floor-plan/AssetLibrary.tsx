import { useTranslation } from "react-i18next";

import { Square, Circle, RectangleHorizontal, Armchair, Sofa, Flower, Columns, Server } from "lucide-react";

import type { FurnitureType } from "@/types/floorPlan";

export const FURNITURE_CATALOG = [
  { id: "table_square_2" as FurnitureType, label: "Mesa 2p", icon: Square, width: 60, height: 60, capacity: 2, color: "#3b82f6" },
  { id: "table_square_4" as FurnitureType, label: "Mesa 4p", icon: Square, width: 80, height: 80, capacity: 4, color: "#3b82f6" },
  { id: "table_round_4" as FurnitureType, label: "Redonda 4p", icon: Circle, width: 80, height: 80, capacity: 4, color: "#10b981" },
  { id: "table_round_6" as FurnitureType, label: "Redonda 6p", icon: Circle, width: 100, height: 100, capacity: 6, color: "#10b981" },
  { id: "table_rectangle_6" as FurnitureType, label: "Rectangular 6p", icon: RectangleHorizontal, width: 120, height: 80, capacity: 6, color: "#8b5cf6" },
  { id: "table_rectangle_8" as FurnitureType, label: "Rectangular 8p", icon: RectangleHorizontal, width: 140, height: 80, capacity: 8, color: "#8b5cf6" },
  { id: "bar_counter" as FurnitureType, label: "Barra", icon: RectangleHorizontal, width: 160, height: 40, capacity: 0, color: "#d97706" },
  { id: "booth" as FurnitureType, label: "Booth / Sofá", icon: Sofa, width: 120, height: 60, capacity: 4, color: "#6366f1" },
  { id: "wall" as FurnitureType, label: "Muro", icon: RectangleHorizontal, width: 120, height: 20, capacity: 0, color: "#475569" },
  { id: "pillar" as FurnitureType, label: "Pilar", icon: Columns, width: 40, height: 40, capacity: 0, color: "#64748b" },
  { id: "plant" as FurnitureType, label: "Planta", icon: Flower, width: 50, height: 50, capacity: 0, color: "#22c55e" },
  { id: "service_station" as FurnitureType, label: "Estación", icon: Server, width: 60, height: 60, capacity: 0, color: "#ec4899" },
] as const;

interface Props {
  onAddFurniture: (type: FurnitureType) => void;
  isEditMode: boolean;
}

export function AssetLibrary({ onAddFurniture, isEditMode }: Props) {
  const { t } = useTranslation();

  if (!isEditMode) return null;

  const tables = FURNITURE_CATALOG.filter(f => f.capacity > 0);
  const decorations = FURNITURE_CATALOG.filter(f => f.capacity === 0);

  return (
    <div className="w-64 bg-slate-800 border-r border-slate-700 overflow-y-auto">
      <div className="p-4">
        <h3 className="text-sm font-bold text-white mb-3">
          {t("floor_plan.asset_library.title", "Biblioteca de Activos")}
        </h3>

        {/* Mesas */}
        <div className="mb-4">
          <h4 className="text-xs font-semibold text-slate-400 uppercase mb-2">
            {t("floor_plan.asset_library.tables", "Mesas")}
          </h4>
          <div className="grid grid-cols-2 gap-2">
            {tables.map((item) => {
              const Icon = item.icon;
              return (
                <button
                  key={item.id}
                  onClick={() => onAddFurniture(item.id)}
                  className="flex flex-col items-center gap-1 p-2 bg-slate-700/50 hover:bg-slate-700 border border-slate-600 hover:border-slate-500 rounded-lg transition-all group"
                  title={item.label}
                >
                  <Icon size={20} style={{ color: item.color }} />
                  <span className="text-[10px] text-slate-300 text-center leading-tight">
                    {item.label}
                  </span>
                </button>
              );
            })}
          </div>
        </div>

        {/* Decoraciones */}
        <div>
          <h4 className="text-xs font-semibold text-slate-400 uppercase mb-2">
            {t("floor_plan.asset_library.decorations", "Decoraciones")}
          </h4>
          <div className="grid grid-cols-2 gap-2">
            {decorations.map((item) => {
              const Icon = item.icon;
              return (
                <button
                  key={item.id}
                  onClick={() => onAddFurniture(item.id)}
                  className="flex flex-col items-center gap-1 p-2 bg-slate-700/50 hover:bg-slate-700 border border-slate-600 hover:border-slate-500 rounded-lg transition-all group"
                  title={item.label}
                >
                  <Icon size={20} style={{ color: item.color }} />
                  <span className="text-[10px] text-slate-300 text-center leading-tight">
                    {item.label}
                  </span>
                </button>
              );
            })}
          </div>
        </div>
      </div>
    </div>
  );
}
