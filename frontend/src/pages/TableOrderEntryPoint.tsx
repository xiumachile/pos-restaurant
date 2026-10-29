import { useAuthStore } from '@/store/useAuthStore';
import { TablesPage } from '@/pages/TablesPage';
import { FloorPlanPage } from '@/pages/FloorPlanPage';
import { FreeOrderPage } from '@/pages/FreeOrderPage';

/**
 * Punto de entrada para la toma de pedidos en canal Mesa.
 * Decide qué vista renderizar según la configuración de la sucursal.
 */
export function TableOrderEntryPoint() {
  const { user } = useAuthStore();
  
  // Default a 'card' si no hay configuración
  const mode = user?.branch?.table_order_mode || 'card';

  if (mode === 'visual') {
    // FloorPlanPage por defecto inicia con isEditMode=false (modo lectura)
    return <FloorPlanPage />;
  }

  if (mode === 'free') {
    return <FreeOrderPage />;
  }

  // Default: Modo Tarjetas (Grid)
  return <TablesPage />;
}
