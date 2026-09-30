import { useTranslation } from 'react-i18next';
import { useSettingsStore } from '../../store/useSettingsStore';
import { Settings, DollarSign, Clock, CheckCircle2 } from 'lucide-react';

/**
 * Página de configuración general del sistema.
 * Permite personalizar preferencias locales del usuario.
 */
export function GeneralSettingsPage() {
  const { t } = useTranslation();
  const {
    showChangePopup,
    changePopupDuration,
    setShowChangePopup,
    setChangePopupDuration,
  } = useSettingsStore();

  return (
    <div className="max-w-3xl mx-auto space-y-6">
      <div>
        <h1 className="text-3xl font-bold text-slate-900 dark:text-white mb-2 flex items-center gap-3">
          <Settings className="w-8 h-8 text-orange-500" />
          Configuración General
        </h1>
        <p className="text-slate-600 dark:text-slate-400">
          Personaliza el comportamiento del sistema según tus necesidades
        </p>
      </div>

      {/* Sección: Popup de Vuelto */}
      <div className="bg-white dark:bg-slate-800 rounded-xl p-6 border border-slate-200 dark:border-slate-700 shadow-sm">
        <h2 className="text-xl font-semibold text-slate-900 dark:text-white mb-4 flex items-center gap-2">
          <DollarSign className="w-5 h-5 text-green-500" />
          Popup de Vuelto
        </h2>
        
        <div className="space-y-4">
          {/* Toggle */}
          <div className="flex items-center justify-between">
            <div className="flex-1">
              <label className="text-slate-900 dark:text-white font-medium block">
                Mostrar popup al cobrar
              </label>
              <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">
                Muestra un popup grande con el monto del vuelto cuando se completa un pago en efectivo
              </p>
            </div>
            <button
              onClick={() => setShowChangePopup(!showChangePopup)}
              className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors flex-shrink-0 ml-4 ${
                showChangePopup ? 'bg-green-500' : 'bg-slate-300 dark:bg-slate-600'
              }`}
              role="switch"
              aria-checked={showChangePopup}
              aria-label="Habilitar popup de vuelto"
            >
              <span
                className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${
                  showChangePopup ? 'translate-x-6' : 'translate-x-1'
                }`}
              />
            </button>
          </div>

          {/* Duración */}
          {showChangePopup && (
            <div className="ml-6 pl-6 border-l-2 border-slate-200 dark:border-slate-700 space-y-3">
              <label className="block text-slate-900 dark:text-white font-medium">
                <Clock className="w-4 h-4 inline mr-1" />
                Duración del popup (segundos)
              </label>
              <div className="flex items-center gap-3 flex-wrap">
                <input
                  type="number"
                  min="1"
                  max="30"
                  value={changePopupDuration}
                  onChange={(e) => setChangePopupDuration(parseInt(e.target.value) || 5)}
                  className="w-24 px-3 py-2 bg-slate-50 dark:bg-slate-700 border border-slate-300 dark:border-slate-600 rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-green-500"
                  aria-label="Duración en segundos"
                />
                <span className="text-slate-600 dark:text-slate-400">segundos</span>
                <div className="flex gap-2 ml-4">
                  {[3, 5, 10].map((seconds) => (
                    <button
                      key={seconds}
                      onClick={() => setChangePopupDuration(seconds)}
                      className={`px-3 py-1 rounded-lg text-sm font-medium transition-colors ${
                        changePopupDuration === seconds
                          ? 'bg-green-500 text-white'
                          : 'bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600'
                      }`}
                    >
                      {seconds}s
                    </button>
                  ))}
                </div>
              </div>
              <p className="text-xs text-slate-500 dark:text-slate-400">
                El popup se cerrará automáticamente después de {changePopupDuration} segundos
              </p>
            </div>
          )}
        </div>
      </div>

      {/* Preview */}
      <div className="bg-slate-50 dark:bg-slate-800/50 rounded-xl p-6 border border-slate-200 dark:border-slate-700">
        <h3 className="text-lg font-semibold text-slate-900 dark:text-white mb-3 flex items-center gap-2">
          <CheckCircle2 className="w-5 h-5 text-blue-500" />
          Vista Previa
        </h3>
        <div className="bg-white dark:bg-slate-900 rounded-lg p-4 text-slate-700 dark:text-slate-300 text-sm">
          {showChangePopup ? (
            <>
              <div className="flex items-start gap-2 mb-2">
                <span className="text-green-500">✅</span>
                <span>El popup <strong>se mostrará</strong> cuando haya vuelto al cobrar</span>
              </div>
              <div className="flex items-start gap-2">
                <span className="text-blue-500">⏱️</span>
                <span>Se cerrará automáticamente en <strong>{changePopupDuration} segundos</strong></span>
              </div>
            </>
          ) : (
            <div className="flex items-start gap-2">
              <span className="text-slate-400">❌</span>
              <span>El popup <strong>no se mostrará</strong> al cobrar</span>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}

export default GeneralSettingsPage;
