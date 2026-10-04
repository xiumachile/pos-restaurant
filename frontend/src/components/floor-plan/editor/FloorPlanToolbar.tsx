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
} from 'lucide-react';

export function FloorPlanToolbar() {
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

  return (
    <div className="bg-white border-b border-gray-200 px-4 py-2 flex items-center justify-between">
      {/* Nombre del plano */}
      <div className="flex items-center gap-4">
        <h2 className="text-lg font-semibold text-gray-800">
          {currentPlan?.name ?? 'Nuevo Plano'}
        </h2>
        <span className="text-sm text-gray-500">
          {currentPlan?.width} x {currentPlan?.height} unidades
        </span>
      </div>

      {/* Herramientas */}
      <div className="flex items-center gap-2">
        {/* Modo de interacción */}
        <div className="flex items-center gap-1 border-r pr-2 mr-2">
          <button
            onClick={() => setEditorMode('select')}
            className={`p-2 rounded ${
              editor.mode === 'select'
                ? 'bg-blue-500 text-white'
                : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
            }`}
            title="Seleccionar (V)"
          >
            <MousePointer size={18} />
          </button>
          <button
            onClick={() => setEditorMode('pan')}
            className={`p-2 rounded ${
              editor.mode === 'pan'
                ? 'bg-blue-500 text-white'
                : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
            }`}
            title="Mover vista (H)"
          >
            <Hand size={18} />
          </button>
        </div>

        {/* Zoom */}
        <div className="flex items-center gap-1 border-r pr-2 mr-2">
          <button
            onClick={zoomOut}
            className="p-2 rounded bg-gray-100 text-gray-700 hover:bg-gray-200"
            title="Alejar"
          >
            <ZoomOut size={18} />
          </button>
          <span className="text-sm font-medium min-w-[60px] text-center">
            {(viewport.scale * 100).toFixed(0)}%
          </span>
          <button
            onClick={zoomIn}
            className="p-2 rounded bg-gray-100 text-gray-700 hover:bg-gray-200"
            title="Acercar"
          >
            <ZoomIn size={18} />
          </button>
          <button
            onClick={resetViewport}
            className="p-2 rounded bg-gray-100 text-gray-700 hover:bg-gray-200"
            title="Restablecer vista"
          >
            <Maximize size={18} />
          </button>
        </div>

        {/* Historial */}
        <div className="flex items-center gap-1 border-r pr-2 mr-2">
          <button
            onClick={undo}
            disabled={!canUndo()}
            className="p-2 rounded bg-gray-100 text-gray-700 hover:bg-gray-200 disabled:opacity-50 disabled:cursor-not-allowed"
            title="Deshacer (Ctrl+Z)"
          >
            <Undo2 size={18} />
          </button>
          <button
            onClick={redo}
            disabled={!canRedo()}
            className="p-2 rounded bg-gray-100 text-gray-700 hover:bg-gray-200 disabled:opacity-50 disabled:cursor-not-allowed"
            title="Rehacer (Ctrl+Y)"
          >
            <Redo2 size={18} />
          </button>
        </div>

        {/* Guardar */}
        <button
          className="flex items-center gap-2 px-4 py-2 rounded bg-blue-500 text-white hover:bg-blue-600"
          title="Guardar plano"
        >
          <Save size={18} />
          <span>Guardar</span>
        </button>
      </div>
    </div>
  );
}
