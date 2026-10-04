import { useState } from 'react';
import { useFloorPlanStore } from '@/stores/floor-plan/floorPlanStore';
import { useState } from 'react';
import { AreaManagementModal } from '@/components/areas/AreaManagementModal';
import { TableManagementPanel } from '@/components/tables/TableManagementPanel';
import { useTranslation } from 'react-i18next';
import { useTableManagement } from '@/hooks/useTableManagement';
import { TableManagementModal } from '@/components/tables/TableManagementModal';
import { Settings } from 'lucide-react';
import { tablesCatalogByCategory } from '../catalog/tables.catalog';
import { decorationsCatalogByCategory } from '../catalog/decorations.catalog';
import type { CatalogItem, FloorPlanObject } from '@/types/floor-plan/floorPlan.types';
import { ChevronDown, ChevronRight, Plus, Armchair } from 'lucide-react';

const Z_INDEX_MAP: Record<string, number> = {
  wall: 1,
  column: 2,
  window: 2,
  door: 2,
  separator: 3,
  decoration: 4,
  furniture: 5,
  plant: 6,
  service: 7,
  table: 10,
  infrastructure: 1,
};

/**
 * Panel izquierdo con biblioteca completa: mesas + decoración + arquitectura
 */
export function FloorPlanSidebar() {
  const [showAreaModal, setShowAreaModal] = useState(false);
  const { t } = useTranslation();
  const tableManagement = useTableManagement();
  const { addObject, currentPlan } = useFloorPlanStore();
  const [expandedCategories, setExpandedCategories] = useState<Record<string, boolean>>({
    'Redondas': true,
    'Cuadradas': false,
    'Rectangulares': false,
    'Vegetación': true,
    'Arquitectura': true,
    'Servicio': false,
  });

  const handleDragStart = (item: CatalogItem) => (e: React.DragEvent) => {
    e.dataTransfer.setData('application/floor-plan-object', JSON.stringify(item));
    e.dataTransfer.effectAllowed = 'copy';
  };

  const handleQuickAdd = (item: CatalogItem) => {
    if (!currentPlan) return;

    const newObject: FloorPlanObject = {
      id: Date.now(),
      uuid: crypto.randomUUID(),
      floor_plan_id: currentPlan.id,
      object_type: item.type,
      object_key: null,
      x: 300 + Math.random() * 200,
      y: 300 + Math.random() * 200,
      width: item.defaultWidth,
      height: item.defaultHeight,
      rotation: 0,
      z_index: Z_INDEX_MAP[item.type] ?? 5,
      properties: item.defaultProperties,
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
    };

    addObject(newObject);
  };

  const toggleCategory = (category: string) => {
    setExpandedCategories((prev) => ({ ...prev, [category]: !prev[category] }));
  };

  const renderCategory = (category: string, items: CatalogItem[]) => (
    <div key={category} className="mb-2">
      <button
        onClick={() => toggleCategory(category)}
        className="w-full flex items-center gap-2 px-2 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded transition-colors"
      >
        {expandedCategories[category] ? <ChevronDown size={14} /> : <ChevronRight size={14} />}
        <span>{category}</span>
        <span className="text-xs text-gray-400 ml-auto">({items.length})</span>
      </button>

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
  );

  return (
    <div className="w-64 bg-white border-r border-gray-200 overflow-y-auto flex flex-col">
      <div className="p-4 border-b border-gray-200 bg-gray-50 sticky top-0 z-10">
        <h2 className="text-lg font-bold text-gray-800 flex items-center gap-2">
          <Armchair size={18} />
          Biblioteca
        </h2>
        

        <p className="text-xs text-gray-500 mt-1">
          Arrastra elementos al plano
        </p>
      </div>

      <div className="flex-1 overflow-y-auto p-2">
        {/* MESAS */}
        <div className="mb-4">
          <h3 className="text-xs font-semibold text-gray-500 px-2 py-2 uppercase tracking-wide border-b border-gray-200 mb-1">
            Mesas
          </h3>
          {Object.entries(tablesCatalogByCategory).map(([category, items]) =>
            renderCategory(category, items)
          )}
        </div>

        {/* DECORACIÓN Y ARQUITECTURA */}
        <div>
          <h3 className="text-xs font-semibold text-gray-500 px-2 py-2 uppercase tracking-wide border-b border-gray-200 mb-1">
            Decoración y elementos
          </h3>
          {Object.entries(decorationsCatalogByCategory).map(([category, items]) =>
            renderCategory(category, items)
          )}
        </div>
      </div>
    
      
      <button
        onClick={() => setShowAreaModal(true)}
        className="w-full flex items-center justify-center gap-2 px-3 py-2 bg-purple-500 hover:bg-purple-600 text-white rounded text-sm font-medium transition-colors mb-2"
      >
        🗺️ {t('areas.manage_areas', 'Gestionar Áreas')}
      </button>

      <TableManagementPanel />
    
      <AreaManagementModal
        isOpen={showAreaModal}
        onClose={() => setShowAreaModal(false)}
      />

    </div>
  );
}
