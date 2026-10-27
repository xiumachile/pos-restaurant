<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('floor_plan_objects', function (Blueprint $table) {
            if (!Schema::hasColumn('floor_plan_objects', 'zone_uuid')) {
                $table->uuid('zone_uuid')->nullable()->after('branch_id');
            }
            if (!Schema::hasColumn('floor_plan_objects', 'table_uuid')) {
                $table->uuid('table_uuid')->nullable()->after('zone_uuid');
            }
            if (!Schema::hasColumn('floor_plan_objects', 'object_type')) {
                $table->string('object_type')->default('decoration')->after('uuid');
            }
            if (!Schema::hasColumn('floor_plan_objects', 'z_index')) {
                $table->integer('z_index')->default(0)->after('rotation');
            }
        });
    }

    public function down(): void
    {
        Schema::table('floor_plan_objects', function (Blueprint $table) {
            if (Schema::hasColumn('floor_plan_objects', 'z_index')) {
                $table->dropColumn('z_index');
            }
            if (Schema::hasColumn('floor_plan_objects', 'object_type')) {
                $table->dropColumn('object_type');
            }
            if (Schema::hasColumn('floor_plan_objects', 'table_uuid')) {
                $table->dropColumn('table_uuid');
            }
            if (Schema::hasColumn('floor_plan_objects', 'zone_uuid')) {
                $table->dropColumn('zone_uuid');
            }
        });
    }
};
