// src/components/floor-plan/renderers/WindowRenderer.tsx
import React from "react";

interface WindowRendererProps {
  width: number;
  height: number; // Grosor del muro donde va la ventana
}

export const WindowRenderer: React.FC<WindowRendererProps> = ({ width, height }) => {
  return (
    <svg width={width} height={height} viewBox={`0 0 ${width} ${height}`}>
      {/* Marco exterior (muro) */}
      <rect x="0" y="0" width={width} height={height} fill="#94a3b8" stroke="#334155" strokeWidth="1" />
      
      {/* Vidrio (más claro) */}
      <rect x="2" y="2" width={width - 4} height={height - 4} fill="#bae6fd" opacity="0.6" />
      
      {/* Línea central del vidrio (indica doble hoja o marco central) */}
      <line x1={width / 2} y1="2" x2={width / 2} y2={height - 2} stroke="#334155" strokeWidth="1" />
      <line x1="2" y1={height / 2} x2={width - 2} y2={height / 2} stroke="#334155" strokeWidth="0.5" opacity="0.5" />
    </svg>
  );
};
