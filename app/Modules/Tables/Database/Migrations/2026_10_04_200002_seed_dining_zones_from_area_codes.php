<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Fase 4.1 - Seed evolutivo: crea dining_zones a partir de los area_code existentes
 * y asigna zone_id a cada mesa, preservando area_code como campo legacy.
 */
return new class extends Migration
{
    // Colores distintivos por zona (paleta consistente con UI)
    private const ZONE_COLORS = [
        'MAIN'    => '#f97316', // orange
        'TERRAZA' => '#10b981', // emerald
        'BAR'     => '#8b5cf6', // violet
        'VIP'     => '#ec4899', // pink
    ];

    private const DEFAULT_COLOR = '#64748b'; // slate para zonas desconocidas

    public function up(): void
    {
        // Obtener todas las combinaciones únicas (branch_id, area_code) con sus traducciones
        $areaGroups = DB::table('restaurant_tables')
            ->select('branch_id', 'company_id', 'area_code', 'area_name_translations')
            ->whereNull('deleted_at')
            ->distinct()
            ->get();

        $zoneMap = []; // branch_id:area_code → zone_id

        foreach ($areaGroups as $group) {
            // Crear zona (si no existe ya)
            $existing = DB::table('dining_zones')
                ->where('branch_id', $group->branch_id)
                ->where('code', $group->area_code)
                ->first();

            if ($existing) {
                $zoneId = $existing->id;
            } else {
                $zoneId = DB::table('dining_zones')->insertGetId([
                    'uuid' => (string) Str::uuid(),
                    'company_id' => $group->company_id,
                    'branch_id' => $group->branch_id,
                    'code' => $group->area_code,
                    'name_translations' => $group->area_name_translations,
                    'color' => self::ZONE_COLORS[$group->area_code] ?? self::DEFAULT_COLOR,
                    'floor_level' => 0,
                    'sort_order' => count($zoneMap),
                    'is_active' => true,
                    'sync_status' => 'pending',
                    'version' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $key = "{$group->branch_id}:{$group->area_code}";
            $zoneMap[$key] = $zoneId;

            // Asignar zone_id a todas las mesas de esa área/sucursal
            DB::table('restaurant_tables')
                ->where('branch_id', $group->branch_id)
                ->where('area_code', $group->area_code)
                ->whereNull('deleted_at')
                ->update([
                    'zone_id' => $zoneId,
                    'updated_at' => now(),
                ]);
        }

        // Distribuir mesas en posiciones iniciales (grid automático por zona)
        // Evita que todas aparezcan superpuestas en (0,0)
        $this->distributeInitialPositions();
    }

    /**
     * Distribuye las mesas en un grid inicial dentro de cada zona.
     * Grid de 5 columnas, espaciado de 140px, offset por zona.
     */
    private function distributeInitialPositions(): void
    {
        $zones = DB::table('dining_zones')
            ->whereNull('deleted_at')
            ->get();

        foreach ($zones as $zone) {
            $tables = DB::table('restaurant_tables')
                ->where('zone_id', $zone->id)
                ->whereNull('deleted_at')
                ->orderBy('table_number')
                ->get();

            // Offset por zona (para que no se superpongan visualmente)
            $zoneOffsetX = ($zone->sort_order % 3) * 700;
            $zoneOffsetY = (int) ($zone->sort_order / 3) * 600;

            foreach ($tables as $index => $table) {
                $col = $index % 5;
                $row = (int) ($index / 5);

                DB::table('restaurant_tables')
                    ->where('id', $table->id)
                    ->update([
                        'position_x' => $zoneOffsetX + ($col * 140) + 50,
                        'position_y' => $zoneOffsetY + ($row * 140) + 50,
                        'updated_at' => now(),
                    ]);
            }
        }
    }

    public function down(): void
    {
        // Resetear zone_id a NULL
        DB::table('restaurant_tables')->update(['zone_id' => null]);
        // Resetear posiciones
        DB::table('restaurant_tables')->update([
            'position_x' => 0,
            'position_y' => 0,
        ]);
        // No eliminamos zonas en down (lo hace la migración 1)
    }
};
