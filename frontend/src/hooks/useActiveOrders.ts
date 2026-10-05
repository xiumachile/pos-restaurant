import { useQuery } from '@tanstack/react-query';
import apiClient from '@/services/apiClient';
import type { Order } from '@/types/orders';

export type ChannelFilter = 'all' | 'delivery' | 'takeout';

interface ActiveOrdersResponse {
  data: Order[];
}

export function useActiveOrders(channel: ChannelFilter = 'all') {
  return useQuery({
    queryKey: ['orders', 'active', channel],
    queryFn: async () => {
      const params = channel !== 'all' ? { channel } : {};
      const response = await apiClient.get<ActiveOrdersResponse>('/orders/active', { params });
      return response.data.data;
    },
    refetchInterval: 10000, // Actualizar cada 10 segundos
    staleTime: 5000,
  });
}
