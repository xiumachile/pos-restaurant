<?php

/**
 * Migración 001: Schema inicial para BD local SQLite.
 *
 * Refactoriza el schema hardcodeado en LocalDatabaseManager::createLocalSchema()
 * al sistema de migraciones versionadas.
 *
 * Retorna un callable que recibe la conexión SQLite y crea las tablas.
 */

use Illuminate\Database\Connection;

return function (Connection $connection): void {
    $schema = $connection->getSchemaBuilder();

    // =========================================
    // Tabla de órdenes locales
    // =========================================
    if (!$schema->hasTable('local_orders')) {
        $schema->create('local_orders', function ($table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('server_id')->nullable();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('waiter_id')->nullable();
            $table->string('order_number', 50);
            $table->string('status', 30)->default('confirmed');
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('tax_total', 10, 2)->default(0);
            $table->decimal('grand_total', 10, 2)->default(0);
            $table->integer('guest_count')->default(1);
            $table->string('sync_status', 20)->default('pending');
            $table->string('idempotency_key', 100)->nullable();
            $table->timestamps();

            $table->index('branch_id');
            $table->index('sync_status');
        });
    }

    // =========================================
    // Tabla de items de orden locales
    // =========================================
    if (!$schema->hasTable('local_order_items')) {
        $schema->create('local_order_items', function ($table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('order_uuid');
            $table->unsignedBigInteger('product_id');
            $table->string('product_name', 200);
            $table->integer('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('subtotal', 10, 2);
            $table->string('kitchen_status', 20)->default('pending');
            $table->timestamps();

            $table->index('order_uuid');
        });
    }

    // =========================================
    // Tabla de mesas locales
    // =========================================
    if (!$schema->hasTable('local_tables')) {
        $schema->create('local_tables', function ($table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 50);
            $table->string('status', 20)->default('available');
            $table->integer('capacity')->default(4);
            $table->uuid('current_order_uuid')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    // =========================================
    // Tabla de métodos de pago locales
    // =========================================
    if (!$schema->hasTable('local_payment_methods')) {
        $schema->create('local_payment_methods', function ($table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 100);
            $table->string('type', 30);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    // =========================================
    // Cola de sincronización
    // =========================================
    if (!$schema->hasTable('sync_queue')) {
        $schema->create('sync_queue', function ($table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('entity_type', 50);
            $table->uuid('entity_uuid');
            $table->string('action', 20);
            $table->text('payload')->nullable();
            $table->string('status', 20)->default('pending');
            $table->integer('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'next_retry_at']);
            $table->index(['entity_type', 'entity_uuid']);
        });
    }

    // =========================================
    // Estado de sincronización
    // =========================================
    if (!$schema->hasTable('sync_state')) {
        $schema->create('sync_state', function ($table) {
            $table->string('key', 100)->primary();
            $table->text('value')->nullable();
            $table->timestamp('updated_at');
        });
    }
};
