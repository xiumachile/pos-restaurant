import { useState } from 'react';
import { useFloorPlanStore } from '@/stores/floor-plan/floorPlanStore';
import { useRestaurantTables } from '@/hooks/floor-plan/useRestaurantTables';
import type { TableProperties } from '@/types/floor-plan/floorPlan.types';
import { useTranslation } from 'react-i18next';
import { Clock, Users, ShoppingCart, AlertCircle } from 'lucide-react';

interface OperationalModeOverlayProps {
  onTableClick?: (tableUuid: string, tableNumber: string) => void;
}

/**
 * Overlay para el modo operativo (garzón).
 * Muestra estado de cada mesa y permite seleccionar para abrir pedido.
 * En esta versión inicial: muestra un tooltip al hacer click.
 * En siguientes iteraciones: consulta estados reales desde el módulo Orders.
 */
export function OperationalModeOverlay({ onTableClick }: OperationalModeOverlayProps) {
  const { t } = useTranslation();
  const { objects, editor, selectObject } = useFloorPlanStore();
  const { tables } = useRestaurantTables();
  const [selectedTableInfo, setSelectedTableInfo] = useState<any>(null);

  const tableObjects = objects.filter((o) => o.object_type === 'table');

  const handleTableClick = (obj: any) => {
    const props = obj.properties as TableProperties;
    const linkedTable = tables.find((t) => t.uuid === obj.object_key);

    const info = {
      uuid: obj.uuid,
      objectKey: obj.object_key,
      label: props.label || obj.uuid.slice(0, 4),
      capacity: props.capacity,
      reference: props.reference,
      linkedTable,
      x: obj.x,
      y: obj.y,
    };

    setSelectedTableInfo(info);
    selectObject(obj.uuid);

    // NO disparar onTableClick automáticamente
    // El usuario debe hacer click en el botón "Abrir pedido" del popup
    // Esto evita navegación accidental cuando la mesa no está vinculada
  };

  // Cerrar popup al hacer click fuera
  const handleBackdropClick = () => {
    setSelectedTableInfo(null);
    selectObject('', false);
  };

  return (
    <>
      {/* Popup de información contextual (Sección 6.2) */}
      {selectedTableInfo && (
        <div
          className="fixed inset-0 z-40"
          onClick={handleBackdropClick}
        >
          <div
            className="absolute bg-white dark:bg-slate-800 rounded-lg shadow-2xl border border-gray-200 dark:border-slate-700 p-4 min-w-[280px] max-w-sm"
            style={{
              left: Math.min(selectedTableInfo.x + 50, window.innerWidth - 320),
              top: Math.min(selectedTableInfo.y + 50, window.innerHeight - 300),
            }}
            onClick={(e) => e.stopPropagation()}
          >
            <div className="flex items-start justify-between mb-3">
              <div>
                <h3 className="text-lg font-bold text-gray-900 dark:text-white">
                  {t('floor_plan.operational.table', 'Mesa')} {selectedTableInfo.label}
                </h3>
                {selectedTableInfo.reference && (
                  <p className="text-xs text-gray-500 dark:text-slate-400 mt-0.5">
                    📍 {selectedTableInfo.reference}
                  </p>
                )}
              </div>
              <button
                onClick={() => setSelectedTableInfo(null)}
                className="text-gray-400 hover:text-gray-600 dark:hover:text-slate-300"
              >
                ✕
              </button>
            </div>

            <div className="space-y-2 mb-3">
              <div className="flex items-center gap-2 text-sm text-gray-700 dark:text-slate-300">
                <Users size={14} />
                <span>
                  {t('floor_plan.operational.capacity', 'Capacidad')}: {selectedTableInfo.capacity} {t('floor_plan.operational.people', 'personas')}
                </span>
              </div>
              {selectedTableInfo.linkedTable && (
                <>
                  <div className="flex items-center gap-2 text-sm">
                    <div className={`w-2 h-2 rounded-full ${
                      selectedTableInfo.linkedTable.status === 'available' ? 'bg-emerald-500' :
                      selectedTableInfo.linkedTable.status === 'occupied' ? 'bg-red-500' :
                      selectedTableInfo.linkedTable.status === 'reserved' ? 'bg-amber-500' :
                      'bg-gray-500'
                    }`} />
                    <span className="text-gray-700 dark:text-slate-300 capitalize">
                      {String(t(`floor_plan.operational.status.${selectedTableInfo.linkedTable.status}`, selectedTableInfo.linkedTable.status))}
                    </span>
                  </div>
                  <div className="text-xs text-gray-500 dark:text-slate-400">
                    {t('floor_plan.operational.connected', 'Conectada con')}: Mesa {selectedTableInfo.linkedTable.table_number}
                  </div>
                </>
              )}
              {!selectedTableInfo.linkedTable && selectedTableInfo.objectKey && (
                <div className="flex items-start gap-1.5 text-xs text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/30 p-2 rounded">
                  <AlertCircle size={12} className="flex-shrink-0 mt-0.5" />
                  <span>
                    {t('floor_plan.operational.table_not_found', 'Mesa real no encontrada en el sistema.')}
                  </span>
                </div>
              )}
              {!selectedTableInfo.objectKey && (
                <div className="flex items-start gap-1.5 text-xs text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/30 p-2 rounded">
                  <AlertCircle size={12} className="flex-shrink-0 mt-0.5" />
                  <span>
                    {t('floor_plan.operational.not_connected', 'Esta mesa no está conectada con una mesa real del restaurante.')}
                  </span>
                </div>
              )}
            </div>

            {/* Acciones según estado (Sección 6.2) */}
            <div className="space-y-1.5">
              {selectedTableInfo.linkedTable && selectedTableInfo.linkedTable.status === 'available' && (
                <button
                  onClick={() => {
                    // Navegar a toma de pedido
                    if (onTableClick && selectedTableInfo.objectKey) {
                      onTableClick(selectedTableInfo.objectKey, selectedTableInfo.label);
                    }
                    setSelectedTableInfo(null);
                  }}
                  className="w-full px-3 py-2 bg-emerald-500 hover:bg-emerald-600 text-white rounded font-medium text-sm flex items-center justify-center gap-2"
                >
                  <ShoppingCart size={16} />
                  {t('floor_plan.operational.open_order', 'Abrir pedido')}
                </button>
              )}
              {selectedTableInfo.linkedTable && selectedTableInfo.linkedTable.status === 'occupied' && (
                <button
                  onClick={() => {
                    // Navegar a ver pedido
                    if (onTableClick && selectedTableInfo.objectKey) {
                      onTableClick(selectedTableInfo.objectKey, selectedTableInfo.label);
                    }
                    setSelectedTableInfo(null);
                  }}
                  className="w-full px-3 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded font-medium text-sm flex items-center justify-center gap-2"
                >
                  <ShoppingCart size={16} />
                  {t('floor_plan.operational.view_order', 'Ver pedido')}
                </button>
              )}
              {selectedTableInfo.linkedTable && selectedTableInfo.linkedTable.status === 'reserved' && (
                <button
                  onClick={() => {
                    // TODO: Navegar a ver reserva
                    alert(`Ver reserva de mesa ${selectedTableInfo.label}`);
                    setSelectedTableInfo(null);
                  }}
                  className="w-full px-3 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded font-medium text-sm flex items-center justify-center gap-2"
                >
                  <Clock size={16} />
                  {t('floor_plan.operational.view_reservation', 'Ver reserva')}
                </button>
              )}
            </div>
          </div>
        </div>
      )}
    </>
  );
}
