import { Group, Rect, Circle, Ellipse, Text } from 'react-konva';
import type { FloorPlanObject, TableProperties } from '@/types/floor-plan/floorPlan.types';
import { FloorPlanChair } from './FloorPlanChair';
import { useFloorPlanStore } from '@/stores/floor-plan/floorPlanStore';

interface FloorPlanTableProps {
  object: FloorPlanObject;
}

/**
 * Renderiza una mesa con sus sillas alrededor.
 * La mesa tiene identidad visual persistente según la Sección 3.3.
 */
export function FloorPlanTable({ object }: FloorPlanTableProps) {
  const { editor, selectObject, moveObject, currentPlan } = useFloorPlanStore();
  const isSelected = editor.selectedObjectIds.includes(object.uuid);

  const properties = object.properties as TableProperties;
  const { shape, capacity, chairs, color, label } = properties;

  const width = object.width ?? 100;
  const height = object.height ?? 100;

  const handleClick = (e: any) => {
    e.cancelBubble = true;
    selectObject(object.uuid, e.evt?.shiftKey);
  };

  const handleDragEnd = (e: any) => {
    const newX = e.target.x();
    const newY = e.target.y();
    const gridSize = currentPlan?.settings?.gridSize ?? 20;
    const snapToGrid = currentPlan?.settings?.snapToGrid ?? true;

    const finalX = snapToGrid ? Math.round(newX / gridSize) * gridSize : newX;
    const finalY = snapToGrid ? Math.round(newY / gridSize) * gridSize : newY;

    moveObject(object.uuid, finalX, finalY);
  };

  // Renderizar la superficie de la mesa según la forma
  const renderTableSurface = () => {
    const commonProps = {
      fill: color,
      stroke: isSelected ? '#3B82F6' : '#1F2937',
      strokeWidth: isSelected ? 3 : 2,
      shadowColor: 'black',
      shadowBlur: 4,
      shadowOpacity: 0.4,
    };

    switch (shape) {
      case 'round':
        return <Circle radius={width / 2} {...commonProps} />;
      case 'oval':
        return <Ellipse radiusX={width / 2} radiusY={height / 2} {...commonProps} />;
      case 'square':
      case 'rectangle':
      default:
        return <Rect x={-width / 2} y={-height / 2} width={width} height={height} {...commonProps} />;
    }
  };

  return (
    <Group
      x={object.x}
      y={object.y}
      rotation={object.rotation}
      draggable
      onClick={handleClick}
      onTap={handleClick}
      onDragEnd={handleDragEnd}
    >
      {/* Sillas (detrás de la mesa) */}
      {chairs.map((chair, idx) => (
        <FloorPlanChair
          key={idx}
          config={chair}
          tableWidth={width}
          tableHeight={height}
          tableShape={shape}
        />
      ))}

      {/* Superficie de la mesa */}
      {renderTableSurface()}

      {/* Número/etiqueta de la mesa */}
      <Text
        text={label || object.uuid.slice(0, 4)}
        fontSize={14}
        fontStyle="bold"
        fill="white"
        align="center"
        width={width}
        offsetX={width / 2}
        offsetY={-8}
      />

      {/* Indicador de capacidad */}
      <Text
        text={`${capacity}p`}
        fontSize={10}
        fill="rgba(255,255,255,0.8)"
        align="center"
        width={width}
        offsetX={width / 2}
        offsetY={4}
      />

      {/* Resaltado de selección */}
      {isSelected && (
        <Rect
          x={-width / 2 - 5}
          y={-height / 2 - 5}
          width={width + 10}
          height={height + 10}
          stroke="#3B82F6"
          strokeWidth={2}
          dash={[5, 3]}
          cornerRadius={8}
        />
      )}
    </Group>
  );
}
