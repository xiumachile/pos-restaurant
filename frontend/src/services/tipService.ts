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
