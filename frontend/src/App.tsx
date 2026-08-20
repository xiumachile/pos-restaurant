import { BrowserRouter } from "react-router-dom";
import { I18nextProvider } from "react-i18next";
import { useDatabaseInit } from "./hooks/useDatabaseInit";
import { DatabaseLoader } from "./components/system/DatabaseLoader";
import AppRoutes from "./router";
import i18n from "./i18n/config";

function AppContent() {
  return (
    <BrowserRouter>
      <I18nextProvider i18n={i18n}>
        <AppRoutes />
      </I18nextProvider>
    </BrowserRouter>
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
