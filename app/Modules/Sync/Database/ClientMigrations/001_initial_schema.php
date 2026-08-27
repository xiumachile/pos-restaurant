<?php

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;

/**
 * Migración 001: Schema inicial para BD local SQLite.
 * 
 * Retorna un callable que recibe la conexión y crea las tablas.
 * NO usa la clase Migration de Laravel para evitar el problema de
 * $connection hardcoded que causaba que la migración se ejecutara
 * en el path de config/database.php en lugar del path dinámico.
 * 
 * Tablas creadas:
 * - local_orders: Órdenes locales (copia simplificada del servidor)
 * - local_order_items: Items de órdenes locales
 * - local_sync_metadata: Metadatos de sincronización
 * - schema_versions: Registro de migraciones aplicadas
 */
return function (Connection $connection): void {
    $schema = $connection->getSchemaBuilder();

    // =========================================
    // Tabla local de órdenes (copia simplificada)
    // =========================================
    if (!$schema->hasTable('local_orders')) {
        $schema->create('local_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('server_id')->nullable();
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
    if (!$schema->hasTable('local_order_items')) {
        $schema->create('local_order_items', function (Blueprint $table) {
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
    if (!$schema->hasTable('local_sync_metadata')) {
        $schema->create('local_sync_metadata', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    // =========================================
    // Tabla de versiones de schema
    // =========================================
    if (!$schema->hasTable('schema_versions')) {
        $schema->create('schema_versions', function (Blueprint $table) {
            $table->string('migration_id', 100)->primary();
            $table->timestamp('applied_at');
            $table->string('checksum', 64)->nullable();
            $table->text('description')->nullable();
        });
    }
};
