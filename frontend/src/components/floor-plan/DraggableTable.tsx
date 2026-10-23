import { useDraggable } from "@dnd-kit/core";
import { CSS } from "@dnd-kit/utilities";
import { useTranslation } from "react-i18next";
import { GripVertical, Users } from "lucide-react";
import type { TablePosition } from "@/types/floorPlan";
import { FURNITURE_CATALOG } from "./AssetLibrary";
import { cn } from "@/lib/utils";

interface Props {
  table: TablePosition;
  isEditMode: boolean;
  isSelected: boolean;
  onClick: (table: TablePosition) => void;
}

const STATUS_COLORS: Record<string, string> = {
  available: "#10b981", occupied: "#ef4444", billing: "#f59e0b", maintenance: "#64748b",
};

export function DraggableTable({ table, isEditMode, isSelected, onClick }: Props) {
  const { t } = useTranslation();
  const { attributes, listeners, setNodeRef, transform, isDragging } = useDraggable({ id: table.uuid, disabled: !isEditMode });

  const furnitureConfig = FURNITURE_CATALOG.find((f) => f.id === table.furniture_type);
  const style = transform ? { transform: CSS.Translate.toString(transform), left: table.position_x, top: table.position_y } : { left: table.position_x, top: table.position_y };

  const width = furnitureConfig?.width ?? (table.shape === "rectangle" ? (table.width ?? 120) : 80);
  const height = furnitureConfig?.height ?? (table.shape === "rectangle" ? (table.height ?? 80) : 80);

  const isDecoration = table.capacity === 0;
  const baseColor = isDecoration ? (furnitureConfig?.color ?? "#64748b") : (STATUS_COLORS[table.status] ?? STATUS_COLORS.available);
  const borderRadius = table.shape === "round" ? "9999px" : "16px";

  const shapeClasses = cn(
    "absolute flex flex-col items-center justify-center select-none transition-all duration-200",
    "border border-white/20 backdrop-blur-sm",
    {
      "cursor-grab active:cursor-grabbing": isEditMode,
      "cursor-pointer hover:scale-105 hover:shadow-2xl": !isEditMode,
      "opacity-90 scale-105 shadow-2xl z-50 ring-2 ring-white/50": isDragging,
      "z-10 shadow-xl": !isDragging,
      "ring-4 ring-orange-500 ring-offset-2 ring-offset-slate-900": isSelected && isEditMode,
    }
  );

  const Icon = furnitureConfig?.icon;

  return (
    <div
      ref={setNodeRef}
      style={{
        ...style, width, height, borderRadius, backgroundColor: baseColor,
        boxShadow: isDragging ? "0 20px 25px -5px rgba(0,0,0,0.5), 0 8px 10px -6px rgba(0,0,0,0.5)" : "0 10px 15px -3px rgba(0,0,0,0.4), 0 4px 6px -4px rgba(0,0,0,0.4), inset 0 1px 0 rgba(255,255,255,0.2)",
        transform: `translate3d(${transform?.x ?? 0}px, ${transform?.y ?? 0}px, 0) rotate(${table.rotation}deg)`,
      }}
      className={shapeClasses}
      onClick={() => onClick(table)}
      {...(isEditMode ? listeners : {})}
      {...(isEditMode ? attributes : {})}
    >
      {/* Brillo interior para efecto 3D */}
      <div className="absolute inset-0 rounded-[inherit] bg-gradient-to-br from-white/20 to-transparent pointer-events-none" />
      
      {isEditMode && (
        <div className="absolute -top-3 -right-3 bg-slate-800 border border-slate-600 rounded-full p-1.5 shadow-lg z-20">
          <GripVertical size={14} className="text-slate-300" />
        </div>
      )}

      {isDecoration && Icon ? (
        <div className="relative z-10 flex flex-col items-center gap-1">
          <Icon size={width > 60 ? 28 : 20} className="text-white/90 drop-shadow-md" />
          <span className="text-[10px] font-bold text-white/80 uppercase tracking-wider">{furnitureConfig?.label}</span>
        </div>
      ) : (
        <div className="relative z-10 flex flex-col items-center gap-1">
          <span className="text-lg font-black text-white drop-shadow-md tracking-tight">{table.table_number}</span>
          {table.capacity > 0 && (
            <div className="flex items-center gap-1 bg-black/20 px-2 py-0.5 rounded-full">
              <Users size={12} className="text-white/90" />
              <span className="text-xs font-bold text-white/90">{table.capacity}</span>
            </div>
          )}
        </div>
      )}

      {!isEditMode && !isDecoration && (
        <div className="absolute -bottom-6 left-1/2 -translate-x-1/2 text-[10px] font-black uppercase tracking-wider whitespace-nowrap px-2 py-1 rounded-md shadow-lg border border-white/10" style={{ backgroundColor: baseColor, color: "white" }}>
          {t(`floor_plan.table.status.${table.status}`)}
        </div>
      )}
    </div>
  );
}
