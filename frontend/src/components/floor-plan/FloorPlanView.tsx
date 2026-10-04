import { useEffect, useState } from 'react';
import { useLocation } from 'react-router-dom';
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
  showModeToggle?: boolean;
}

/**
 * Guarda defensivo: solo renderizar en rutas donde corresponde.
 * Esto previene que el canvas Konva y el toolbar "floten" sobre otras páginas
 * si el componente no se desmonta correctamente.
 */
function shouldRenderFloorPlan(pathname: string): boolean {
  // Rutas donde SÍ debe aparecer el plano:
  // - "/" (índice, que es TablesPage)
  // - "/tables" (explícito)
  // - "/floor-plan"
  if (pathname === '/' || pathname === '/tables' || pathname === '/floor-plan') {
    return true;
  }
  
  // Rutas donde NO debe aparecer:
  // - "/tables/:uuid" (toma de pedido) - patrón UUID
  // - "/orders/..." 
  // - Cualquier otra ruta
  return false;
}

export function FloorPlanView({ isEditMode, onToggleEditMode, onTableClick, showModeToggle = true }: FloorPlanViewProps) {
  const location = useLocation();
  const { editor, setCurrentPlan, setObjects, reset } = useFloorPlanStore();
  const { save, hasChanges, isSaving, lastSavedAt } = useFloorPlanPersistence();
  const [canvasSize, setCanvasSize] = useState({ width: 1000, height: 700 });

  useFloorPlanKeyboard();

  // GUARD: No renderizar en rutas donde no corresponde
  const shouldRender = shouldRenderFloorPlan(location.pathname);

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
        settings: { gridSize: 20, snapToGrid: true, showGrid: true },
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

  // Cleanup agresivo al desmontar
  useEffect(() => {
    return () => {
      console.log('[FloorPlanView] Desmontando - limpiando estado');
      reset();
      // Destruir todos los stages de Konva residuales
      const konvaContainers = document.querySelectorAll('.konvajs-content');
      konvaContainers.forEach((el) => {
        if (el.parentNode) {
          el.parentNode.removeChild(el);
        }
      });
    };
  }, [reset]);

  // Calcular tamaño dinámico del canvas
  useEffect(() => {
    const updateSize = () => {
      const sidebarWidth = isEditMode ? 256 : 0;
      const propertiesWidth = isEditMode && editor.selectedObjectIds.length > 0 ? 320 : 0;
      const toolbarHeight = 60;
      const container = document.getElementById('floor-plan-container');
      const availWidth = container?.clientWidth ?? (window.innerWidth - sidebarWidth - propertiesWidth);
      const availHeight = container?.clientHeight ?? (window.innerHeight - toolbarHeight - 80);
      setCanvasSize({
        width: Math.max(availWidth - sidebarWidth - propertiesWidth, 400),
        height: Math.max(availHeight - toolbarHeight, 300),
      });
    };

    updateSize();
    window.addEventListener('resize', updateSize);
    return () => window.removeEventListener('resize', updateSize);
  }, [editor.selectedObjectIds.length, isEditMode]);

  // GUARD: Si la ruta no corresponde, NO renderizar nada
  // Esto es el fix definitivo contra la superposición
  if (!shouldRender) {
    console.log('[FloorPlanView] Ruta no corresponde, no renderizando:', location.pathname);
    return null;
  }

  const hasSelection = editor.selectedObjectIds.length > 0;

  return (
    <div id="floor-plan-container" className="h-full flex flex-col overflow-hidden">
      <FloorPlanToolbar
        onSave={isEditMode ? save : undefined}
        isSaving={isSaving}
        hasChanges={hasChanges}
        lastSavedAt={lastSavedAt}
        isEditMode={isEditMode}
        onToggleEditMode={showModeToggle ? onToggleEditMode : undefined}
      />
      <div className="flex flex-1 overflow-hidden relative">
        {isEditMode && <FloorPlanSidebar />}
        <FloorPlanCanvas
          width={canvasSize.width}
          height={canvasSize.height}
          isEditMode={isEditMode}
          onTableClick={onTableClick}
        />
        {isEditMode && hasSelection && <FloorPlanProperties />}
        {!isEditMode && <OperationalModeOverlay onTableClick={onTableClick} />}
      </div>
    </div>
  );
}
