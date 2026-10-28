// src/components/floor-plan/renderers/BoothRenderer.tsx
import React from "react";

interface BoothRendererProps {
  width: number;
  height: number;
}

export const BoothRenderer: React.FC<BoothRendererProps> = ({ width, height }) => {
  const seatDepth = height * 0.25;
  const tableWidth = width * 0.6;
  const tableHeight = height * 0.4;
  const tableX = (width - tableWidth) / 2;
  const tableY = (height - tableHeight) / 2;

  return (
    <svg width={width} height={height} viewBox={`0 0 ${width} ${height}`}>
      {/* Sombra general */}
      <rect x="2" y="2" width={width - 4} height={height - 4} rx="4" fill="#000000" opacity="0.15" />

      {/* Asiento trasero (respaldo alto) */}
      <rect x="4" y="4" width={width - 8} height={seatDepth} rx="3" fill="#475569" stroke="#334155" strokeWidth="1" />
      <rect x="6" y="6" width={width - 12} height={seatDepth * 0.4} rx="2" fill="#64748b" opacity="0.5" />

      {/* Asiento delantero */}
      <rect x="4" y={height - seatDepth - 4} width={width - 8} height={seatDepth} rx="3" fill="#475569" stroke="#334155" strokeWidth="1" />
      <rect x="6" y={height - seatDepth - 4} width={width - 12} height={seatDepth * 0.6} rx="2" fill="#64748b" opacity="0.5" />

      {/* Mesa central */}
      <rect x={tableX} y={tableY} width={tableWidth} height={tableHeight} rx="4" fill="#D9B98B" stroke="#8D6845" strokeWidth="1" />
      <rect x={tableX + 2} y={tableY + 2} width={tableWidth - 4} height={tableHeight * 0.3} rx="2" fill="#FFFFFF" opacity="0.2" />
    </svg>
  );
};
