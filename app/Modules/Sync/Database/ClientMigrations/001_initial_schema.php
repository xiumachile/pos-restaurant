<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración 001: Schema inicial para BD local SQLite.
 * 
 * Refactoriza el schema hardcodeado en LocalDatabaseManager::createLocalSchema()
 * al sistema de migraciones versionadas.
 * 
 * Tablas creadas:
 * - local_orders: Órdenes locales (copia simplificada del servidor)
 * - local_order_items: Items de órdenes locales
 * - local_sync_metadata: Metadatos de sincronización
 * - schema_versions: Registro de migraciones aplicadas (NUEVO en F3.2)
 */
return new class extends Migration
{
    protected $connection = 'sqlite_local';

    public function up(): void
    {
        // =========================================
        // Tabla local de órdenes (copia simplificada)
        // =========================================
        if (!Schema::connection($this->connection)->hasTable('local_orders')) {
            Schema::connection($this->connection)->create('local_orders', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('server_id')->nullable(); // ID en el servidor
                $table->unsignedBigInteger('branch_id');
                $table->unsignedBigInteger('waiter_id')->nullable();
                $table->string('order_number', 50);
                $table->string('type', 20);
                $table->string('status', 20);
                $table->decimal('subtotal', 14, 2)->default(0);
                $table->decimal('tax_amount', 14, 2)->default(0);
                $table->decimal('discount_amount', 14, 2)->default(0);
                $table->decimal('total', 14, 2)->default(0);
                $table->text('notes')->nullable();
                $table->unsignedInteger('version')->default(1);
                $table->string('sync_status', 20)->default('pending');
                $table->timestamp('last_synced_at')->nullable();
                $table->timestamps();

                $table->index(['branch_id', 'sync_status']);
                $table->index('sync_status');
            });
        }

        // =========================================
        // Tabla local de items de orden
        // =========================================
        if (!Schema::connection($this->connection)->hasTable('local_order_items')) {
            Schema::connection($this->connection)->create('local_order_items', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('server_id')->nullable();
                $table->unsignedBigInteger('local_order_id');
                $table->string('name_snapshot', 255);
                $table->decimal('unit_price_snapshot', 14, 2);
                $table->unsignedInteger('quantity')->default(1);
                $table->decimal('subtotal', 14, 2);
                $table->decimal('tax_amount', 14, 2)->default(0);
                $table->text('notes')->nullable();
                $table->string('sync_status', 20)->default('pending');
                $table->timestamps();

                $table->index('local_order_id');
                $table->index('sync_status');
            });
        }

        // =========================================
        // Tabla de metadatos de sync
        // =========================================
        if (!Schema::connection($this->connection)->hasTable('local_sync_metadata')) {
            Schema::connection($this->connection)->create('local_sync_metadata', function (Blueprint $table) {
                $table->id();
                $table->string('key', 100)->unique();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }

        // =========================================
        // Tabla de versiones de schema (NUEVO en F3.2)
        // =========================================
        if (!Schema::connection($this->connection)->hasTable('schema_versions')) {
            Schema::connection($this->connection)->create('schema_versions', function (Blueprint $table) {
                $table->string('migration_id', 100)->primary();
                $table->timestamp('applied_at');
                $table->string('checksum', 64)->nullable();
                $table->text('description')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('schema_versions');
        Schema::connection($this->connection)->dropIfExists('local_sync_metadata');
        Schema::connection($this->connection)->dropIfExists('local_order_items');
        Schema::connection($this->connection)->dropIfExists('local_orders');
    }
};
