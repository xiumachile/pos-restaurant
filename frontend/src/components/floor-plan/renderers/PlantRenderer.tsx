// src/components/floor-plan/renderers/PlantRenderer.tsx
import React from "react";

interface PlantRendererProps {
  diameter: number;
}

export const PlantRenderer: React.FC<PlantRendererProps> = ({ diameter }) => {
  const radius = diameter / 2;
  const center = radius;

  return (
    <svg width={diameter} height={diameter} viewBox={`0 0 ${diameter} ${diameter}`}>
      <defs>
        {/* Gradiente para dar volumen al follaje */}
        <radialGradient id="foliageGrad" cx="30%" cy="30%">
          <stop offset="0%" stopColor="#4ade80" />
          <stop offset="100%" stopColor="#166534" />
        </radialGradient>
      </defs>

      {/* 1. Maceta (base) */}
      <circle cx={center} cy={center} r={radius * 0.9} fill="#78350f" />
      <circle cx={center} cy={center} r={radius * 0.75} fill="#92400e" />
      
      {/* 2. Tierra */}
      <circle cx={center} cy={center} r={radius * 0.65} fill="#451a03" />

      {/* 3. Follaje orgánico (múltiples formas superpuestas para evitar el aspecto de "diana") */}
      <g fill="url(#foliageGrad)" opacity="0.9">
        <circle cx={center} cy={center} r={radius * 0.6} />
        <circle cx={center - radius * 0.3} cy={center - radius * 0.2} r={radius * 0.4} />
        <circle cx={center + radius * 0.35} cy={center + radius * 0.1} r={radius * 0.45} />
        <circle cx={center} cy={center + radius * 0.35} r={radius * 0.35} />
        <ellipse cx={center - radius * 0.4} cy={center + radius * 0.3} rx={radius * 0.3} ry={radius * 0.2} transform={`rotate(-30 ${center - radius * 0.4} ${center + radius * 0.3})`} />
        <ellipse cx={center + radius * 0.4} cy={center - radius * 0.3} rx={radius * 0.25} ry={radius * 0.35} transform={`rotate(45 ${center + radius * 0.4} ${center - radius * 0.3})`} />
      </g>
    </svg>
  );
};
