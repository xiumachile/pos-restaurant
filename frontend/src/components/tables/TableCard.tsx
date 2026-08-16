import type { RestaurantTable } from "@/types/tables";
import type { TableCart } from "@/types/cart";
import { TableStatusBadge } from "./TableStatusBadge";
import { TABLE_STATUS_STYLES } from "@/types/tables";
import { Users, ShoppingCart } from "lucide-react";

interface TableCardProps {
  table: RestaurantTable;
  onClick?: (table: RestaurantTable) => void;
  cartItems?: Record<string, TableCart>;
}

export function TableCard({ table, onClick, cartItems }: TableCardProps) {
  const style = TABLE_STATUS_STYLES[table.status];
  const isClickable = table.status !== "maintenance";

  // Items en el carrito local de esta mesa
  const cart = cartItems?.[table.uuid];
  const cartCount = cart?.items.reduce((sum, i) => sum + i.quantity, 0) ?? 0;

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

      {/* Badge de carrito local con items */}
      {cartCount > 0 && (
        <span className="absolute -top-2 -right-2 bg-orange-500 text-white text-xs font-bold rounded-full min-w-[24px] h-6 px-1.5 flex items-center justify-center gap-1 shadow-lg">
          <ShoppingCart size={12} />
          {cartCount}
        </span>
      )}

      {/* Indicador de pedido activo en backend */}
      {table.has_active_order && cartCount === 0 && (
        <div className="absolute top-2 right-2 w-2.5 h-2.5 rounded-full bg-red-500 animate-pulse" />
      )}
    </button>
  );
}
