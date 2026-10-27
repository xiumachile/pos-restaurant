import React from "react";

export interface GeometryProps {
  color: string;
  capacity?: number;
  className?: string;
}

// ============================================================================
// MESAS - Vista cenital realista con sillas
// ============================================================================
export const TableSquareGeometry: React.FC<GeometryProps> = ({ color, capacity = 4 }) => {
  // Sillas como semicírculos alrededor de la mesa
  const chairs = [];
  const chairSize = 12;
  const offset = 8;
  
  if (capacity >= 2) {
    // Arriba y abajo
    chairs.push(<rect key="t" x={50 - chairSize/2} y={offset} width={chairSize} height={chairSize} rx="3" fill={color} opacity="0.6" />);
    chairs.push(<rect key="b" x={50 - chairSize/2} y={100 - offset - chairSize} width={chairSize} height={chairSize} rx="3" fill={color} opacity="0.6" />);
  }
  if (capacity >= 4) {
    // Izquierda y derecha
    chairs.push(<rect key="l" x={offset} y={50 - chairSize/2} width={chairSize} height={chairSize} rx="3" fill={color} opacity="0.6" />);
    chairs.push(<rect key="r" x={100 - offset - chairSize} y={50 - chairSize/2} width={chairSize} height={chairSize} rx="3" fill={color} opacity="0.6" />);
  }

  return (
    <svg viewBox="0 0 100 100" className="w-full h-full">
      {chairs}
      {/* Sombra de la mesa */}
      <rect x="23" y="23" width="54" height="54" rx="4" fill="rgba(0,0,0,0.15)" />
      {/* Mesa con color de estado */}
      <rect x="22" y="22" width="56" height="56" rx="4" fill={color} />
      {/* Brillo superior (simula superficie) */}
      <rect x="22" y="22" width="56" height="20" rx="4" fill="rgba(255,255,255,0.25)" />
      {/* Borde sutil */}
      <rect x="22" y="22" width="56" height="56" rx="4" fill="none" stroke="rgba(0,0,0,0.2)" strokeWidth="1" />
    </svg>
  );
};

export const TableRoundGeometry: React.FC<GeometryProps> = ({ color, capacity = 4 }) => {
  const chairs = [];
  const radius = 42;
  const chairSize = 11;
  
  for (let i = 0; i < capacity; i++) {
    const angle = (i * 360) / capacity - 90;
    const rad = (angle * Math.PI) / 180;
    const cx = 50 + radius * Math.cos(rad);
    const cy = 50 + radius * Math.sin(rad);
    chairs.push(
      <circle key={i} cx={cx} cy={cy} r={chairSize/2} fill={color} opacity="0.6" />
    );
  }

  return (
    <svg viewBox="0 0 100 100" className="w-full h-full">
      {chairs}
      {/* Sombra */}
      <circle cx="51" cy="51" r="35" fill="rgba(0,0,0,0.15)" />
      {/* Mesa redonda */}
      <circle cx="50" cy="50" r="35" fill={color} />
      {/* Brillo */}
      <circle cx="50" cy="50" r="35" fill="url(#roundTableShine)" />
      <defs>
        <radialGradient id="roundTableShine" cx="35%" cy="35%">
          <stop offset="0%" stopColor="rgba(255,255,255,0.3)" />
          <stop offset="100%" stopColor="rgba(0,0,0,0.1)" />
        </radialGradient>
      </defs>
      <circle cx="50" cy="50" r="35" fill="none" stroke="rgba(0,0,0,0.2)" strokeWidth="1" />
    </svg>
  );
};

export const TableRectGeometry: React.FC<GeometryProps> = ({ color, capacity = 6 }) => {
  const chairs = [];
  const chairSize = 11;
  const topChairs = Math.ceil(capacity / 2);
  const botChairs = Math.floor(capacity / 2);
  
  for (let i = 0; i < topChairs; i++) {
    const x = 20 + (i * 60) / Math.max(topChairs - 1, 1);
    chairs.push(<circle key={`t${i}`} cx={x} cy="12" r={chairSize/2} fill={color} opacity="0.6" />);
  }
  for (let i = 0; i < botChairs; i++) {
    const x = 20 + (i * 60) / Math.max(botChairs - 1, 1);
    chairs.push(<circle key={`b${i}`} cx={x} cy="88" r={chairSize/2} fill={color} opacity="0.6" />);
  }

  return (
    <svg viewBox="0 0 100 100" className="w-full h-full">
      {chairs}
      {/* Sombra */}
      <rect x="11" y="29" width="78" height="42" rx="4" fill="rgba(0,0,0,0.15)" />
      {/* Mesa rectangular */}
      <rect x="10" y="28" width="80" height="44" rx="4" fill={color} />
      {/* Brillo */}
      <rect x="10" y="28" width="80" height="18" rx="4" fill="rgba(255,255,255,0.25)" />
      <rect x="10" y="28" width="80" height="44" rx="4" fill="none" stroke="rgba(0,0,0,0.2)" strokeWidth="1" />
    </svg>
  );
};

