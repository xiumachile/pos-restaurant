import { useEffect, useRef } from "react";
import { useAuthStore } from "@/store/useAuthStore";
import apiClient from "@/services/apiClient";

/**
 * Hook que implementa refresh automático del JWT.
 * Refresca el token cuando quedan < 2 minutos de validez.
 * Esto previene que la sesión se cierre mientras el usuario está activo.
 */
export function useAuthRefresh() {
  const token = useAuthStore((s) => s.token);
  const user = useAuthStore((s) => s.user);
  const setAuth = useAuthStore((s) => s.setAuth);
  const clearAuth = useAuthStore((s) => s.clearAuth);
  const isRefreshing = useRef(false);

  useEffect(() => {
    if (!token || !user) return;

    const checkAndRefresh = async () => {
      // Evitar múltiples refresh simultáneos
      if (isRefreshing.current) return;

      try {
        // Decodificar JWT para obtener exp (sin librerías externas)
        const tokenParts = token.split(".");
        if (tokenParts.length !== 3) return;

        const payload = JSON.parse(atob(tokenParts[1]));
        const expTimestamp = payload.exp * 1000; // Convertir a milliseconds
        const now = Date.now();
        const timeUntilExpiry = expTimestamp - now;

        console.log(`[AuthRefresh] Token expira en ${Math.round(timeUntilExpiry / 1000)}s`);

        // Si quedan menos de 2 minutos, refrescar
        if (timeUntilExpiry > 0 && timeUntilExpiry < 120000) {
          isRefreshing.current = true;
          console.log("[AuthRefresh] Refrescando token...");
          
          try {
            const response = await apiClient.post("/auth/refresh");
            const newToken = response.data.access_token;
            
            // Actualizar token manteniendo el mismo usuario
            setAuth(user, newToken);
            
            console.log("[AuthRefresh] Token refrescado exitosamente");
          } catch (refreshError) {
            console.error("[AuthRefresh] Error refrescando token:", refreshError);
            // Si el refresh falla por 401, limpiar sesión
            if ((refreshError as any)?.response?.status === 401) {
              clearAuth();
            }
          } finally {
            isRefreshing.current = false;
          }
        }
      } catch (error) {
        console.error("[AuthRefresh] Error al decodificar token:", error);
      }
    };

    // Verificar cada 30 segundos
    const interval = setInterval(checkAndRefresh, 30000);
    
    // Verificar inmediatamente al montar
    checkAndRefresh();

    return () => clearInterval(interval);
  }, [token, user, setAuth, clearAuth]);
}
