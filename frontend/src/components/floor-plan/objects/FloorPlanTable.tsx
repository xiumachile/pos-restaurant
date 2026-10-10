import { useRef, useEffect } from 'react';
import { Group, Rect, Circle, Ellipse, Text, Transformer } from 'react-konva';
import type { FloorPlanObject, TableProperties } from '@/types/floor-plan/floorPlan.types';
import { FloorPlanChair } from './FloorPlanChair';
import { useFloorPlanStore } from '@/stores/floor-plan/floorPlanStore';
import type { OperationalTableData } from '@/hooks/floor-plan/useOperationalTableData';

interface FloorPlanTableProps {
  object: FloorPlanObject;
  isSelected: boolean;
  isEditMode?: boolean;
  onOperationalClick?: (tableUuid: string, tableNumber: string) => void;
  operationalData?: OperationalTableData | null;
}

/**
 * Renderiza una mesa con sus sillas.
 * 
 * MODO EDICIÓN: permite arrastrar, redimensionar, rotar.
 * MODO OPERATIVO (garzón): muestra información operacional directamente sobre la mesa:
 *   - Color de borde según estado
 *   - Tiempo transcurrido desde apertura
 *   - Monto total consumido
 *   - Indicador de items pendientes
 */
export function FloorPlanTable({ object, isSelected, isEditMode = true, onOperationalClick, operationalData }: FloorPlanTableProps) {
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
    if (isEditMode) {
      selectObject(object.uuid, e.evt?.shiftKey);
    } else if (onOperationalClick && object.object_key) {
      const props = object.properties as any;
      onOperationalClick(object.object_key, props?.label || '');
    } else {
      selectObject(object.uuid, false);
    }
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

  // ═══════════════════════════════════════════════════════════
  // COLORES SEGÚN ESTADO OPERACIONAL
  // ═══════════════════════════════════════════════════════════
  const getStrokeColor = () => {
    if (isEditMode) return isSelected ? '#3B82F6' : '#1F2937';
    if (!operationalData) return '#1F2937';
    
    switch (operationalData.status) {
      case 'available': return '#10b981'; // verde
      case 'occupied': return '#ef4444'; // rojo
      case 'reserved': return '#f59e0b'; // ámbar
      case 'maintenance':
      case 'blocked': return '#6b7280'; // gris
      default: return '#1F2937';
    }
  };

  const getStatusFillColor = () => {
    if (!operationalData) return '#1F2937';
    switch (operationalData.status) {
      case 'available': return '#10b981';
      case 'occupied': return '#ef4444';
      case 'reserved': return '#f59e0b';
      case 'maintenance':
      case 'blocked': return '#6b7280';
      default: return '#1F2937';
    }
  };

  const formatTimeElapsed = (minutes: number): string => {
    if (minutes < 60) return `${minutes}m`;
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;
    return `${hours}h${mins > 0 ? ` ${mins}m` : ''}`;
  };

  const formatAmount = (amount: number): string => {
    // Formato corto: $12.5k para miles, $1.2M para millones
    if (amount >= 1000000) {
      return `$${(amount / 1000000).toFixed(1)}M`;
    }
    if (amount >= 10000) {
      return `$${(amount / 1000).toFixed(0)}k`;
    }
    if (amount >= 1000) {
      return `$${(amount / 1000).toFixed(1)}k`;
    }
    return `$${amount}`;
  };

  const renderTableSurface = () => {
    const commonProps = {
      fill: color,
      stroke: getStrokeColor(),
      strokeWidth: isEditMode ? 2 : 4,
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

  // ═══════════════════════════════════════════════════════════
  // BADGES OPERACIONALES (solo en modo operativo)
  // ═══════════════════════════════════════════════════════════
  const renderOperationalBadges = () => {
    if (isEditMode || !operationalData) return null;

    const { status, timeElapsedMinutes, totalAmount, guestCount, hasPendingItems } = operationalData;
    
    // Configurar texto e icono según estado (ACCESIBILIDAD: texto como identificador primario)
    const statusConfig = {
      available: {
        icon: '✓',
        text: 'DISPONIBLE',
        textColor: '#10b981', // verde
      },
      occupied: {
        icon: '👥',
        text: 'OCUPADA',
        textColor: '#3b82f6', // azul
      },
      reserved: {
        icon: '📅',
        text: 'RESERVADA',
        textColor: '#f59e0b', // ámbar
      },
      maintenance: {
        icon: '🔧',
        text: 'MANTENIMIENTO',
        textColor: '#6b7280', // gris
      },
      blocked: {
        icon: '🚫',
        text: 'NO DISPONIBLE',
        textColor: '#ef4444', // rojo
      },
    };

    const config = statusConfig[status] || statusConfig.available;
    const badgeWidth = width - 8;
    
    return (
      <>
        {/* Badge principal: ESTADO (texto MAYÚSCULAS + icono) - CENTRADO SIN MARCO */}
        <Group x={-(badgeWidth / 2)} y={-height / 2 + 20}>
          <Text
            text={`${config.icon} ${config.text}`}
            fontSize={11}
            fill={config.textColor}
            fontStyle="bold"
            width={badgeWidth}
            align="center"
            y={5}
          />
        </Group>

        {/* Información contextual para mesas ocupadas */}
        {status === 'occupied' && (
          <>
            {/* Tiempo transcurrido - CENTRADO */}
            {timeElapsedMinutes !== null && timeElapsedMinutes !== undefined && (
              <Group x={-(badgeWidth / 2)} y={-height / 2 + 38}>
                <Text
                  text={`⏱ ${formatTimeElapsed(timeElapsedMinutes)}`}
                  fontSize={10}
                  fill="#94a3b8"
                  width={badgeWidth}
                  align="center"
                />
              </Group>
            )}

            {/* Conteo de personas - CENTRADO */}
            {guestCount !== null && guestCount !== undefined && guestCount > 0 && (
              <Group x={-(badgeWidth / 2)} y={-height / 2 + 52}>
                <Text
                  text={`${guestCount} personas`}
                  fontSize={9}
                  fill="#64748b"
                  width={badgeWidth}
                  align="center"
                />
              </Group>
            )}
          </>
        )}

        {/* Monto total (para ocupada o por cobrar) - CENTRADO SIN MARCO */}
        {totalAmount !== null && totalAmount !== undefined && totalAmount > 0 && (
          <Group x={-(badgeWidth / 2)} y={height / 2 - 24}>
            <Text
              text={`💰 ${formatAmount(totalAmount)}`}
              fontSize={10}
              fill="#10b981"
              fontStyle="bold"
              width={badgeWidth}
              align="center"
              y={4}
            />
          </Group>
        )}

        {/* Indicador de items pendientes (esquina superior derecha) */}
        {hasPendingItems && (
          <Group x={width / 2 - 14} y={-height / 2 + 2}>
            <Circle
              radius={9}
              fill="#dc2626"
              stroke="white"
              strokeWidth={2}
            />
            <Text
              text="!"
              fontSize={12}
              fill="white"
              fontStyle="bold"
              align="center"
              width={18}
              y={-6}
            />
          </Group>
        )}
      </>
    );
  };

  return (
    <>
      <Group
        ref={groupRef}
        x={object.x}
        y={object.y}
        rotation={object.rotation}
        draggable={isEditMode}
        onClick={handleClick}
        onTap={handleClick}
        onDragStart={handleDragStart}
        onDragEnd={handleDragEnd}
        onTransformEnd={handleTransformEnd}
        width={width}
        height={height}
      >
        {/* Sillas (solo en modo edición para no saturar visualmente) */}
        {isEditMode && chairs.map((chair, idx) => (
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

        {/* Número de mesa (centro, grande) */}
        <Text
          text={label || object.uuid.slice(0, 4)}
          fontSize={isEditMode ? 14 : 18}
          fontStyle="bold"
          fill="white"
          align="center"
          width={width}
          offsetX={width / 2}
          offsetY={isEditMode ? -8 : -12}
          shadowColor="black"
          shadowBlur={2}
          shadowOpacity={0.8}
        />

        {/* Capacidad (solo en modo edición) */}
        {isEditMode && (
          <Text
            text={`${capacity}p`}
            fontSize={10}
            fill="rgba(255,255,255,0.8)"
            align="center"
            width={width}
            offsetX={width / 2}
            offsetY={4}
          />
        )}

        {/* Estado operativo visible (solo modo operativo, disponible) */}
        {!isEditMode && operationalData?.status === 'available' && (
          <Text
            text="✓ Libre"
            fontSize={12}
            fontStyle="bold"
            fill="#10b981"
            align="center"
            width={width}
            offsetX={width / 2}
            offsetY={4}
            shadowColor="black"
            shadowBlur={2}
          />
        )}

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

        {/* Badges operacionales */}
        {renderOperationalBadges()}
      </Group>

      {/* Transformer solo en modo edición y cuando está seleccionada */}
      {isEditMode && isSelected && (
        <Transformer
          ref={transformerRef}
          rotateEnabled={true}
          enabledAnchors={['top-left', 'top-right', 'bottom-left', 'bottom-right']}
          boundBoxFunc={(oldBox, newBox) => {
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
