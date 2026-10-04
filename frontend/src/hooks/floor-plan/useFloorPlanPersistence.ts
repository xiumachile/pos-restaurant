import { useEffect, useState, useCallback } from 'react';
import { useFloorPlanStore } from '@/stores/floor-plan/floorPlanStore';
import { localStorageService } from '@/services/floor-plan/localStorageService';
import type { FloorPlan } from '@/types/floor-plan/floorPlan.types';

/**
 * Hook para manejar persistencia local del plano actual.
 * - Auto-carga el plano guardado al iniciar
 * - Detecta cambios sin guardar
 * - Provee método save()
 */
export function useFloorPlanPersistence() {
  const { currentPlan, objects, setCurrentPlan, setObjects } = useFloorPlanStore();
  const [isSaving, setIsSaving] = useState(false);
  const [lastSavedAt, setLastSavedAt] = useState<string | null>(null);
  const [hasChanges, setHasChanges] = useState(false);

  // Auto-cargar al iniciar
  useEffect(() => {
    const stored = localStorageService.loadCurrentPlan();
    if (stored) {
      setCurrentPlan(stored.plan);
      setObjects(stored.objects);
      setLastSavedAt(stored.savedAt);
      console.log('📂 Plano cargado desde localStorage:', stored.plan.name);
    }
  }, [setCurrentPlan, setObjects]);

  // Detectar cambios
  useEffect(() => {
    if (!currentPlan) return;

    const stored = localStorageService.loadCurrentPlan();
    if (!stored) {
      setHasChanges(objects.length > 0);
      return;
    }

    // Comparación simple: cantidad de objetos + timestamp de actualización
    const objectsChanged = stored.objects.length !== objects.length;
    const planChanged = stored.plan.updated_at !== currentPlan.updated_at;

    setHasChanges(objectsChanged || planChanged);
  }, [currentPlan, objects]);

  // Guardar plano
  const save = useCallback(async () => {
    if (!currentPlan) {
      alert('No hay plano que guardar');
      return;
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
      setLastSavedAt(updatedPlan.updated_at);
      setHasChanges(false);

      return { success: true, savedAt: updatedPlan.updated_at };
    } catch (error) {
      console.error('Error al guardar:', error);
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
