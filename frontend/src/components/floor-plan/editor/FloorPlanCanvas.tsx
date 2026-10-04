import { useRef, useEffect } from 'react';
import { Stage, Layer, Rect, Line, Text } from 'react-konva';
import { useFloorPlanStore } from '@/stores/floor-plan/floorPlanStore';
import type { CanvasViewport } from '@/types/floor-plan/floorPlan.types';

interface FloorPlanCanvasProps {
  width: number;
  height: number;
}

export function FloorPlanCanvas({ width, height }: FloorPlanCanvasProps) {
  const stageRef = useRef<any>(null);
  const { currentPlan, objects, viewport, setViewport, editor } = useFloorPlanStore();

  const planWidth = currentPlan?.width ?? 1200;
  const planHeight = currentPlan?.height ?? 1800;
  const settings = currentPlan?.settings;
  const showGrid = settings?.showGrid ?? true;
  const gridSize = settings?.gridSize ?? 20;

  // Manejar zoom con rueda del mouse
  const handleWheel = (e: any) => {
    e.evt.preventDefault();

    const stage = stageRef.current;
    const oldScale = viewport.scale;
    const pointer = stage.getPointerPosition();

    const mousePointTo = {
      x: (pointer.x - viewport.x) / oldScale,
      y: (pointer.y - viewport.y) / oldScale,
    };

    const direction = e.evt.deltaY > 0 ? -1 : 1;
    const newScale = direction > 0 ? oldScale * 1.1 : oldScale / 1.1;
    const clampedScale = Math.max(0.2, Math.min(3, newScale));

    setViewport({
      scale: clampedScale,
      x: pointer.x - mousePointTo.x * clampedScale,
      y: pointer.y - mousePointTo.y * clampedScale,
    });
  };

  // Manejar drag del stage (pan)
  const handleDragEnd = (e: any) => {
    setViewport({
      x: e.target.x(),
      y: e.target.y(),
    });
  };

  // Renderizar cuadrícula
  const renderGrid = () => {
    if (!showGrid) return null;

    const lines = [];
    const gridColor = '#e0e0e0';

    // Líneas verticales
    for (let x = 0; x <= planWidth; x += gridSize) {
      lines.push(
        <Line
          key={`v-${x}`}
          points={[x, 0, x, planHeight]}
          stroke={gridColor}
          strokeWidth={0.5}
        />
      );
    }

    // Líneas horizontales
    for (let y = 0; y <= planHeight; y += gridSize) {
      lines.push(
        <Line
          key={`h-${y}`}
          points={[0, y, planWidth, y]}
          stroke={gridColor}
          strokeWidth={0.5}
        />
      );
    }

    return lines;
  };

  // Renderizar borde del plano
  const renderPlanBorder = () => (
    <Rect
      x={0}
      y={0}
      width={planWidth}
      height={planHeight}
      fill="#f5f5f5"
      stroke="#333"
      strokeWidth={2}
    />
  );

  // Renderizar dimensiones
  const renderDimensions = () => (
    <>
      <Text
        x={planWidth / 2 - 50}
        y={-30}
        text={`${planWidth} unidades`}
        fontSize={14}
        fill="#666"
      />
      <Text
        x={-80}
        y={planHeight / 2}
        text={`${planHeight} unidades`}
        fontSize={14}
        fill="#666"
        rotation={-90}
      />
    </>
  );

  return (
    <div className="flex-1 overflow-hidden bg-gray-100 relative">
      <Stage
        ref={stageRef}
        width={width}
        height={height}
        scaleX={viewport.scale}
        scaleY={viewport.scale}
        x={viewport.x}
        y={viewport.y}
        draggable={editor.mode === 'pan' || editor.mode === 'select'}
        onWheel={handleWheel}
        onDragEnd={handleDragEnd}
      >
        <Layer>
          {renderPlanBorder()}
          {renderGrid()}
          {renderDimensions()}
        </Layer>
        <Layer>
          {/* Aquí se renderizarán los objetos en la siguiente fase */}
        </Layer>
      </Stage>

      {/* Indicador de zoom */}
      <div className="absolute bottom-4 right-4 bg-white px-3 py-2 rounded shadow text-sm">
        Zoom: {(viewport.scale * 100).toFixed(0)}%
      </div>
    </div>
  );
}
