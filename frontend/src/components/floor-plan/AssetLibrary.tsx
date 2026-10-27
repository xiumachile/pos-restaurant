import { useDraggable } from "@dnd-kit/core";
import { useTranslation } from "react-i18next";
import { 
  Square, Circle, RectangleHorizontal, Sofa, Columns, Flower, 
  Server, DoorOpen, LayoutGrid, Armchair
} from "lucide-react";
import type { ObjectType } from "@/types/floorPlan";

interface Props {
  onAddObject: (type: ObjectType) => void;
  isEditMode: boolean;
}

const ICONS: Record<ObjectType, any> = {
  table: Square,
  chair: Armchair,
  wall: RectangleHorizontal,
  door: DoorOpen,
  window: LayoutGrid,
  column: Columns,
  plant: Flower,
  counter: Server,
  booth: Sofa,
  bench: RectangleHorizontal,
  sofa: Sofa,
  service_station: Server,
  partition: LayoutGrid,
  other: Circle,
};

function PaletteCard({ type, onAdd }: { type: ObjectType; onAdd: () => void }) {
  const { attributes, listeners, setNodeRef, isDragging } = useDraggable({
    id: `library-${type}`,
    data: { fromLibrary: true, objectType: type },
  });
  
  const Icon = ICONS[type] || Circle;

  return (
    <button
      ref={setNodeRef}
      {...listeners}
      {...attributes}
      onClick={onAdd}
      className={`flex flex-col items-center gap-2 p-3 rounded-lg border border-slate-600 bg-slate-700/50 hover:border-orange-500/60 hover:bg-slate-700 transition-all group ${
        isDragging ? "opacity-40 scale-95" : ""
      }`}
    >
      <Icon size={24} className="text-slate-300 group-hover:text-orange-400" />
      <span className="text-xs font-medium text-slate-200 capitalize">
        {type}
      </span>
    </button>
  );
}

export function AssetLibrary({ onAddObject, isEditMode }: Props) {
  const { t } = useTranslation();
  
  if (!isEditMode) return null;

  const tables: ObjectType[] = ["table"];
  const decorations: ObjectType[] = [
    "wall", "door", "window", "column", "plant", 
    "counter", "booth", "bench", "sofa", "service_station", "partition"
  ];

  const renderSection = (title: string, items: ObjectType[]) => (
    <div className="mb-6">
      <h4 className="text-xs font-semibold text-slate-400 uppercase mb-3 tracking-wider">{title}</h4>
      <div className="grid grid-cols-2 gap-2">
        {items.map((item) => (
          <PaletteCard key={item} type={item} onAdd={() => onAddObject(item)} />
        ))}
      </div>
    </div>
  );

  return (
    <div className="w-64 bg-slate-800 border-r border-slate-700 overflow-y-auto shrink-0">
      <div className="p-4">
        <h3 className="text-sm font-bold text-white mb-1">
          {t("floor_plan.asset_library.title", "Biblioteca")}
        </h3>
        <p className="text-[10px] text-slate-500 mb-4 leading-relaxed">
          {t("floor_plan.asset_library.hint", "Haz clic para agregar al centro del plano")}
        </p>
        {renderSection(t("floor_plan.asset_library.tables", "Mesas"), tables)}
        {renderSection(t("floor_plan.asset_library.decorations", "Mobiliario y Decoración"), decorations)}
      </div>
    </div>
  );
}

// ============================================================================
// LEGACY EXPORTS (Para compatibilidad con cualquier archivo residual)
// ============================================================================
export const FURNITURE_CATALOG = [
  { id: 'table_square_4', type: 'table', width: 80, height: 80 },
  { id: 'table_round_4', type: 'table', width: 80, height: 80 },
  { id: 'table_rectangle_6', type: 'table', width: 120, height: 80 },
  { id: 'wall', type: 'wall', width: 200, height: 20 },
  { id: 'plant', type: 'plant', width: 60, height: 60 },
  { id: 'column', type: 'column', width: 40, height: 40 },
  { id: 'booth', type: 'booth', width: 120, height: 80 },
  { id: 'bar_counter', type: 'counter', width: 160, height: 40 },
];
