import { useState } from "react";
import type { Product } from "@/types/catalog";
import { getTranslatedName, formatPrice } from "@/types/catalog";
import { Plus, Package, Settings } from "lucide-react";
import { ComboSubstitutionModal } from "./ComboSubstitutionModal";

interface ProductCardProps {
  product: Product;
  onAdd?: (product: Product) => void;
}

export function ProductCard({ product, onAdd }: ProductCardProps) {
  const name = getTranslatedName(product.name_translations);
  const price = formatPrice(product.base_price);
  const [showSubstitutionModal, setShowSubstitutionModal] = useState(false);

  const canConfigureSubstitutions =
    product.is_combo && !!product.menu_item_uuid;

  return (
    <>
      <div className="bg-slate-800/50 border border-slate-700 rounded-xl p-4 hover:border-orange-500/50 hover:shadow-lg transition-all flex flex-col h-full">
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

        <p className="text-xs text-slate-500 mb-3">{product.sku}</p>

        <div className="flex items-center justify-between gap-2 pt-3 border-t border-slate-700 mt-auto">
          <span className="text-lg font-bold text-orange-400">{price}</span>
          <div className="flex gap-1.5">
            {canConfigureSubstitutions && (
              <button
                onClick={() => setShowSubstitutionModal(true)}
                className="flex items-center gap-1 px-2.5 py-1.5 bg-slate-700 hover:bg-slate-600 text-white rounded-lg text-sm font-medium transition-colors"
                title="Configurar sustituciones"
              >
                <Settings size={14} />
              </button>
            )}
            {onAdd && (
              <button
                onClick={() => onAdd(product)}
                className="flex items-center gap-1 px-3 py-1.5 bg-orange-500 hover:bg-orange-600 text-white rounded-lg text-sm font-medium transition-colors"
              >
                <Plus size={14} />
                Agregar
              </button>
            )}
          </div>
        </div>
      </div>

      {showSubstitutionModal && canConfigureSubstitutions && (
        <ComboSubstitutionModal
          menuItemUuid={product.menu_item_uuid!}
          comboName={name}
          onClose={() => setShowSubstitutionModal(false)}
        />
      )}
    </>
  );
}
