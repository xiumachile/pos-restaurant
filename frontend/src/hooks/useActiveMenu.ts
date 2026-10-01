import { useQuery } from "@tanstack/react-query";
import { menuActiveService, type ActiveMenuResponse } from "@/services/menuActiveService";
import { useActiveChannelStore, type ChannelType } from "@/stores/useActiveChannelStore";

/**
 * Hook que retorna la carta activa según el canal actual.
 * 
 * - Se refresca automáticamente cuando cambia el canal (queryKey incluye channel)
 * - Cache de 30 segundos por canal
 * - Retorna null si no hay carta activa (404 del backend)
 * - enabled: siempre true (el POS siempre necesita una carta)
 */
export function useActiveMenu() {
  const channel = useActiveChannelStore((s) => s.channel);

  return useQuery<ActiveMenuResponse | null, Error>({
    queryKey: ["pos", "active-menu", channel],
    queryFn: () => menuActiveService.getActive(channel),
    staleTime: 30 * 1000,
    retry: 1,
  });
}
