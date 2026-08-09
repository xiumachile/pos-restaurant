<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_tables', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Multiempresa
            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->foreignId('branch_id')
                ->constrained('branches')
                ->cascadeOnDelete();

            // Área (con i18n)
            $table->string('area_code', 50);
            $table->jsonb('area_name_translations');

            // Identificación de la mesa
            $table->string('table_number', 20);
            $table->integer('capacity')->default(4);

            // Estado de la mesa (máquina de estados)
            $table->string('status', 30)->default('available')
                ->comment('available, occupied, billing, maintenance');

            // Pedido actual (sin FK por ahora, la tabla orders se crea en Fase 5)
            // Se agrega la constraint FK en la migración de la Fase 5
            $table->unsignedBigInteger('current_order_id')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Unique: table_number único dentro de sucursal
            $table->unique(['branch_id', 'table_number'], 'uk_table_number');

            // Unique: area_code único dentro de sucursal
            $table->unique(['branch_id', 'area_code'], 'uk_area_code');

            // Índices
            $table->index(['company_id', 'branch_id', 'status']);
            $table->index(['branch_id', 'status']);
            $table->index('current_order_id');
        });

        // Índice GIN para búsquedas en traducciones de área
        DB::statement('CREATE INDEX idx_restaurant_tables_area_name_translations ON restaurant_tables USING gin (area_name_translations)');

        // Constraint: status válido
        DB::statement("
            ALTER TABLE restaurant_tables
            ADD CONSTRAINT chk_table_status
            CHECK (status IN ('available', 'occupied', 'billing', 'maintenance'))
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_tables');
    }
};
