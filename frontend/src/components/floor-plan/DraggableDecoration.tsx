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
      case 'booth':
        return (
          <div className="absolute inset-0 flex items-center justify-center">
            <div className="w-[90%] h-[80%] bg-indigo-600 rounded-lg border-2 border-indigo-800 relative">
              <div className="absolute top-0 left-0 right-0 h-[30%] bg-indigo-500 rounded-t-lg" />
              <div className="absolute bottom-2 left-2 right-2 h-[40%] bg-indigo-400 rounded" />
            </div>
          </div>
        );
      case 'service_station':
        return (
          <div className="absolute inset-0 flex items-center justify-center">
            <div className="w-[80%] h-[80%] bg-pink-600 rounded-lg border-2 border-pink-800 flex flex-col items-center justify-center gap-1">
              <div className="w-[60%] h-[30%] bg-white/80 rounded" />
              <div className="w-[40%] h-[20%] bg-white/60 rounded" />
            </div>
          </div>
        );
      default:
        return (
          <div className="absolute inset-2 rounded bg-slate-500 border border-slate-600 flex items-center justify-center">
            <span className="text-[8px] text-white/70 font-bold">{config.label}</span>
          </div>
        );
    }
  };

  return (
    <div
      ref={setNodeRef}
      style={{
        width: config.width,
        height: config.height,
        left: decoration.position_x,
        top: decoration.position_y,
        transform: transform 
          ? `${CSS.Translate.toString(transform)} rotate(${decoration.rotation}deg)`
          : `rotate(${decoration.rotation}deg)`,
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
