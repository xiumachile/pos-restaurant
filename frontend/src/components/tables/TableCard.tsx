import type { RestaurantTable } from "@/types/tables";
import { TableStatusBadge } from "./TableStatusBadge";
import { TABLE_STATUS_STYLES } from "@/types/tables";
import { Users } from "lucide-react";

interface TableCardProps {
  table: RestaurantTable;
  onClick?: (table: RestaurantTable) => void;
}

export function TableCard({ table, onClick }: TableCardProps) {
  const style = TABLE_STATUS_STYLES[table.status];
  const isClickable = table.status !== "maintenance";

  return (
    <button
      type="button"
      onClick={() => onClick?.(table)}
      disabled={!isClickable}
      className={`
        relative p-4 rounded-xl border-2 transition-all text-left
        ${style.bg} ${style.border}
        ${isClickable ? "hover:scale-105 hover:shadow-lg cursor-pointer" : "opacity-60 cursor-not-allowed"}
      `}
    >
      <div className="text-3xl font-bold text-white mb-2">
        {table.table_number}
      </div>

      <div className="flex items-center gap-1 text-sm text-slate-300 mb-3">
        <Users size={14} />
        <span>{table.capacity} personas</span>
      </div>

      <TableStatusBadge status={table.status} />

      {table.has_active_order && (
        <div className="absolute top-2 right-2 w-2 h-2 rounded-full bg-red-500 animate-pulse" />
      )}
    </button>
  );
}
