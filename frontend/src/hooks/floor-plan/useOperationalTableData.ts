import { useMemo } from 'react';
import { useRestaurantTables } from './useRestaurantTables';
import { useQuery } from '@tanstack/react-query';
import { ordersService } from '@/services/ordersService';
import type { OperationalTable } from '@/services/floorPlanService';

export interface OperationalTableData {
  status: 'available' | 'occupied' | 'reserved' | 'maintenance' | 'blocked';
  tableNumber: string;
  capacity: number;
  waiterName?: string | null;
  totalAmount?: number | null;
  openedAt?: string | null;
  timeElapsedMinutes?: number | null;
  guestCount?: number | null;
  hasPendingItems?: boolean;
  currentOrderId?: number | null;
}

/**
 * Hook que combina datos de mesas operativas con sus pedidos activos.
 * Devuelve un mapa: tableUuid -> OperationalTableData
 * 
 * Actualiza cada 10 segundos para reflejar cambios de estado en tiempo real.
 */
export function useOperationalTableData() {
  const { tables, loading: tablesLoading } = useRestaurantTables();

  // Query para traer todos los pedidos activos de la sucursal
  const { data: allOrders = [], isLoading: ordersLoading } = useQuery({
    queryKey: ['all-active-orders'],
    queryFn: async () => {
      try {
        // Intentar traer pedidos activos del backend
        // Si el endpoint no existe, usar lista vacía
        const response = await import('@/services/apiClient').then(m => 
          m.default.get('/orders', { params: { status: ['draft', 'confirmed', 'preparing', 'ready', 'served'] } })
        );
        return response.data?.data ?? response.data ?? [];
      } catch (err) {
        console.warn('[useOperationalTableData] Error cargando pedidos:', err);
        return [];
      }
    },
    refetchInterval: 10000, // Actualizar cada 10 segundos
    staleTime: 5000,
  });

  // Construir mapa de pedidos por table_id
  const ordersByTableId = useMemo(() => {
    const map = new Map<number, any>();
    for (const order of allOrders) {
      if (order.table_id) {
        // Si hay múltiples pedidos en la misma mesa, usar el más reciente
        const existing = map.get(order.table_id);
        if (!existing || new Date(order.created_at) > new Date(existing.created_at)) {
          map.set(order.table_id, order);
        }
      }
    }
    return map;
  }, [allOrders]);

  // Construir mapa final: tableUuid -> OperationalTableData
  const operationalDataMap = useMemo(() => {
    const map = new Map<string, OperationalTableData>();

    for (const table of tables) {
      const order = table.current_order_id ? ordersByTableId.get(table.current_order_id) : null;
      
      let timeElapsedMinutes: number | null = null;
      if (order?.created_at) {
        const diff = Date.now() - new Date(order.created_at).getTime();
        timeElapsedMinutes = Math.floor(diff / 60000);
      }

      map.set(table.uuid, {
        status: table.status as OperationalTableData['status'],
        tableNumber: table.table_number,
        capacity: table.capacity,
        waiterName: order?.waiter_name ?? null,
        totalAmount: order?.total_amount ?? null,
        openedAt: order?.created_at ?? null,
        timeElapsedMinutes,
        guestCount: order?.guest_count ?? null,
        hasPendingItems: order?.has_pending_items ?? false,
        currentOrderId: table.current_order_id ?? null,
      });
    }

    return map;
  }, [tables, ordersByTableId]);

  return {
    operationalDataMap,
    isLoading: tablesLoading || ordersLoading,
  };
}
