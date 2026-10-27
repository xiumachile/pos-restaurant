import { useDraggable } from "@dnd-kit/core";
import { CSS } from "@dnd-kit/utilities";
import { GripVertical, DollarSign } from "lucide-react";
import type { FloorPlanObject } from "@/types/floorPlan";
import { 
  TableSquareGeometry, TableRoundGeometry, TableRectGeometry,
  BoothGeometry, WallGeometry, PlantGeometry, BarCounterGeometry, PillarGeometry
} from "./geometries/FurnitureGeometries";

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

const DEFAULT_DECO_COLORS: Record<string, string> = {
  wall: "#64748b",
  plant: "#22c55e",
  booth: "#6366f1",
  counter: "#d97706",
  pillar: "#94a3b8",
  column: "#94a3b8",
  door: "#8b5cf6",
  window: "#38bdf8",
  bench: "#a855f7",
  sofa: "#ec4899",
  service_station: "#f59e0b",
  partition: "#6b7280",
  table: "#10b981",
};

// ✅ Tamaños por defecto según tipo
const DEFAULT_SIZES: Record<string, { width: number; height: number }> = {
  table: { width: 80, height: 80 },
  wall: { width: 200, height: 20 },
  plant: { width: 60, height: 60 },
  column: { width: 40, height: 40 },
  booth: { width: 120, height: 80 },
  counter: { width: 160, height: 40 },
  door: { width: 60, height: 20 },
  window: { width: 80, height: 20 },
  bench: { width: 100, height: 40 },
  sofa: { width: 120, height: 60 },
  service_station: { width: 80, height: 60 },
  partition: { width: 100, height: 20 },
};

export function DraggableObject({ object, isEditMode, isSelected, onClick }: Props) {
  const { attributes, listeners, setNodeRef, transform, isDragging } = useDraggable({
    id: object.uuid,
    disabled: !isEditMode,
  });

  // ✅ Determinar color con fallback
  let color = DEFAULT_DECO_COLORS[object.object_type] || "#94a3b8";
  if (object.object_type === "table" && object.operational_data) {
    color = STATUS_COLORS[object.operational_data.status] || STATUS_COLORS.available;
  }

  // ✅ Tamaños con fallback obligatorio (nunca null)
  const width = object.width || DEFAULT_SIZES[object.object_type]?.width || 80;
  const height = object.height || DEFAULT_SIZES[object.object_type]?.height || 80;

  // ✅ Renderizar geometría según tipo
  const renderGeometry = () => {
    const cap = object.operational_data?.capacity || object.properties?.capacity || 4;
    switch (object.object_type) {
      case "table":
        if (object.properties?.shape === "round") return <TableRoundGeometry color={color} capacity={cap} />;
        if (object.properties?.shape === "rectangle") return <TableRectGeometry color={color} capacity={cap} />;
        return <TableSquareGeometry color={color} capacity={cap} />;
      case "booth": return <BoothGeometry color={color} />;
      case "wall": return <WallGeometry color={color} />;
      case "plant": return <PlantGeometry color={color} />;
      case "counter": return <BarCounterGeometry color={color} />;
      case "column":
      case "column": return <PillarGeometry color={color} />;
      default: return <div className="w-full h-full bg-slate-500/50 rounded" />;
    }
  };

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
        zIndex: object.z_index || (isDragging ? 50 : 10),
      }}
      className={`select-none transition-all duration-200 ${
        isEditMode ? "cursor-grab active:cursor-grabbing" : "cursor-pointer hover:scale-105"
      } ${isDragging ? "opacity-70 scale-105" : ""} ${
        isSelected && isEditMode ? "ring-4 ring-orange-500 ring-offset-2 ring-offset-slate-900" : ""
      }`}
      onClick={onClick}
      {...(isEditMode ? listeners : {})}
      {...(isEditMode ? attributes : {})}
    >
      {/* Capa visual con pointer-events-none */}
      <div className="absolute inset-0 pointer-events-none flex items-center justify-center p-1">
        {renderGeometry()}
      </div>

      {/* Información operacional para mesas */}
      {object.object_type === "table" && object.operational_data && (
        <div className="absolute inset-0 pointer-events-none flex flex-col items-center justify-center">
          <div className="bg-black/60 px-2 py-1 rounded-full border border-white/20 backdrop-blur-sm">
            <span className="text-xs font-black text-white leading-none">
              {object.operational_data.table_number}
            </span>
          </div>
          {object.operational_data.has_active_order && (
            <div className="absolute -bottom-6 bg-slate-900/90 text-white text-[10px] px-2 py-1 rounded-full border border-slate-700 flex items-center gap-1.5 whitespace-nowrap shadow-lg">
              <DollarSign size={10} className="text-emerald-400" />
              <span className="font-bold">Activa</span>
            </div>
          )}
        </div>
      )}

      {/* Handle de arrastre en modo edición */}
      {isEditMode && (
        <div className="absolute -top-3 -right-3 bg-slate-800 border border-slate-600 rounded-full p-1 shadow-lg z-50 pointer-events-auto">
          <GripVertical size={14} className="text-slate-300" />
        </div>
      )}
    </div>
  );
}
