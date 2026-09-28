import { useTranslation } from 'react-i18next';
import { setTerminalLanguage } from '../i18n';

interface LanguageSetupProps {
  onComplete: () => void;
}

export function LanguageSetup({ onComplete }: LanguageSetupProps) {
  const { t } = useTranslation();

  const handleSelectLanguage = (lang: 'es' | 'zh') => {
    setTerminalLanguage(lang);
    onComplete();
  };

  return (
    <div className="min-h-screen bg-slate-900 flex items-center justify-center">
      <div className="bg-slate-800 rounded-xl p-8 max-w-md w-full">
        <h1 className="text-2xl font-bold text-white mb-6 text-center">
          Seleccione el idioma / 选择语言
        </h1>
        
        <div className="space-y-4">
          <button
            onClick={() => handleSelectLanguage('es')}
            className="w-full px-6 py-4 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-semibold transition-colors"
          >
            Español (Chile)
          </button>
          
          <button
            onClick={() => handleSelectLanguage('zh')}
            className="w-full px-6 py-4 bg-red-600 hover:bg-red-700 text-white rounded-lg font-semibold transition-colors"
          >
            中文 (简体)
          </button>
        </div>
      </div>
    </div>
  );
}
