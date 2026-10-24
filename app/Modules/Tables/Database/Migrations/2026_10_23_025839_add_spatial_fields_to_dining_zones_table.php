<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dining_zones', function (Blueprint $table) {
            // Posición y dimensiones de la zona (independientes de las mesas)
            $table->integer('position_x')->default(0)->after('floor_level');
            $table->integer('position_y')->default(0)->after('position_x');
            $table->integer('width')->default(400)->after('position_y');
            $table->integer('height')->default(300)->after('width');
        });
    }

    public function down(): void
    {
        Schema::table('dining_zones', function (Blueprint $table) {
            $table->dropColumn(['position_x', 'position_y', 'width', 'height']);
        });
    }
};
