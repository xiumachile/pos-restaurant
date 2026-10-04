import { useDraggable } from "@dnd-kit/core";
import { CSS } from "@dnd-kit/utilities";
import { useTranslation } from "react-i18next";
import { GripVertical, Users } from "lucide-react";
import type { TablePosition } from "@/types/floorPlan";
import { cn } from "@/lib/utils";

interface Props {
  table: TablePosition;
  isEditMode: boolean;
  onClick: (table: TablePosition) => void;
  onDragEnd?: (table: TablePosition, x: number, y: number) => void;
}

const STATUS_COLORS: Record<string, string> = {
  available: "#10b981", // emerald-500
  occupied: "#ef4444", // red-500
  billing: "#f59e0b", // amber-500
  maintenance: "#64748b", // slate-500
};

const GRID_SIZE = 20;

function snapToGrid(value: number): number {
  return Math.round(value / GRID_SIZE) * GRID_SIZE;
}

export function DraggableTable({ table, isEditMode, onClick }: Props) {
  const { t } = useTranslation();

  const { attributes, listeners, setNodeRef, transform, isDragging } =
    useDraggable({
      id: table.uuid,
      disabled: !isEditMode,
    });

  const style = transform
    ? {
        transform: CSS.Translate.toString(transform),
        left: table.position_x,
        top: table.position_y,
      }
    : {
        left: table.position_x,
        top: table.position_y,
      };

  const baseSize = 80;
  const width = table.shape === "rectangle" ? (table.width ?? 120) : baseSize;
  const height = table.shape === "rectangle" ? (table.height ?? 80) : baseSize;

  const color = STATUS_COLORS[table.status] || STATUS_COLORS.available;
  const statusLabel = t(`floor_plan.table.status.${table.status}`);

  const shapeClasses = cn(
    "absolute flex flex-col items-center justify-center shadow-lg border-2 transition-shadow",
    "text-white font-bold select-none",
    {
      "rounded-lg": table.shape === "square" || table.shape === "rectangle",
      "rounded-full": table.shape === "round",
      "cursor-grab active:cursor-grabbing": isEditMode,
      "cursor-pointer hover:shadow-xl hover:scale-105": !isEditMode,
      "opacity-70 scale-105 shadow-2xl z-50": isDragging,
      "z-10": !isDragging,
    }
  );

  return (
    <div
      ref={setNodeRef}
      style={{
        ...style,
        width,
        height,
        backgroundColor: color,
        borderColor: color,
        transform: `translate3d(${transform?.x ?? 0}px, ${transform?.y ?? 0}px, 0) rotate(${table.rotation}deg)`,
      }}
      className={shapeClasses}
      onClick={() => !isEditMode && onClick(table)}
      {...(isEditMode ? listeners : {})}
      {...(isEditMode ? attributes : {})}
      title={`${table.table_number} · ${statusLabel}`}
    >
      {isEditMode && (
        <div className="absolute -top-2 -right-2 bg-slate-900 rounded-full p-1 shadow-md">
          <GripVertical size={12} className="text-white" />
        </div>
      )}

      <div className="text-base font-bold">{table.table_number}</div>

      <div className="flex items-center gap-1 text-xs opacity-90 mt-0.5">
        <Users size={10} />
        <span>{table.capacity}</span>
      </div>

      {!isEditMode && (
        <div
          className="absolute -bottom-5 left-1/2 -translate-x-1/2 text-xs whitespace-nowrap px-1.5 py-0.5 rounded"
          style={{ backgroundColor: color, color: "white" }}
        >
          {statusLabel}
        </div>
      )}
    </div>
  );
}