// ============================================================================
// MURO - Línea gruesa con textura
// ============================================================================
export const WallGeometry: React.FC<GeometryProps> = ({ color }) => (
  <svg viewBox="0 0 100 100" className="w-full h-full" preserveAspectRatio="none">
    {/* Sombra del muro */}
    <rect x="1" y="2" width="98" height="96" fill="rgba(0,0,0,0.2)" />
    {/* Muro principal */}
    <rect x="0" y="0" width="100" height="100" fill={color} />
    {/* Textura de líneas horizontales (simula ladrillo o panel) */}
    <line x1="0" y1="25" x2="100" y2="25" stroke="rgba(0,0,0,0.15)" strokeWidth="1" />
    <line x1="0" y1="50" x2="100" y2="50" stroke="rgba(0,0,0,0.15)" strokeWidth="1" />
    <line x1="0" y1="75" x2="100" y2="75" stroke="rgba(0,0,0,0.15)" strokeWidth="1" />
    {/* Borde superior (luz) */}
    <line x1="0" y1="0" x2="100" y2="0" stroke="rgba(255,255,255,0.3)" strokeWidth="2" />
    {/* Borde inferior (sombra) */}
    <line x1="0" y1="100" x2="100" y2="100" stroke="rgba(0,0,0,0.3)" strokeWidth="2" />
  </svg>
);

// ============================================================================
// PLANTA - Maceta con follaje orgánico
// ============================================================================
export const PlantGeometry: React.FC<GeometryProps> = ({ color }) => (
  <svg viewBox="0 0 100 100" className="w-full h-full">
    {/* Sombra de la maceta */}
    <ellipse cx="52" cy="52" rx="40" ry="40" fill="rgba(0,0,0,0.15)" />
    {/* Maceta (círculo exterior marrón) */}
    <circle cx="50" cy="50" r="40" fill="#6B4423" />
    <circle cx="50" cy="50" r="36" fill="#8B5A2B" />
    {/* Tierra */}
    <circle cx="50" cy="50" r="32" fill="#4A3728" />
    {/* Follaje orgánico (múltiples círculos verdes superpuestos) */}
    <circle cx="50" cy="50" r="30" fill={color} opacity="0.85" />
    <circle cx="38" cy="42" r="16" fill={color} opacity="0.9" />
    <circle cx="62" cy="45" r="14" fill={color} opacity="0.85" />
    <circle cx="45" cy="62" r="15" fill={color} opacity="0.9" />
    <circle cx="58" cy="58" r="13" fill={color} opacity="0.85" />
    {/* Hojas individuales (óvalos rotados) */}
    <ellipse cx="35" cy="38" rx="10" ry="6" fill={color} opacity="0.95" transform="rotate(-30 35 38)" />
    <ellipse cx="65" cy="42" rx="9" ry="5" fill={color} opacity="0.9" transform="rotate(40 65 42)" />
    <ellipse cx="48" cy="68" rx="10" ry="6" fill={color} opacity="0.95" transform="rotate(20 48 68)" />
    <ellipse cx="62" cy="62" rx="8" ry="5" fill={color} opacity="0.9" transform="rotate(-25 62 62)" />
  </svg>
);

