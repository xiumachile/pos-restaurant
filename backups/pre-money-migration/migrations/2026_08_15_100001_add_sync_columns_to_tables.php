<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = ['orders', 'order_items', 'order_item_modifiers', 'payments', 'bills', 'restaurant_tables'];

        foreach ($tables as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                // Agregar columnas sync
                if (!Schema::hasColumn($tableName, 'sync_status')) {
                    $table->string('sync_status', 20)->default('pending');
                }
                if (!Schema::hasColumn($tableName, 'version')) {
                    $table->unsignedInteger('version')->default(1);
                }
                if (!Schema::hasColumn($tableName, 'last_synced_at')) {
                    $table->timestamp('last_synced_at')->nullable();
                }
                if (!Schema::hasColumn($tableName, 'offline_id')) {
                    $table->string('offline_id', 64)->nullable();
                }

                // Crear índice SOLO si la tabla tiene branch_id
                if (Schema::hasColumn($tableName, 'branch_id')) {
                    $table->index(['sync_status', 'branch_id']);
                } elseif (Schema::hasColumn($tableName, 'company_id')) {
                    // Fallback: índice por company_id
                    $table->index(['sync_status', 'company_id']);
                } else {
                    // Fallback simple: solo por sync_status
                    $table->index('sync_status');
                }
            });
        }
    }

    public function down(): void
    {
        $tables = ['orders', 'order_items', 'order_item_modifiers', 'payments', 'bills', 'restaurant_tables'];

        foreach ($tables as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                // Intentar eliminar los posibles índices
                $indexCandidates = [
                    "{$tableName}_sync_status_branch_id_index",
                    "{$tableName}_sync_status_company_id_index",
                    "{$tableName}_sync_status_index",
                ];

                foreach ($indexCandidates as $indexName) {
                    try {
                        $table->dropIndex($indexName);
                    } catch (\Exception $e) {
                        // Ignorar si el índice no existe
                    }
                }

                // Eliminar columnas
                $columnsToDrop = ['sync_status', 'version', 'last_synced_at', 'offline_id'];
                $existingColumns = Schema::getColumnListing($tableName);
                $columnsToDrop = array_filter($columnsToDrop, fn($col) => in_array($col, $existingColumns));
                
                if (!empty($columnsToDrop)) {
                    $table->dropColumn($columnsToDrop);
                }
            });
        }
    }
};
