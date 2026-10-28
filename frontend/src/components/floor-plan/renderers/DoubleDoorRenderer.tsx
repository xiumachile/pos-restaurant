// src/components/floor-plan/renderers/DoubleDoorRenderer.tsx
import React from "react";

interface DoubleDoorRendererProps {
  width: number;
  height: number; // Grosor del muro
}

export const DoubleDoorRenderer: React.FC<DoubleDoorRendererProps> = ({ width, height }) => {
  const halfWidth = width / 2;
  const radius = halfWidth;

  return (
    <svg width={width} height={height + radius} viewBox={`0 0 ${width} ${height + radius}`}>
      {/* Hueco en el muro (las dos puertas) */}
      <rect x="0" y="0" width={halfWidth} height={height} fill="#e2e8f0" stroke="#334155" strokeWidth="1" />
      <rect x={halfWidth} y="0" width={halfWidth} height={height} fill="#e2e8f0" stroke="#334155" strokeWidth="1" />

      {/* Hoja de la puerta izquierda (abre hacia la izquierda) */}
      <line
        x1="0"
        y1={height}
        x2={halfWidth}
        y2={height + radius}
        stroke="#0f172a"
        strokeWidth="2"
      />

      {/* Hoja de la puerta derecha (abre hacia la derecha) */}
      <line
        x1={width}
        y1={height}
        x2={halfWidth}
        y2={height + radius}
        stroke="#0f172a"
        strokeWidth="2"
      />

      {/* Arco de apertura izquierdo */}
      <path
        d={`M 0 ${height} A ${radius} ${radius} 0 0 0 ${halfWidth} ${height + radius}`}
        fill="none"
        stroke="#94a3b8"
        strokeWidth="1"
        strokeDasharray="4 4"
      />

      {/* Arco de apertura derecho */}
      <path
        d={`M ${width} ${height} A ${radius} ${radius} 0 0 1 ${halfWidth} ${height + radius}`}
        fill="none"
        stroke="#94a3b8"
        strokeWidth="1"
        strokeDasharray="4 4"
      />

      {/* Bisagras (puntos de pivote) */}
      <circle cx="0" cy={height} r="2" fill="#0f172a" />
      <circle cx={width} cy={height} r="2" fill="#0f172a" />

      {/* Línea central (donde se encuentran las dos puertas) */}
      <line
        x1={halfWidth}
        y1="0"
        x2={halfWidth}
        y2={height}
        stroke="#334155"
        strokeWidth="1"
      />
    </svg>
  );
};
