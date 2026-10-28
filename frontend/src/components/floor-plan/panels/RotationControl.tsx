// src/components/floor-plan/panels/RotationControl.tsx
import React, { useState } from "react";
import { RotateCcw, RotateCw } from "lucide-react";

interface RotationControlProps {
  value: number; // Grados actuales (0-360)
  onChange: (degrees: number) => void;
  step?: number; // Incremento del slider (default: 1)
}

const PRESET_ANGLES = [0, 45, 90, 135, 180, 225, 270, 315];

export const RotationControl: React.FC<RotationControlProps> = ({
  value,
  onChange,
  step = 1,
}) => {
  const [isCustom, setIsCustom] = useState(false);

  const handlePresetClick = (degrees: number) => {
    onChange(degrees);
    setIsCustom(false);
  };

  const handleSliderChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const newValue = parseInt(e.target.value, 10);
    onChange(newValue);
    setIsCustom(true);
  };

  const handleInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const newValue = parseInt(e.target.value, 10);
    if (!isNaN(newValue) && newValue >= 0 && newValue <= 360) {
      onChange(newValue);
      setIsCustom(true);
    }
  };

  const rotateBy = (delta: number) => {
    const newValue = (value + delta + 360) % 360;
    onChange(newValue);
  };

  return (
    <div className="space-y-3">
      {/* Header con controles rápidos */}
      <div className="flex items-center justify-between">
        <label className="text-xs font-semibold text-slate-400 uppercase">
          Rotación
        </label>
        <div className="flex items-center gap-1">
          <button
            onClick={() => rotateBy(-15)}
            className="p-1.5 bg-slate-700 hover:bg-slate-600 rounded transition-colors"
            title="Rotar -15°"
          >
            <RotateCcw size={14} className="text-slate-300" />
          </button>
          <button
            onClick={() => rotateBy(15)}
            className="p-1.5 bg-slate-700 hover:bg-slate-600 rounded transition-colors"
            title="Rotar +15°"
          >
            <RotateCw size={14} className="text-slate-300" />
          </button>
        </div>
      </div>

      {/* Ángulos predefinidos (grados fijos) */}
      <div className="grid grid-cols-4 gap-2">
        {PRESET_ANGLES.map((angle) => (
          <button
            key={angle}
            onClick={() => handlePresetClick(angle)}
            className={`py-2 px-3 rounded text-xs font-medium transition-all ${
              value === angle && !isCustom
                ? "bg-orange-600 text-white shadow-lg"
                : "bg-slate-700 text-slate-300 hover:bg-slate-600"
            }`}
          >
            {angle}°
          </button>
        ))}
      </div>

      {/* Control variable (slider + input) */}
      <div className="space-y-2 pt-2 border-t border-slate-700">
        <div className="flex items-center justify-between">
          <span className="text-xs text-slate-400">Ángulo personalizado</span>
          <div className="flex items-center gap-2">
            <input
              type="number"
              min="0"
              max="360"
              value={value}
              onChange={handleInputChange}
              className="w-16 px-2 py-1 bg-slate-700 border border-slate-600 rounded text-xs text-white text-center"
            />
            <span className="text-xs text-slate-400">°</span>
          </div>
        </div>

        <input
          type="range"
          min="0"
          max="360"
          step={step}
          value={value}
          onChange={handleSliderChange}
          className="w-full h-2 bg-slate-700 rounded-lg appearance-none cursor-pointer accent-orange-500"
        />

        {/* Indicador visual de rotación */}
        <div className="flex items-center justify-center pt-2">
          <div className="relative w-16 h-16">
            <div className="absolute inset-0 rounded-full border-2 border-slate-600" />
            <div
              className="absolute top-1/2 left-1/2 w-1 h-6 bg-orange-500 origin-bottom rounded-full"
              style={{
                transform: `translate(-50%, -100%) rotate(${value}deg)`,
              }}
            />
            <div className="absolute top-1/2 left-1/2 w-2 h-2 bg-orange-500 rounded-full -translate-x-1/2 -translate-y-1/2" />
          </div>
        </div>
      </div>
    </div>
  );
};
