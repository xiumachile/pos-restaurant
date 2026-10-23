import { useState } from "react";
import {
  DndContext,
  DragEndEvent,
  PointerSensor,
  useSensor,
  useSensors,
  DragOverlay,
} from "@dnd-kit/core";
import { restrictToWindowEdges } from "@dnd-kit/modifiers";
import { useTranslation } from "react-i18next";
import type { DiningZone, TablePosition, DecorationPosition } from "@/types/floorPlan";
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
}

const GRID_SIZE = 40; // Cuadrícula más amplia y elegante

function snapToGrid(value: number): number {
  return Math.round(value / GRID_SIZE) * GRID_SIZE;
}

export function FloorPlanCanvas({
  zones, tables, decorations, isEditMode, selectedTableUuid,
  onTablePositionChange, onDecorationPositionChange, onTableClick, onTableSelect,
}: Props) {
  const { t } = useTranslation();
  const [activeTable, setActiveTable] = useState<TablePosition | null>(null);
  const [activeDecoration, setActiveDecoration] = useState<DecorationPosition | null>(null);

  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 8 } })
  );

  const handleDragStart = (event: any) => {
    const table = tables.find((t) => t.uuid === event.active.id);
    if (table) { setActiveTable(table); return; }
    const decoration = decorations.find((d) => d.uuid === event.active.id);
    if (decoration) setActiveDecoration(decoration);
  };

  const handleDragEnd = (event: DragEndEvent) => {
    const { active, delta } = event;
    const table = tables.find((t) => t.uuid === active.id);
    if (table) {
      onTablePositionChange(table.uuid, Math.max(0, snapToGrid(table.position_x + delta.x)), Math.max(0, snapToGrid(table.position_y + delta.y)));
      setActiveTable(null);
      return;
    }
    const decoration = decorations.find((d) => d.uuid === active.id);
    if (decoration) {
      onDecorationPositionChange(decoration.uuid, Math.max(0, snapToGrid(decoration.position_x + delta.x)), Math.max(0, snapToGrid(decoration.position_y + delta.y)));
      setActiveDecoration(null);
    }
  };

  const handleTableClick = (table: TablePosition) => {
    if (isEditMode) onTableSelect(table.uuid);
    else onTableClick(table);
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

  return (
    <DndContext sensors={sensors} modifiers={[restrictToWindowEdges]} onDragStart={handleDragStart} onDragEnd={handleDragEnd}>
      <div className="relative w-full h-full min-h-[600px] rounded-2xl overflow-hidden border border-slate-700/50 shadow-2xl">
        {/* Fondo Premium: Cuadrícula sutil + Viñeta */}
        <div
          className="absolute inset-0 pointer-events-none"
          style={{
            backgroundColor: "#0f172a", // slate-900
            backgroundImage: `linear-gradient(rgba(255, 255, 255, 0.03) 1px, transparent 1px), linear-gradient(90deg, rgba(255, 255, 255, 0.03) 1px, transparent 1px)`,
            backgroundSize: `${GRID_SIZE}px ${GRID_SIZE}px`,
          }}
        />
        <div className="absolute inset-0 pointer-events-none bg-[radial-gradient(circle_at_center,transparent_0%,rgba(0,0,0,0.5)_100%)]" />

        {zones.map((zone) => <DiningZoneBackground key={zone.uuid} zone={zone} tables={tables} />)}
        {decorations.map((decoration) => <DraggableDecoration key={decoration.uuid} decoration={decoration} isEditMode={isEditMode} />)}
        {tables.map((table) => (
          <DraggableTable key={table.uuid} table={table} isEditMode={isEditMode} isSelected={table.uuid === selectedTableUuid} onClick={handleTableClick} />
        ))}

        <DragOverlay>
          {activeTable && <DraggableTable table={activeTable} isEditMode={true} isSelected={false} onClick={() => {}} />}
          {activeDecoration && <DraggableDecoration decoration={activeDecoration} isEditMode={true} />}
        </DragOverlay>
      </div>
    </DndContext>
  );
}
