import apiClient from "./apiClient";
import type {
  LoginRequest,
  LoginPosRequest,
  LoginResponse,
} from "@/types/auth";

export const authService = {
  async login(data: LoginRequest): Promise<LoginResponse> {
    const response = await apiClient.post<LoginResponse>("/auth/login", data);
    return response.data;
  },

  async loginWithPin(data: LoginPosRequest): Promise<LoginResponse> {
    const response = await apiClient.post<LoginResponse>("/auth/login/pos", data);
    return response.data;
  },

  async logout(): Promise<void> {
    try {
      await apiClient.post("/auth/logout");
    } catch {
      // Ignorar errores
    }
  },

  async me() {
    const response = await apiClient.get("/auth/me");
    return response.data;
  },
};
