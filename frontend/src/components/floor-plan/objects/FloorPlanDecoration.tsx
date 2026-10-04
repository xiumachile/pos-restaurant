import { useRef, useEffect } from 'react';
import { Group, Rect, Circle, Text, Transformer } from 'react-konva';
import type { FloorPlanObject } from '@/types/floor-plan/floorPlan.types';
import { useFloorPlanStore } from '@/stores/floor-plan/floorPlanStore';

interface FloorPlanDecorationProps {
  object: FloorPlanObject;
  isSelected: boolean;
}

/**
 * Renderiza un elemento decorativo/arquitectónico con emoji visual.
 * Los emojis se guardan en properties.emoji y se renderizan con Text de Konva.
 */
export function FloorPlanDecoration({ object, isSelected }: FloorPlanDecorationProps) {
  const groupRef = useRef<any>(null);
  const transformerRef = useRef<any>(null);
  const { selectObject, moveObject, updateObject, currentPlan, pushHistory } = useFloorPlanStore();

  const properties = object.properties as any;
  const color = properties?.color ?? '#6b7280';
  const label = properties?.label ?? '';
  const emoji = properties?.emoji ?? '⬜';

  const width = object.width ?? 80;
  const height = object.height ?? 80;

  const isCircle = ['plant', 'lamp', 'stool', 'column'].includes(object.object_type);
  const isLinear = ['wall', 'window', 'door', 'screen', 'lattice', 'panel', 'entrance', 'emergency-exit'].includes(object.object_type);

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

    node.scaleX(1);
    node.scaleY(1);

    const newWidth = Math.max(20, node.width() * scaleX);
    const newHeight = Math.max(20, node.height() * scaleY);

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

  const renderShape = () => {
    const strokeColor = isSelected ? '#3B82F6' : '#1F2937';
    const strokeWidth = isSelected ? 3 : 2;

    if (isCircle) {
      return (
        <Circle
          radius={Math.min(width, height) / 2}
          fill={color}
          stroke={strokeColor}
          strokeWidth={strokeWidth}
          shadowColor="black"
          shadowBlur={3}
          shadowOpacity={0.3}
        />
      );
    }

    return (
      <Rect
        x={-width / 2}
        y={-height / 2}
        width={width}
        height={height}
        fill={color}
        stroke={strokeColor}
        strokeWidth={strokeWidth}
        shadowColor="black"
        shadowBlur={3}
        shadowOpacity={0.3}
        cornerRadius={isLinear ? 2 : 6}
      />
    );
  };

  // Calcular tamaño del emoji proporcional al objeto
  const emojiFontSize = Math.min(width, height) * 0.6;

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
        {renderShape()}

        {/* Emoji central (referencia visual) */}
        <Text
          text={emoji}
          fontSize={emojiFontSize}
          align="center"
          verticalAlign="middle"
          width={width}
          height={height}
          offsetX={width / 2}
          offsetY={height / 2}
          fill="black"
        />

        {/* Etiqueta opcional debajo */}
        {label && (
          <Text
            text={label}
            fontSize={10}
            fontStyle="bold"
            fill="white"
            align="center"
            width={width}
            offsetX={width / 2}
            offsetY={height / 2 + 5}
          />
        )}
      </Group>

      {isSelected && (
        <Transformer
          ref={transformerRef}
          rotateEnabled={true}
          enabledAnchors={['top-left', 'top-right', 'bottom-left', 'bottom-right']}
          boundBoxFunc={(oldBox, newBox) => {
            if (newBox.width < 20 || newBox.height < 20) {
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
