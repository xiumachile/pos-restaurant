import { useQuery } from "@tanstack/react-query";
import { menuActiveService, type ActiveMenuResponse } from "@/services/menuActiveService";
import type { ChannelType } from "@/stores/useActiveChannelStore";

/**
 * Hook que retorna la carta activa según el canal indicado.
 * 
 * - Se refresca automáticamente cuando cambia el canal (queryKey incluye channel)
 * - Cache de 30 segundos por canal
 * - Retorna null si no hay carta activa (404 del backend)
 * 
 * Fase 2: El canal ahora se pasa como parámetro (viene del cart del pedido),
 * no se lee del store global. Esto garantiza que cada pedido use su propio canal.
 */
export function useActiveMenu(channel: ChannelType) {
  return useQuery<ActiveMenuResponse | null, Error>({
    queryKey: ["pos", "active-menu", channel],
    queryFn: () => menuActiveService.getActive(channel),
    staleTime: 30 * 1000,
    retry: 1,
    enabled: !!channel,
  });
}
