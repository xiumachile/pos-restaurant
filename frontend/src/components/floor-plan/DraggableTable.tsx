import { useDraggable } from "@dnd-kit/core";
import { CSS } from "@dnd-kit/utilities";
import { useTranslation } from "react-i18next";
import { GripVertical, Users, Clock, DollarSign } from "lucide-react";
import type { TablePosition } from "@/types/floorPlan";
import { FURNITURE_CATALOG } from "./AssetLibrary";
import { cn } from "@/lib/utils";
import { formatElapsed } from "@/lib/floorPlanLive";

interface Props {
  table: TablePosition;
  isEditMode: boolean;
  isSelected: boolean;
  onClick: (table: TablePosition) => void;
  liveOrderTotal?: number | null;
  liveSince?: string | null;
}

const STATUS_COLORS: Record<string, string> = {
  available: "#10b981",
  occupied: "#ef4444",
  billing: "#f59e0b",
  maintenance: "#64748b",
};

export function DraggableTable({ table, isEditMode, isSelected, onClick, liveOrderTotal, liveSince }: Props) {
  const { t } = useTranslation();
  const { attributes, listeners, setNodeRef, transform, isDragging } = useDraggable({
    id: table.uuid,
    disabled: !isEditMode,
  });

  const furnitureConfig = FURNITURE_CATALOG.find((f) => f.id === table.furniture_type);
  const width = furnitureConfig?.width ?? (table.shape === "rectangle" ? (table.width ?? 120) : 80);
  const height = furnitureConfig?.height ?? (table.shape === "rectangle" ? (table.height ?? 80) : 80);
  const color = STATUS_COLORS[table.status] ?? STATUS_COLORS.available;

  const renderChairs = () => {
    if (table.capacity <= 0) return null;
    const chairClass = "absolute w-3 h-3 bg-slate-200 border border-slate-400 rounded-full shadow-sm z-0";
    
    if (table.shape === "round" || table.shape === "square") {
      if (table.capacity >= 6) {
        return (
          <>
            <div className={`${chairClass} top-0 left-1/4 -translate-x-1/2 -translate-y-1/2`} />
            <div className={`${chairClass} top-0 left-3/4 -translate-x-1/2 -translate-y-1/2`} />
            <div className={`${chairClass} bottom-0 left-1/4 -translate-x-1/2 translate-y-1/2`} />
            <div className={`${chairClass} bottom-0 left-3/4 -translate-x-1/2 translate-y-1/2`} />
            <div className={`${chairClass} left-0 top-1/4 -translate-y-1/2 -translate-x-1/2`} />
            <div className={`${chairClass} right-0 top-1/4 -translate-y-1/2 translate-x-1/2`} />
          </>
        );
      }
      if (table.capacity >= 4) {
        return (
          <>
            <div className={`${chairClass} top-0 left-1/2 -translate-x-1/2 -translate-y-1/2`} />
            <div className={`${chairClass} bottom-0 left-1/2 -translate-x-1/2 translate-y-1/2`} />
            <div className={`${chairClass} left-0 top-1/2 -translate-y-1/2 -translate-x-1/2`} />
            <div className={`${chairClass} right-0 top-1/2 -translate-y-1/2 translate-x-1/2`} />
          </>
        );
      }
      return (
        <>
          <div className={`${chairClass} top-0 left-1/2 -translate-x-1/2 -translate-y-1/2`} />
          <div className={`${chairClass} bottom-0 left-1/2 -translate-x-1/2 translate-y-1/2`} />
        </>
      );
    }
    
    if (table.shape === "rectangle") {
      const chairsPerSide = Math.ceil(table.capacity / 2);
      return (
        <>
          {Array.from({ length: chairsPerSide }).map((_, i) => (
            <div key={`t-${i}`} className={chairClass} style={{ top: 0, left: `${((i + 1) / (chairsPerSide + 1)) * 100}%`, transform: 'translate(-50%, -50%)' }} />
          ))}
          {Array.from({ length: Math.floor(table.capacity / 2) }).map((_, i) => (
            <div key={`b-${i}`} className={chairClass} style={{ bottom: 0, left: `${((i + 1) / (Math.floor(table.capacity / 2) + 1)) * 100}%`, transform: 'translate(-50%, 50%)' }} />
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
        width,
        height,
        left: table.position_x,
        top: table.position_y,
        // ✅ CORREGIDO: Solo un transform, combinando el de dnd-kit y la rotación
        transform: transform 
          ? `${CSS.Translate.toString(transform)} rotate(${table.rotation}deg)`
          : `rotate(${table.rotation}deg)`,
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
      {/* Capa visual con pointer-events-none para no bloquear el drag */}
      <div className="absolute inset-0 pointer-events-none flex items-center justify-center">
        {renderChairs()}
        
        {/* Cuerpo de la mesa */}
        <div 
          className={cn(
            "relative z-10 flex flex-col items-center justify-center shadow-lg border-2 border-white/20",
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
          <span className="text-white font-black text-sm drop-shadow-md leading-none">
            {table.table_number}
          </span>
          
          {/* ✅ Info en vivo del pedido */}
          {!isEditMode && table.has_active_order && liveOrderTotal !== null && (
            <div className="absolute -bottom-6 bg-slate-900/90 text-white text-[10px] px-2 py-1 rounded-full border border-slate-700 flex items-center gap-1.5 whitespace-nowrap shadow-lg">
              <DollarSign size={10} className="text-emerald-400" />
              <span className="font-bold">${liveOrderTotal}</span>
              {liveSince && (
                <>
                  <span className="text-slate-500">|</span>
                  <Clock size={10} className="text-amber-400" />
                  <span className="text-amber-400">{formatElapsed(liveSince)}</span>
                </>
              )}
            </div>
          )}
        </div>
      </div>

      {isEditMode && (
        <div className="absolute -top-3 -right-3 bg-slate-800 border border-slate-600 rounded-full p-1 shadow-lg z-20 pointer-events-auto">
          <GripVertical size={14} className="text-slate-300" />
        </div>
      )}
    </div>
  );
}
