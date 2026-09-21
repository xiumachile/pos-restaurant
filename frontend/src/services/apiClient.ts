import axios, { type InternalAxiosRequestConfig } from 'axios';
import { useAuthStore } from '@/store/useAuthStore';
import { validateRequestMoney, validateResponseMoney } from '@/lib/apiClientMoneyGuard';

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
    // Agregar Authorization header si hay token
    const token = useAuthStore.getState().token;
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
// RESPONSE INTERCEPTOR
// ═══════════════════════════════════════════════════════════════

apiClient.interceptors.response.use(
  (response) => {
    // ═══════════════════════════════════════════════════════════
    // MONEY CONTRACT: Validar response ANTES de usar (ADR-018)
    // ═══════════════════════════════════════════════════════════
    return validateResponseMoney(response);
  },
  (error) => {
    // Manejar errores de autenticación
    if (error.response?.status === 401) {
      useAuthStore.getState().clearAuth();
    }
    return Promise.reject(error);
  }
);

export { apiClient };
export default apiClient;
