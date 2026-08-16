import i18n from "i18next";
import { initReactI18next } from "react-i18next";
import esCL from "./locales/es-CL.json";
import zhCN from "./locales/zh-CN.json";

i18n.use(initReactI18next).init({
  resources: {
    "es-CL": { translation: esCL },
    "zh-CN": { translation: zhCN },
  },
  lng: "es-CL",
  fallbackLng: "es-CL",
  interpolation: {
    escapeValue: false,
  },
});

export default i18n;
