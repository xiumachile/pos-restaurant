<?php

namespace Modules\Sync\Domain\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

/**
 * Gestiona la base de datos local SQLite para modo offline.
 * 
 * Responsabilidades:
 * - Inicializar la BD local con el esquema necesario
 * - Gestionar la conexión dinámica entre Postgres y SQLite
 * - Proveer métodos para verificar el estado de la BD local
 * 
 * En producción, este manager se usa en el cliente desktop (Tauri)
 * para tener una copia local de los datos críticos.
 */
class LocalDatabaseManager
{
    protected string $connectionName = 'sqlite_local';
    protected ?string $databasePath;

    public function __construct(?string $databasePath = null)
    {
        $this->databasePath = $databasePath ?? database_path('local.sqlite');
    }

    /**
     * Inicializa la base de datos local con el esquema mínimo.
     */
    public function initialize(): bool
    {
        try {
            // Crear el archivo SQLite si no existe
            if (!file_exists($this->databasePath)) {
                touch($this->databasePath);
                Log::info('LocalDatabaseManager: SQLite file created', [
                    'path' => $this->databasePath,
                ]);
            }

            // Configurar la conexión dinámicamente
            config([
                "database.connections.{$this->connectionName}.database" => $this->databasePath,
            ]);

            // Crear tablas locales
            $this->createLocalSchema();

            return true;
        } catch (\Throwable $e) {
            Log::error('LocalDatabaseManager: Initialization failed', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Crea el esquema local para modo offline.
     */
    protected function createLocalSchema(): void
    {
        $connection = DB::connection($this->connectionName);
        $schema = $connection->getSchemaBuilder();

        // Tabla local de órdenes (copia simplificada)
        if (!$schema->hasTable('local_orders')) {
            $schema->create('local_orders', function ($table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('server_id')->nullable(); // ID en el servidor
                $table->unsignedBigInteger('branch_id');
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

        // Tabla local de items de orden
        if (!$schema->hasTable('local_order_items')) {
            $schema->create('local_order_items', function ($table) {
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

        // Tabla de metadatos de sync
        if (!$schema->hasTable('local_sync_metadata')) {
            $schema->create('local_sync_metadata', function ($table) {
                $table->id();
                $table->string('key', 100)->unique();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }

        Log::info('LocalDatabaseManager: Local schema created');
    }

    /**
     * Verifica si la BD local está disponible.
     */
    public function isAvailable(): bool
    {
        try {
            return file_exists($this->databasePath) && is_writable($this->databasePath);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Obtiene el tamaño de la BD local en bytes.
     */
    public function getDatabaseSize(): int
    {
        if (!file_exists($this->databasePath)) {
            return 0;
        }

        return filesize($this->databasePath);
    }

    /**
     * Limpia la BD local (elimina todos los datos).
     */
    public function clear(): bool
    {
        try {
            if (file_exists($this->databasePath)) {
                unlink($this->databasePath);
            }
            return $this->initialize();
        } catch (\Throwable $e) {
            Log::error('LocalDatabaseManager: Clear failed', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Obtiene la ruta de la BD local.
     */
    public function getDatabasePath(): string
    {
        return $this->databasePath;
    }
}
