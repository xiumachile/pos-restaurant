import { useDraggable } from "@dnd-kit/core";
import { CSS } from "@dnd-kit/utilities";
import { GripVertical } from "lucide-react";
import type { DecorationPosition } from "@/types/floorPlan";
import { FURNITURE_CATALOG } from "./AssetLibrary";
import { cn } from "@/lib/utils";

interface Props { decoration: DecorationPosition; isEditMode: boolean; }

export function DraggableDecoration({ decoration, isEditMode }: Props) {
  const { attributes, listeners, setNodeRef, transform, isDragging } = useDraggable({ id: decoration.uuid, disabled: !isEditMode });
  const config = FURNITURE_CATALOG.find((f) => f.id === decoration.type);
  if (!config) return null;
  const Icon = config.icon;

  const style = transform ? { transform: CSS.Translate.toString(transform), left: decoration.position_x, top: decoration.position_y } : { left: decoration.position_x, top: decoration.position_y };

  return (
    <div
      ref={setNodeRef}
      style={{ ...style, width: config.width, height: config.height, backgroundColor: config.color, borderColor: `${config.color}80`, transform: `translate3d(${transform?.x ?? 0}px, ${transform?.y ?? 0}px, 0) rotate(${decoration.rotation}deg)` }}
      className={cn("absolute flex items-center justify-center shadow-lg border-2 transition-all", {
        "rounded-sm": config.id === "wall" || config.id === "bar_counter",
        "rounded-full": config.id === "plant" || config.id.includes("round"),
        "rounded-lg": !config.id.includes("round") && config.id !== "wall" && config.id !== "bar_counter",
        "cursor-grab active:cursor-grabbing": isEditMode,
        "opacity-0": isDragging, // ✅ ESTO EVITA EL DUPLICADO VISUAL
        "z-10": !isDragging,
      })}
      {...(isEditMode ? listeners : {})} {...(isEditMode ? attributes : {})}
    >
      {isEditMode && <div className="absolute -top-2 -right-2 bg-slate-900 rounded-full p-1 shadow-md"><GripVertical size={12} className="text-white" /></div>}
      <Icon size={config.id === "wall" ? 16 : 28} className="text-white/90" />
    </div>
  );
}
