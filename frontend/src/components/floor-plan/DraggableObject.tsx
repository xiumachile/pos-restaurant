// src/components/floor-plan/DraggableObject.tsx
import { useDraggable } from "@dnd-kit/core";
import { CSS } from "@dnd-kit/utilities";
import { GripVertical, DollarSign } from "lucide-react";
import type { FloorPlanObject } from "@/types/floorPlan";
import { ObjectRenderer } from "./renderers/ObjectRenderer";

interface Props {
  object: FloorPlanObject;
  isEditMode: boolean;
  isSelected: boolean;
  onClick: () => void;
}

const STATUS_COLORS: Record<string, string> = {
  available: "#10b981",
  occupied: "#ef4444",
  billing: "#f59e0b",
  maintenance: "#64748b",
};

export function DraggableObject({ object, isEditMode, isSelected, onClick }: Props) {
  const { attributes, listeners, setNodeRef, transform, isDragging } = useDraggable({
    id: object.uuid,
    disabled: !isEditMode,
  });

  const width = object.width || 80;
  const height = object.height || 80;

  let statusColor = STATUS_COLORS.available;
  if (object.object_type.startsWith("table") && object.operational_data) {
    statusColor = STATUS_COLORS[object.operational_data.status] || STATUS_COLORS.available;
  }

  return (
    <div
      ref={setNodeRef}
      style={{
        position: "absolute",
        left: object.position_x || 0,
        top: object.position_y || 0,
        width,
        height,
        transform: transform
          ? `${CSS.Translate.toString(transform)} rotate(${object.rotation || 0}deg)`
          : `rotate(${object.rotation || 0}deg)`,
        transformOrigin: "center center",
        zIndex: object.z_index || (isDragging ? 50 : 10),
      }}
      className={`select-none transition-all duration-100 ${
        isEditMode ? "cursor-grab active:cursor-grabbing" : "cursor-pointer"
      } ${isDragging ? "opacity-70 scale-105" : ""} ${
        isSelected && isEditMode ? "ring-2 ring-orange-500 ring-offset-2 ring-offset-slate-900" : ""
      }`}
      onClick={onClick}
      {...(isEditMode ? listeners : {})}
      {...(isEditMode ? attributes : {})}
    >
      <div className="absolute inset-0 pointer-events-none overflow-visible">
        <ObjectRenderer object={object} statusColor={statusColor} />
      </div>

      {object.object_type.startsWith("table") && object.operational_data && (
        <div
          className="absolute inset-0 pointer-events-none flex items-center justify-center"
          style={{ transform: `rotate(${-object.rotation || 0}deg)` }}
        >
          <div className="bg-black/70 px-2 py-1 rounded-full border border-white/30 backdrop-blur-sm">
            <span className="text-white font-black text-xs drop-shadow-md select-none">
              {object.operational_data.table_number}
            </span>
          </div>
        </div>
      )}

      {object.operational_data?.has_active_order && (
        <div className="absolute -top-2 -right-2 bg-emerald-500 text-white text-[9px] px-1.5 py-0.5 rounded-full border-2 border-white shadow-lg pointer-events-none">
          <DollarSign size={8} className="inline" />
        </div>
      )}

      {isEditMode && (
        <div className="absolute -top-3 -right-3 bg-slate-800 border-2 border-slate-600 rounded-full p-1 shadow-lg z-50 pointer-events-auto">
          <GripVertical size={12} className="text-slate-300" />
        </div>
      )}
    </div>
  );
}
