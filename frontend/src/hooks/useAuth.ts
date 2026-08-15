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
      const message =
        err.response?.data?.message ||
        err.response?.data?.error ||
        "PIN inválido";
      setError(message);
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
