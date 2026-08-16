import type { Product } from "@/types/catalog";
import { getTranslatedName, formatPrice } from "@/types/catalog";
import { Plus, Package } from "lucide-react";

interface ProductCardProps {
  product: Product;
  onAdd?: (product: Product) => void;
}

/**
 * Card individual de producto con nombre, precio, badge combo y botón agregar.
 */
export function ProductCard({ product, onAdd }: ProductCardProps) {
  const name = getTranslatedName(product.name_translations);
  const price = formatPrice(product.base_price);
  const description = getTranslatedName(product.description_translations ?? {});
  const hasDescription = description && description !== "Sin nombre";

  return (
    <div className="bg-slate-800/50 border border-slate-700 rounded-xl p-4 hover:border-orange-500/50 hover:shadow-lg transition-all flex flex-col h-full">
      {/* Header: nombre + badge combo */}
      <div className="flex items-start justify-between gap-2 mb-2">
        <h3 className="font-semibold text-white leading-tight flex-1">
          {name}
        </h3>
        {product.is_combo && (
          <span className="flex items-center gap-1 px-2 py-0.5 rounded-full bg-purple-500/20 text-purple-300 border border-purple-500/30 text-xs font-medium flex-shrink-0">
            <Package size={10} />
            Combo
          </span>
        )}
      </div>

      {/* SKU */}
      <p className="text-xs text-slate-500 mb-2">{product.sku}</p>

      {/* Descripción (si existe) */}
      {hasDescription && (
        <p className="text-sm text-slate-400 mb-3 line-clamp-2 flex-1">
          {description}
        </p>
      )}

      {!hasDescription && <div className="flex-1" />}

      {/* Footer: precio + botón agregar */}
      <div className="flex items-center justify-between gap-2 pt-3 border-t border-slate-700 mt-auto">
        <span className="text-lg font-bold text-orange-400">{price}</span>
        <button
          onClick={() => onAdd?.(product)}
          className="flex items-center gap-1 px-3 py-1.5 bg-orange-500 hover:bg-orange-600 active:bg-orange-700 text-white rounded-lg text-sm font-medium transition-colors"
        >
          <Plus size={14} />
          Agregar
        </button>
      </div>
    </div>
  );
}
