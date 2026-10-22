import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';

// Importación robusta de JSON (compatible con Vite/Webpack)
import * as esCLRaw from './locales/es-CL.json';
import * as zhCNRaw from './locales/zh-CN.json';

const esCL = (esCLRaw as any).default || esCLRaw;
const zhCN = (zhCNRaw as any).default || zhCNRaw;

const getTerminalLanguage = (): string => {
  const stored = localStorage.getItem('terminal_language');
  if (stored && stored.startsWith('zh')) return 'zh';
  return 'es'; // Default seguro
};

const targetLng = getTerminalLanguage();

i18n
  .use(initReactI18next)
  .init({
    resources: {
      es: { translation: esCL },
      'es-CL': { translation: esCL }, // Alias para evitar fallos de detección del navegador
      zh: { translation: zhCN },
      'zh-CN': { translation: zhCN }, // Alias
    },
    lng: targetLng,
    fallbackLng: 'es',
    supportedLngs: ['es', 'es-CL', 'zh', 'zh-CN'],
    interpolation: {
      escapeValue: false,
    },
    // Desactivar cualquier detección automática del navegador
    detection: {
      order: [], 
      caches: [],
    },
    
    // 🧹 LIMPIEZA: Solo activar modo debug en desarrollo
    debug: process.env.NODE_ENV === 'development',
    
    // 🔍 VALIDACIÓN: Detectar y reportar claves que no existen en el idioma activo
    missingKeyHandler: (lng, ns, key, fallbackValue) => {
      if (process.env.NODE_ENV === 'development') {
        console.warn(
          `[i18n] ⚠️ Clave de traducción faltante: "${key}" en idioma "${lng}". ` +
          `Se usará el fallback en español.`
        );
      }
    },
  });

export const setTerminalLanguage = (lang: 'es' | 'zh') => {
  localStorage.setItem('terminal_language', lang);
  i18n.changeLanguage(lang).catch((err) => {
    if (process.env.NODE_ENV === 'development') {
      console.error('[i18n] ❌ Error al cambiar idioma:', err);
    }
  });
};

export default i18n;
