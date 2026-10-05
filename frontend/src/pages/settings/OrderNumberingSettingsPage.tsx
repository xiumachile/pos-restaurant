import { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { Save, RotateCcw, Hash, Calendar, AlertCircle, Loader2 } from 'lucide-react';
import { useOrderNumberingConfig } from '@/hooks/useOrderNumberingConfig';

export function OrderNumberingSettingsPage() {
  const { t } = useTranslation();
  const { config, isLoading, updateConfig, isUpdating, resetSequence, isResetting } = useOrderNumberingConfig();

  const [formData, setFormData] = useState({
    is_enabled: false,
    prefix: 'ORD',
    reset_frequency: 'daily' as 'daily' | 'monthly',
  });

  const [hasChanges, setHasChanges] = useState(false);

  useEffect(() => {
    if (config) {
      setFormData({
        is_enabled: config.is_enabled,
        prefix: config.prefix,
        reset_frequency: config.reset_frequency,
      });
    }
  }, [config]);

  useEffect(() => {
    if (config) {
      const changed =
        formData.is_enabled !== config.is_enabled ||
        formData.prefix !== config.prefix ||
        formData.reset_frequency !== config.reset_frequency;
      setHasChanges(changed);
    }
  }, [formData, config]);

  const handleSave = () => {
    updateConfig(formData);
  };

  const handleResetSequence = () => {
    if (confirm(t('settings.order_numbering.confirm_reset', '¿Estás seguro de resetear la secuencia a 1?'))) {
      resetSequence();
    }
  };

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-64">
        <Loader2 className="animate-spin text-orange-500" size={48} />
      </div>
    );
  }

  return (
    <div className="max-w-4xl mx-auto p-6 space-y-6">
      {/* Header */}
      <div>
        <h1 className="text-3xl font-bold text-gray-900 dark:text-white mb-2">
          {t('settings.order_numbering.title', 'Numeración de Pedidos')}
        </h1>
        <p className="text-gray-600 dark:text-slate-400">
          {t('settings.order_numbering.description', 'Configura el formato y comportamiento de los números de orden')}
        </p>
      </div>

      {/* Toggle principal */}
      <div className="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-gray-200 dark:border-slate-700 p-6">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className={`w-12 h-12 rounded-lg flex items-center justify-center ${
              formData.is_enabled 
                ? 'bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400'
                : 'bg-gray-100 dark:bg-slate-700 text-gray-400 dark:text-slate-500'
            }`}>
              <Hash size={24} />
            </div>
            <div>
              <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
                {t('settings.order_numbering.enable', 'Numeración Personalizada')}
              </h3>
              <p className="text-sm text-gray-500 dark:text-slate-400">
                {formData.is_enabled
                  ? t('settings.order_numbering.enabled_desc', 'Usando formato personalizado')
                  : t('settings.order_numbering.disabled_desc', 'Usando formato por defecto: ORD-{branchId}-{YYYYMMDD}-{####}')}
              </p>
            </div>
          </div>
          <label className="relative inline-flex items-center cursor-pointer">
            <input
              type="checkbox"
              checked={formData.is_enabled}
              onChange={(e) => setFormData({ ...formData, is_enabled: e.target.checked })}
              className="sr-only peer"
            />
            <div className="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-orange-300 dark:peer-focus:ring-orange-800 rounded-full peer dark:bg-slate-600 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-slate-600 peer-checked:bg-orange-500"></div>
          </label>
        </div>
      </div>

      {/* Configuración (solo si está habilitado) */}
      {formData.is_enabled && (
        <div className="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-gray-200 dark:border-slate-700 p-6 space-y-6">
          {/* Prefijo */}
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-2">
              {t('settings.order_numbering.prefix', 'Prefijo')}
            </label>
            <input
              type="text"
              value={formData.prefix}
              onChange={(e) => setFormData({ ...formData, prefix: e.target.value.toUpperCase().replace(/[^A-Z0-9]/g, '') })}
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
                  formData.reset_frequency === 'daily'
                    ? 'border-orange-500 bg-orange-50 dark:bg-orange-900/20'
                    : 'border-gray-200 dark:border-slate-600 hover:border-gray-300 dark:hover:border-slate-500'
                }`}
              >
                <input
                  type="radio"
                  name="reset_frequency"
                  value="daily"
                  checked={formData.reset_frequency === 'daily'}
                  onChange={(e) => setFormData({ ...formData, reset_frequency: e.target.value as 'daily' | 'monthly' })}
                  className="sr-only"
                />
                <div>
                  <p className="font-medium text-gray-900 dark:text-white">
                    {t('settings.order_numbering.daily', 'Diario')}
                  </p>
                  <p className="text-sm text-gray-500 dark:text-slate-400">
                    {t('settings.order_numbering.daily_desc', 'Formato: {PREFIX}-{YYYYMMDD}-{####}')}
                  </p>
                </div>
              </label>

              <label
                className={`flex items-center gap-3 p-4 border-2 rounded-lg cursor-pointer transition-all ${
                  formData.reset_frequency === 'monthly'
                    ? 'border-orange-500 bg-orange-50 dark:bg-orange-900/20'
                    : 'border-gray-200 dark:border-slate-600 hover:border-gray-300 dark:hover:border-slate-500'
                }`}
              >
                <input
                  type="radio"
                  name="reset_frequency"
                  value="monthly"
                  checked={formData.reset_frequency === 'monthly'}
                  onChange={(e) => setFormData({ ...formData, reset_frequency: e.target.value as 'daily' | 'monthly' })}
                  className="sr-only"
                />
                <div>
                  <p className="font-medium text-gray-900 dark:text-white">
                    {t('settings.order_numbering.monthly', 'Mensual')}
                  </p>
                  <p className="text-sm text-gray-500 dark:text-slate-400">
                    {t('settings.order_numbering.monthly_desc', 'Formato: {PREFIX}-{YYYYMM}-{####}')}
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
              {formData.prefix}-
              {formData.reset_frequency === 'daily' ? '20261005' : '202610'}
              -0001
            </code>
          </div>

          {/* Estado actual */}
          {config && (
            <div className="bg-gray-50 dark:bg-slate-700/50 rounded-lg p-4">
              <p className="text-sm text-gray-700 dark:text-slate-300">
                <span className="font-medium">
                  {t('settings.order_numbering.current_sequence', 'Secuencia actual')}:
                </span>{' '}
                <span className="font-mono">{config.current_sequence}</span>
              </p>
              {config.last_reset_date && (
                <p className="text-sm text-gray-600 dark:text-slate-400 mt-1">
                  <span className="font-medium">
                    {t('settings.order_numbering.last_reset', 'Último reset')}:
                  </span>{' '}
                  {config.last_reset_date}
                </p>
              )}
            </div>
          )}
        </div>
      )}

      {/* Acciones */}
      <div className="flex items-center justify-between gap-4">
        <div>
          {formData.is_enabled && (
            <button
              onClick={handleResetSequence}
              disabled={isResetting}
              className="flex items-center gap-2 px-4 py-2 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors disabled:opacity-50"
            >
              <RotateCcw size={18} />
              {t('settings.order_numbering.reset_sequence', 'Resetear Secuencia')}
            </button>
          )}
        </div>

        <button
          onClick={handleSave}
          disabled={!hasChanges || isUpdating}
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
            {t('settings.order_numbering.note_content', 'Los cambios en la configuración solo afectan a nuevos pedidos. Los pedidos existentes mantendrán su número original.')}
          </p>
        </div>
      </div>
    </div>
  );
}
