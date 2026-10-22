import { useQuery, useQueryClient } from "@tanstack/react-query";
import { useEffect, useState, useRef, useCallback } from "react";
import { ordersService } from "@/services/ordersService";
import { OrderRepository } from "@/db/repositories/OrderRepository";
import { adaptLocalOrder, type OrderWithSource } from "@/types/localOrderAdapter";
import type { Order } from "@/types/orders";

const KEY = (tableUuid: string) => ["table-orders", tableUuid];

export function useTableOrders(tableUuid: string | null) {
  // Todos los hooks están en el nivel superior, incondicionalmente (Regla de React)
  const [localOrders, setLocalOrders] = useState<OrderWithSource[]>([]);
  const [localStatuses, setLocalStatuses] = useState<Map<string, string>>(new Map());
  const previousSignatureRef = useRef<string>("");

  const loadLocalOrders = useCallback(async () => {
    if (!tableUuid) {
      if (previousSignatureRef.current !== "") {
        previousSignatureRef.current = "";
        setLocalOrders([]);
        setLocalStatuses(new Map());
      }
      return;
    }

    try {
      // 1. Cargar pendientes (para mostrar en UI)
      const pendingLocal = await OrderRepository.findPendingByTable(tableUuid);
      const adapted: OrderWithSource[] = [];
      for (const order of pendingLocal) {
        const items = await OrderRepository.findItemsByOrderLocalUuid(order.local_uuid);
        adapted.push(adaptLocalOrder(order, items));
      }
      
      const signature = adapted.map(o => `${o.uuid}:${(o as any)._syncStatus || ''}`).join('|');
      if (signature !== previousSignatureRef.current) {
        previousSignatureRef.current = signature;
        setLocalOrders(adapted);
      }

      // 2. Cargar TODOS los estados locales de esta mesa (para filtrar la nube)
      const statuses = await OrderRepository.getLocalOrderStatusesByTable(tableUuid);
      setLocalStatuses(statuses);

    } catch (error) {
      console.error("[useTableOrders] Error cargando pedidos locales:", error);
    }
  }, [tableUuid]);

  useEffect(() => {
    if (!tableUuid) {
      previousSignatureRef.current = "";
      setLocalOrders([]);
      setLocalStatuses(new Map());
      return;
    }

    loadLocalOrders();
    const interval = setInterval(loadLocalOrders, 10000);
    return () => clearInterval(interval);
  }, [tableUuid, loadLocalOrders]);

  const cloudQuery = useQuery<Order[], Error>({
    queryKey: tableUuid ? KEY(tableUuid) : ["table-orders", "disabled"],
    queryFn: () => ordersService.listTableOrders(tableUuid!),
    enabled: !!tableUuid,
    refetchInterval: 15000,
    staleTime: 5000,
  });

  const cloudOrders = cloudQuery.data || [];
  
  const localCloudIds = new Set(
    localOrders
      .filter(o => o._isLocal && (o as any).uuid && !String((o as any).uuid).startsWith("TEMP"))
      .map(o => (o as any).uuid)
  );

  // 🛡️ FILTRO OFFLINE-FIRST: La verdad local terminal prevalece sobre la nube
  const deduplicatedCloud = cloudOrders.filter(cloudOrder => {
    // A. Si ya está en la lista de pendientes locales, no duplicar
    if (localCloudIds.has(cloudOrder.uuid)) {
      return false;
    }

    // B. CRÍTICO: Si localmente tenemos registro de esta orden y está en estado terminal,
    // IGNORAMOS la versión de la nube (que podría estar desactualizada por fallo de sync).
    const localStatus = localStatuses.get(cloudOrder.uuid);
    if (localStatus && ['paid', 'closed', 'cancelled'].includes(localStatus)) {
      console.log(`[useTableOrders] 🛡️ Offline-First: Ignorando orden nube ${cloudOrder.uuid} (status nube: ${cloudOrder.status}) porque localmente ya está: ${localStatus}`);
      return false;
    }

    // C. Filtro de seguridad estándar
    if (cloudOrder.status === 'paid' || cloudOrder.status === 'closed' || cloudOrder.status === 'cancelled') {
      return false;
    }

    return true;
  });

  const merged: OrderWithSource[] = [...localOrders, ...deduplicatedCloud];

  // Logs de diagnóstico
  useEffect(() => {
    console.log(`[useTableOrders] 📊 Mesa: ${tableUuid}`);
    console.log(`[useTableOrders] 📱 Locales (pendientes):`, localOrders.map((o: any) => ({ uuid: o.uuid, status: o.status })));
    console.log(`[useTableOrders] ☁️ Nube (RAW):`, cloudOrders.map((o: any) => ({ uuid: o.uuid, status: o.status })));
    console.log(`[useTableOrders] 🔄 Fusionados finales (Activos):`, merged.map((o: any) => ({ uuid: o.uuid, status: o.status, source: o._isLocal ? 'local' : 'cloud' })));
  }, [tableUuid, localOrders, cloudOrders, merged]);

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
