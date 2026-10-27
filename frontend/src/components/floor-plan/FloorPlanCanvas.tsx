import { useState, useMemo } from "react";
import { DndContext, DragEndEvent, DragOverEvent, PointerSensor, useSensor, useSensors, DragOverlay } from "@dnd-kit/core";
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

// ✅ DISTRIBUIR mesas en (0,0) automáticamente en una cuadrícula
function distributeObjects(objects: FloorPlanObject[], canvasWidth: number, canvasHeight: number): FloorPlanObject[] {
  const gridSize = 150;
  const startX = 100;
  const startY = 100;
  const maxCols = Math.floor((canvasWidth - 200) / gridSize);
  let col = 0;
  let row = 0;

  return objects.map((obj, index) => {
    // Si está en (0,0) o tiene posición inválida, distribuirlo
    if ((obj.position_x === 0 && obj.position_y === 0) || 
        obj.position_x < 0 || obj.position_y < 0 ||
        obj.position_x > canvasWidth || obj.position_y > canvasHeight) {
      const distributed = {
        ...obj,
        position_x: startX + col * gridSize,
        position_y: startY + row * gridSize,
      };
      col++;
      if (col >= maxCols) {
        col = 0;
        row++;
      }
      return distributed;
    }
    return obj;
  });
}

export function FloorPlanCanvas({
  zones,
  objects,
  isEditMode,
  selectedObjectUuid,
  onObjectPositionChange,
  onAddObjectAt,
  onObjectClick,
  onObjectSelect,
  canvasConfig,
}: Props) {
  const { t } = useTranslation();
  const [activeId, setActiveId] = useState<string | null>(null);
  const [activeType, setActiveType] = useState<"palette" | "object" | null>(null);
  const [activePaletteType, setActivePaletteType] = useState<ObjectType | null>(null);

  const gridSize = canvasConfig.grid_size ?? 40;
  const canvasWidth = canvasConfig.width ?? 2400;
  const canvasHeight = canvasConfig.height ?? 1800;

  // ✅ DISTRIBUIR objetos automáticamente
  const distributedObjects = useMemo(
    () => distributeObjects(objects, canvasWidth, canvasHeight),
    [objects, canvasWidth, canvasHeight]
  );

  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 8 } })
  );

  const handleDragStart = (event: any) => {
    setActiveId(event.active.id);
    if (event.active.data.current?.fromLibrary) {
      setActiveType("palette");
      setActivePaletteType(event.active.data.current?.objectType || null);
    } else if (distributedObjects.find((obj) => obj.uuid === event.active.id)) {
      setActiveType("object");
      setActivePaletteType(null);
    }
  };

  const handleDragOver = (event: DragOverEvent) => {};

  const handleDragEnd = (event: DragEndEvent) => {
    const { active, delta, over } = event;
    
    if (activeType === "palette" && activePaletteType && over) {
      const dropX = snapToGrid((active.rect.current.translated?.left || 100) - (over.rect.left || 0), gridSize);
      const dropY = snapToGrid((active.rect.current.translated?.top || 100) - (over.rect.top || 0), gridSize);
      onAddObjectAt(activePaletteType, Math.max(0, dropX), Math.max(0, dropY));
    } else if (activeType === "object") {
      const obj = distributedObjects.find((o) => o.uuid === active.id);
      if (obj) {
        const objWidth = obj.width || (obj.object_type === 'table' ? 80 : 60);
        const objHeight = obj.height || (obj.object_type === 'table' ? 80 : 60);
        const newX = Math.max(0, Math.min(canvasWidth - objWidth, snapToGrid(obj.position_x + delta.x, gridSize)));
        const newY = Math.max(0, Math.min(canvasHeight - objHeight, snapToGrid(obj.position_y + delta.y, gridSize)));
        onObjectPositionChange(obj.uuid, newX, newY);
      }
    }
    
    setActiveId(null);
    setActiveType(null);
    setActivePaletteType(null);
  };

  const handleObjectClick = (obj: FloorPlanObject) => {
    isEditMode ? onObjectSelect(obj.uuid) : onObjectClick(obj);
  };

  const activeObject = activeType === "object" ? distributedObjects.find((o) => o.uuid === activeId) || null : null;

  return (
    <DndContext 
      sensors={sensors} 
      modifiers={[restrictToParentElement]} 
      onDragStart={handleDragStart} 
      onDragOver={handleDragOver}
      onDragEnd={handleDragEnd}
    >
      <div className="floor-plan-canvas-container w-full h-full overflow-auto bg-slate-950 rounded-2xl border border-slate-700/50 shadow-2xl">
        <div
          className="relative shadow-2xl"
          style={{
            width: canvasWidth,
            height: canvasHeight,
            backgroundImage: `linear-gradient(rgba(255, 255, 255, 0.03) 1px, transparent 1px), linear-gradient(90deg, rgba(255, 255, 255, 0.03) 1px, transparent 1px)`,
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
              onClick={() => handleObjectClick(obj)}
            />
          ))}

          <DragOverlay dropAnimation={null}>
            {activeObject && (
              <DraggableObject
                object={activeObject}
                isEditMode={true}
                isSelected={false}
                onClick={() => {}}
              />
            )}
            {activeType === "palette" && activePaletteType && (
              <div className="opacity-80 scale-110 drop-shadow-2xl pointer-events-none">
                <div className="w-16 h-16 rounded-lg border-2 border-dashed border-orange-400 bg-slate-800/80 flex items-center justify-center">
                  <span className="text-[10px] text-orange-400 font-bold">Nuevo</span>
                </div>
              </div>
            )}
          </DragOverlay>
        </div>
      </div>
    </DndContext>
  );
}
