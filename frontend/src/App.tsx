import { useEffect } from "react";
import { RouterProvider } from "react-router-dom";
import { I18nextProvider } from "react-i18next";
import { useDatabaseInit } from "./hooks/useDatabaseInit";
import { useSyncWorker } from "./hooks/useSyncWorker";
import { usePrintEngine } from "./hooks/usePrintEngine";
import { useAuthRefresh } from "./hooks/useAuthRefresh";
import { useSyncStore } from "./store/useSyncStore";
import { useCatalogSyncInvalidation } from "./hooks/useCatalog";
import { DatabaseLoader } from "./components/system/DatabaseLoader";
import { router } from "./router";
import i18n from "./i18n/config";
import { preloadAuthToken } from "./services/secureStorage";

function AppContent() {
  // Refresh automático del JWT cuando queda < 2 minutos
  useAuthRefresh();

  // 🔐 SEGURIDAD: Precargar token desde Tauri Store a cache síncrona
  // Esto garantiza que los interceptors de axios puedan acceder al token
  // sin tener que hacer await en cada request
  useEffect(() => {
    preloadAuthToken().catch((err) => {
      console.warn("[App] ⚠️ No se pudo precargar token:", err);
    });
  }, []);

  // Atajo Ctrl+Shift+O para toggle de modo offline simulado
  // Útil para testing cuando el backend corre en localhost
  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.ctrlKey && e.shiftKey && e.key.toLowerCase() === "o") {
        e.preventDefault();
        useSyncStore.getState().toggleSimulatedOffline();
      }
    };
    window.addEventListener("keydown", handleKeyDown);
    return () => window.removeEventListener("keydown", handleKeyDown);
  }, []);
  // Iniciar worker de sincronización en background
  useSyncWorker();
  // Iniciar PrintEngine (polling de PrintJobs cada 5s)
  usePrintEngine();
  // FIX: Invalidar queries de catálogo cuando cambia el estado de conexión
  // Esto garantiza que al pasar online↔offline, useCategories y useProducts
  // recarguen los datos desde SQLite (offline) o backend (online)
  useCatalogSyncInvalidation();

  return (
    <I18nextProvider i18n={i18n}>
      <RouterProvider router={router} />
    </I18nextProvider>
  );
}

function App() {
  const { isReady, isInitializing, error } = useDatabaseInit();

  return (
    <DatabaseLoader isInitializing={isInitializing} error={error}>
      {isReady && <AppContent />}
    </DatabaseLoader>
  );
}

export default App;
