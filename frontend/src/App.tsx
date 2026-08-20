import { RouterProvider } from "react-router-dom";
import { I18nextProvider } from "react-i18next";
import { useDatabaseInit } from "./hooks/useDatabaseInit";
import { useSyncWorker } from "./hooks/useSyncWorker";
import { DatabaseLoader } from "./components/system/DatabaseLoader";
import { router } from "./router";
import i18n from "./i18n/config";

function AppContent() {
  // Iniciar worker de sincronización en background
  useSyncWorker();

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
