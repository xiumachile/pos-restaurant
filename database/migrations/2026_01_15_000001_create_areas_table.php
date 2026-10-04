<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->string('code', 50); // MAIN, BAR, TERRAZA, VIP, etc.
            $table->json('name_translations'); // {es: "Salón Principal", zh: "主厅"}
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['branch_id', 'code']);
            $table->index(['company_id', 'branch_id']);
        });

        // Migrar áreas existentes de restaurant_tables
        DB::statement('
            INSERT INTO areas (uuid, company_id, branch_id, code, name_translations, sort_order, created_at, updated_at)
            SELECT DISTINCT
                gen_random_uuid(),
                company_id,
                branch_id,
                area_code,
                area_name_translations,
                0,
                NOW(),
                NOW()
            FROM restaurant_tables
            WHERE area_code IS NOT NULL
              AND deleted_at IS NULL
            ON CONFLICT (branch_id, code) DO NOTHING
        ');

        // Agregar foreign key a restaurant_tables
        Schema::table('restaurant_tables', function (Blueprint $table) {
            $table->foreignId('area_id')->nullable()->after('branch_id')->constrained('areas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_tables', function (Blueprint $table) {
            $table->dropForeign(['area_id']);
            $table->dropColumn('area_id');
        });

        Schema::dropIfExists('areas');
    }
};
