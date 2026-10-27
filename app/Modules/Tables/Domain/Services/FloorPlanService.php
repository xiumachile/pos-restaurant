<?php

namespace Modules\Tables\Domain\Services;

use Illuminate\Support\Facades\DB;
use Modules\Tables\Domain\Entities\DiningZone;
use Modules\Tables\Domain\Entities\FloorPlanObject;
use Modules\Tables\Domain\Entities\RestaurantTable;
use App\Shared\Application\TenantContext;

class FloorPlanService
{
    public function getFloorPlan(int $companyId, int $branchId): array
    {
        if (class_exists(TenantContext::class)) {
            app(TenantContext::class)->setCompany($companyId, $branchId);
        }

        try {
            // 1. Zonas
            $zones = DiningZone::where('branch_id', $branchId)
                ->select('dining_zones.*')
                ->selectSub(function ($query) {
                    $query->selectRaw('count(*)')
                          ->from('floor_plan_objects')
                          ->whereColumn('floor_plan_objects.zone_uuid', 'dining_zones.uuid')
                          ->where('floor_plan_objects.object_type', 'table');
                }, 'tables_count')
                ->ordered()
                ->get();

            // 2. Objetos gráficos
            $objects = FloorPlanObject::where('branch_id', $branchId)
                ->with('table')
                ->get()
                ->map(function ($obj) {
                    $data = $obj->toArray();
                    if ($obj->object_type === 'table' && $obj->table) {
                        $data['operational_data'] = [
                            'table_number' => $obj->table->table_number,
                            'capacity' => $obj->table->capacity,
                            'status' => $obj->table->status,
                            'has_active_order' => (bool) $obj->table->current_order_id,
                            'current_order_id' => $obj->table->current_order_id,
                        ];
                    }
                    return $data;
                });

            // 3. Mesas disponibles
            $linkedTableUuids = DB::table('floor_plan_objects')
                ->where('branch_id', $branchId)
                ->whereNotNull('table_uuid')
                ->pluck('table_uuid')
                ->toArray();

            $availableTablesRaw = DB::table('restaurant_tables')
                ->where('branch_id', $branchId)
                ->whereNull('deleted_at')
                ->whereNotIn('uuid', $linkedTableUuids)
                ->get(['uuid', 'table_number', 'capacity', 'status', 'current_order_id']);

            $availableTables = collect($availableTablesRaw)->map(function ($table) {
                return [
                    'uuid' => $table->uuid,
                    'table_number' => $table->table_number,
                    'capacity' => $table->capacity,
                    'status' => $table->status,
                    'has_active_order' => (bool) $table->current_order_id,
                    'current_order_id' => $table->current_order_id,
                ];
            })->toArray();

            // 4. Mesas con posición (COMPATIBILIDAD PARA TESTS Y FRONTEND LEGACY)
            $tables = RestaurantTable::where('company_id', $companyId)
                ->where('branch_id', $branchId)
                ->whereNull('deleted_at')
                ->whereNotNull('position_x')
                ->with('zone')
                ->get()
                ->map(function ($table) {
                    return [
                        'uuid' => $table->uuid,
                        'table_number' => $table->table_number,
                        'zone_id' => $table->zone_id,
                        'zone_uuid' => $table->zone ? $table->zone->uuid : null,
                        'capacity' => $table->capacity,
                        'status' => $table->status,
                        'has_active_order' => (bool) $table->current_order_id,
                        'current_order_id' => $table->current_order_id,
                        'position_x' => $table->position_x,
                        'position_y' => $table->position_y,
                        'rotation' => $table->rotation,
                        'shape' => $table->shape,
                        'width' => $table->width,
                        'height' => $table->height,
                        'furniture_type' => $table->furniture_type ?? 'table_square_4',
                    ];
                });

            // 5. Decoraciones (COMPATIBILIDAD)
            $decorations = FloorPlanObject::where('company_id', $companyId)
                ->where('branch_id', $branchId)
                ->where('object_type', '!=', 'table')
                ->get()
                ->map(function ($obj) {
                    return [
                        'uuid' => $obj->uuid,
                        'type' => $obj->type ?? $obj->object_type,
                        'position_x' => $obj->position_x,
                        'position_y' => $obj->position_y,
                        'rotation' => $obj->rotation,
                        'width' => $obj->width,
                        'height' => $obj->height,
                        'properties' => $obj->properties,
                    ];
                });

            // 6. Configuración del Canvas
            $settings = DB::table('floor_plan_settings')->where('branch_id', $branchId)->first();

            return [
                'plan' => [
                    'width' => $settings->canvas_width ?? 2400,
                    'height' => $settings->canvas_height ?? 1800,
                    'grid_size' => $settings->grid_size ?? 40,
                    'unit' => 'px',
                    'version' => 1,
                ],
                'zones' => $zones,
                'objects' => $objects,
                'available_tables' => $availableTables,
                'tables' => $tables,         // ✅ NECESARIO PARA LOS TESTS
                'decorations' => $decorations, // ✅ NECESARIO PARA LOS TESTS
            ];
        } finally {
            // Limpieza opcional
        }
    }

