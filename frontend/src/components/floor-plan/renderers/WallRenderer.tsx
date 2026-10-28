// src/components/floor-plan/renderers/WallRenderer.tsx
import React from "react";

interface WallRendererProps {
  width: number;
  height: number; // Grosor del muro
}

export const WallRenderer: React.FC<WallRendererProps> = ({ width, height }) => {
  return (
    <svg width={width} height={height} viewBox={`0 0 ${width} ${height}`}>
      {/* Sombra sutil */}
      <rect x="1" y="1" width={width - 2} height={height - 2} fill="#000000" opacity="0.15" />
      
      {/* Cuerpo del muro */}
      <rect x="0" y="0" width={width} height={height} fill="#94a3b8" />
      
      {/* Textura de líneas diagonales sutiles (patrón de corte arquitectónico) */}
      <pattern id="wall-hatch" x="0" y="0" width="8" height="8" patternUnits="userSpaceOnUse" patternTransform="rotate(45)">
        <line x1="0" y1="0" x2="0" y2="8" stroke="#64748b" strokeWidth="1" />
      </pattern>
      <rect x="0" y="0" width={width} height={height} fill="url(#wall-hatch)" opacity="0.4" />
      
      {/* Bordes definidos */}
      <rect x="0" y="0" width={width} height={height} fill="none" stroke="#334155" strokeWidth="1" />
    </svg>
  );
};
