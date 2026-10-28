import React from "react";
import { calculateChairPositions, SeatLayout } from "../geometry/chairLayout";

interface TableRendererProps {
  shape: "round" | "square" | "rectangle";
  width: number;
  height: number;
  seatLayout: SeatLayout;
  statusColor: string;
  showChairs?: boolean;
}

interface ChairProps {
  size: number;
  upholstery?: string;
}

const Chair: React.FC<ChairProps> = ({ size, upholstery = "#9A7653" }) => {
  const w = size;
  const h = size * 1.3;

  return (
    <g>
      <rect x={2} y={2} width={w - 2} height={h - 2} rx={3} fill="#000000" opacity={0.18} />
      <rect x={1} y={1} width={w - 2} height={h - 2} rx={3} fill="#594737" stroke="#3D3027" strokeWidth={0.8} />
      <rect x={3} y={3} width={w - 6} height={h * 0.34} rx={2} fill="#76563D" stroke="#493728" strokeWidth={0.6} />
      <path d={`M ${w * 0.25} ${h * 0.09} L ${w * 0.75} ${h * 0.09}`} stroke="#B89A7C" strokeWidth={0.8} opacity={0.8} />
      <rect x={3} y={h * 0.38} width={w - 6} height={h * 0.56} rx={3} fill={upholstery} stroke="#6E513C" strokeWidth={0.7} />
      <path d={`M ${w * 0.2} ${h * 0.47} L ${w * 0.8} ${h * 0.47}`} stroke="#FFFFFF" strokeWidth={0.8} opacity={0.35} />
    </g>
  );
};

export const TableRenderer: React.FC<TableRendererProps> = ({
  shape,
  width,
  height,
  seatLayout,
  statusColor,
  showChairs = true,
}) => {
  const isRound = shape === "round";
  const radius = isRound ? Math.min(width, height) / 2 : 0;
  const chairSize = Math.min(width, height) * 0.22;
  const chairHeight = chairSize * 1.3;

  const chairs = calculateChairPositions(shape, width, height, seatLayout, chairSize, chairHeight);

  return (
    <svg width={width} height={height} viewBox={`0 0 ${width} ${height}`} className="overflow-visible" style={{ overflow: "visible" }} role="img" aria-label={`Mesa ${shape}`}>
      <defs>
        <filter id="table-shadow" x="-25%" y="-25%" width="150%" height="150%">
          <feDropShadow dx={0} dy={2} stdDeviation={2} floodColor="#000000" floodOpacity={0.22} />
        </filter>
        <linearGradient id="wood-top" x1="0" y1="0" x2="0.9" y2="1">
          <stop offset="0%" stopColor="#D9B98B" />
          <stop offset="50%" stopColor="#C9A574" />
          <stop offset="100%" stopColor="#B78E5B" />
        </linearGradient>
      </defs>

      {/* Sillas (solo si showChairs es true) */}
      {showChairs && chairs.map((chair, index) => (
        <g key={`${chair.x}-${chair.y}-${index}`} transform={`translate(${chair.x}, ${chair.y}) rotate(${chair.rotation}, ${chairSize / 2}, ${chairHeight / 2})`}>
          <Chair size={chairSize} />
        </g>
      ))}

      {/* Cuerpo de la mesa */}
      <g filter="url(#table-shadow)">
        {isRound ? (
          <>
            <circle cx={width / 2} cy={height / 2} r={radius - 1} fill="#76583D" />
            <circle cx={width / 2} cy={height / 2} r={radius - 4} fill="url(#wood-top)" stroke="#8D6845" strokeWidth={1.5} />
            <circle cx={width / 2} cy={height / 2} r={radius * 0.78} fill="none" stroke="#FFFFFF" strokeWidth={0.8} opacity={0.22} />
          </>
        ) : (
          <>
            <rect x={1} y={1} width={width - 2} height={height - 2} rx={shape === "square" ? 5 : 7} fill="#76583D" />
            <rect x={3} y={3} width={width - 6} height={height - 6} rx={shape === "square" ? 4 : 6} fill="url(#wood-top)" stroke="#8D6845" strokeWidth={1.2} />
            <path d={`M ${width * 0.12} ${height * 0.28} L ${width * 0.88} ${height * 0.28} M ${width * 0.12} ${height * 0.72} L ${width * 0.88} ${height * 0.72}`} stroke="#FFFFFF" strokeWidth={0.7} opacity={0.16} />
          </>
        )}
      </g>

      {/* Indicador de estado */}
      <circle cx={width - 8} cy={8} r={3} fill={statusColor} stroke="#FFFFFF" strokeWidth={0.8} />
    </svg>
  );
};
