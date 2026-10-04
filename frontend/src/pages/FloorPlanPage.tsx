import { useEffect, useState } from 'react';
import { useFloorPlanStore } from '@/stores/floor-plan/floorPlanStore';
import { FloorPlanCanvas } from '@/components/floor-plan/editor/FloorPlanCanvas';
import { FloorPlanToolbar } from '@/components/floor-plan/editor/FloorPlanToolbar';
import { FloorPlanSidebar } from '@/components/floor-plan/editor/FloorPlanSidebar';
import { FloorPlanProperties } from '@/components/floor-plan/editor/FloorPlanProperties';
import { useFloorPlanKeyboard } from '@/hooks/floor-plan/useFloorPlanKeyboard';
import type { FloorPlan } from '@/types/floor-plan/floorPlan.types';

const mockPlan: FloorPlan = {
  id: 1,
  uuid: '550e8400-e29b-41d4-a716-446655440000',
  company_id: 1,
  branch_id: 1,
  name: 'Salón Principal',
  slug: 'salon-principal',
  width: 1200,
  height: 1800,
  scale: 100,
  background: null,
  settings: {
    gridSize: 20,
    snapToGrid: true,
    showGrid: true,
  },
  version: 1,
  status: 'draft',
  published_at: null,
  published_by: null,
  created_at: new Date().toISOString(),
  updated_at: new Date().toISOString(),
};

export function FloorPlanPage() {
  const { setCurrentPlan, reset, editor } = useFloorPlanStore();
  const [canvasSize, setCanvasSize] = useState({ width: 1000, height: 700 });

  // Atajos de teclado globales
  useFloorPlanKeyboard();

  useEffect(() => {
    setCurrentPlan(mockPlan);
    return () => {
      reset();
    };
  }, [setCurrentPlan, reset]);

  useEffect(() => {
    const updateSize = () => {
      const sidebarWidth = 256; // w-64
      const propertiesWidth = editor.selectedObjectIds.length > 0 ? 320 : 0; // w-80
      const toolbarHeight = 60;
      setCanvasSize({
        width: window.innerWidth - sidebarWidth - propertiesWidth,
        height: window.innerHeight - toolbarHeight,
      });
    };

    updateSize();
    window.addEventListener('resize', updateSize);
    return () => window.removeEventListener('resize', updateSize);
  }, [editor.selectedObjectIds.length]);

  const hasSelection = editor.selectedObjectIds.length > 0;

  return (
    <div className="h-screen flex flex-col">
      <FloorPlanToolbar />
      <div className="flex flex-1 overflow-hidden">
        <FloorPlanSidebar />
        <FloorPlanCanvas width={canvasSize.width} height={canvasSize.height} />
        {hasSelection && <FloorPlanProperties />}
      </div>
    </div>
  );
}
