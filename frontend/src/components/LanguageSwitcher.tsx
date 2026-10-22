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
    // Ignorar si ya está en el idioma seleccionado (evita re-render innecesario)
    if (lang === currentLang) return;
    setTerminalLanguage(lang);
  };

  return (
    <div
      className="flex items-center gap-1 bg-slate-100 dark:bg-slate-700/50 rounded-lg p-1"
      role="group"
      aria-label={t('language_switcher.switch_to_spanish').split(' ').slice(0, -1).join(' ')}
    >
      <button
        type="button"
        onClick={() => handleChange('es')}
        className={`px-3 py-1.5 text-xs font-semibold rounded-md transition-all ${
          currentLang === 'es'
            ? 'bg-blue-600 text-white shadow-sm'
            : 'text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600'
        }`}
        aria-label={t('language_switcher.switch_to_spanish')}
        aria-pressed={currentLang === 'es'}
        title={t('language_switcher.spanish')}
      >
        ES
      </button>
      <button
        type="button"
        onClick={() => handleChange('zh')}
        className={`px-3 py-1.5 text-xs font-semibold rounded-md transition-all ${
          currentLang === 'zh'
            ? 'bg-red-600 text-white shadow-sm'
            : 'text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600'
        }`}
        aria-label={t('language_switcher.switch_to_chinese')}
        aria-pressed={currentLang === 'zh'}
        title={t('language_switcher.chinese_simplified')}
      >
        中文
      </button>
    </div>
  );
}
