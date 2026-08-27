<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The database connection that should be used by the migration.
     *
     * @var string
     */
    protected $connection = 'sqlite_local';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // =========================================
        // Tabla de órdenes locales
        // =========================================
        if (!Schema::connection($this->connection)->hasTable('local_orders')) {
            Schema::connection($this->connection)->create('local_orders', function (Blueprint $table) {
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
        if (!Schema::connection($this->connection)->hasTable('local_order_items')) {
            Schema::connection($this->connection)->create('local_order_items', function (Blueprint $table) {
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
        if (!Schema::connection($this->connection)->hasTable('local_tables')) {
            Schema::connection($this->connection)->create('local_tables', function (Blueprint $table) {
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
        if (!Schema::connection($this->connection)->hasTable('local_payment_methods')) {
            Schema::connection($this->connection)->create('local_payment_methods', function (Blueprint $table) {
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
        if (!Schema::connection($this->connection)->hasTable('sync_queue')) {
            Schema::connection($this->connection)->create('sync_queue', function (Blueprint $table) {
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
        if (!Schema::connection($this->connection)->hasTable('sync_state')) {
            Schema::connection($this->connection)->create('sync_state', function (Blueprint $table) {
                $table->string('key', 100)->primary();
                $table->text('value')->nullable();
                $table->timestamp('updated_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('sync_state');
        Schema::connection($this->connection)->dropIfExists('sync_queue');
        Schema::connection($this->connection)->dropIfExists('local_payment_methods');
        Schema::connection($this->connection)->dropIfExists('local_tables');
        Schema::connection($this->connection)->dropIfExists('local_order_items');
        Schema::connection($this->connection)->dropIfExists('local_orders');
    }
};
