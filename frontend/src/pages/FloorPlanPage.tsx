import { useEffect, useState } from 'react';
import { useFloorPlanStore } from '@/stores/floor-plan/floorPlanStore';
import { FloorPlanCanvas } from '@/components/floor-plan/editor/FloorPlanCanvas';
import { FloorPlanToolbar } from '@/components/floor-plan/editor/FloorPlanToolbar';
import { FloorPlanSidebar } from '@/components/floor-plan/editor/FloorPlanSidebar';
import { FloorPlanProperties } from '@/components/floor-plan/editor/FloorPlanProperties';
import { useFloorPlanKeyboard } from '@/hooks/floor-plan/useFloorPlanKeyboard';
import { useFloorPlanPersistence } from '@/hooks/floor-plan/useFloorPlanPersistence';
import { localStorageService } from '@/services/floor-plan/localStorageService';
import type { FloorPlan } from '@/types/floor-plan/floorPlan.types';

export function FloorPlanPage() {
  const { editor, setCurrentPlan } = useFloorPlanStore();
  const { save, hasChanges, isSaving, lastSavedAt } = useFloorPlanPersistence();
  const [canvasSize, setCanvasSize] = useState({ width: 1000, height: 700 });

  // Atajos de teclado globales
  useFloorPlanKeyboard();

  // Cargar plano mock si no hay ninguno guardado
  useEffect(() => {
    if (!localStorageService.hasStoredPlan()) {
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
      setCurrentPlan(mockPlan);
    }
  }, [setCurrentPlan]);


  // Guardar con Ctrl+S
  useEffect(() => {
    const handleSave = (e: KeyboardEvent) => {
      if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        save();
      }
    };
    window.addEventListener('keydown', handleSave);
    return () => window.removeEventListener('keydown', handleSave);
  }, [save]);

  // Calcular tamaño del canvas
  useEffect(() => {
    const updateSize = () => {
      const sidebarWidth = 256;
      const propertiesWidth = editor.selectedObjectIds.length > 0 ? 320 : 0;
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
      <FloorPlanToolbar
        onSave={save}
        isSaving={isSaving}
        hasChanges={hasChanges}
        lastSavedAt={lastSavedAt}
      />
      <div className="flex flex-1 overflow-hidden">
        <FloorPlanSidebar />
        <FloorPlanCanvas width={canvasSize.width} height={canvasSize.height} />
        {hasSelection && <FloorPlanProperties />}
      </div>
    </div>
  );
}
