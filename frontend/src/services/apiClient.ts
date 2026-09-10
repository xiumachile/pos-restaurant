import { v4 as uuidv4 } from "uuid";
import axios, { AxiosError, InternalAxiosRequestConfig } from "axios";
import { useSyncStore } from "@/store/useSyncStore";
import { getItemSync } from "@/services/secureStorage";
import { useAuthStore } from "@/store/useAuthStore";

const API_URL = import.meta.env.VITE_API_URL || "http://localhost:8000/api/v1";

export const apiClient = axios.create({
  baseURL: API_URL,
  headers: {
    "Content-Type": "application/json",
    Accept: "application/json",
  },
  timeout: 15000,
});

// SIMULATED OFFLINE: Rechazar requests cuando el usuario activa modo offline simulado
// Esto permite probar flujos offline incluso cuando el backend local está activo.
// Activar con Ctrl+Shift+O (atajo global en App.tsx)
apiClient.interceptors.request.use((config) => {
  const simulatedOffline = useSyncStore.getState().simulatedOffline;
  if (simulatedOffline) {
    console.warn(`[apiClient] 🧪 SIMULATED OFFLINE: Rechazando ${config.method?.toUpperCase()} ${config.url}`);
    return Promise.reject({
      message: "Network Error (simulated offline)",
      code: "ERR_NETWORK",
      isSimulatedOffline: true,
    });
  }
  return config;
});

// Idempotencia por defecto (Principio #7): toda mutación lleva Idempotency-Key
// Si el caller ya envía su propia clave estable (createOrder/createPayment), se respeta.
apiClient.interceptors.request.use((config) => {
  const method = (config.method || "").toUpperCase();
  if (["POST", "PUT", "PATCH", "DELETE"].includes(method)) {
    const headers: any = config.headers || {};
    if (!headers["Idempotency-Key"]) {
      headers["Idempotency-Key"] = uuidv4();
    }
  }
  return config;
});

// Interceptor request: inyectar JWT desde secureStorage (con cache síncrona)
apiClient.interceptors.request.use(
  (config: InternalAxiosRequestConfig) => {
    // Usar versión síncrona (desde cache o localStorage como fallback)
    const token = getItemSync("access_token");
    if (token && config.headers) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => Promise.reject(error)
);

// Interceptor response: manejar 401
apiClient.interceptors.response.use(
  (response) => response,
  async (error: AxiosError) => {
    if (error.response?.status === 401) {
      // Limpiar auth vía store (que limpia secureStorage también)
      useAuthStore.getState().clearAuth();
      
      if (!window.location.pathname.includes("/login")) {
        window.location.href = "/login";
      }
    }
    return Promise.reject(error);
  }
);

export default apiClient;
