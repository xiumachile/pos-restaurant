import apiClient from "./apiClient";
import type { TipPolicy } from "@/types/tips";

interface SingleResponse<T> {
  data: T;
}

export const tipService = {
  async getPolicy(): Promise<TipPolicy> {
    const response = await apiClient.get<SingleResponse<TipPolicy>>(
      "/cashier/tip-policy"
    );
    return (response.data as any).data;
  },

  async updatePolicy(policy: Partial<TipPolicy>): Promise<TipPolicy> {
    const response = await apiClient.put<SingleResponse<TipPolicy>>(
      "/cashier/tip-policy",
      policy
    );
    return (response.data as any).data;
  },
};

import type { TipPayout, TipSummary, Waiter } from "@/types/tips";

export const tipPayoutService = {
  async listPayouts(): Promise<TipPayout[]> {
    const response = await apiClient.get<{ data: TipPayout[] }>(
      "/cashier/tip-payouts"
    );
    return (response.data as any).data || [];
  },

  async createPayout(payload: {
    waiter_id: number;
    amount: number;
    payment_method: string;
    notes?: string;
  }): Promise<TipPayout> {
    const response = await apiClient.post<{ data: TipPayout }>(
      "/cashier/tip-payouts",
      payload
    );
    return (response.data as any).data;
  },

  async voidPayout(uuid: string): Promise<void> {
    await apiClient.delete(`/cashier/tip-payouts/${uuid}`);
  },

  async getSummary(): Promise<TipSummary | null> {
    const response = await apiClient.get<{ data: TipSummary | null }>(
      "/cashier/tips/summary"
    );
    return (response.data as any).data;
  },

  async listWaiters(): Promise<Waiter[]> {
    const response = await apiClient.get<{ data: Waiter[] }>(
      "/cashier/waiters"
    );
    return (response.data as any).data || [];
  },
};

import type { TipsByWaiterData, GeneratePayoutsResponse } from "@/types/tips";

export const tipWizardService = {
  async getTipsByWaiter(): Promise<TipsByWaiterData | null> {
    const response = await apiClient.get<{ data: TipsByWaiterData | null }>(
      "/cashier/tips/by-waiter"
    );
    return (response.data as any).data;
  },

  async generatePayouts(): Promise<GeneratePayoutsResponse> {
    const response = await apiClient.post<{ data: GeneratePayoutsResponse }>(
      "/cashier/tips/generate-payouts"
    );
    return (response.data as any).data;
  },
};
