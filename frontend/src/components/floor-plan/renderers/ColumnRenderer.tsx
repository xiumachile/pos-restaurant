// src/components/floor-plan/renderers/ColumnRenderer.tsx
import React from "react";

interface ColumnRendererProps {
  width: number;
  height: number;
}

export const ColumnRenderer: React.FC<ColumnRendererProps> = ({ width, height }) => {
  const isSquare = Math.abs(width - height) < 5;
  const size = Math.min(width, height);

  return (
    <svg width={width} height={height} viewBox={`0 0 ${width} ${height}`}>
      <defs>
        <filter id="column-shadow" x="-50%" y="-50%" width="200%" height="200%">
          <feDropShadow dx="0" dy="3" stdDeviation="3" floodColor="#000000" floodOpacity="0.3" />
        </filter>
        <radialGradient id="column-gradient" cx="30%" cy="30%">
          <stop offset="0%" stopColor="#d4d4d8" />
          <stop offset="50%" stopColor="#a1a1aa" />
          <stop offset="100%" stopColor="#71717a" />
        </radialGradient>
      </defs>

      {/* Sombra proyectada */}
      <ellipse
        cx={width / 2 + 2}
        cy={height / 2 + 3}
        rx={size * 0.45}
        ry={size * 0.45}
        fill="#000000"
        opacity="0.2"
      />

      {/* Base de la columna */}
      {isSquare ? (
        <rect
          x="2"
          y="2"
          width={width - 4}
          height={height - 4}
          rx="3"
          fill="url(#column-gradient)"
          stroke="#52525b"
          strokeWidth="1.5"
          filter="url(#column-shadow)"
        />
      ) : (
        <ellipse
          cx={width / 2}
          cy={height / 2}
          rx={width / 2 - 2}
          ry={height / 2 - 2}
          fill="url(#column-gradient)"
          stroke="#52525b"
          strokeWidth="1.5"
          filter="url(#column-shadow)"
        />
      )}

      {/* Centro oscuro (núcleo estructural) */}
      {isSquare ? (
        <rect
          x={width * 0.35}
          y={height * 0.35}
          width={width * 0.3}
          height={height * 0.3}
          rx="2"
          fill="#3f3f46"
          opacity="0.4"
        />
      ) : (
        <circle
          cx={width / 2}
          cy={height / 2}
          r={size * 0.15}
          fill="#3f3f46"
          opacity="0.4"
        />
      )}

      {/* Brillo superior (simula luz) */}
      {isSquare ? (
        <rect
          x="4"
          y="4"
          width={width * 0.4}
          height={height * 0.2}
          rx="2"
          fill="#ffffff"
          opacity="0.3"
        />
      ) : (
        <ellipse
          cx={width * 0.4}
          cy={height * 0.35}
          rx={size * 0.2}
          ry={size * 0.1}
          fill="#ffffff"
          opacity="0.3"
        />
      )}
    </svg>
  );
};
