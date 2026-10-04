import { useEffect, useState } from 'react';
import { useFloorPlanStore } from '@/stores/floor-plan/floorPlanStore';
import { FloorPlanCanvas } from '@/components/floor-plan/editor/FloorPlanCanvas';
import { FloorPlanToolbar } from '@/components/floor-plan/editor/FloorPlanToolbar';
import { FloorPlanSidebar } from '@/components/floor-plan/editor/FloorPlanSidebar';
import type { FloorPlan } from '@/types/floor-plan/floorPlan.types';

// Plano de ejemplo para desarrollo
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
  const { setCurrentPlan, reset } = useFloorPlanStore();
  const [canvasSize, setCanvasSize] = useState({ width: 1000, height: 700 });

  useEffect(() => {
    setCurrentPlan(mockPlan);
    return () => {
      reset();
    };
  }, [setCurrentPlan, reset]);

  // Recalcular tamaño del canvas cuando cambia la ventana
  useEffect(() => {
    const updateSize = () => {
      const sidebarWidth = 256; // w-64
      const toolbarHeight = 60;
      setCanvasSize({
        width: window.innerWidth - sidebarWidth,
        height: window.innerHeight - toolbarHeight,
      });
    };

    updateSize();
    window.addEventListener('resize', updateSize);
    return () => window.removeEventListener('resize', updateSize);
  }, []);

  return (
    <div className="h-screen flex flex-col">
      <FloorPlanToolbar />
      <div className="flex flex-1 overflow-hidden">
        <FloorPlanSidebar />
        <FloorPlanCanvas width={canvasSize.width} height={canvasSize.height} />
      </div>
    </div>
  );
}
