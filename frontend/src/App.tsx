import { useState, useEffect } from "react";
import { RouterProvider } from "react-router-dom";
import { I18nextProvider } from "react-i18next";
import { useDatabaseInit } from "./hooks/useDatabaseInit";
import { useSyncWorker } from "./hooks/useSyncWorker";
import { usePrintEngine } from "./hooks/usePrintEngine";
import { useAuthRefresh } from "./hooks/useAuthRefresh";
import { useSyncStore } from "./store/useSyncStore";
import { useThemeStore } from "./store/useThemeStore";
import { useCatalogSyncInvalidation } from "./hooks/useCatalog";
import { DatabaseLoader } from "./components/system/DatabaseLoader";
import { LanguageSetup } from "./components/LanguageSetup";
import { router } from "./router";
import i18n from "./i18n";
import { preloadAuthToken } from "./services/secureStorage";

function AppContent() {
  // 🌗 Aplicar tema oscuro/claro globalmente
  useEffect(() => {
    const root = window.document.documentElement;
    const theme = useThemeStore.getState().theme;
    if (theme === 'dark') {
      root.classList.add('dark');
    } else {
      root.classList.remove('dark');
    }
  }, []);

  // Refresh automático del JWT cuando queda < 2 minutos
  useAuthRefresh();

  // 🔐 SEGURIDAD: Precargar token desde Tauri Store a cache síncrona
  useEffect(() => {
    preloadAuthToken().catch((err) => {
      console.warn("[App] ⚠️ No se pudo precargar token:", err);
    });
  }, []);

  // Atajo Ctrl+Shift+O para toggle de modo offline simulado
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
  useCatalogSyncInvalidation();

  return (
    <I18nextProvider i18n={i18n}>
      <RouterProvider router={router} />
    </I18nextProvider>
  );
}

function App() {
  const [showLanguageSetup, setShowLanguageSetup] = useState(() => {
    return !localStorage.getItem('terminal_language');
  });

  const { isReady, isInitializing, error } = useDatabaseInit();

  // Si es la primera vez, mostrar selector de idioma
  if (showLanguageSetup) {
    return <LanguageSetup onComplete={() => setShowLanguageSetup(false)} />;
  }

  return (
    <DatabaseLoader isInitializing={isInitializing} error={error}>
      {isReady && <AppContent />}
    </DatabaseLoader>
  );
}

export default App;
