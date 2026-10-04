import { useEffect, useState } from 'react';
import { useFloorPlanStore } from '@/stores/floor-plan/floorPlanStore';
import { FloorPlanCanvas } from './editor/FloorPlanCanvas';
import { FloorPlanToolbar } from './editor/FloorPlanToolbar';
import { FloorPlanSidebar } from './editor/FloorPlanSidebar';
import { FloorPlanProperties } from './editor/FloorPlanProperties';
import { OperationalModeOverlay } from './operational/OperationalModeOverlay';
import { useFloorPlanKeyboard } from '@/hooks/floor-plan/useFloorPlanKeyboard';
import { useFloorPlanPersistence } from '@/hooks/floor-plan/useFloorPlanPersistence';
import { localStorageService } from '@/services/floor-plan/localStorageService';
import type { FloorPlan } from '@/types/floor-plan/floorPlan.types';

interface FloorPlanViewProps {
  isEditMode: boolean;
  onToggleEditMode?: () => void;
  onTableClick?: (tableUuid: string, tableNumber: string) => void;
  showModeToggle?: boolean; // Controla si se muestra el botón de toggle
}

/**
 * Componente principal del plano que sirve tanto para:
 * - Vista operativa (garzón): solo lectura, click en mesa abre pedido
 * - Modo edición (admin): con sidebar, propiedades y herramientas
 */
export function FloorPlanView({ isEditMode, onToggleEditMode, onTableClick, showModeToggle = true }: FloorPlanViewProps) {
  const { editor, setCurrentPlan, setObjects } = useFloorPlanStore();
  const { save, hasChanges, isSaving, lastSavedAt } = useFloorPlanPersistence();
  const [canvasSize, setCanvasSize] = useState({ width: 1000, height: 700 });

  // Atajos de teclado solo en modo edición
  useEffect(() => {
    if (!isEditMode) return;
    // useFloorPlanKeyboard ya maneja esto internamente
  }, [isEditMode]);

  useFloorPlanKeyboard();

  // Ctrl+S para guardar (solo en modo edición)
  useEffect(() => {
    if (!isEditMode) return;
    const handleSave = (e: KeyboardEvent) => {
      if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        save();
      }
    };
    window.addEventListener('keydown', handleSave);
    return () => window.removeEventListener('keydown', handleSave);
  }, [save, isEditMode]);

  // Cargar plano al iniciar
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

  // Calcular tamaño dinámico del canvas
  useEffect(() => {
    const updateSize = () => {
      const sidebarWidth = isEditMode ? 256 : 0;
      const propertiesWidth = isEditMode && editor.selectedObjectIds.length > 0 ? 320 : 0;
      const toolbarHeight = 60;
      setCanvasSize({
        width: window.innerWidth - sidebarWidth - propertiesWidth,
        height: window.innerHeight - toolbarHeight,
      });
    };

    updateSize();
    window.addEventListener('resize', updateSize);
    return () => window.removeEventListener('resize', updateSize);
  }, [editor.selectedObjectIds.length, isEditMode]);

  const hasSelection = editor.selectedObjectIds.length > 0;

  return (
    <div className="h-screen flex flex-col">
      <FloorPlanToolbar
        onSave={isEditMode ? save : undefined}
        isSaving={isSaving}
        hasChanges={hasChanges}
        lastSavedAt={lastSavedAt}
        isEditMode={isEditMode}
        onToggleEditMode={showModeToggle ? onToggleEditMode : undefined}
      />
      <div className="flex flex-1 overflow-hidden relative">
        {/* Sidebar solo en modo edición */}
        {isEditMode && <FloorPlanSidebar />}

        {/* Canvas siempre visible */}
        <FloorPlanCanvas
          width={canvasSize.width}
          height={canvasSize.height}
          isEditMode={isEditMode}
          onTableClick={onTableClick}
        />

        {/* Panel de propiedades solo en modo edición */}
        {isEditMode && hasSelection && <FloorPlanProperties />}

        {/* Overlay del modo operativo (indicadores de estado) */}
        {!isEditMode && <OperationalModeOverlay onTableClick={onTableClick} />}
      </div>
    </div>
  );
}
