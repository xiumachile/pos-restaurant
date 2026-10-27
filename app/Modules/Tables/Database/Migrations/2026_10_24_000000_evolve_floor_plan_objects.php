<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('floor_plan_objects', function (Blueprint $table) {
            $table->uuid('zone_uuid')->nullable()->after('branch_id');
            $table->uuid('table_uuid')->nullable()->after('zone_uuid');
            $table->string('object_type')->default('decoration')->after('uuid');
            $table->integer('z_index')->default(0)->after('rotation'); // ✅ AGREGADO
            
            $table->index('zone_uuid');
            $table->unique(['branch_id', 'table_uuid'], 'unique_table_per_branch'); 
        });

        // Migración de datos: Vincular mesas operativas existentes a objetos gráficos
        $tables = DB::table('restaurant_tables')->whereNull('deleted_at')->get();
        
        foreach ($tables as $table) {
            if ($table->position_x !== null || $table->position_y !== null) {
                DB::table('floor_plan_objects')->updateOrInsert(
                    [
                        'branch_id' => $table->branch_id,
                        'table_uuid' => $table->uuid,
                    ],
                    [
                        'company_id' => $table->company_id,
                        'uuid' => (string) Str::uuid(),
                        'object_type' => 'table',
                        'type' => $table->shape ?? 'table_square_4',
                        'zone_uuid' => $table->zone_id ? DB::table('dining_zones')->where('id', $table->zone_id)->value('uuid') : null,
                        'position_x' => (int) $table->position_x,
                        'position_y' => (int) $table->position_y,
                        'rotation' => (int) ($table->rotation ?? 0),
                        'width' => $table->width,
                        'height' => $table->height,
                        'z_index' => 0,
                        'properties' => json_encode(['shape' => $table->shape, 'capacity' => $table->capacity]),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }

    public function down(): void
    {
        Schema::table('floor_plan_objects', function (Blueprint $table) {
            $table->dropUnique('unique_table_per_branch');
            $table->dropColumn(['zone_uuid', 'table_uuid', 'object_type', 'z_index']);
        });
    }
};
