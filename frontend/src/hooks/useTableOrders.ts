import { useQuery, useQueryClient } from "@tanstack/react-query";
import { useEffect, useState, useRef, useCallback } from "react";
import { ordersService } from "@/services/ordersService";
import { OrderRepository } from "@/db/repositories/OrderRepository";
import { adaptLocalOrder, type OrderWithSource } from "@/types/localOrderAdapter";
import type { Order } from "@/types/orders";

const KEY = (tableUuid: string) => ["table-orders", tableUuid];

/**
 * Hook para obtener pedidos activos de una mesa.
 * 
 * 🔀 Fusión de fuentes (offline-first):
 * 1. Pedidos locales con sync_status = pending/error (instantáneo)
 * 2. Pedidos de la nube (con polling cada 10s)
 * 3. Deduplicación: si un pedido local ya tiene cloud_id, no se muestra dos veces
 * 
 * Orden: locales primero (con badge "⏳ Sin sincronizar"), luego cloud.
 */
export function useTableOrders(tableUuid: string | null) {
  const [localOrders, setLocalOrders] = useState<OrderWithSource[]>([]);
  const previousOrdersRef = useRef<string>("");

  // 1. Leer pedidos locales pendientes (optimizado para evitar re-renders)
  const loadLocalOrders = useCallback(async () => {
    if (!tableUuid) {
      setLocalOrders([]);
      return;
    }

    try {
      const pendingLocal = await OrderRepository.findPendingByTable(tableUuid);
      
      // Cargar items de cada pedido y adaptar formato
      const adapted: OrderWithSource[] = [];
      for (const order of pendingLocal) {
        const items = await OrderRepository.findItemsByOrderLocalUuid(order.local_uuid);
        adapted.push(adaptLocalOrder(order, items));
      }
      
      // Solo actualizar estado si realmente cambió (evitar re-renders innecesarios)
      const signature = adapted.map(o => o.uuid).join(",");
      if (signature !== previousOrdersRef.current) {
        previousOrdersRef.current = signature;
        setLocalOrders(adapted);
      }
    } catch (error) {
      console.error("[useTableOrders] Error cargando pedidos locales:", error);
    }
  }, [tableUuid]);

  useEffect(() => {
    if (!tableUuid) {
      setLocalOrders([]);
      previousOrdersRef.current = "";
      return;
    }

    // Cargar inmediatamente
    loadLocalOrders();

    // Re-cargar cada 5 segundos (no 3) y solo si hay cambios reales
    const interval = setInterval(loadLocalOrders, 5000);
    return () => clearInterval(interval);
  }, [tableUuid, loadLocalOrders]);

  // Escuchar invalidaciones de React Query
  const queryClient = useQueryClient();
  useEffect(() => {
    if (!tableUuid) return;

    const unsubscribe = queryClient.getQueryCache().subscribe((event) => {
      if (event?.query?.queryKey?.[0] === "table-orders" && 
          event?.query?.queryKey?.[1] === tableUuid) {
        loadLocalOrders();
      }
    });

    return unsubscribe;
  }, [tableUuid, queryClient, loadLocalOrders]);

  // 2. Leer pedidos de la nube
  const cloudQuery = useQuery<Order[], Error>({
    queryKey: tableUuid ? KEY(tableUuid) : ["table-orders", "disabled"],
    queryFn: () => ordersService.listTableOrders(tableUuid!),
    enabled: !!tableUuid,
    refetchInterval: 10000,
    staleTime: 5000,
  });

  // 3. Fusionar: locales primero, cloud después (deduplicar por cloud_id)
  const cloudOrders = cloudQuery.data || [];
  const localCloudIds = new Set(
    localOrders
      .filter(o => o._isLocal && (o as any).uuid && !String((o as any).uuid).startsWith("TEMP"))
      .map(o => (o as any).uuid)
  );

  const deduplicatedCloud = cloudOrders.filter(
    cloudOrder => !localCloudIds.has(cloudOrder.uuid)
  );

  const merged: OrderWithSource[] = [...localOrders, ...deduplicatedCloud];

  return {
    ...cloudQuery,
    data: merged as Order[],
    hasLocalPending: localOrders.length > 0,
  };
}

export function useInvalidateTableOrders(tableUuid: string) {
  const queryClient = useQueryClient();
  return () => {
    queryClient.invalidateQueries({ queryKey: KEY(tableUuid) });
  };
}
