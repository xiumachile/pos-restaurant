import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';

// Importación robusta de JSON
import * as esCLRaw from './locales/es-CL.json';
import * as zhCNRaw from './locales/zh-CN.json';

const esCL = (esCLRaw as any).default || esCLRaw;
const zhCN = (zhCNRaw as any).default || zhCNRaw;

console.log('🔥 [i18n] esCL cargado:', esCL);
console.log('🔥 [i18n] zhCN cargado:', zhCN);
console.log('🔑 [i18n] zhCN.auth.login =', (zhCN as any).auth?.login);
console.log('🔑 [i18n] esCL.auth.login =', (esCL as any).auth?.login);

const getTerminalLanguage = (): string => {
  const stored = localStorage.getItem('terminal_language');
  if (stored && stored.startsWith('zh')) return 'zh';
  return 'es'; // Default seguro
};

const targetLng = getTerminalLanguage();
console.log('🎯 [i18n] Idioma objetivo forzado:', targetLng);

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
  });

console.log('✅ [i18n] Inicializado. i18n.language es ahora:', i18n.language);

export const setTerminalLanguage = (lang: 'es' | 'zh') => {
  console.log('[i18n] 🔄 Solicitando cambio de idioma a:', lang);
  localStorage.setItem('terminal_language', lang);
  i18n.changeLanguage(lang).then(() => {
    console.log('[i18n] ✅ Idioma cambiado exitosamente. i18n.language ahora es:', i18n.language);
  }).catch((err) => {
    console.error('[i18n] ❌ Error al cambiar idioma:', err);
  });
};

export default i18n;
