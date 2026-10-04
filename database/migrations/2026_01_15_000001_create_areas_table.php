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
            $table->string('code', 50);
            $table->json('name_translations');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['branch_id', 'code']);
        });

        // Migrar áreas existentes desde restaurant_tables
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
    }

    public function down(): void
    {
        Schema::dropIfExists('areas');
    }
};
