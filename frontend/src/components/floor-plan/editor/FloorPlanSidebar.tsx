import { useState } from 'react';
import { useFloorPlanStore } from '@/stores/floor-plan/floorPlanStore';
import { tablesCatalogByCategory } from '../catalog/tables.catalog';
import type { CatalogItem, FloorPlanObject } from '@/types/floor-plan/floorPlan.types';
import { ChevronDown, ChevronRight, Plus } from 'lucide-react';

/**
 * Panel izquierdo del editor con biblioteca de objetos.
 * Implementa la Sección 4.1 de la especificación.
 */
export function FloorPlanSidebar() {
  const { addObject, setCreatingObjectType, currentPlan } = useFloorPlanStore();
  const [expandedCategories, setExpandedCategories] = useState<Record<string, boolean>>({
    'Redondas': true,
    'Cuadradas': true,
    'Rectangulares': true,
    'Ovaladas': false,
    'Comunitarias': false,
    'Altas/Bar': false,
  });

  const handleDragStart = (item: CatalogItem) => (e: React.DragEvent) => {
    // Transferir datos del item al drag
    e.dataTransfer.setData('application/floor-plan-object', JSON.stringify(item));
    e.dataTransfer.effectAllowed = 'copy';
  };

  const handleQuickAdd = (item: CatalogItem) => {
    if (!currentPlan) return;

    // Agregar al centro del canvas
    const newObject: FloorPlanObject = {
      id: Date.now(), // Será reemplazado por UUID del backend
      uuid: crypto.randomUUID(),
      floor_plan_id: currentPlan.id,
      object_type: item.type,
      object_key: null,
      x: 200 + Math.random() * 100,
      y: 200 + Math.random() * 100,
      width: item.defaultWidth,
      height: item.defaultHeight,
      rotation: 0,
      z_index: 1,
      properties: item.defaultProperties,
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
    };

    addObject(newObject);
  };

  const toggleCategory = (category: string) => {
    setExpandedCategories((prev) => ({ ...prev, [category]: !prev[category] }));
  };

  return (
    <div className="w-64 bg-white border-r border-gray-200 overflow-y-auto flex flex-col">
      {/* Header */}
      <div className="p-4 border-b border-gray-200 bg-gray-50">
        <h2 className="text-lg font-bold text-gray-800">Biblioteca</h2>
        <p className="text-xs text-gray-500 mt-1">
          Arrastra elementos al plano o haz click en +
        </p>
      </div>

      {/* Categorías de mesas */}
      <div className="flex-1 overflow-y-auto p-2">
        <h3 className="text-sm font-semibold text-gray-600 px-2 py-2 uppercase tracking-wide">
          Mesas
        </h3>

        {Object.entries(tablesCatalogByCategory).map(([category, items]) => (
          <div key={category} className="mb-2">
            {/* Header de categoría */}
            <button
              onClick={() => toggleCategory(category)}
              className="w-full flex items-center gap-2 px-2 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded transition-colors"
            >
              {expandedCategories[category] ? <ChevronDown size={14} /> : <ChevronRight size={14} />}
              <span>{category}</span>
              <span className="text-xs text-gray-400 ml-auto">({items.length})</span>
            </button>

            {/* Items de la categoría */}
            {expandedCategories[category] && (
              <div className="grid grid-cols-2 gap-1 p-1">
                {items.map((item) => (
                  <div
                    key={item.subtype}
                    draggable
                    onDragStart={handleDragStart(item)}
                    className="flex flex-col items-center p-2 border border-gray-200 rounded bg-white hover:bg-blue-50 hover:border-blue-300 cursor-grab active:cursor-grabbing transition-colors group"
                    title={item.label}
                  >
                    <div className="text-2xl mb-1">{item.icon}</div>
                    <div className="text-xs text-gray-700 text-center font-medium leading-tight">
                      {item.label}
                    </div>
                    <button
                      onClick={(e) => {
                        e.stopPropagation();
                        handleQuickAdd(item);
                      }}
                      className="mt-1 p-1 rounded bg-blue-500 text-white opacity-0 group-hover:opacity-100 transition-opacity"
                      title="Agregar al centro"
                    >
                      <Plus size={12} />
                    </button>
                  </div>
                ))}
              </div>
            )}
          </div>
        ))}

        {/* Secciones futuras */}
        <div className="mt-4 p-3 border border-dashed border-gray-300 rounded text-center">
          <p className="text-xs text-gray-400">
            Próximamente:<br />
            Decoración, Arquitectura
          </p>
        </div>
      </div>
    </div>
  );
}
