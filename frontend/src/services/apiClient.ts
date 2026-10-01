import axios, { type InternalAxiosRequestConfig, type AxiosResponse, type AxiosError } from 'axios';
import { useAuthStore } from '@/store/useAuthStore';
import { validateRequestMoney, validateResponseMoney } from '@/lib/apiClientMoneyGuard';
import { getItemSync, updateSyncCache, setItem } from './secureStorage';

const API_URL = import.meta.env.VITE_API_URL || 'http://localhost:8000/api/v1';

const apiClient = axios.create({
  baseURL: API_URL,
  timeout: 30000,
  headers: {
    'Content-Type': 'application/json',
  },
});

// ═══════════════════════════════════════════════════════════════
// REQUEST INTERCEPTOR
// ═══════════════════════════════════════════════════════════════

apiClient.interceptors.request.use(
  (config) => {
    // P1-010: Generar Idempotency-Key automático para mutaciones si no existe
    const isMutation = ['post', 'put', 'patch', 'delete'].includes((config.method || '').toLowerCase());
    if (isMutation && !config.headers['Idempotency-Key']) {
      config.headers['Idempotency-Key'] = crypto.randomUUID();
    }
    // P1-007: Leer token de la fuente de verdad segura (caché RAM), no de Zustand (que no lo persiste)
    const token = getItemSync('access_token') || useAuthStore.getState().token;
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }

    // Agregar headers de tenant context
    const { user } = useAuthStore.getState();
    if (user?.company_id) {
      config.headers['X-Company-Id'] = user.company_id.toString();
    }
    if (user?.branch_id) {
      config.headers['X-Branch-Id'] = user.branch_id.toString();
    }

    // ═══════════════════════════════════════════════════════════
    // MONEY CONTRACT: Validar request ANTES de enviar (ADR-018)
    // ═══════════════════════════════════════════════════════════
    return validateRequestMoney(config);
  },
  (error) => {
    return Promise.reject(error);
  }
);

// ═══════════════════════════════════════════════════════════════
// RESPONSE INTERCEPTOR con retry automático en 401
// ═══════════════════════════════════════════════════════════════

let isRefreshing = false;
let failedQueue: Array<{
  resolve: (value?: unknown) => void;
  reject: (reason?: unknown) => void;
}> = [];

const processQueue = (error: AxiosError | null, token: string | null = null) => {
  failedQueue.forEach((prom) => {
    if (error) {
      prom.reject(error);
    } else {
      prom.resolve(token);
    }
  });
  failedQueue = [];
};

const refreshAccessToken = async (): Promise<string> => {
  const response = await axios.post(`${API_URL}/auth/refresh`);
  const newToken = response.data.access_token;
  
  // Actualizar token en cache síncrono (para interceptors)
  updateSyncCache('access_token', newToken);
  
  // Actualizar token en storage persistente (async, no bloquea)
  setItem('access_token', newToken);
  
  // Actualizar token en store
  const { user } = useAuthStore.getState();
  if (user) {
    await useAuthStore.getState().setAuth(user, newToken);
  }
  
  return newToken;
};

apiClient.interceptors.response.use(
  (response: AxiosResponse) => {
    // ═══════════════════════════════════════════════════════════
    // MONEY CONTRACT: Validar response ANTES de usar (ADR-018)
    // ═══════════════════════════════════════════════════════════
    return validateResponseMoney(response);
  },
  async (error: AxiosError) => {
    const originalRequest = error.config as InternalAxiosRequestConfig & {
      _retry?: boolean;
    };

    // Si es 401 y no es el endpoint de refresh y no hemos reintentado aún
    if (
      error.response?.status === 401 &&
      !originalRequest._retry &&
      !originalRequest.url?.includes('/auth/refresh')
    ) {
      // Si ya estamos refrescando, encolar esta request
      if (isRefreshing) {
        return new Promise((resolve, reject) => {
          failedQueue.push({ resolve, reject });
        })
          .then((token) => {
            originalRequest.headers.Authorization = `Bearer ${token}`;
            return apiClient(originalRequest);
          })
          .catch((err) => {
            return Promise.reject(err);
          });
      }

      originalRequest._retry = true;
      isRefreshing = true;

      try {
        console.log('[AuthRefresh] Token expirado, intentando refresh...');
        const newToken = await refreshAccessToken();
        console.log('[AuthRefresh] Token refrescado exitosamente');
        
        processQueue(null, newToken);
        
        // Reintentar la request original con el nuevo token
        originalRequest.headers.Authorization = `Bearer ${newToken}`;
        return apiClient(originalRequest);
      } catch (refreshError) {
        console.error('[AuthRefresh] Error refrescando token:', refreshError);
        processQueue(refreshError as AxiosError, null);
        
        // Si el refresh falla, limpiar sesión
        useAuthStore.getState().clearAuth();
        return Promise.reject(refreshError);
      } finally {
        isRefreshing = false;
      }
    }

    // Para otros errores, simplemente rechazar
    return Promise.reject(error);
  }
);

export { apiClient };
export default apiClient;
