import { useEffect, useState } from 'react';
import { floorPlanService, type OperationalTable } from '@/services/floorPlanService';
import { useFloorPlanStore } from '@/stores/floor-plan/floorPlanStore';

/**
 * Hook para cargar mesas operativas del módulo Tables.
 * Retorna todas las mesas + lista de mesas disponibles (no vinculadas aún)
 */
export function useRestaurantTables() {
  const [tables, setTables] = useState<OperationalTable[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const { objects } = useFloorPlanStore();

  const loadTables = async () => {
    setLoading(true);
    setError(null);
    try {
      console.log('🔄 Cargando mesas operativas...');
      const data = await floorPlanService.getOperationalTables();
      console.log('✅ Mesas cargadas:', data.length, data);
      setTables(data);
    } catch (err: any) {
      console.error('❌ Error al cargar mesas:', err);
      setError(err?.message ?? 'Error al cargar mesas');
      setTables([]);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadTables();
  }, []);

  // UUIDs de mesas ya vinculadas a objetos gráficos
  const linkedTableUuids = new Set(
    objects
      .filter((obj) => obj.object_type === 'table' && obj.object_key)
      .map((obj) => obj.object_key as string)
  );

  // Mesas disponibles (no vinculadas a ningún objeto gráfico)
  const availableTables = tables.filter((t) => !linkedTableUuids.has(t.uuid));

  // Mesas ya vinculadas
  const linkedTables = tables.filter((t) => linkedTableUuids.has(t.uuid));

  return {
    tables,
    availableTables,
    linkedTables,
    loading,
    error,
    reload: loadTables,
    isTableLinked: (tableUuid: string) => linkedTableUuids.has(tableUuid),
  };
}