    public function saveFloorPlan(int $branchId, array $payload): array
    {
        $companyId = DB::table('branches')->where('id', $branchId)->value('company_id');
        if (!$companyId) {
            throw new \RuntimeException('Sucursal no encontrada.');
        }

        if (class_exists(TenantContext::class)) {
            app(TenantContext::class)->setCompany($companyId, $branchId);
        }

        return DB::transaction(function () use ($companyId, $branchId, $payload) {
            $tablesUpdatedCount = 0;

            if (empty($payload['objects']) && !empty($payload['tables'])) {
                foreach ($payload['tables'] as $tableData) {
                    $payload['objects'][] = [
                        'uuid' => $tableData['uuid'],
                        'object_type' => 'table',
                        'table_uuid' => $tableData['uuid'],
                        'position_x' => (int) $tableData['position_x'],
                        'position_y' => (int) $tableData['position_y'],
                        'rotation' => (int) ($tableData['rotation'] ?? 0),
                        'width' => (int) ($tableData['width'] ?? 80),
                        'height' => (int) ($tableData['height'] ?? 80),
                        'properties' => ['shape' => $tableData['shape'] ?? 'square'],
                        'type' => 'table',
                    ];
                }
            }

            $incomingObjectUuids = array_column($payload['objects'] ?? [], 'uuid');
            $incomingZoneUuids = array_column($payload['zones'] ?? [], 'uuid');

            foreach ($payload['zones'] ?? [] as $zoneData) {
                DiningZone::updateOrCreate(
                    ['uuid' => $zoneData['uuid'], 'branch_id' => $branchId],
                    [
                        'company_id' => $companyId,
                        'code' => $zoneData['code'],
                        'name_translations' => $zoneData['name_translations'] ?? [],
                        'type' => $zoneData['type'] ?? 'other',
                        'position_x' => (int) $zoneData['position_x'],
                        'position_y' => (int) $zoneData['position_y'],
                        'width' => (int) $zoneData['width'],
                        'height' => (int) $zoneData['height'],
                        'rotation' => (int) ($zoneData['rotation'] ?? 0),
                        'color' => $zoneData['properties']['color'] ?? '#cccccc',
                    ]
                );
            }
            if (!empty($incomingZoneUuids)) {
                DiningZone::where('branch_id', $branchId)->whereNotIn('uuid', $incomingZoneUuids)->delete();
            }

            foreach ($payload['objects'] ?? [] as $objData) {
                if ($objData['object_type'] === 'table' && !empty($objData['table_uuid'])) {
                    $isValidTable = DB::table('restaurant_tables')
                        ->where('uuid', $objData['table_uuid'])
                        ->where('branch_id', $branchId)
                        ->whereNull('deleted_at')
                        ->first();
                    
                    if (!$isValidTable) {
                        throw new \InvalidArgumentException("La mesa {$objData['table_uuid']} no pertenece a esta sucursal o no existe.");
                    }

                    DB::table('restaurant_tables')
                        ->where('uuid', $objData['table_uuid'])
                        ->update([
                            'position_x' => (int) $objData['position_x'],
                            'position_y' => (int) $objData['position_y'],
                            'rotation' => (int) ($objData['rotation'] ?? 0),
                            'shape' => $objData['properties']['shape'] ?? ($objData['shape'] ?? 'square'),
                            'width' => (int) $objData['width'],
                            'height' => (int) $objData['height'],
                            'updated_at' => now(),
                        ]);
                }

                FloorPlanObject::updateOrCreate(
                    ['uuid' => $objData['uuid'], 'branch_id' => $branchId],
                    [
                        'company_id' => $companyId,
                        'zone_uuid' => $objData['zone_uuid'] ?? null,
                        'table_uuid' => $objData['table_uuid'] ?? null,
                        'object_type' => $objData['object_type'] ?? 'decoration',
                        'type' => $objData['object_type'] ?? $objData['type'] ?? 'decoration',
                        'position_x' => (int) $objData['position_x'],
                        'position_y' => (int) $objData['position_y'],
                        'width' => (int) $objData['width'],
                        'height' => (int) $objData['height'],
                        'rotation' => (int) ($objData['rotation'] ?? 0),
                        'z_index' => (int) ($objData['z_index'] ?? 0),
                        'properties' => $objData['properties'] ?? null,
                    ]
                );
                
                if ($objData['object_type'] === 'table') {
                    $tablesUpdatedCount++;
                }
            }
            
            if (!empty($incomingObjectUuids)) {
                FloorPlanObject::where('branch_id', $branchId)->whereNotIn('uuid', $incomingObjectUuids)->delete();
            }

            if (!empty($payload['plan'])) {
                DB::table('floor_plan_settings')->updateOrInsert(
                    ['branch_id' => $branchId],
                    [
                        'company_id' => $companyId,
                        'canvas_width' => (int) $payload['plan']['width'],
                        'canvas_height' => (int) $payload['plan']['height'],
                        'grid_size' => (int) $payload['plan']['grid_size'],
                        'updated_at' => now(),
                    ]
                );
            }

            return ['tables_updated' => $tablesUpdatedCount];
        });
    }

    public function createZone(int $companyId, int $branchId, array $data): DiningZone
    {
        if (class_exists(TenantContext::class)) {
            app(TenantContext::class)->setCompany($companyId, $branchId);
        }
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

        $hasTables = FloorPlanObject::where('zone_uuid', $zone->uuid)->where('object_type', 'table')->exists();
        $hasTablesOld = RestaurantTable::where('zone_id', $zone->id)->whereNull('deleted_at')->exists();

        if ($hasTables || $hasTablesOld) {
            throw new \RuntimeException("No se puede eliminar la zona '{$zone->code}' porque tiene mesas asignadas.");
        }

        $zone->delete();
    }
}
