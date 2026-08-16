import type { TablesArea, RestaurantTable } from "@/types/tables";
import type { TableCart } from "@/types/cart";
import { TableCard } from "./TableCard";
import { MapPin } from "lucide-react";

interface TablesGridProps {
  area: TablesArea;
  onTableClick?: (table: RestaurantTable) => void;
  cartItems?: Record<string, TableCart>;
}

export function TablesGrid({ area, onTableClick, cartItems }: TablesGridProps) {
  return (
    <div className="mb-8">
      <div className="flex items-center gap-2 mb-4">
        <MapPin size={20} className="text-orange-400" />
        <h2 className="text-xl font-semibold text-white">{area.area_name}</h2>
        <span className="text-sm text-slate-400">({area.tables.length} mesas)</span>
      </div>

      <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4">
        {area.tables.map((table) => (
          <TableCard
            key={table.uuid}
            table={table}
            onClick={onTableClick}
            cartItems={cartItems}
          />
        ))}
      </div>
    </div>
  );
}
