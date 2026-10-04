import { Circle } from 'react-konva';
import type { ChairConfig } from '@/types/floor-plan/floorPlan.types';

interface FloorPlanChairProps {
  config: ChairConfig;
  tableWidth: number;
  tableHeight: number;
  tableShape: string;
}

/**
 * Renderiza una silla individual alrededor de una mesa.
 * La posición es relativa al centro de la mesa.
 */
export function FloorPlanChair({ config, tableWidth, tableHeight, tableShape }: FloorPlanChairProps) {
  const chairSize = 18;
  const offset = tableShape === 'round' ? 8 : 12; // Distancia al borde

  let x = 0;
  let y = 0;

  switch (config.position) {
    case 'top':
      x = 0;
      y = -(tableHeight / 2 + offset + chairSize / 2);
      break;
    case 'bottom':
      x = 0;
      y = tableHeight / 2 + offset + chairSize / 2;
      break;
    case 'left':
      x = -(tableWidth / 2 + offset + chairSize / 2);
      y = 0;
      break;
    case 'right':
      x = tableWidth / 2 + offset + chairSize / 2;
      y = 0;
      break;
    case 'top-right':
      x = tableWidth / 2 * 0.7 + offset;
      y = -(tableHeight / 2 * 0.7 + offset);
      break;
    case 'top-left':
      x = -(tableWidth / 2 * 0.7 + offset);
      y = -(tableHeight / 2 * 0.7 + offset);
      break;
    case 'bottom-right':
      x = tableWidth / 2 * 0.7 + offset;
      y = tableHeight / 2 * 0.7 + offset;
      break;
    case 'bottom-left':
      x = -(tableWidth / 2 * 0.7 + offset);
      y = tableHeight / 2 * 0.7 + offset;
      break;
  }

  return (
    <Circle
      x={x}
      y={y}
      radius={chairSize / 2}
      fill="#4B5563"
      stroke="#1F2937"
      strokeWidth={1}
      shadowColor="black"
      shadowBlur={2}
      shadowOpacity={0.3}
    />
  );
}
