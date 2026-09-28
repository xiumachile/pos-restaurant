import { useTranslation } from 'react-i18next';
import { setTerminalLanguage } from '../i18n';

/**
 * Selector de idioma discreto para el Header.
 * Permite cambiar entre Español y Chino Mandarín.
 * El cambio es inmediato y persiste en localStorage.
 */
export function LanguageSwitcher() {
  const { t, i18n } = useTranslation();
  const currentLang = i18n.language;

  const handleChange = (lang: 'es' | 'zh') => {
    console.log('[LanguageSwitcher] 🖱️ Click en idioma:', lang, '| Idioma actual:', currentLang);
    console.log('[LanguageSwitcher] 🧪 PRUEBA t(): auth.login en ES =', i18n.t('auth.login', { lng: 'es' }));
    console.log('[LanguageSwitcher] 🧪 PRUEBA t(): auth.login en ZH =', i18n.t('auth.login', { lng: 'zh' }));
    console.log('[LanguageSwitcher] 🧪 PRUEBA t(): auth.login en IDIOMA ACTUAL =', t('auth.login'));
    if (lang !== currentLang) {
      setTerminalLanguage(lang);
    } else {
      console.log('[LanguageSwitcher] ⏭️ Ya está en ese idioma, ignorando.');
    }
  };

  return (
    <div className="flex items-center gap-1 bg-slate-100 dark:bg-slate-700/50 rounded-lg p-1">
      <button
        onClick={() => handleChange('es')}
        className={`px-3 py-1.5 text-xs font-semibold rounded-md transition-all ${
          currentLang === 'es'
            ? 'bg-blue-600 text-white shadow-sm'
            : 'text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600'
        }`}
        aria-label="Español"
        title="Español"
      >
        ES
      </button>
      <button
        onClick={() => handleChange('zh')}
        className={`px-3 py-1.5 text-xs font-semibold rounded-md transition-all ${
          currentLang === 'zh'
            ? 'bg-red-600 text-white shadow-sm'
            : 'text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600'
        }`}
        aria-label="中文"
        title="中文 (简体)"
      >
        中文
      </button>
    </div>
  );
}
