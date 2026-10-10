import { useRef, useEffect } from 'react';
import { Stage, Layer, Rect, Line, Text } from 'react-konva';
import { useFloorPlanStore } from '@/stores/floor-plan/floorPlanStore';
import { useOperationalTableData } from '@/hooks/floor-plan/useOperationalTableData';
import { FloorPlanTable } from '../objects/FloorPlanTable';
import { FloorPlanDecoration } from '../objects/FloorPlanDecoration';
import type { CatalogItem, FloorPlanObject } from '@/types/floor-plan/floorPlan.types';

interface FloorPlanCanvasProps {
  width: number;
  height: number;
  isEditMode?: boolean;
  onTableClick?: (tableUuid: string, tableNumber: string) => void;
}

export function FloorPlanCanvas({ width, height, isEditMode = true, onTableClick }: FloorPlanCanvasProps) {
  const stageRef = useRef<any>(null);
  const { operationalDataMap } = useOperationalTableData();
  const containerRef = useRef<HTMLDivElement>(null);
  const {
    currentPlan,
    objects,
    viewport,
    setViewport,
    editor,
    clearSelection,
    addObject,
  } = useFloorPlanStore();

  const planWidth = currentPlan?.width ?? 1200;
  const planHeight = currentPlan?.height ?? 1800;
  const settings = currentPlan?.settings;
  const showGrid = settings?.showGrid ?? true;
  const gridSize = settings?.gridSize ?? 20;
  const snapToGrid = settings?.snapToGrid ?? true;

  // Zoom con rueda del mouse
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

  // Pan con drag del stage
  const handleDragEnd = (e: any) => {
    if (e.target !== stageRef.current) return;
    setViewport({ x: e.target.x(), y: e.target.y() });
  };

  // Click en canvas vacío: limpiar selección
  const handleStageClick = (e: any) => {
    if (e.target === stageRef.current || e.target.name() === 'plan-bg') {
      clearSelection();
    }
  };

  // ============ DRAG & DROP DESDE SIDEBAR ============

  const handleDragOver = (e: React.DragEvent) => {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'copy';
  };

  const handleDrop = (e: React.DragEvent) => {
    e.preventDefault();

    const itemData = e.dataTransfer.getData('application/floor-plan-object');
    if (!itemData || !currentPlan) return;

    const item: CatalogItem = JSON.parse(itemData);
    const stage = stageRef.current;
    if (!stage) return;

    // Convertir coordenadas del drop a coordenadas del plano
    const rect = containerRef.current?.getBoundingClientRect();
    if (!rect) return;

    const pointerX = e.clientX - rect.left;
    const pointerY = e.clientY - rect.top;

    // Convertir a coordenadas del plano (considerando viewport)
    let x = (pointerX - viewport.x) / viewport.scale;
    let y = (pointerY - viewport.y) / viewport.scale;

    // Snap a cuadrícula si está habilitado
    if (snapToGrid) {
      x = Math.round(x / gridSize) * gridSize;
      y = Math.round(y / gridSize) * gridSize;
    }

    // Asegurar que esté dentro del plano
    x = Math.max(0, Math.min(planWidth, x));
    y = Math.max(0, Math.min(planHeight, y));

    const zIndexMap: Record<string, number> = {
      table: 10, wall: 1, column: 2, window: 2, door: 2, separator: 3,
      decoration: 4, furniture: 5, plant: 6, service: 7, infrastructure: 1,
    };

    const newObject: FloorPlanObject = {
      id: Date.now(),
      uuid: crypto.randomUUID(),
      floor_plan_id: currentPlan.id,
      object_type: item.type,
      object_key: null,
      x,
      y,
      width: item.defaultWidth,
      height: item.defaultHeight,
      rotation: 0,
      z_index: zIndexMap[item.type] ?? 5,
      properties: item.defaultProperties,
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
    };

    addObject(newObject);
  };

  // ============ RENDERIZADO ============

  const renderGrid = () => {
    if (!showGrid) return null;
    const lines = [];
    for (let x = 0; x <= planWidth; x += gridSize) {
      lines.push(<Line key={`v-${x}`} points={[x, 0, x, planHeight]} stroke="#e5e7eb" strokeWidth={0.5} />);
    }
    for (let y = 0; y <= planHeight; y += gridSize) {
      lines.push(<Line key={`h-${y}`} points={[0, y, planWidth, y]} stroke="#e5e7eb" strokeWidth={0.5} />);
    }
    return lines;
  };

  return (
    <div
      ref={containerRef}
      className="flex-1 overflow-hidden bg-gray-100 relative"
      onDragOver={handleDragOver}
      onDrop={handleDrop}
    >
      <Stage
        ref={stageRef}
        width={width}
        height={height}
        scaleX={viewport.scale}
        scaleY={viewport.scale}
        x={viewport.x}
        y={viewport.y}
        draggable={editor.mode === 'pan'}
        onWheel={handleWheel}
        onDragEnd={handleDragEnd}
        onClick={handleStageClick}
        onTap={handleStageClick}
      >
        {/* Capa de fondo */}
        <Layer>
          <Rect
            name="plan-bg"
            x={0}
            y={0}
            width={planWidth}
            height={planHeight}
            fill="#fafafa"
            stroke="#374151"
            strokeWidth={2}
          />
          {renderGrid()}
          <Text x={planWidth / 2 - 50} y={-25} text={`${planWidth} x ${planHeight}`} fontSize={14} fill="#6b7280" />
        </Layer>

        {/* Capa de objetos */}
        <Layer>
          {[...objects].sort((a, b) => (a.z_index ?? 0) - (b.z_index ?? 0)).map((obj) => {
            const isSelected = editor.selectedObjectIds.includes(obj.uuid);
            if (obj.object_type === 'table') {
              const opData = obj.object_key ? operationalDataMap.get(obj.object_key) : null;
              return (
                <FloorPlanTable
                  key={obj.uuid}
                  object={obj}
                  isSelected={isSelected}
                  isEditMode={isEditMode}
                  onOperationalClick={onTableClick}
                  operationalData={opData}
                />
              );
            }
            return <FloorPlanDecoration key={obj.uuid} object={obj} isSelected={isSelected} />;
          })}
        </Layer>
      </Stage>

      {/* Indicador de zoom */}
      <div className="absolute bottom-4 right-4 bg-white px-3 py-2 rounded shadow text-sm text-gray-700">
        {(viewport.scale * 100).toFixed(0)}% · {objects.length} obj
      </div>

      {/* Instrucciones cuando no hay objetos */}
      {objects.length === 0 && (
        <div className="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 text-center pointer-events-none">
          <p className="text-gray-400 text-lg">
            Arrastra mesas desde la biblioteca ←
          </p>
          <p className="text-gray-400 text-sm mt-2">
            o haz click en + en cualquier mesa
          </p>
        </div>
      )}
    </div>
  );
}
