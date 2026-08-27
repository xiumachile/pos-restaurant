<?php

namespace Modules\Sync\Domain\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Gestiona la base de datos local SQLite para modo offline.
 *
 * Responsabilidades:
 * - Inicializar la BD local con el esquema necesario
 * - Gestionar la conexión dinámica entre Postgres y SQLite
 * - Aplicar migraciones de schema versionadas (F3.2)
 * - Proveer métodos para verificar el estado de la BD local
 *
 * F3.2: Refactorizado para usar SchemaVersionManager en lugar de
 *       crear el schema manualmente. Esto permite aplicar cambios
 *       incrementales y detectar incompatibilidades.
 */
class LocalDatabaseManager
{
    protected string $connectionName = 'sqlite_local';
    protected ?string $databasePath;
    protected SchemaVersionManager $schemaManager;

    public function __construct(
        ?string $databasePath = null,
        ?SchemaVersionManager $schemaManager = null
    ) {
        $this->databasePath = $databasePath ?? database_path('local.sqlite');
        // Pasar connectionName al SchemaVersionManager para que use la misma conexión
        $this->schemaManager = $schemaManager ?? new SchemaVersionManager($this->connectionName);
    }

    /**
     * Inicializa la BD local y aplica migraciones pendientes.
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

            // Limpiar conexión cacheada
            DB::purge($this->connectionName);

            // Aplicar migraciones pendientes (F3.2)
            $migrationResult = $this->schemaManager->applyPendingMigrations();

            Log::info('LocalDatabaseManager: migrations applied', [
                'applied' => $migrationResult['applied'],
                'migrations' => $migrationResult['migrations_applied'],
                'errors' => count($migrationResult['errors']),
            ]);

            if (!empty($migrationResult['errors'])) {
                Log::error('LocalDatabaseManager: some migrations failed', [
                    'errors' => $migrationResult['errors'],
                ]);
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('LocalDatabaseManager: Initialization failed', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Verifica si la BD local está lista para usarse.
     */
    public function isAvailable(): bool
    {
        try {
            if (!file_exists($this->databasePath)) {
                return false;
            }

            config([
                "database.connections.{$this->connectionName}.database" => $this->databasePath,
            ]);

            DB::purge($this->connectionName);

            $connection = DB::connection($this->connectionName);
            $schema = $connection->getSchemaBuilder();

            // Verificar que existan las tablas críticas
            return $schema->hasTable('schema_versions')
                && $schema->hasTable('local_orders')
                && $schema->hasTable('local_sync_metadata');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Retorna la versión actual del schema local.
     */
    public function getSchemaVersion(): string
    {
        return $this->schemaManager->getCurrentVersion();
    }

    /**
     * Retorna el manager de schema (para casos avanzados).
     */
    public function getSchemaManager(): SchemaVersionManager
    {
        return $this->schemaManager;
    }

    /**
     * Verifica compatibilidad con la versión del servidor.
     */
    public function isCompatibleWithServer(string $serverVersion): bool
    {
        return $this->schemaManager->isCompatibleWith($serverVersion);
    }

    /**
     * Retorna el tamaño del archivo SQLite en bytes.
     *
     * Método necesario para SyncController::health() y tests de LocalDatabaseTest.
     */
    public function getDatabaseSize(): int
    {
        if (!file_exists($this->databasePath)) {
            return 0;
        }

        return filesize($this->databasePath);
    }

    /**
     * Limpia completamente la BD local (elimina el archivo SQLite y la reinicializa).
     *
     * Método necesario para tests de LocalDatabaseTest y SyncFullIntegrationTest.
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
     * Retorna la ruta del archivo SQLite.
     *
     * Método necesario para tests de LocalDatabaseTest.
     */
    public function getDatabasePath(): string
    {
        return $this->databasePath;
    }
}
