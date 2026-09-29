import './i18n';
import React from "react";
import ReactDOM from "react-dom/client";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import App from "./App";
import "./index.css";
import "./i18n";
import { preloadAuthToken } from "@/services/secureStorage";
import { useAuthStore } from "@/store/useAuthStore";

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      retry: 2,
      staleTime: 10000,
    },
  },
});

// Inicialización asíncrona para restaurar el token de forma segura antes de renderizar
async function initializeApp() {
  console.log("[main] 🔐 Precargando token de autenticación...");
  await preloadAuthToken();
  
  // Restaurar estado de autenticación si existe token y usuario en localStorage
  const token = localStorage.getItem("access_token") || (await import("@/services/secureStorage").then(m => m.getItem("access_token")));
  const authStorage = localStorage.getItem("auth-storage");
  
  if (token && authStorage) {
    try {
      const parsed = JSON.parse(authStorage);
      if (parsed.state?.user) {
        useAuthStore.getState().setAuth(parsed.state.user, token);
        console.log("[main] ✅ Sesión restaurada exitosamente");
      }
    } catch (e) {
      console.warn("[main] ⚠️ No se pudo restaurar la sesión:", e);
    }
  }

  ReactDOM.createRoot(document.getElementById("root") as HTMLElement).render(
    <React.StrictMode>
      <QueryClientProvider client={queryClient}>
        <App />
      </QueryClientProvider>
    </React.StrictMode>
  );
}

initializeApp();
