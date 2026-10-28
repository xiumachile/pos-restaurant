// src/components/floor-plan/CanvasSettingsPanel.tsx
import { useState } from "react";
import { X } from "lucide-react";
import type { CanvasConfig } from "@/types/floorPlan";

interface Props {
  canvasConfig: CanvasConfig;
  onSave: (config: CanvasConfig) => void;
  onClose: () => void;
  isOpen: boolean;
}

export function CanvasSettingsPanel({ canvasConfig, onSave, onClose, isOpen }: Props) {
  const [width, setWidth] = useState(canvasConfig.width);
  const [height, setHeight] = useState(canvasConfig.height);
  const [gridSize, setGridSize] = useState(canvasConfig.grid_size);

  if (!isOpen) return null;

  const handleSave = () => {
    onSave({
      ...canvasConfig,
      width: Math.max(800, Math.min(4000, width)),
      height: Math.max(600, Math.min(3000, height)),
      grid_size: Math.max(10, Math.min(100, gridSize)),
    });
  };

  return (
    <div className="fixed inset-0 bg-black/70 flex items-center justify-center z-50">
      <div className="bg-slate-800 rounded-lg border border-slate-700 p-6 w-96">
        <div className="flex items-center justify-between mb-4">
          <h3 className="text-lg font-bold text-white">Configuración del Lienzo</h3>
          <button onClick={onClose} className="p-1 hover:bg-slate-700 rounded">
            <X size={20} className="text-slate-400" />
          </button>
        </div>

        <div className="space-y-4">
          <div>
            <label className="text-xs font-semibold text-slate-400 uppercase block mb-1">
              Ancho (px)
            </label>
            <input
              type="number"
              value={width}
              onChange={(e) => setWidth(parseInt(e.target.value) || 2400)}
              className="w-full px-3 py-2 bg-slate-700 border border-slate-600 rounded text-white"
            />
          </div>

          <div>
            <label className="text-xs font-semibold text-slate-400 uppercase block mb-1">
              Alto (px)
            </label>
            <input
              type="number"
              value={height}
              onChange={(e) => setHeight(parseInt(e.target.value) || 1800)}
              className="w-full px-3 py-2 bg-slate-700 border border-slate-600 rounded text-white"
            />
          </div>

          <div>
            <label className="text-xs font-semibold text-slate-400 uppercase block mb-1">
              Tamaño de cuadrícula (px)
            </label>
            <input
              type="number"
              value={gridSize}
              onChange={(e) => setGridSize(parseInt(e.target.value) || 40)}
              className="w-full px-3 py-2 bg-slate-700 border border-slate-600 rounded text-white"
            />
          </div>
        </div>

        <div className="flex gap-2 mt-6">
          <button
            onClick={onClose}
            className="flex-1 px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded transition-colors"
          >
            Cancelar
          </button>
          <button
            onClick={handleSave}
            className="flex-1 px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white rounded transition-colors font-medium"
          >
            Guardar
          </button>
        </div>
      </div>
    </div>
  );
}
