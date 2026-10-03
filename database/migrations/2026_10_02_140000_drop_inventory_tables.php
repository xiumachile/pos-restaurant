<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ADR-022: Drop de las tablas del antiguo módulo Inventory.
     * 
     * El módulo Inventory fue consolidado en Recipes. Las tablas ya no
     * tienen datos (verificado: 0 filas en producción) y no hay FKs
     * externas que apunten a ellas.
     * 
     * Tablas eliminadas:
     * - stock_movements (historial de movimientos → ahora raw_ingredient_movements)
     * - inventory_stocks (stock por sucursal → ahora raw_ingredients.current_stock_base)
     * - inventory_items (items → ahora raw_ingredients)
     * 
     * El rollback recrea las tablas con el schema original (ver migración
     * 2026_08_12_025957_create_inventory_tables.php).
     */
    public function up(): void
    {
        // Orden de drop: hijos primero (FKs)
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('inventory_stocks');
        Schema::dropIfExists('inventory_items');
    }

    public function down(): void
    {
        // Recrear tablas con schema original (para rollback de emergencia)
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('sku', 100)->nullable();
            $table->jsonb('name_translations');
            $table->string('unit', 20)->default('unit');
            $table->decimal('cost_price', 12, 2)->default(0);
            $table->decimal('min_stock', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'is_active']);
            $table->unique(['company_id', 'sku']);
        });

        Schema::create('inventory_stocks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->decimal('quantity', 12, 2)->default(0);
            $table->timestamp('last_movement_at')->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'inventory_item_id']);
            $table->index(['company_id', 'branch_id']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->string('type', 30);
            $table->decimal('quantity', 12, 2);
            $table->decimal('balance_after', 12, 2);
            $table->string('reference_type', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'branch_id', 'inventory_item_id']);
            $table->index(['reference_type', 'reference_id']);
            $table->index(['company_id', 'created_at']);
        });
    }
};
