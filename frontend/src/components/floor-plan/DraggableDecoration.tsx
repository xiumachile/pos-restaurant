import { useDraggable } from "@dnd-kit/core";
import { CSS } from "@dnd-kit/utilities";
import { GripVertical } from "lucide-react";
import type { DecorationPosition } from "@/types/floorPlan";
import { FURNITURE_CATALOG } from "./AssetLibrary";
import { cn } from "@/lib/utils";

interface Props {
  decoration: DecorationPosition;
  isEditMode: boolean;
}

export function DraggableDecoration({ decoration, isEditMode }: Props) {
  const { attributes, listeners, setNodeRef, transform, isDragging } = useDraggable({
    id: decoration.uuid,
    disabled: !isEditMode,
  });

  const config = FURNITURE_CATALOG.find((f) => f.id === decoration.type);
  if (!config) return null;

  const style = transform 
    ? { transform: CSS.Translate.toString(transform), left: decoration.position_x, top: decoration.position_y }
    : { left: decoration.position_x, top: decoration.position_y };

  // Renderizado visual específico por tipo
  const renderVisual = () => {
    switch (config.id) {
      case 'plant':
        return (
          <div className="absolute inset-0 flex items-center justify-center">
            <div className="w-[90%] h-[90%] rounded-full bg-green-600 opacity-80" />
            <div className="absolute w-[70%] h-[70%] rounded-full bg-green-500" />
            <div className="absolute w-[40%] h-[40%] rounded-full bg-green-400 top-2 left-2" />
          </div>
        );
      case 'pillar':
        return (
          <div className="absolute inset-2 rounded bg-slate-400 border-2 border-slate-600 shadow-inner flex items-center justify-center">
            <div className="w-2 h-2 bg-slate-700 rounded-full" />
          </div>
        );
      case 'wall':
        return (
          <div className="absolute inset-0 bg-slate-500 border-y-2 border-slate-700 flex items-center justify-center overflow-hidden">
            <div className="w-full h-[2px] bg-slate-400 opacity-50" />
          </div>
        );
      case 'bar_counter':
        return (
          <div className="absolute inset-0 bg-amber-700 rounded border-2 border-amber-900 flex items-center justify-center">
            <div className="w-[90%] h-[30%] bg-amber-600 rounded-sm mt-1" />
          </div>
        );
      default:
        return <div className="absolute inset-2 rounded bg-slate-500 opacity-50" />;
    }
  };

  return (
    <div
      ref={setNodeRef}
      style={{
        ...style,
        width: config.width,
        height: config.height,
        transform: `translate3d(${transform?.x ?? 0}px, ${transform?.y ?? 0}px, 0) rotate(${decoration.rotation}deg)`,
      }}
      className={cn(
        "absolute select-none transition-all",
        {
          "cursor-grab active:cursor-grabbing": isEditMode,
          "opacity-70 scale-105 z-50": isDragging,
          "z-10": !isDragging,
        }
      )}
      {...(isEditMode ? listeners : {})}
      {...(isEditMode ? attributes : {})}
    >
      {/* Capa visual con pointer-events-none para no bloquear el drag */}
      <div className="absolute inset-0 pointer-events-none">
        {renderVisual()}
      </div>

      {isEditMode && (
        <div className="absolute -top-2 -right-2 bg-slate-900 border border-slate-600 rounded-full p-1 shadow-md z-20 pointer-events-auto">
          <GripVertical size={12} className="text-slate-300" />
        </div>
      )}
    </div>
  );
}
