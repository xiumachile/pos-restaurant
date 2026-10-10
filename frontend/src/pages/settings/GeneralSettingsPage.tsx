import { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { useOrderNumberingConfig } from '@/hooks/useOrderNumberingConfig';
import { Save, RotateCcw, Hash, Calendar, AlertCircle, Loader2 } from 'lucide-react';
import { useSettingsStore } from '../../store/useSettingsStore';
import { Settings, DollarSign, Clock, CheckCircle2 } from 'lucide-react';

/**
 * Página de configuración general del sistema.
 * Permite personalizar preferencias locales del usuario.
 * Soporte bilingüe: español (es-CL) y chino (zh-CN)
 */
export function GeneralSettingsPage() {
  const { config: numberingConfig, updateConfig, isUpdating, resetSequence, isResetting } = useOrderNumberingConfig();
  const [numberingForm, setNumberingForm] = useState({
    is_enabled: false,
    prefix: 'ORD',
    reset_frequency: 'daily' as 'daily' | 'monthly',
  });
  const [numberingHasChanges, setNumberingHasChanges] = useState(false);

  // Cargar configuración de numeración
  useEffect(() => {
    if (numberingConfig) {
      setNumberingForm({
        is_enabled: numberingConfig.is_enabled,
        prefix: numberingConfig.prefix,
        reset_frequency: numberingConfig.reset_frequency,
      });
    }
  }, [numberingConfig]);

  // Detectar cambios
  useEffect(() => {
    if (numberingConfig) {
      const changed =
        numberingForm.is_enabled !== numberingConfig.is_enabled ||
        numberingForm.prefix !== numberingConfig.prefix ||
        numberingForm.reset_frequency !== numberingConfig.reset_frequency;
      setNumberingHasChanges(changed);
    }
  }, [numberingForm, numberingConfig]);

  const handleSaveNumbering = () => {
    updateConfig(numberingForm);
  };

  const handleResetNumberingSequence = () => {
    if (confirm(t('settings.order_numbering.confirm_reset', '¿Estás seguro de resetear la secuencia a 1?'))) {
      resetSequence();
    }
  };

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
          {t("settings.title")} - {t("settings.general_title")}
        </h1>
        <p className="text-slate-600 dark:text-slate-400">
          {t("settings.general_desc")}
        </p>
      </div>

      {/* Sección: Popup de Vuelto */}
      <div className="bg-white dark:bg-slate-800 rounded-xl p-6 border border-slate-200 dark:border-slate-700 shadow-sm">
        <h2 className="text-xl font-semibold text-slate-900 dark:text-white mb-4 flex items-center gap-2">
          <DollarSign className="w-5 h-5 text-green-500" />
          {t("settings.change_popup_title")}
        </h2>
        
        <div className="space-y-4">
          {/* Toggle */}
          <div className="flex items-center justify-between">
            <div className="flex-1">
              <label className="text-slate-900 dark:text-white font-medium block">
                {t("settings.show_change_popup")}
              </label>
              <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">
                {t("settings.show_change_popup_desc")}
              </p>
            </div>
            <button
              onClick={() => setShowChangePopup(!showChangePopup)}
              className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors flex-shrink-0 ml-4 ${
                showChangePopup ? 'bg-green-500' : 'bg-slate-300 dark:bg-slate-600'
              }`}
              role="switch"
              aria-checked={showChangePopup}
              aria-label={t("settings.show_change_popup")}
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
                {t("settings.change_popup_duration")}
              </label>
              <div className="flex items-center gap-3 flex-wrap">
                <input
                  type="number"
                  min="1"
                  max="30"
                  value={changePopupDuration}
                  onChange={(e) => setChangePopupDuration(parseInt(e.target.value) || 5)}
                  className="w-24 px-3 py-2 bg-slate-50 dark:bg-slate-700 border border-slate-300 dark:border-slate-600 rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-green-500"
                  aria-label={t("settings.change_popup_duration")}
                />
                <span className="text-slate-600 dark:text-slate-400">
                  {t("settings.seconds")}
                </span>
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
                {t("settings.change_popup_duration_desc", { seconds: changePopupDuration })}
              </p>
            </div>
          )}
        </div>
      </div>

      {/* Preview */}
      <div className="bg-slate-50 dark:bg-slate-800/50 rounded-xl p-6 border border-slate-200 dark:border-slate-700">
        <h3 className="text-lg font-semibold text-slate-900 dark:text-white mb-3 flex items-center gap-2">
          <CheckCircle2 className="w-5 h-5 text-blue-500" />
          {t("settings.preview_title")}
        </h3>
        <div className="bg-white dark:bg-slate-900 rounded-lg p-4 text-slate-700 dark:text-slate-300 text-sm space-y-2">
          {showChangePopup ? (
            <>
              <div className="flex items-start gap-2">
                <span className="text-green-500">✅</span>
                <span>{t("settings.preview_enabled")}</span>
              </div>
              <div className="flex items-start gap-2">
                <span className="text-blue-500">⏱️</span>
                <span>{t("settings.preview_auto_close", { seconds: changePopupDuration })}</span>
              </div>
            </>
          ) : (
            <div className="flex items-start gap-2">
              <span className="text-slate-400">❌</span>
              <span>{t("settings.preview_disabled")}</span>
            </div>
          )}
        </div>
      </div>

      {/* ═══════════════════════════════════════════ */}
      {/* Numeración de Pedidos */}
      {/* ═══════════════════════════════════════════ */}
      <div className="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-gray-200 dark:border-slate-700 p-6 space-y-6">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className={`w-12 h-12 rounded-lg flex items-center justify-center ${
              numberingForm.is_enabled 
                ? 'bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400'
                : 'bg-gray-100 dark:bg-slate-700 text-gray-400 dark:text-slate-500'
            }`}>
              <Hash size={24} />
            </div>
            <div>
              <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
                {t('settings.order_numbering.title', 'Numeración de Pedidos')}
              </h3>
              <p className="text-sm text-gray-500 dark:text-slate-400">
                {numberingForm.is_enabled
                  ? t('settings.order_numbering.enabled_desc', 'Usando formato personalizado')
                  : t('settings.order_numbering.disabled_desc', 'Usando formato por defecto')}
              </p>
            </div>
          </div>
          <label className="relative inline-flex items-center cursor-pointer">
            <input
              type="checkbox"
              checked={numberingForm.is_enabled}
              onChange={(e) => setNumberingForm({ ...numberingForm, is_enabled: e.target.checked })}
              className="sr-only peer"
            />
            <div className="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-orange-300 dark:peer-focus:ring-orange-800 rounded-full peer dark:bg-slate-600 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-slate-600 peer-checked:bg-orange-500"></div>
          </label>
        </div>

        {numberingForm.is_enabled && (
          <div className="space-y-6 mt-6">
            {/* Prefijo */}
            <div>
              <label className="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-2">
                {t('settings.order_numbering.prefix', 'Prefijo')}
              </label>
              <input
                type="text"
                value={numberingForm.prefix}
                onChange={(e) => setNumberingForm({ ...numberingForm, prefix: e.target.value.toUpperCase().replace(/[^A-Z0-9]/g, '') })}
                maxLength={10}
                className="w-full px-4 py-2 border border-gray-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                placeholder="ORD"
              />
              <p className="text-xs text-gray-500 dark:text-slate-400 mt-1">
                {t('settings.order_numbering.prefix_help', 'Solo letras mayúsculas y números (máx. 10 caracteres)')}
              </p>
            </div>

            {/* Frecuencia de reset */}
            <div>
              <label className="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-2">
                <Calendar size={16} className="inline mr-2" />
                {t('settings.order_numbering.reset_frequency', 'Frecuencia de Reset')}
              </label>
              <div className="grid grid-cols-2 gap-3">
                <label
                  className={`flex items-center gap-3 p-4 border-2 rounded-lg cursor-pointer transition-all ${
                    numberingForm.reset_frequency === 'daily'
                      ? 'border-orange-500 bg-orange-50 dark:bg-orange-900/20'
                      : 'border-gray-200 dark:border-slate-600 hover:border-gray-300 dark:hover:border-slate-500'
                  }`}
                >
                  <input
                    type="radio"
                    name="reset_frequency"
                    value="daily"
                    checked={numberingForm.reset_frequency === 'daily'}
                    onChange={(e) => setNumberingForm({ ...numberingForm, reset_frequency: e.target.value as 'daily' | 'monthly' })}
                    className="sr-only"
                  />
                  <div>
                    <p className="font-medium text-gray-900 dark:text-white">
                      {t('settings.order_numbering.daily', 'Diario')}
                    </p>
                    <p className="text-sm text-gray-500 dark:text-slate-400">
                      {numberingForm.prefix}-{new Date().toISOString().slice(0, 10).replace(/-/g, '')}-0001
                    </p>
                  </div>
                </label>

                <label
                  className={`flex items-center gap-3 p-4 border-2 rounded-lg cursor-pointer transition-all ${
                    numberingForm.reset_frequency === 'monthly'
                      ? 'border-orange-500 bg-orange-50 dark:bg-orange-900/20'
                      : 'border-gray-200 dark:border-slate-600 hover:border-gray-300 dark:hover:border-slate-500'
                  }`}
                >
                  <input
                    type="radio"
                    name="reset_frequency"
                    value="monthly"
                    checked={numberingForm.reset_frequency === 'monthly'}
                    onChange={(e) => setNumberingForm({ ...numberingForm, reset_frequency: e.target.value as 'daily' | 'monthly' })}
                    className="sr-only"
                  />
                  <div>
                    <p className="font-medium text-gray-900 dark:text-white">
                      {t('settings.order_numbering.monthly', 'Mensual')}
                    </p>
                    <p className="text-sm text-gray-500 dark:text-slate-400">
                      {numberingForm.prefix}-{new Date().toISOString().slice(0, 7).replace(/-/g, '')}-0001
                    </p>
                  </div>
                </label>
              </div>
            </div>

            {/* Preview */}
            <div className="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
              <p className="text-sm font-medium text-blue-900 dark:text-blue-200 mb-2">
                {t('settings.order_numbering.preview', 'Vista Previa')}
              </p>
              <code className="text-lg font-mono text-blue-800 dark:text-blue-300">
                {numberingForm.prefix}-
                {numberingForm.reset_frequency === 'daily' 
                  ? new Date().toISOString().slice(0, 10).replace(/-/g, '')
                  : new Date().toISOString().slice(0, 7).replace(/-/g, '')}
                -{String(numberingConfig?.current_sequence ?? 1).padStart(4, '0')}
              </code>
            </div>

            {/* Acciones */}
            <div className="flex items-center justify-between gap-4">
              <button
                onClick={handleResetNumberingSequence}
                disabled={isResetting}
                className="flex items-center gap-2 px-4 py-2 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors disabled:opacity-50"
              >
                <RotateCcw size={18} />
                {t('settings.order_numbering.reset_sequence', 'Resetear Secuencia')}
              </button>

              <button
                onClick={handleSaveNumbering}
                disabled={!numberingHasChanges || isUpdating}
                className="flex items-center gap-2 px-6 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg font-medium transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {isUpdating ? (
                  <Loader2 size={18} className="animate-spin" />
                ) : (
                  <Save size={18} />
                )}
                {t('common.save', 'Guardar')}
              </button>
            </div>

            {/* Info adicional */}
            <div className="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4 flex gap-3">
              <AlertCircle size={20} className="text-yellow-600 dark:text-yellow-400 flex-shrink-0 mt-0.5" />
              <div className="text-sm text-yellow-800 dark:text-yellow-200">
                <p className="font-medium mb-1">
                  {t('settings.order_numbering.note_title', 'Nota importante')}
                </p>
                <p>
                  {t('settings.order_numbering.note_content', 'Los cambios solo afectan a nuevos pedidos. Los pedidos existentes mantendrán su número original.')}
                </p>
              </div>
            </div>
          </div>
        )}
      </div>

    </div>
  );
}

export default GeneralSettingsPage;
