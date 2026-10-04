<?php

namespace Modules\Tables\Domain\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Tables\Domain\Entities\DiningZone;
use Modules\Tables\Domain\Entities\RestaurantTable;
use Modules\Tables\Domain\Exceptions\InvalidTableStatusTransition;
use Modules\Tables\Domain\ValueObjects\TableShape;

/**
 * Servicio de dominio para operaciones del floor plan.
 *
 * RESPONSABILIDADES:
 * - Cargar el layout completo (zonas + mesas con posiciones)
 * - Guardar posiciones de mesas en transacción atómica
 * - Validar integridad del floor plan (mesas sin zona, etc.)
 */
class FloorPlanService
{
    /**
     * Carga el floor plan completo del branch.
     * Retorna zonas activas con sus mesas (todas, incluyendo inactivas).
     */
    public function getFloorPlan(int $companyId, int $branchId): array
    {
        $zones = DiningZone::where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->withCount('tables')
            ->ordered()
            ->get();

        // Traer TODAS las mesas del branch (no solo las de zonas activas)
        // para mostrar también mesas "legacy" sin zona asignada
        $tables = RestaurantTable::where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->with('zone')
            ->get();

        return [
            'zones' => $zones,
            'tables' => $tables,
        ];
    }

    /**
     * Guarda las posiciones de todas las mesas en una transacción atómica.
     *
     * @param int $branchId
     * @param array $tablesData Array de [uuid => [position_x, position_y, rotation, shape, width, height]]
     * @return Collection Mesas actualizadas
     * @throws \InvalidArgumentException Si alguna mesa no pertenece al branch
     */
    public function saveFloorPlan(int $branchId, array $tablesData): Collection
    {
        $uuids = array_column($tablesData, 'uuid');

        return DB::transaction(function () use ($branchId, $tablesData, $uuids) {
            // Cargar todas las mesas del branch por UUID (defensa extra)
            $tables = RestaurantTable::where('branch_id', $branchId)
                ->whereNull('deleted_at')
                ->whereIn('uuid', $uuids)
                ->get()
                ->keyBy('uuid');

            // Verificar que todas las mesas existen en el branch
            if ($tables->count() !== count($uuids)) {
                throw new \InvalidArgumentException(
                    'Algunas mesas no pertenecen a esta sucursal.'
                );
            }

            // Actualizar cada mesa
            foreach ($tablesData as $tableData) {
                $table = $tables->get($tableData['uuid']);

                $table->update([
                    'position_x' => (int) $tableData['position_x'],
                    'position_y' => (int) $tableData['position_y'],
                    'rotation' => (int) $tableData['rotation'],
                    'shape' => $tableData['shape'],
                    'width' => $tableData['width'] ?? null,
                    'height' => $tableData['height'] ?? null,
                ]);
            }

            return $tables->values();
        });
    }

    /**
     * Crea una nueva zona en el branch.
     */
    public function createZone(int $companyId, int $branchId, array $data): DiningZone
    {
        return DiningZone::create(array_merge($data, [
            'company_id' => $companyId,
            'branch_id' => $branchId,
        ]));
    }

    /**
     * Actualiza una zona existente (solo campos visibles).
     *
     * @throws \InvalidArgumentException Si la zona no pertenece al branch
     */
    public function updateZone(string $uuid, int $branchId, array $data): DiningZone
    {
        $zone = DiningZone::where('uuid', $uuid)
            ->where('branch_id', $branchId)
            ->firstOrFail();

        $zone->update($data);

        return $zone->fresh();
    }

    /**
     * Elimina una zona solo si no tiene mesas asociadas.
     *
     * @throws \RuntimeException Si la zona tiene mesas asociadas
     * @throws \InvalidArgumentException Si la zona no pertenece al branch
     */
    public function deleteZone(string $uuid, int $branchId): void
    {
        $zone = DiningZone::where('uuid', $uuid)
            ->where('branch_id', $branchId)
            ->firstOrFail();

        if ($zone->activeTablesCount() > 0) {
            throw new \RuntimeException(
                "No se puede eliminar la zona '{$zone->code}' porque tiene {$zone->activeTablesCount()} mesa(s) asignada(s). " .
                "Mueva las mesas a otra zona primero."
            );
        }

        $zone->delete();
    }
}
