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
 * OPTIMIZACIONES APLICADAS:
 * - useCallback para estabilizar loadLocalOrders
 * - useRef para comparar cambios reales antes de setState
 * - Signature-based comparison evita re-renders innecesarios
 * - Sin logs de debug
 */
export function useTableOrders(tableUuid: string | null) {
  const [localOrders, setLocalOrders] = useState<OrderWithSource[]>([]);
  const previousSignatureRef = useRef<string>("");

  // useCallback estabiliza la referencia para evitar recreaciones
  const loadLocalOrders = useCallback(async () => {
    if (!tableUuid) {
      if (previousSignatureRef.current !== "") {
        previousSignatureRef.current = "";
        setLocalOrders([]);
      }
      return;
    }

    try {
      const pendingLocal = await OrderRepository.findPendingByTable(tableUuid);
      
      const adapted: OrderWithSource[] = [];
      for (const order of pendingLocal) {
        const items = await OrderRepository.findItemsByOrderLocalUuid(order.local_uuid);
        adapted.push(adaptLocalOrder(order, items));
      }
      
      // OPTIMIZACIÓN: Solo actualiza estado si hay cambios reales
      // Firma basada en uuids de pedidos para detectar cambios
      const signature = adapted.map(o => `${o.uuid}:${(o as any)._syncStatus || ''}`).join('|');
      
      if (signature !== previousSignatureRef.current) {
        previousSignatureRef.current = signature;
        setLocalOrders(adapted);
      }
    } catch (error) {
      console.error("[useTableOrders] Error cargando pedidos locales:", error);
    }
  }, [tableUuid]);

  useEffect(() => {
    if (!tableUuid) {
      previousSignatureRef.current = "";
      setLocalOrders([]);
      return;
    }

    // Cargar inmediatamente al montar
    loadLocalOrders();

    // Polling cada 10s (balance entre frescura y performance)
    const interval = setInterval(loadLocalOrders, 10000);
    return () => clearInterval(interval);
  }, [tableUuid, loadLocalOrders]);

  // Leer pedidos de la nube (React Query maneja cache y deduplicación)
  const cloudQuery = useQuery<Order[], Error>({
    queryKey: tableUuid ? KEY(tableUuid) : ["table-orders", "disabled"],
    queryFn: () => ordersService.listTableOrders(tableUuid!),
    enabled: !!tableUuid,
    refetchInterval: 15000, // Polling cada 15s (menos agresivo)
    staleTime: 5000,
  });

  // Fusionar: locales primero, cloud después (deduplicar por cloud_id)
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
