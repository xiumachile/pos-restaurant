import apiClient from './apiClient';

export interface OrderNumberingConfig {
  is_enabled: boolean;
  prefix: string;
  reset_frequency: 'daily' | 'monthly';
  current_sequence: number;
  last_reset_date: string | null;
}

export const orderNumberingService = {
  async get(): Promise<OrderNumberingConfig> {
    const response = await apiClient.get('/settings/order-numbering');
    return response.data.data;
  },

  async update(config: Partial<OrderNumberingConfig>): Promise<OrderNumberingConfig> {
    const response = await apiClient.put('/settings/order-numbering', config);
    return response.data.data;
  },

  async resetSequence(): Promise<{ current_sequence: number; last_reset_date: string }> {
    const response = await apiClient.post('/settings/order-numbering/reset');
    return response.data.data;
  },
};
