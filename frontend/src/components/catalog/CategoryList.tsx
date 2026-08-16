import type { Category } from "@/types/catalog";
import { getTranslatedName } from "@/types/catalog";
import { Utensils } from "lucide-react";

interface CategoryListProps {
  categories: Category[];
  selectedId: number | null;
  productsByCategory: Record<number, number>;
  totalProducts: number;
  onSelect: (categoryId: number | null) => void;
}

/**
 * Lista de categorías en sidebar con contadores.
 */
export function CategoryList({
  categories,
  selectedId,
  productsByCategory,
  totalProducts,
  onSelect,
}: CategoryListProps) {
  return (
    <aside className="w-64 bg-slate-800/50 border border-slate-700 rounded-xl p-4 h-fit">
      <h2 className="text-sm font-semibold text-slate-400 uppercase tracking-wide mb-3 px-2">
        Categorías
      </h2>

      <nav className="space-y-1">
        {/* Opción "Todas" */}
        <button
          onClick={() => onSelect(null)}
          className={`w-full flex items-center justify-between px-3 py-2.5 rounded-lg text-left transition-colors ${
            selectedId === null
              ? "bg-orange-500 text-white"
              : "text-slate-300 hover:bg-slate-700"
          }`}
        >
          <span className="flex items-center gap-2 font-medium">
            <Utensils size={16} />
            Todas
          </span>
          <span
            className={`text-xs px-2 py-0.5 rounded-full ${
              selectedId === null
                ? "bg-white/20"
                : "bg-slate-700 text-slate-400"
            }`}
          >
            {totalProducts}
          </span>
        </button>

        {/* Lista de categorías */}
        {categories.map((cat) => {
          const count = productsByCategory[cat.id] || 0;
          const isSelected = selectedId === cat.id;
          return (
            <button
              key={cat.id}
              onClick={() => onSelect(cat.id)}
              className={`w-full flex items-center justify-between px-3 py-2.5 rounded-lg text-left transition-colors ${
                isSelected
                  ? "bg-orange-500 text-white"
                  : "text-slate-300 hover:bg-slate-700"
              }`}
            >
              <span className="font-medium truncate">
                {getTranslatedName(cat.name_translations)}
              </span>
              <span
                className={`text-xs px-2 py-0.5 rounded-full flex-shrink-0 ml-2 ${
                  isSelected
                    ? "bg-white/20"
                    : "bg-slate-700 text-slate-400"
                }`}
              >
                {count}
              </span>
            </button>
          );
        })}
      </nav>
    </aside>
  );
}
