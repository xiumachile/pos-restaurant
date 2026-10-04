import { useEffect, useState, useCallback, useRef } from 'react';
import { useFloorPlanStore } from '@/stores/floor-plan/floorPlanStore';
import { localStorageService } from '@/services/floor-plan/localStorageService';
import type { FloorPlan } from '@/types/floor-plan/floorPlan.types';

/**
 * Hook para manejar persistencia local del plano actual.
 * - Auto-carga el plano guardado al iniciar
 * - Detecta cambios sin guardar (comparación profunda)
 * - Provee método save()
 */
export function useFloorPlanPersistence() {
  const { currentPlan, objects, setCurrentPlan, setObjects } = useFloorPlanStore();
  const [isSaving, setIsSaving] = useState(false);
  const [lastSavedAt, setLastSavedAt] = useState<string | null>(null);
  const [hasChanges, setHasChanges] = useState(false);
  
  // Ref para almacenar el último estado guardado
  const lastSavedStateRef = useRef<string | null>(null);

  // Auto-cargar al iniciar
  useEffect(() => {
    const stored = localStorageService.loadCurrentPlan();
    if (stored) {
      setCurrentPlan(stored.plan);
      setObjects(stored.objects);
      setLastSavedAt(stored.savedAt);
      
      // Guardar estado inicial como referencia
      const stateSignature = JSON.stringify({
        plan: stored.plan,
        objects: stored.objects,
      });
      lastSavedStateRef.current = stateSignature;
      
      console.log('📂 Plano cargado desde localStorage:', stored.plan.name);
    }
  }, [setCurrentPlan, setObjects]);

  // Detectar cambios con comparación profunda
  useEffect(() => {
    if (!currentPlan) return;

    const currentStateSignature = JSON.stringify({
      plan: currentPlan,
      objects: objects,
    });

    if (lastSavedStateRef.current === null) {
      // Primera vez: si hay objetos, hay cambios
      setHasChanges(objects.length > 0);
    } else {
      // Comparar con el último estado guardado
      setHasChanges(currentStateSignature !== lastSavedStateRef.current);
    }
  }, [currentPlan, objects]);

  // Guardar plano
  const save = useCallback(async () => {
    if (!currentPlan) {
      console.warn('⚠️ No hay plano que guardar');
      return { success: false };
    }

    setIsSaving(true);
    try {
      // Actualizar timestamp
      const updatedPlan: FloorPlan = {
        ...currentPlan,
        updated_at: new Date().toISOString(),
      };
      setCurrentPlan(updatedPlan);

      localStorageService.saveCurrentPlan(updatedPlan, objects);
      
      // Actualizar referencia del último estado guardado
      const newStateSignature = JSON.stringify({
        plan: updatedPlan,
        objects: objects,
      });
      lastSavedStateRef.current = newStateSignature;
      
      setLastSavedAt(updatedPlan.updated_at);
      setHasChanges(false);

      console.log('✅ Plano guardado:', {
        name: updatedPlan.name,
        objects: objects.length,
        savedAt: updatedPlan.updated_at,
      });

      return { success: true, savedAt: updatedPlan.updated_at };
    } catch (error) {
      console.error('❌ Error al guardar:', error);
      throw error;
    } finally {
      setIsSaving(false);
    }
  }, [currentPlan, objects, setCurrentPlan]);

  // Crear nuevo plano (limpia el actual)
  const createNew = useCallback(() => {
    const newPlan: FloorPlan = {
      id: Date.now(),
      uuid: crypto.randomUUID(),
      company_id: 1,
      branch_id: 1,
      name: 'Nuevo Plano',
      slug: null,
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

    setCurrentPlan(newPlan);
    setObjects([]);
    lastSavedStateRef.current = null;
    setHasChanges(true);
  }, [setCurrentPlan, setObjects]);

  return {
    save,
    createNew,
    isSaving,
    lastSavedAt,
    hasChanges,
    hasStoredPlan: localStorageService.hasStoredPlan(),
  };
}
