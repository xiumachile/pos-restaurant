import { RouterProvider } from "react-router-dom";
import { I18nextProvider } from "react-i18next";
import { useDatabaseInit } from "./hooks/useDatabaseInit";
import { DatabaseLoader } from "./components/system/DatabaseLoader";
import { router } from "./router";
import i18n from "./i18n/config";

function App() {
  const { isReady, isInitializing, error } = useDatabaseInit();

  return (
    <DatabaseLoader isInitializing={isInitializing} error={error}>
      {isReady && (
        <I18nextProvider i18n={i18n}>
          <RouterProvider router={router} />
        </I18nextProvider>
      )}
    </DatabaseLoader>
  );
}

export default App;
