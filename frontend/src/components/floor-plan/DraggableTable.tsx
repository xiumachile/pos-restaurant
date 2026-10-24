import { useDraggable } from "@dnd-kit/core";
import { CSS } from "@dnd-kit/utilities";
import { useTranslation } from "react-i18next";
import { GripVertical } from "lucide-react";
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
  available: "#10b981", // Emerald
  occupied: "#ef4444",  // Red
  billing: "#f59e0b",   // Amber
  maintenance: "#64748b", // Slate
};

export function DraggableTable({ table, isEditMode, isSelected, onClick }: Props) {
  const { t } = useTranslation();
  const { attributes, listeners, setNodeRef, transform, isDragging } = useDraggable({
    id: table.uuid,
    disabled: !isEditMode,
  });

  const furnitureConfig = FURNITURE_CATALOG.find((f) => f.id === table.furniture_type);
  
  const style = transform 
    ? { transform: CSS.Translate.toString(transform), left: table.position_x, top: table.position_y }
    : { left: table.position_x, top: table.position_y };

  const width = furnitureConfig?.width ?? (table.shape === "rectangle" ? (table.width ?? 120) : 80);
  const height = furnitureConfig?.height ?? (table.shape === "rectangle" ? (table.height ?? 80) : 80);

  const color = STATUS_COLORS[table.status] ?? STATUS_COLORS.available;

  // Función para dibujar las sillas dinámicamente
  const renderChairs = () => {
    if (table.capacity <= 0) return null;
    const chair = "absolute w-3 h-3 bg-slate-300 border border-slate-400 rounded-full shadow-sm z-0";
    
    // Lógica simple de posicionamiento según capacidad
    if (table.shape === "round" || table.shape === "square") {
      if (table.capacity >= 2) return (
        <>
          <div className={`${chair} top-0 left-1/2 -translate-x-1/2 -translate-y-1/2`} />
          <div className={`${chair} bottom-0 left-1/2 -translate-x-1/2 translate-y-1/2`} />
        </>
      );
      if (table.capacity >= 4) return (
        <>
          <div className={`${chair} top-0 left-1/2 -translate-x-1/2 -translate-y-1/2`} />
          <div className={`${chair} bottom-0 left-1/2 -translate-x-1/2 translate-y-1/2`} />
          <div className={`${chair} left-0 top-1/2 -translate-y-1/2 -translate-x-1/2`} />
          <div className={`${chair} right-0 top-1/2 -translate-y-1/2 translate-x-1/2`} />
        </>
      );
    }
    
    if (table.shape === "rectangle") {
      return (
        <>
          {/* Sillas arriba */}
          {Array.from({ length: Math.ceil(table.capacity / 2) }).map((_, i) => (
            <div key={`top-${i}`} className={`${chair} top-0`} style={{ left: `${((i + 1) / (Math.ceil(table.capacity / 2) + 1)) * 100}%`, transform: 'translate(-50%, -50%)' }} />
          ))}
          {/* Sillas abajo */}
          {Array.from({ length: Math.floor(table.capacity / 2) }).map((_, i) => (
            <div key={`bot-${i}`} className={`${chair} bottom-0`} style={{ left: `${((i + 1) / (Math.floor(table.capacity / 2) + 1)) * 100}%`, transform: 'translate(-50%, 50%)' }} />
          ))}
        </>
      );
    }
    return null;
  };

  return (
    <div
      ref={setNodeRef}
      style={{
        ...style,
        width,
        height,
        transform: `translate3d(${transform?.x ?? 0}px, ${transform?.y ?? 0}px, 0) rotate(${table.rotation}deg)`,
      }}
      className={cn(
        "absolute select-none transition-all",
        {
          "cursor-grab active:cursor-grabbing": isEditMode,
          "cursor-pointer hover:scale-105": !isEditMode,
          "opacity-70 scale-105 z-50": isDragging,
          "z-10": !isDragging,
          "ring-4 ring-orange-500 ring-offset-2 ring-offset-slate-900": isSelected && isEditMode,
        }
      )}
      onClick={() => onClick(table)}
      {...(isEditMode ? listeners : {})}
      {...(isEditMode ? attributes : {})}
    >
      {/* ✅ Capa Visual: pointer-events-none es CRÍTICO para no romper el drag */}
      <div className="absolute inset-0 pointer-events-none flex items-center justify-center">
        
        {/* Sillas alrededor */}
        {renderChairs()}

        {/* Cuerpo de la mesa */}
        <div 
          className={cn(
            "relative z-10 flex items-center justify-center shadow-lg border-2 border-white/20",
            {
              "rounded-lg": table.shape === "square",
              "rounded-full": table.shape === "round",
              "rounded-md": table.shape === "rectangle",
            }
          )}
          style={{ 
            width: '85%', 
            height: '85%', 
            backgroundColor: color,
            boxShadow: `inset 0 2px 4px rgba(255,255,255,0.3), 0 4px 6px rgba(0,0,0,0.3)`
          }}
        >
          {/* Número de mesa */}
          <span className="text-white font-black text-sm drop-shadow-md leading-none">
            {table.table_number}
          </span>
        </div>
      </div>

      {/* Handle de arrastre (solo en modo edición) */}
      {isEditMode && (
        <div className="absolute -top-3 -right-3 bg-slate-800 border border-slate-600 rounded-full p-1 shadow-lg z-20 pointer-events-auto">
          <GripVertical size={14} className="text-slate-300" />
        </div>
      )}
    </div>
  );
}
