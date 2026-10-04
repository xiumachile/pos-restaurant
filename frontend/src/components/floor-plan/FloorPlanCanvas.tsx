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
import type { DiningZone, TablePosition } from "@/types/floorPlan";
import { DiningZoneBackground } from "./DiningZoneBackground";
import { DraggableTable } from "./DraggableTable";

interface Props {
  zones: DiningZone[];
  tables: TablePosition[];
  isEditMode: boolean;
  onTablePositionChange: (uuid: string, x: number, y: number) => void;
  onTableClick: (table: TablePosition) => void;
}

const GRID_SIZE = 20;

function snapToGrid(value: number): number {
  return Math.round(value / GRID_SIZE) * GRID_SIZE;
}

export function FloorPlanCanvas({
  zones,
  tables,
  isEditMode,
  onTablePositionChange,
  onTableClick,
}: Props) {
  const { t } = useTranslation();
  const [activeTable, setActiveTable] = useState<TablePosition | null>(null);

  // Sensor con activación de 8px para evitar drags accidentales al hacer click
  const sensors = useSensors(
    useSensor(PointerSensor, {
      activationConstraint: {
        distance: 8,
      },
    })
  );

  const handleDragStart = (event: any) => {
    const table = tables.find((t) => t.uuid === event.active.id);
    if (table) setActiveTable(table);
  };

  const handleDragEnd = (event: DragEndEvent) => {
    const { active, delta } = event;
    const table = tables.find((t) => t.uuid === active.id);

    if (table) {
      const newX = snapToGrid(table.position_x + delta.x);
      const newY = snapToGrid(table.position_y + delta.y);

      // Prevenir posiciones negativas
      const clampedX = Math.max(0, newX);
      const clampedY = Math.max(0, newY);

      onTablePositionChange(table.uuid, clampedX, clampedY);
    }

    setActiveTable(null);
  };

  const handleDragCancel = () => {
    setActiveTable(null);
  };

  if (tables.length === 0) {
    return (
      <div className="w-full h-full flex items-center justify-center text-slate-400">
        <div className="text-center">
          <p className="text-lg mb-2">{t("floor_plan.canvas.empty")}</p>
          <p className="text-sm opacity-70">
            {t("floor_plan.canvas.empty_hint")}
          </p>
        </div>
      </div>
    );
  }

  return (
    <DndContext
      sensors={sensors}
      modifiers={[restrictToWindowEdges]}
      onDragStart={handleDragStart}
      onDragEnd={handleDragEnd}
      onDragCancel={handleDragCancel}
    >
      <div className="relative w-full h-full min-h-[600px] bg-slate-900/30 rounded-lg overflow-auto">
        {/* Grid de fondo (puntos) */}
        <div
          className="absolute inset-0 pointer-events-none opacity-20"
          style={{
            backgroundImage:
              "radial-gradient(circle, #64748b 1px, transparent 1px)",
            backgroundSize: `${GRID_SIZE}px ${GRID_SIZE}px`,
          }}
        />

        {/* Fondos de zonas (detrás de las mesas) */}
        {zones.map((zone) => (
          <DiningZoneBackground key={zone.uuid} zone={zone} tables={tables} />
        ))}

        {/* Mesas (draggable en modo editar) */}
        {tables.map((table) => (
          <DraggableTable
            key={table.uuid}
            table={table}
            isEditMode={isEditMode}
            onClick={onTableClick}
          />
        ))}

        {/* Overlay durante drag (para feedback visual) */}
        <DragOverlay>
          {activeTable && (
            <DraggableTable
              table={activeTable}
              isEditMode={true}
              onClick={() => {}}
            />
          )}
        </DragOverlay>
      </div>
    </DndContext>
  );
}
