<?php

namespace Modules\Tables\Domain\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Tables\Domain\Entities\DiningZone;
use Modules\Tables\Domain\Entities\FloorPlanObject;
use Modules\Tables\Domain\Entities\RestaurantTable;

class FloorPlanService
{
    public function getFloorPlan(int $companyId, int $branchId): array
    {
        $zones = DiningZone::where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->withCount('tables')
            ->ordered()
            ->get();

        $tables = RestaurantTable::where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->with('zone')
            ->get();

        // Cargar objetos decorativos
        $decorations = FloorPlanObject::where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->get();

        $settings = DB::table('floor_plan_settings')
            ->where('branch_id', $branchId)
            ->first();

        return [
            'zones' => $zones,
            'tables' => $tables,
            'decorations' => $decorations,
            'canvas' => $settings ? [
                'width' => $settings->canvas_width,
                'height' => $settings->canvas_height,
                'grid_size' => $settings->grid_size,
            ] : [
                'width' => 2000,
                'height' => 1500,
                'grid_size' => 40,
            ],
        ];
    }

    public function saveFloorPlan(
        int $branchId,
        array $tablesData,
        array $decorationsData = [],
        array $canvasData = []
    ): array {
        $uuids = array_column($tablesData, 'uuid');

        return DB::transaction(function () use ($branchId, $tablesData, $uuids, $decorationsData, $canvasData) {
            // Actualizar mesas
            $tables = $this->updateTables($branchId, $tablesData, $uuids);

            // Guardar decoraciones (sync completo)
            $decorations = $this->syncDecorations($branchId, $decorationsData);

            // Guardar configuración del canvas
            if (!empty($canvasData)) {
                $this->saveCanvasConfig($branchId, $canvasData);
            }

            return [
                'tables' => $tables,
                'decorations' => $decorations,
            ];
        });
    }

    private function updateTables(int $branchId, array $tablesData, array $uuids): Collection
    {
        $uniqueUuids = array_unique($uuids);

        $tables = RestaurantTable::where('branch_id', $branchId)
            ->whereNull('deleted_at')
            ->whereIn('uuid', $uniqueUuids)
            ->get()
            ->keyBy('uuid');

        if ($tables->count() !== count($uniqueUuids)) {
            throw new \InvalidArgumentException(
                'Algunas mesas no pertenecen a esta sucursal.'
            );
        }

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
    }

    private function saveCanvasConfig(int $branchId, array $canvasData): void
    {
        $companyId = DB::table('branches')->where('id', $branchId)->value('company_id');

        if ($companyId === null) {
            throw new \RuntimeException('No se encontró la sucursal indicada.');
        }

        DB::table('floor_plan_settings')->updateOrInsert(
            ['branch_id' => $branchId],
            [
                'company_id' => $companyId,
                'canvas_width' => $canvasData['width'] ?? 2000,
                'canvas_height' => $canvasData['height'] ?? 1500,
                'grid_size' => $canvasData['grid_size'] ?? 40,
                'updated_at' => now(),
            ]
        );
    }

    private function syncDecorations(int $branchId, array $decorationsData): Collection
    {
        $company = DB::table('branches')->where('id', $branchId)->value('company_id');

        if ($company === null) {
            throw new \RuntimeException('No se encontró la sucursal indicada.');
        }

        // Eliminar decoraciones existentes
        FloorPlanObject::where('branch_id', $branchId)->delete();

        // Insertar nuevas decoraciones
        $decorations = collect();
        foreach ($decorationsData as $decoData) {
            $decoration = FloorPlanObject::create([
                'company_id' => $company,
                'branch_id' => $branchId,
                'uuid' => $decoData['uuid'],
                'type' => $decoData['type'],
                'position_x' => (int) $decoData['position_x'],
                'position_y' => (int) $decoData['position_y'],
                'rotation' => (int) $decoData['rotation'],
                'width' => $decoData['width'] ?? null,
                'height' => $decoData['height'] ?? null,
                'properties' => $decoData['properties'] ?? null,
            ]);
            $decorations->push($decoration);
        }

        return $decorations;
    }

    public function createZone(int $companyId, int $branchId, array $data): DiningZone
    {
        return DiningZone::create(array_merge($data, [
            'company_id' => $companyId,
            'branch_id' => $branchId,
        ]));
    }

    public function updateZone(string $uuid, int $branchId, array $data): DiningZone
    {
        $zone = DiningZone::where('uuid', $uuid)
            ->where('branch_id', $branchId)
            ->firstOrFail();

        $zone->update($data);

        return $zone->fresh();
    }

    public function deleteZone(string $uuid, int $branchId): void
    {
        $zone = DiningZone::where('uuid', $uuid)
            ->where('branch_id', $branchId)
            ->firstOrFail();

        if ($zone->activeTablesCount() > 0) {
            throw new \RuntimeException(
                "No se puede eliminar la zona '{$zone->code}' porque tiene {$zone->activeTablesCount()} mesa(s) asignada(s)."
            );
        }

        $zone->delete();
    }
}
