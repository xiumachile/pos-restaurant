// src/components/floor-plan/renderers/DoorRenderer.tsx
import React from "react";

interface DoorRendererProps {
  width: number;
  height: number; // Grosor del muro
  swingDirection: 'left' | 'right';
}

export const DoorRenderer: React.FC<DoorRendererProps> = ({ width, height, swingDirection }) => {
  // El radio del arco es igual al ancho de la puerta
  const radius = width;
  const isLeft = swingDirection === 'left';
  
  // Path del arco de apertura (cuarto de círculo)
  // M = inicio, A = arco (rx ry x-axis-rotation large-arc-flag sweep-flag x y)
  const arcPath = isLeft 
    ? `M 0 ${height} A ${radius} ${radius} 0 0 0 ${width} ${height + width}` // Abre hacia la izquierda/abajo
    : `M ${width} ${height} A ${radius} ${radius} 0 0 1 0 ${height + width}`; // Abre hacia la derecha/abajo

  return (
    <svg width={width} height={height + width} viewBox={`0 0 ${width} ${height + width}`} className="overflow-visible">
      {/* 1. Hueco en el muro (la puerta en sí) */}
      <rect x="0" y="0" width={width} height={height} fill="#e2e8f0" stroke="#334155" strokeWidth="1" />
      
      {/* 2. Hoja de la puerta (línea gruesa) */}
      <line 
        x1={isLeft ? 0 : width} 
        y1={height} 
        x2={isLeft ? width : 0} 
        y2={height + width} 
        stroke="#0f172a" 
        strokeWidth="2" 
      />
      
      {/* 3. Arco de apertura (línea discontinua para indicar recorrido) */}
      <path d={arcPath} fill="none" stroke="#94a3b8" strokeWidth="1" strokeDasharray="4 4" />
      
      {/* 4. Bisagra (punto de pivote) */}
      <circle cx={isLeft ? 0 : width} cy={height} r="2" fill="#0f172a" />
    </svg>
  );
};
