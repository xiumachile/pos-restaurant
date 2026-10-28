// src/components/floor-plan/renderers/CounterRenderer.tsx
import React from "react";

interface CounterRendererProps {
  width: number;
  height: number;
}

export const CounterRenderer: React.FC<CounterRendererProps> = ({ width, height }) => {
  const topHeight = height * 0.4; // Parte superior (donde se apoya el cliente)
  
  return (
    <svg width={width} height={height} viewBox={`0 0 ${width} ${height}`}>
      {/* Sombra */}
      <rect x="2" y="2" width={width - 4} height={height - 4} rx="2" fill="#000000" opacity="0.15" />

      {/* Cuerpo principal (frente de barra) */}
      <rect x="0" y="0" width={width} height={height} rx="2" fill="#475569" stroke="#334155" strokeWidth="1" />
      
      {/* Superficie de trabajo (más clara) */}
      <rect x="2" y="2" width={width - 4} height={topHeight} rx="1" fill="#94a3b8" />
      
      {/* Línea de separación */}
      <line x1="2" y1={topHeight} x2={width - 2} y2={topHeight} stroke="#334155" strokeWidth="1" />
      
      {/* Detalle de estantes o frente */}
      <rect x="4" y={topHeight + 4} width={width - 8} height={height - topHeight - 8} rx="1" fill="#334155" opacity="0.5" />
    </svg>
  );
};
