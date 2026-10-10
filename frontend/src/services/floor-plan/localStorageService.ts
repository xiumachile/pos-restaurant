import type { FloorPlan, FloorPlanObject } from '@/types/floor-plan/floorPlan.types';

const STORAGE_KEY = 'floor_plan_current';
const STORAGE_VERSION = 1;

interface StoredFloorPlan {
  version: number;
  plan: FloorPlan;
  objects: FloorPlanObject[];
  savedAt: string;
}

/**
 * Servicio de persistencia local usando localStorage.
 * En futuras sesiones se migrará a IndexedDB para mejor capacidad.
 */
export const localStorageService = {
  /**
   * Guarda el plano completo (plan + objetos) en localStorage
   */
  saveCurrentPlan(plan: FloorPlan, objects: FloorPlanObject[]): void {
    try {
      const data: StoredFloorPlan = {
        version: STORAGE_VERSION,
        plan,
        objects,
        savedAt: new Date().toISOString(),
      };
      localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
      console.log('✅ Plano guardado localmente:', {
        plan: plan.name,
        objects: objects.length,
        savedAt: data.savedAt,
      });
    } catch (error) {
      console.error('❌ Error al guardar plano local:', error);
      throw error;
    }
  },

  /**
   * Carga el plano guardado desde localStorage
   */
  loadCurrentPlan(): StoredFloorPlan | null {
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      if (!raw) return null;

      const data = JSON.parse(raw) as StoredFloorPlan;

      // Validar versión
      if (data.version !== STORAGE_VERSION) {
        console.warn('⚠️ Versión de plano incompatible, descartando');
        localStorage.removeItem(STORAGE_KEY);
        return null;
      }

      return data;
    } catch (error) {
      console.error('❌ Error al cargar plano local:', error);
      return null;
    }
  },

  /**
   * Elimina el plano guardado
   */
  clearCurrentPlan(): void {
    localStorage.removeItem(STORAGE_KEY);
  },

  /**
   * Verifica si hay un plano guardado
   */
  hasStoredPlan(): boolean {
    return localStorage.getItem(STORAGE_KEY) !== null;
  },

  /**
   * Obtiene metadata del plano guardado (sin cargar todo)
   */
  getStoredPlanMetadata(): { name: string; savedAt: string; objectCount: number } | null {
    const data = this.loadCurrentPlan();
    if (!data) return null;
    return {
      name: data.plan.name,
      savedAt: data.savedAt,
      objectCount: data.objects.length,
    };
  },
};
