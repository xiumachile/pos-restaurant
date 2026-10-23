import { useState } from "react";
import { DndContext, DragEndEvent, PointerSensor, useSensor, useSensors, DragOverlay } from "@dnd-kit/core";
import { restrictToParentElement } from "@dnd-kit/modifiers";
import { useTranslation } from "react-i18next";
import type { DiningZone, TablePosition, DecorationPosition, CanvasConfig } from "@/types/floorPlan";
import { DiningZoneBackground } from "./DiningZoneBackground";
import { DraggableTable } from "./DraggableTable";
import { DraggableDecoration } from "./DraggableDecoration";

interface Props {
  zones: DiningZone[];
  tables: TablePosition[];
  decorations: DecorationPosition[];
  isEditMode: boolean;
  selectedTableUuid: string | null;
  onTablePositionChange: (uuid: string, x: number, y: number) => void;
  onDecorationPositionChange: (uuid: string, x: number, y: number) => void;
  onTableClick: (table: TablePosition) => void;
  onTableSelect: (uuid: string | null) => void;
  canvasConfig: CanvasConfig; // ✅ Agregado
}

function snapToGrid(value: number, gridSize: number): number {
  return Math.round(value / gridSize) * gridSize;
}

export function FloorPlanCanvas({
  zones,
  tables,
  decorations,
  isEditMode,
  selectedTableUuid,
  onTablePositionChange,
  onDecorationPositionChange,
  onTableClick,
  onTableSelect,
  canvasConfig,
}: Props) {
  const { t } = useTranslation();
  const [activeId, setActiveId] = useState<string | null>(null);
  const [activeType, setActiveType] = useState<"table" | "decoration" | null>(null);

  const gridSize = canvasConfig.grid_size ?? 40;
  const canvasWidth = canvasConfig.width ?? 2000;
  const canvasHeight = canvasConfig.height ?? 1500;

  const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 8 } }));

  const handleDragStart = (event: any) => {
    setActiveId(event.active.id);
    if (tables.find((t) => t.uuid === event.active.id)) setActiveType("table");
    else if (decorations.find((d) => d.uuid === event.active.id)) setActiveType("decoration");
  };

  const handleDragEnd = (event: DragEndEvent) => {
    const { active, delta } = event;
    const table = tables.find((t) => t.uuid === active.id);
    if (table) {
      onTablePositionChange(
        table.uuid,
        Math.max(0, snapToGrid(table.position_x + delta.x, gridSize)),
        Math.max(0, snapToGrid(table.position_y + delta.y, gridSize))
      );
    } else {
      const decoration = decorations.find((d) => d.uuid === active.id);
      if (decoration) {
        onDecorationPositionChange(
          decoration.uuid,
          Math.max(0, snapToGrid(decoration.position_x + delta.x, gridSize)),
          Math.max(0, snapToGrid(decoration.position_y + delta.y, gridSize))
        );
      }
    }
    setActiveId(null);
    setActiveType(null);
  };

  const handleTableClick = (table: TablePosition) => {
    isEditMode ? onTableSelect(table.uuid) : onTableClick(table);
  };

  if (tables.length === 0 && decorations.length === 0) {
    return (
      <div className="w-full h-full flex items-center justify-center text-slate-500">
        <div className="text-center p-8 border-2 border-dashed border-slate-700 rounded-2xl bg-slate-800/30">
          <p className="text-lg font-medium mb-2">{t("floor_plan.canvas.empty", "El plano está vacío")}</p>
          <p className="text-sm opacity-70">{t("floor_plan.canvas.empty_hint", "Agrega mesas o decoraciones en modo edición")}</p>
        </div>
      </div>
    );
  }

  const activeTable = activeType === "table" ? tables.find((t) => t.uuid === activeId) : null;
  const activeDecoration = activeType === "decoration" ? decorations.find((d) => d.uuid === activeId) : null;

  return (
    <DndContext sensors={sensors} modifiers={[restrictToParentElement]} onDragStart={handleDragStart} onDragEnd={handleDragEnd}>
      {/* Contenedor con scroll */}
      <div className="w-full h-full overflow-auto bg-slate-950 rounded-2xl border border-slate-700/50 shadow-2xl p-8 flex items-center justify-center">

        {/* Lienzo de tamaño fijo */}
        <div
          className="relative shadow-2xl"
          style={{
            width: canvasWidth,
            height: canvasHeight,
            backgroundImage: `linear-gradient(rgba(255, 255, 255, 0.03) 1px, transparent 1px), linear-gradient(90deg, rgba(255, 255, 255, 0.03) 1px, transparent 1px)`,
            backgroundSize: `${gridSize}px ${gridSize}px`,
          }}
        >
          {zones.map((zone) => <DiningZoneBackground key={zone.uuid} zone={zone} tables={tables} />)}
          {decorations.map((decoration) => <DraggableDecoration key={decoration.uuid} decoration={decoration} isEditMode={isEditMode} />)}
          {tables.map((table) => <DraggableTable key={table.uuid} table={table} isEditMode={isEditMode} isSelected={table.uuid === selectedTableUuid} onClick={handleTableClick} />)}

          <DragOverlay dropAnimation={null}>
            {activeTable && <DraggableTable table={activeTable} isEditMode={true} isSelected={false} onClick={() => {}} />}
            {activeDecoration && <DraggableDecoration decoration={activeDecoration} isEditMode={true} />}
          </DragOverlay>
        </div>
      </div>
    </DndContext>
  );
}
