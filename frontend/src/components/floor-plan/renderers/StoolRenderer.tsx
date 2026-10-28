// src/components/floor-plan/renderers/StoolRenderer.tsx
import React from "react";

interface StoolRendererProps {
  size: number;
}

export const StoolRenderer: React.FC<StoolRendererProps> = ({ size }) => {
  return (
    <svg width={size} height={size} viewBox={`0 0 ${size} ${size}`}>
      <defs>
        <filter id="stool-shadow" x="-50%" y="-50%" width="200%" height="200%">
          <feDropShadow dx="0" dy="2" stdDeviation="1.5" floodColor="#000000" floodOpacity="0.25" />
        </filter>
      </defs>

      {/* Sombra */}
      <circle cx={size / 2 + 1} cy={size / 2 + 2} r={size * 0.42} fill="#000000" opacity="0.2" />

      {/* Base circular del taburete */}
      <circle
        cx={size / 2}
        cy={size / 2}
        r={size * 0.42}
        fill="#52525b"
        stroke="#3f3f46"
        strokeWidth="1"
        filter="url(#stool-shadow)"
      />

      {/* Asiento tapizado */}
      <circle
        cx={size / 2}
        cy={size / 2}
        r={size * 0.35}
        fill="#71717a"
        stroke="#52525b"
        strokeWidth="0.8"
      />

      {/* Brillo en el asiento */}
      <circle
        cx={size * 0.4}
        cy={size * 0.38}
        r={size * 0.15}
        fill="#ffffff"
        opacity="0.2"
      />

      {/* Centro (poste metálico visto desde arriba) */}
      <circle
        cx={size / 2}
        cy={size / 2}
        r={size * 0.12}
        fill="#3f3f46"
        stroke="#27272a"
        strokeWidth="0.5"
      />
    </svg>
  );
};
