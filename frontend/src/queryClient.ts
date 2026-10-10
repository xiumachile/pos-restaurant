import { QueryClient } from "@tanstack/react-query";

/**
 * QueryClient global de la aplicación.
 * 
 * Se exporta desde aquí para que pueda ser usado por:
 * - main.tsx (para QueryClientProvider)
 * - SyncStrategies (para invalidación manual después de sincronizar)
 * - Cualquier servicio fuera del árbol de componentes React
 */
export const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      retry: 2,
      staleTime: 10000,
    },
  },
});
