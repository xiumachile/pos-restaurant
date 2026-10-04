import { useFloorPlanStore } from '@/stores/floor-plan/floorPlanStore';
import {
  ZoomIn,
  ZoomOut,
  Maximize,
  Hand,
  MousePointer,
  Undo2,
  Redo2,
  Save,
  CheckCircle2,
  Loader2,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';

interface FloorPlanToolbarProps {
  onSave?: () => Promise<{ success: boolean } | void> | void;
  isSaving?: boolean;
  hasChanges?: boolean;
  lastSavedAt?: string | null;
}

export function FloorPlanToolbar({
  onSave,
  isSaving = false,
  hasChanges = false,
  lastSavedAt = null,
}: FloorPlanToolbarProps) {
  const { t } = useTranslation();
  const {
    viewport,
    editor,
    setEditorMode,
    resetViewport,
    zoomIn,
    zoomOut,
    canUndo,
    canRedo,
    undo,
    redo,
    currentPlan,
  } = useFloorPlanStore();

  const formatSavedTime = (isoString: string | null): string => {
    if (!isoString) return '';
    const date = new Date(isoString);
    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffMin = Math.floor(diffMs / 60000);

    if (diffMin < 1) return t('floor_plan.toolbar.saved_just_now', 'Justo ahora');
    if (diffMin < 60) return t('floor_plan.toolbar.saved_minutes_ago', 'Hace {{min}} min', { min: diffMin });

    return date.toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit' });
  };

  return (
    <div className="bg-white border-b border-gray-200 px-4 py-2 flex items-center justify-between dark:bg-slate-900 dark:border-slate-800">
      {/* Nombre del plano + estado */}
      <div className="flex items-center gap-4">
        <div>
          <h2 className="text-lg font-semibold text-gray-800 dark:text-white flex items-center gap-2">
            {currentPlan?.name ?? t('floor_plan.toolbar.new_plan', 'Nuevo Plano')}
            {hasChanges && (
              <span className="text-xs font-normal text-amber-600 dark:text-amber-400 bg-amber-100 dark:bg-amber-900/30 px-2 py-0.5 rounded">
                {t('floor_plan.toolbar.unsaved_changes', 'Sin guardar')}
              </span>
            )}
          </h2>
          <div className="text-xs text-gray-500 dark:text-slate-400 flex items-center gap-2">
            <span>
              {currentPlan?.width} × {currentPlan?.height}
            </span>
            {lastSavedAt && !hasChanges && (
              <span className="flex items-center gap-1 text-emerald-600 dark:text-emerald-400">
                <CheckCircle2 size={10} />
                {t('floor_plan.toolbar.last_saved', 'Guardado')}: {formatSavedTime(lastSavedAt)}
              </span>
            )}
          </div>
        </div>
      </div>

      {/* Herramientas */}
      <div className="flex items-center gap-2">
        {/* Modo de interacción */}
        <div className="flex items-center gap-1 border-r border-gray-200 dark:border-slate-700 pr-2 mr-2">
          <button
            onClick={() => setEditorMode('select')}
            className={`p-2 rounded transition-colors ${
              editor.mode === 'select'
                ? 'bg-blue-500 text-white'
                : 'bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-slate-300 hover:bg-gray-200 dark:hover:bg-slate-700'
            }`}
            title={t('floor_plan.toolbar.select_mode', 'Seleccionar (V)')}
          >
            <MousePointer size={18} />
          </button>
          <button
            onClick={() => setEditorMode('pan')}
            className={`p-2 rounded transition-colors ${
              editor.mode === 'pan'
                ? 'bg-blue-500 text-white'
                : 'bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-slate-300 hover:bg-gray-200 dark:hover:bg-slate-700'
            }`}
            title={t('floor_plan.toolbar.pan_mode', 'Mover vista (H)')}
          >
            <Hand size={18} />
          </button>
        </div>

        {/* Zoom */}
        <div className="flex items-center gap-1 border-r border-gray-200 dark:border-slate-700 pr-2 mr-2">
          <button
            onClick={zoomOut}
            className="p-2 rounded bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-slate-300 hover:bg-gray-200 dark:hover:bg-slate-700"
            title={t('floor_plan.toolbar.zoom_out', 'Alejar')}
          >
            <ZoomOut size={18} />
          </button>
          <span className="text-sm font-medium min-w-[60px] text-center text-gray-700 dark:text-slate-300">
            {(viewport.scale * 100).toFixed(0)}%
          </span>
          <button
            onClick={zoomIn}
            className="p-2 rounded bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-slate-300 hover:bg-gray-200 dark:hover:bg-slate-700"
            title={t('floor_plan.toolbar.zoom_in', 'Acercar')}
          >
            <ZoomIn size={18} />
          </button>
          <button
            onClick={resetViewport}
            className="p-2 rounded bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-slate-300 hover:bg-gray-200 dark:hover:bg-slate-700"
            title={t('floor_plan.toolbar.reset_view', 'Restablecer vista')}
          >
            <Maximize size={18} />
          </button>
        </div>

        {/* Historial */}
        <div className="flex items-center gap-1 border-r border-gray-200 dark:border-slate-700 pr-2 mr-2">
          <button
            onClick={undo}
            disabled={!canUndo()}
            className="p-2 rounded bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-slate-300 hover:bg-gray-200 dark:hover:bg-slate-700 disabled:opacity-50 disabled:cursor-not-allowed"
            title={t('floor_plan.toolbar.undo', 'Deshacer (Ctrl+Z)')}
          >
            <Undo2 size={18} />
          </button>
          <button
            onClick={redo}
            disabled={!canRedo()}
            className="p-2 rounded bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-slate-300 hover:bg-gray-200 dark:hover:bg-slate-700 disabled:opacity-50 disabled:cursor-not-allowed"
            title={t('floor_plan.toolbar.redo', 'Rehacer (Ctrl+Y)')}
          >
            <Redo2 size={18} />
          </button>
        </div>

        {/* Guardar */}
        <button
          onClick={onSave}
          disabled={isSaving || !hasChanges}
          className="flex items-center gap-2 px-4 py-2 rounded bg-blue-500 text-white hover:bg-blue-600 disabled:bg-gray-300 dark:disabled:bg-slate-700 disabled:text-gray-500 dark:disabled:text-slate-500 disabled:cursor-not-allowed transition-colors"
          title={hasChanges
            ? t('floor_plan.toolbar.save_changes', 'Guardar cambios (Ctrl+S)')
            : t('floor_plan.toolbar.no_changes', 'No hay cambios para guardar')}
        >
          {isSaving ? (
            <Loader2 size={18} className="animate-spin" />
          ) : (
            <Save size={18} />
          )}
          <span>{t('floor_plan.toolbar.save', 'Guardar')}</span>
        </button>
      </div>
    </div>
  );
}
