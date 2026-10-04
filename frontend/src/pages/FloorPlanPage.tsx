import { useEffect } from 'react';
import { useFloorPlanStore } from '@/stores/floor-plan/floorPlanStore';
import { FloorPlanCanvas } from '@/components/floor-plan/editor/FloorPlanCanvas';
import { FloorPlanToolbar } from '@/components/floor-plan/editor/FloorPlanToolbar';
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
  const { setCurrentPlan } = useFloorPlanStore();

  useEffect(() => {
    // Cargar plano de ejemplo
    setCurrentPlan(mockPlan);

    return () => {
      // Limpiar al desmontar
      setCurrentPlan(null);
    };
  }, [setCurrentPlan]);

  return (
    <div className="h-screen flex flex-col">
      <FloorPlanToolbar />
      <FloorPlanCanvas width={window.innerWidth} height={window.innerHeight - 60} />
    </div>
  );
}
