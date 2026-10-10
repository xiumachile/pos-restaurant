import { useEffect, useState, useCallback, useRef } from 'react';
import { useFloorPlanStore } from '@/stores/floor-plan/floorPlanStore';
import { localStorageService } from '@/services/floor-plan/localStorageService';
import type { FloorPlan, FloorPlanObject } from '@/types/floor-plan/floorPlan.types';

/**
 * Valida que un string sea un UUID válido
 */
function isValidUUID(uuid: string | null | undefined): boolean {
  if (!uuid || typeof uuid !== 'string') return false;
  const uuidRegex = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;
  return uuidRegex.test(uuid);
}

/**
 * Repara object_keys corruptos en los objetos del plano
 * Si object_key no es un UUID válido, lo establece a null
 */
function repairCorruptedObjectKeys(objects: FloorPlanObject[]): { repaired: FloorPlanObject[], count: number } {
  let repairCount = 0;
  
  const repaired = objects.map((obj) => {
    if (obj.object_key && !isValidUUID(obj.object_key)) {
      console.warn(`[useFloorPlanPersistence] Reparando object_key corrupto en objeto ${obj.uuid}: "${obj.object_key}" → null`);
      repairCount++;
      return { ...obj, object_key: null };
    }
    return obj;
  });
  
  return { repaired, count: repairCount };
}

/**
 * Hook para manejar persistencia local del plano actual.
 * - Auto-carga el plano guardado al iniciar
 * - Repara automáticamente object_keys corruptos
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
      // REPARACIÓN AUTOMÁTICA: Limpiar object_keys corruptos
      const { repaired, count } = repairCorruptedObjectKeys(stored.objects);
      
      if (count > 0) {
        console.log(`[useFloorPlanPersistence] Reparados ${count} object_keys corruptos`);
        // Guardar la versión reparada inmediatamente
        localStorageService.saveCurrentPlan(stored.plan, repaired);
      }
      
      setCurrentPlan(stored.plan);
      setObjects(repaired);
      setLastSavedAt(stored.savedAt);
      
      // Guardar estado inicial como referencia
      const stateSignature = JSON.stringify({
        plan: stored.plan,
        objects: repaired,
      });
      lastSavedStateRef.current = stateSignature;
      
      console.log('📂 Plano cargado desde localStorage:', stored.plan.name, 
                  count > 0 ? `(reparados ${count} vínculos corruptos)` : '');
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
      // VALIDACIÓN: Asegurar que no se guarden object_keys corruptos
      const { repaired, count } = repairCorruptedObjectKeys(objects);
      
      if (count > 0) {
        console.warn(`[useFloorPlanPersistence] Prevenido guardado de ${count} object_keys corruptos`);
      }
      
      // Actualizar timestamp
      const updatedPlan: FloorPlan = {
        ...currentPlan,
        updated_at: new Date().toISOString(),
      };
      setCurrentPlan(updatedPlan);

      localStorageService.saveCurrentPlan(updatedPlan, repaired);
      
      // Actualizar referencia del último estado guardado
      const newStateSignature = JSON.stringify({
        plan: updatedPlan,
        objects: repaired,
      });
      lastSavedStateRef.current = newStateSignature;
      
      setLastSavedAt(updatedPlan.updated_at);
      setHasChanges(false);

      console.log('✅ Plano guardado:', {
        name: updatedPlan.name,
        objects: repaired.length,
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