// ============================================================================
// COLUMNA - Cuadrado con volumen 3D
// ============================================================================
export const PillarGeometry: React.FC<GeometryProps> = ({ color }) => (
  <svg viewBox="0 0 100 100" className="w-full h-full">
    {/* Sombra proyectada */}
    <rect x="12" y="12" width="78" height="78" rx="3" fill="rgba(0,0,0,0.2)" />
    {/* Base de la columna */}
    <rect x="10" y="10" width="80" height="80" rx="3" fill={color} />
    {/* Gradiente para simular volumen cilíndrico/cuadrado */}
    <rect x="10" y="10" width="80" height="80" rx="3" fill="url(#pillarShine)" />
    {/* Centro (núcleo estructural) */}
    <rect x="35" y="35" width="30" height="30" rx="2" fill="rgba(0,0,0,0.15)" />
    {/* Borde */}
    <rect x="10" y="10" width="80" height="80" rx="3" fill="none" stroke="rgba(0,0,0,0.3)" strokeWidth="1.5" />
    <defs>
      <linearGradient id="pillarShine" x1="0%" y1="0%" x2="100%" y2="100%">
        <stop offset="0%" stopColor="rgba(255,255,255,0.3)" />
        <stop offset="50%" stopColor="rgba(255,255,255,0)" />
        <stop offset="100%" stopColor="rgba(0,0,0,0.2)" />
      </linearGradient>
    </defs>
  </svg>
);

// ============================================================================
// BOOTH - Asientos en U con mesa
// ============================================================================
export const BoothGeometry: React.FC<GeometryProps> = ({ color }) => (
  <svg viewBox="0 0 100 100" className="w-full h-full">
    {/* Sombra del booth */}
    <rect x="12" y="12" width="76" height="76" rx="4" fill="rgba(0,0,0,0.15)" />
    {/* Respaldo izquierdo */}
    <rect x="10" y="10" width="18" height="80" rx="3" fill={color} opacity="0.85" />
    <rect x="10" y="10" width="18" height="15" rx="3" fill="rgba(255,255,255,0.2)" />
    {/* Respaldo derecho */}
    <rect x="72" y="10" width="18" height="80" rx="3" fill={color} opacity="0.85" />
    <rect x="72" y="10" width="18" height="15" rx="3" fill="rgba(255,255,255,0.2)" />
    {/* Respaldo superior (conecta los dos) */}
    <rect x="10" y="10" width="80" height="18" rx="3" fill={color} opacity="0.9" />
    <rect x="10" y="10" width="80" height="10" rx="3" fill="rgba(255,255,255,0.2)" />
    {/* Asientos interiores */}
    <rect x="28" y="28" width="44" height="12" rx="2" fill={color} opacity="0.7" />
    <rect x="28" y="60" width="44" height="12" rx="2" fill={color} opacity="0.7" />
    {/* Mesa central */}
    <rect x="35" y="42" width="30" height="16" rx="2" fill="#8B6F47" />
    <rect x="35" y="42" width="30" height="8" rx="2" fill="rgba(255,255,255,0.2)" />
    {/* Borde */}
    <rect x="10" y="10" width="80" height="80" rx="3" fill="none" stroke="rgba(0,0,0,0.25)" strokeWidth="1" />
  </svg>
);

// ============================================================================
// BARRA/MOSTRADOR - Rectángulo largo con taburetes
// ============================================================================
export const BarCounterGeometry: React.FC<GeometryProps> = ({ color }) => (
  <svg viewBox="0 0 100 100" className="w-full h-full" preserveAspectRatio="none">
    {/* Sombra */}
    <rect x="1" y="2" width="98" height="96" fill="rgba(0,0,0,0.15)" />
    {/* Barra principal */}
    <rect x="0" y="0" width="100" height="100" fill={color} />
    {/* Superficie de la barra (parte superior más clara) */}
    <rect x="0" y="0" width="100" height="35" fill="rgba(255,255,255,0.2)" />
    {/* Línea de separación */}
    <line x1="0" y1="35" x2="100" y2="35" stroke="rgba(0,0,0,0.25)" strokeWidth="1.5" />
    {/* Taburetes (círculos en la parte inferior) */}
    <circle cx="20" cy="70" r="10" fill={color} opacity="0.6" />
    <circle cx="20" cy="70" r="7" fill="rgba(255,255,255,0.15)" />
    <circle cx="50" cy="70" r="10" fill={color} opacity="0.6" />
    <circle cx="50" cy="70" r="7" fill="rgba(255,255,255,0.15)" />
    <circle cx="80" cy="70" r="10" fill={color} opacity="0.6" />
    <circle cx="80" cy="70" r="7" fill="rgba(255,255,255,0.15)" />
    {/* Borde */}
    <rect x="0" y="0" width="100" height="100" fill="none" stroke="rgba(0,0,0,0.25)" strokeWidth="1" />
  </svg>
);
