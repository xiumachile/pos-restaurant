// src/components/floor-plan/AssetLibrary.tsx
import { useTranslation } from "react-i18next";
import { FURNITURE_CATALOG, type Category, type ObjectType, calculateSeatLayout } from "./catalog/furnitureCatalog";
import { ObjectRenderer } from "./renderers/ObjectRenderer";

interface Props {
  onAddObject: (objectType: ObjectType) => void;
  isEditMode: boolean;
}

const CATEGORY_ORDER: Category[] = ["mesas", "asientos", "arquitectura", "servicio", "decoracion"];

const CATEGORY_LABELS: Record<Category, string> = {
  mesas: "Mesas",
  asientos: "Asientos",
  arquitectura: "Arquitectura",
  servicio: "Servicio",
  decoracion: "Decoración",
};

// ✅ CORREGIDO: Usar calculateSeatLayout para calcular dinámicamente
const createDummyObject = (type: ObjectType) => {
  const def = FURNITURE_CATALOG[type];
  const seatLayout = calculateSeatLayout(def.defaultCapacity, def.defaultShape);
  
  return {
    uuid: `dummy-${type}`,
    object_type: type,
    table_uuid: null,
    zone_uuid: null,
    position_x: 0,
    position_y: 0,
    width: def.defaultWidth,
    height: def.defaultHeight,
    rotation: 0,
    z_index: 0,
    properties: {
      shape: def.defaultShape,
      capacity: def.defaultCapacity,
      seat_layout: seatLayout,
      swing_direction: "right",
    },
  };
};

export function AssetLibrary({ onAddObject, isEditMode }: Props) {
  const { t } = useTranslation();

  if (!isEditMode) return null;

  const grouped = CATEGORY_ORDER.map((category) => ({
    category,
    items: Object.values(FURNITURE_CATALOG).filter((item) => item.category === category),
  })).filter((group) => group.items.length > 0);

  return (
    <div className="w-72 bg-slate-800 border-r border-slate-700 overflow-y-auto shrink-0">
      <div className="p-4">
        <h3 className="text-sm font-bold text-white mb-1">
          {t("floor_plan.asset_library.title", "Biblioteca de Objetos")}
        </h3>
        <p className="text-[10px] text-slate-500 mb-4 leading-relaxed">
          {t("floor_plan.asset_library.hint", "Haz clic para agregar al centro del plano")}
        </p>

        {grouped.map(({ category, items }) => (
          <div key={category} className="mb-6">
            <h4 className="text-xs font-semibold text-slate-400 uppercase mb-3 tracking-wider border-b border-slate-700 pb-1">
              {CATEGORY_LABELS[category]}
            </h4>
            <div className="grid grid-cols-2 gap-2">
              {items.map((item) => (
                <button
                  key={item.type}
                  onClick={() => onAddObject(item.type)}
                  className="flex flex-col items-center gap-1 p-2 rounded-lg border border-slate-600 bg-slate-700/50 hover:border-orange-500/60 hover:bg-slate-700 transition-all group"
                  title={item.name}
                >
                  <div className="w-16 h-16 flex items-center justify-center bg-slate-900 rounded">
                    <ObjectRenderer object={createDummyObject(item.type)} showChairs={false} />
                  </div>
                  <span className="text-[10px] font-medium text-slate-300 text-center leading-tight">
                    {item.name}
                  </span>
                </button>
              ))}
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
