// src/components/floor-plan/renderers/ServiceStationRenderer.tsx
import React from "react";

interface ServiceStationRendererProps {
  width: number;
  height: number;
}

export const ServiceStationRenderer: React.FC<ServiceStationRendererProps> = ({ width, height }) => {
  return (
    <svg width={width} height={height} viewBox={`0 0 ${width} ${height}`}>
      <defs>
        <filter id="station-shadow" x="-50%" y="-50%" width="200%" height="200%">
          <feDropShadow dx="0" dy="2" stdDeviation="2" floodColor="#000000" floodOpacity="0.25" />
        </filter>
      </defs>

      {/* Sombra */}
      <rect x="2" y="2" width={width - 4} height={height - 4} rx="3" fill="#000000" opacity="0.2" />

      {/* Cuerpo principal del mueble */}
      <rect
        x="1"
        y="1"
        width={width - 2}
        height={height - 2}
        rx="3"
        fill="#475569"
        stroke="#334155"
        strokeWidth="1.2"
        filter="url(#station-shadow)"
      />

      {/* Superficie superior (donde se apoyan los platos) */}
      <rect
        x="3"
        y="3"
        width={width - 6}
        height={height * 0.4}
        rx="2"
        fill="#64748b"
      />

      {/* Compartimentos internos (cajones o estantes) */}
      <rect
        x="5"
        y={height * 0.5}
        width={width * 0.45 - 5}
        height={height * 0.45 - 5}
        rx="2"
        fill="#334155"
        stroke="#1e293b"
        strokeWidth="0.8"
      />
      <rect
        x={width * 0.5 + 2}
        y={height * 0.5}
        width={width * 0.45 - 5}
        height={height * 0.45 - 5}
        rx="2"
        fill="#334155"
        stroke="#1e293b"
        strokeWidth="0.8"
      />

      {/* Detalle de cubiertos o servilletas (líneas pequeñas) */}
      <line
        x1={width * 0.15}
        y1={height * 0.65}
        x2={width * 0.35}
        y2={height * 0.65}
        stroke="#94a3b8"
        strokeWidth="1"
      />
      <line
        x1={width * 0.15}
        y1={height * 0.75}
        x2={width * 0.35}
        y2={height * 0.75}
        stroke="#94a3b8"
        strokeWidth="1"
      />

      {/* Brillo en la superficie */}
      <rect
        x="3"
        y="3"
        width={width - 6}
        height={height * 0.15}
        rx="2"
        fill="#ffffff"
        opacity="0.15"
      />
    </svg>
  );
};
