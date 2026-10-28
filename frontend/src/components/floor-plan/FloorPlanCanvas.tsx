// src/components/floor-plan/FloorPlanCanvas.tsx
import { useState, useMemo, useCallback } from "react";
import { DndContext, DragEndEvent, PointerSensor, useSensor, useSensors, DragOverlay } from "@dnd-kit/core";
import { restrictToParentElement } from "@dnd-kit/modifiers";
import { useTranslation } from "react-i18next";
import type { FloorPlanObject, FloorPlanZone, CanvasConfig, ObjectType } from "@/types/floorPlan";
import { DiningZoneBackground } from "./DiningZoneBackground";
import { DraggableObject } from "./DraggableObject";

interface Props {
  zones: FloorPlanZone[];
  objects: FloorPlanObject[];
  isEditMode: boolean;
  selectedObjectUuid: string | null;
  onObjectPositionChange: (uuid: string, x: number, y: number) => void;
  onAddObjectAt: (objectType: ObjectType, x: number, y: number) => void;
  onObjectClick: (object: FloorPlanObject) => void;
  onObjectSelect: (uuid: string | null) => void;
  canvasConfig: CanvasConfig;
}

function snapToGrid(value: number, gridSize: number): number {
  return Math.round(value / gridSize) * gridSize;
}

function distributeObjects(objects: FloorPlanObject[], canvasWidth: number): FloorPlanObject[] {
  const gridSize = 150;
  const startX = 100;
  const startY = 100;
  const maxCols = Math.max(1, Math.floor((canvasWidth - 200) / gridSize));
  let col = 0;
  let row = 0;

  return objects.map((obj) => {
    if ((obj.position_x === 0 && obj.position_y === 0) || obj.position_x < 0 || obj.position_y < 0) {
      const distributed = {
        ...obj,
        position_x: startX + col * gridSize,
        position_y: startY + row * gridSize,
      };
      col++;
      if (col >= maxCols) { col = 0; row++; }
      return distributed;
    }
    return obj;
  });
}

export function FloorPlanCanvas({
  zones, objects, isEditMode, selectedObjectUuid,
  onObjectPositionChange, onAddObjectAt, onObjectClick, onObjectSelect, canvasConfig,
}: Props) {
  const { t } = useTranslation();
  const [activeId, setActiveId] = useState<string | null>(null);
  const [activeType, setActiveType] = useState<"palette" | "object" | null>(null);
  const [activePaletteType, setActivePaletteType] = useState<ObjectType | null>(null);

  const gridSize = canvasConfig.grid_size ?? 40;
  const canvasWidth = canvasConfig.width ?? 2400;
  const canvasHeight = canvasConfig.height ?? 1800;

  const distributedObjects = useMemo(() => distributeObjects(objects, canvasWidth), [objects, canvasWidth]);

  const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 8 } }));
  const modifiers = useMemo(() => [restrictToParentElement], []);

  const handleDragStart = useCallback((event: any) => {
    setActiveId(event.active.id);
    if (event.active.data.current?.fromLibrary) {
      setActiveType("palette");
      setActivePaletteType(event.active.data.current?.objectType || null);
    } else if (distributedObjects.find((obj) => obj.uuid === event.active.id)) {
      setActiveType("object");
      setActivePaletteType(null);
    }
  }, [distributedObjects]);

  const handleDragEnd = useCallback((event: DragEndEvent) => {
    const { active, delta, over } = event;
    
    if (activeType === "palette" && activePaletteType && over) {
      const dropX = snapToGrid((active.rect.current.translated?.left || 100) - (over.rect.left || 0), gridSize);
      const dropY = snapToGrid((active.rect.current.translated?.top || 100) - (over.rect.top || 0), gridSize);
      onAddObjectAt(activePaletteType, Math.max(0, dropX), Math.max(0, dropY));
    } else if (activeType === "object") {
      const obj = distributedObjects.find((o) => o.uuid === active.id);
      if (obj) {
        const objWidth = obj.width || 80;
        const objHeight = obj.height || 80;
        const newX = Math.max(0, Math.min(canvasWidth - objWidth, snapToGrid(obj.position_x + delta.x, gridSize)));
        const newY = Math.max(0, Math.min(canvasHeight - objHeight, snapToGrid(obj.position_y + delta.y, gridSize)));
        onObjectPositionChange(obj.uuid, newX, newY);
      }
    }
    setActiveId(null);
    setActiveType(null);
    setActivePaletteType(null);
  }, [activeType, activePaletteType, distributedObjects, gridSize, canvasWidth, canvasHeight, onAddObjectAt, onObjectPositionChange]);

  const activeObject = activeType === "object" ? distributedObjects.find((o) => o.uuid === activeId) || null : null;

  return (
    <DndContext 
      sensors={sensors} 
      modifiers={modifiers} 
      onDragStart={handleDragStart} 
      onDragEnd={handleDragEnd}
    >
      <div className="floor-plan-canvas-container w-full h-full overflow-auto bg-slate-950 rounded-2xl border border-slate-700/50 shadow-2xl">
        <div
          className="relative shadow-2xl"
          style={{
            width: canvasWidth,
            height: canvasHeight,
            backgroundImage: `repeating-linear-gradient(90deg, #d4a373 0px, #d4a373 38px, #a97142 38px, #a97142 40px)`,
            backgroundSize: `${gridSize}px ${gridSize}px`,
          }}
        >
          {zones.map((zone) => (
            <DiningZoneBackground key={zone.uuid} zone={zone} />
          ))}
          
          {distributedObjects.map((obj) => (
            <DraggableObject
              key={obj.uuid}
              object={obj}
              isEditMode={isEditMode}
              isSelected={obj.uuid === selectedObjectUuid}
              onClick={() => (isEditMode ? onObjectSelect(obj.uuid) : onObjectClick(obj))}
            />
          ))}

          <DragOverlay dropAnimation={null}>
            {activeObject && (
              <div className="opacity-80 scale-105 drop-shadow-2xl">
                <DraggableObject object={activeObject} isEditMode={true} isSelected={false} onClick={() => {}} />
              </div>
            )}
          </DragOverlay>
        </div>
      </div>
    </DndContext>
  );
}
