<?php

namespace Modules\Tables\Domain\Services;

use Illuminate\Support\Facades\DB;
use Modules\Tables\Domain\Entities\DiningZone;
use Modules\Tables\Domain\Entities\FloorPlanObject;
use Modules\Tables\Domain\Entities\RestaurantTable;

class FloorPlanService
{
    public function getFloorPlan(int $companyId, int $branchId): array
    {
        $zones = DiningZone::where('branch_id', $branchId)->ordered()->get();

        $zonesWithCount = $zones->map(function ($zone) use ($branchId) {
            $count = DB::table('floor_plan_objects')
                ->where('branch_id', $branchId)
                ->where('zone_uuid', $zone->uuid)
                ->where('object_type', 'like', 'table%')
                ->count();
            
            $zone->tables_count = $count;
            return $zone;
        });

        $objects = FloorPlanObject::where('branch_id', $branchId)
            ->with('table')
            ->get()
            ->map(function ($obj) {
                $data = $obj->toArray();
                if (str_starts_with($obj->object_type, 'table') && $obj->table) {
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

        $linkedUuids = DB::table('floor_plan_objects')
            ->where('branch_id', $branchId)
            ->whereNotNull('table_uuid')
            ->pluck('table_uuid')
            ->toArray();

        $availableTables = DB::table('restaurant_tables')
            ->where('branch_id', $branchId)
            ->whereNull('deleted_at')
            ->whereNotIn('uuid', $linkedUuids)
            ->get(['uuid', 'table_number', 'capacity', 'status', 'current_order_id'])
            ->map(fn($t) => (array) $t)
            ->values()
            ->toArray();

        $settings = DB::table('floor_plan_settings')->where('branch_id', $branchId)->first();

        return [
            'plan' => [
                'width' => $settings->canvas_width ?? 2400,
                'height' => $settings->canvas_height ?? 1800,
                'grid_size' => $settings->grid_size ?? 40,
                'unit' => 'px',
                'version' => 1,
            ],
            'zones' => $zonesWithCount,
            'objects' => $objects,
            'available_tables' => $availableTables,
            'tables' => [],
            'decorations' => [],
        ];
    }

    public function saveFloorPlan(int $branchId, array $payload): array
    {
        $companyId = DB::table('branches')->where('id', $branchId)->value('company_id');
        if (!$companyId) throw new \RuntimeException('Sucursal no encontrada.');

        return DB::transaction(function () use ($companyId, $branchId, $payload) {
            $tablesUpdatedCount = 0;
            $incomingObjectUuids = array_column($payload['objects'] ?? [], 'uuid');

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
                        'color' => $zoneData['color'] ?? '#cccccc',
                    ]
                );
            }

            $allZones = DB::table('dining_zones')->where('branch_id', $branchId)->get();

            foreach ($payload['objects'] ?? [] as $objData) {
                $isTable = str_starts_with($objData['object_type'], 'table');
                
                if ($isTable) {
                    if (empty($objData['table_uuid'])) {
                        throw new \InvalidArgumentException("Una mesa gráfica debe tener un 'table_uuid' válido.");
                    }
                    
                    $realTable = DB::table('restaurant_tables')
                        ->where('uuid', $objData['table_uuid'])
                        ->where('branch_id', $branchId)
                        ->whereNull('deleted_at')
                        ->first();
                    
                    if (!$realTable) {
                        throw new \InvalidArgumentException("La mesa con UUID {$objData['table_uuid']} no existe.");
                    }

                    $zoneUuid = $objData['zone_uuid'] ?? $this->deduceZoneByPosition($allZones, $objData['position_x'], $objData['position_y']);

                    DB::table('restaurant_tables')
                        ->where('uuid', $objData['table_uuid'])
                        ->update([
                            'position_x' => (int) $objData['position_x'],
                            'position_y' => (int) $objData['position_y'],
                            'rotation' => (int) ($objData['rotation'] ?? 0),
                            'shape' => $objData['properties']['shape'] ?? 'square',
                            'width' => (int) ($objData['width'] ?? 80),
                            'height' => (int) ($objData['height'] ?? 80),
                            'updated_at' => now(),
                        ]);
                } else {
                    $zoneUuid = $objData['zone_uuid'] ?? $this->deduceZoneByPosition($allZones, $objData['position_x'], $objData['position_y']);
                }

                // ✅ FIX: Agregar campo 'type' igual a 'object_type' para evitar NOT NULL violation
                $insertData = [
                    'company_id' => $companyId,
                    'zone_uuid' => $zoneUuid,
                    'table_uuid' => $objData['table_uuid'] ?? null,
                    'object_type' => $objData['object_type'],
                    'type' => $objData['object_type'], // ✅ Campo type obligatorio
                    'position_x' => (int) $objData['position_x'],
                    'position_y' => (int) $objData['position_y'],
                    'width' => (int) ($objData['width'] ?? 80),
                    'height' => (int) ($objData['height'] ?? 80),
                    'rotation' => (int) ($objData['rotation'] ?? 0),
                    'z_index' => (int) ($objData['z_index'] ?? 0),
                    'properties' => $objData['properties'] ?? [],
                ];

                FloorPlanObject::updateOrCreate(
                    ['uuid' => $objData['uuid'], 'branch_id' => $branchId],
                    $insertData
                );
                
                if ($isTable) $tablesUpdatedCount++;
            }
            
            if (!empty($incomingObjectUuids)) {
                FloorPlanObject::where('branch_id', $branchId)
                    ->whereNotIn('uuid', $incomingObjectUuids)
                    ->delete();
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

    private function deduceZoneByPosition($zones, int $x, int $y): ?string
    {
        foreach ($zones as $zone) {
            $zx = (int) $zone->position_x;
            $zy = (int) $zone->position_y;
            $zw = (int) $zone->width;
            $zh = (int) $zone->height;
            
            if ($x >= $zx && $x <= ($zx + $zw) && $y >= $zy && $y <= ($zy + $zh)) {
                return $zone->uuid;
            }
        }
        return null;
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
        $zone = DiningZone::where('uuid', $uuid)->where('branch_id', $branchId)->firstOrFail();
        $zone->update($data);
        return $zone->fresh();
    }

    public function deleteZone(string $uuid, int $branchId): void
    {
        $zone = DiningZone::where('uuid', $uuid)->where('branch_id', $branchId)->firstOrFail();
        $hasTables = FloorPlanObject::where('zone_uuid', $zone->uuid)->where('object_type', 'like', 'table%')->exists();
        
        if ($hasTables) {
            throw new \RuntimeException("No se puede eliminar la zona '{$zone->code}' porque tiene mesas asignadas.");
        }
        $zone->delete();
    }
}
