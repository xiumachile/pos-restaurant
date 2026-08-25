<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->string('timezone', 64)
                ->default('America/Santiago')
                ->after('address')
                ->comment('Timezone de la sucursal. Ej: America/Santiago, America/Bogota');
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn('timezone');
        });
    }
};
