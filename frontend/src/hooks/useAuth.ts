import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { useAuthStore } from "@/store/useAuthStore";
import { authService } from "@/services/authService";
import type { LoginRequest, LoginPosRequest } from "@/types/auth";

export function useAuth() {
  const { user, isAuthenticated, setAuth, clearAuth } = useAuthStore();
  const navigate = useNavigate();
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const login = async (data: LoginRequest) => {
    setLoading(true);
    setError(null);
    try {
      const response = await authService.login(data);
      setAuth(response.user, response.access_token);
      navigate("/");
    } catch (err: any) {
      const message =
        err.response?.data?.message ||
        err.response?.data?.error ||
        "Error al iniciar sesión";
      setError(message);
      throw err;
    } finally {
      setLoading(false);
    }
  };

  const loginWithPin = async (data: LoginPosRequest) => {
    setLoading(true);
    setError(null);
    try {
      const response = await authService.loginWithPin(data);
      setAuth(response.user, response.access_token);
      navigate("/");
    } catch (err: any) {
      let message = "Error al iniciar sesión";
      const status = err.response?.status;
      const data = err.response?.data;
      
      // Errores de red/infraestructura
      if (!err.response || err.code === 'ERR_NETWORK' || err.code === 'ECONNABORTED') {
        message = "No se pudo conectar con el servidor. Verifica que el backend esté corriendo.";
      }
      // Errores del servidor (5xx)
      else if (status >= 500) {
        message = "Error del servidor. Intenta nuevamente en unos segundos.";
      }
      // Credenciales inválidas (401)
      else if (status === 401) {
        message = data?.message || "PIN inválido. Intenta nuevamente.";
      }
      // Errores de validación (422)
      else if (status === 422) {
        message = data?.message || "Datos inválidos.";
      }
      // Otros errores
      else {
        message = data?.message || data?.error || `Error ${status || 'desconocido'}`;
      }
      
      setError(message);
      console.error("[useAuth] Login error:", { status, code: err.code, message });
      throw err;
    } finally {
      setLoading(false);
    }
  };

  const logout = async () => {
    await authService.logout();
    clearAuth();
    navigate("/login");
  };

  return { user, isAuthenticated, loading, error, login, loginWithPin, logout };
}
