import { useRef, useEffect } from 'react';
import { Group, Rect, Circle, Ellipse, Text, Transformer } from 'react-konva';
import type { FloorPlanObject, TableProperties } from '@/types/floor-plan/floorPlan.types';
import { FloorPlanChair } from './FloorPlanChair';
import { useFloorPlanStore } from '@/stores/floor-plan/floorPlanStore';

interface FloorPlanTableProps {
  object: FloorPlanObject;
  isSelected: boolean;
}

/**
 * Renderiza una mesa con sus sillas.
 * Incluye Transformer para rotar/redimensionar cuando está seleccionada.
 */
export function FloorPlanTable({ object, isSelected }: FloorPlanTableProps) {
  const groupRef = useRef<any>(null);
  const transformerRef = useRef<any>(null);
  const { selectObject, moveObject, updateObject, currentPlan, pushHistory } = useFloorPlanStore();

  const properties = object.properties as TableProperties;
  const { shape, capacity, chairs, color, label } = properties;

  const width = object.width ?? 100;
  const height = object.height ?? 100;

  // Adjuntar el transformer cuando esté seleccionado
  useEffect(() => {
    if (isSelected && transformerRef.current && groupRef.current) {
      transformerRef.current.nodes([groupRef.current]);
      transformerRef.current.getLayer()?.batchDraw();
    }
  }, [isSelected]);

  const handleClick = (e: any) => {
    e.cancelBubble = true;
    selectObject(object.uuid, e.evt?.shiftKey);
  };

  const handleDragStart = () => {
    // Guardar posición inicial para historial
    pushHistory({
      type: 'MOVE_OBJECTS',
      objectIds: [object.uuid],
      positions: { [object.uuid]: { x: object.x, y: object.y } },
    });
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

  const handleTransformEnd = () => {
    const node = groupRef.current;
    if (!node) return;

    const scaleX = node.scaleX();
    const scaleY = node.scaleY();
    const newRotation = node.rotation();

    // Aplicar escala y resetear a 1
    node.scaleX(1);
    node.scaleY(1);

    const newWidth = Math.max(40, node.width() * scaleX);
    const newHeight = Math.max(40, node.height() * scaleY);

    pushHistory({
      type: 'UPDATE_OBJECT',
      objectId: object.uuid,
      changes: {
        width: object.width,
        height: object.height,
        rotation: object.rotation,
      },
    });

    updateObject(object.uuid, {
      width: Math.round(newWidth),
      height: Math.round(newHeight),
      rotation: Math.round(newRotation),
    });
  };

  const renderTableSurface = () => {
    const commonProps = {
      fill: color,
      stroke: '#1F2937',
      strokeWidth: 2,
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
    <>
      <Group
        ref={groupRef}
        x={object.x}
        y={object.y}
        rotation={object.rotation}
        draggable
        onClick={handleClick}
        onTap={handleClick}
        onDragStart={handleDragStart}
        onDragEnd={handleDragEnd}
        onTransformEnd={handleTransformEnd}
        width={width}
        height={height}
      >
        {/* Sillas */}
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

        {/* Número de mesa */}
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

      {/* Transformer solo cuando está seleccionada */}
      {isSelected && (
        <Transformer
          ref={transformerRef}
          rotateEnabled={true}
          enabledAnchors={['top-left', 'top-right', 'bottom-left', 'bottom-right']}
          boundBoxFunc={(oldBox, newBox) => {
            // Tamaño mínimo 40x40
            if (newBox.width < 40 || newBox.height < 40) {
              return oldBox;
            }
            return newBox;
          }}
          anchorSize={8}
          anchorStrokeWidth={2}
          anchorStroke="#3B82F6"
          anchorFill="white"
          borderStroke="#3B82F6"
          borderStrokeWidth={2}
          borderDash={[5, 3]}
        />
      )}
    </>
  );
}
