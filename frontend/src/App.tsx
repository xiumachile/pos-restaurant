import { RouterProvider } from "react-router-dom";
import { I18nextProvider } from "react-i18next";
import { useDatabaseInit } from "./hooks/useDatabaseInit";
import { useSyncWorker } from "./hooks/useSyncWorker";
import { usePrintEngine } from "./hooks/usePrintEngine";
import { useAuthRefresh } from "./hooks/useAuthRefresh";
import { DatabaseLoader } from "./components/system/DatabaseLoader";
import { router } from "./router";
import i18n from "./i18n/config";

function AppContent() {
  // Refresh automático del JWT cuando queda < 2 minutos
  useAuthRefresh();
  // Iniciar worker de sincronización en background
  useSyncWorker();
  // Iniciar PrintEngine (polling de PrintJobs cada 5s)
  usePrintEngine();

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
