<?php

namespace Modules\Sync\Domain\Services;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Sync\Domain\Entities\SchemaVersion;

/**
 * Gestiona el versionado del schema de la BD local SQLite.
 *
 * Responsabilidades:
 * - Detectar migraciones pendientes
 * - Aplicar migraciones en orden
 * - Verificar compatibilidad con el servidor
 * - Proveer información de versión actual
 *
 * F3.2: Sistema de versionado de schema.
 */
class SchemaVersionManager
{
    public const SCHEMA_VERSION = '1.0.0';

    protected string $connectionName;
    protected string $migrationsPath;

    public function __construct(
        string $connectionName = 'sqlite_local',
        ?string $migrationsPath = null
    ) {
        $this->connectionName = $connectionName;
        $this->migrationsPath = $migrationsPath
            ?? base_path('app/Modules/Sync/Database/ClientMigrations');
    }

    /**
     * Obtiene la conexión SQLite local.
     */
    protected function getConnection(): Connection
    {
        return DB::connection($this->connectionName);
    }

    /**
     * Retorna la versión actual del schema.
     */
    public function getCurrentVersion(): string
    {
        try {
            $connection = $this->getConnection();
            $applied = SchemaVersion::all($connection);

            if (empty($applied)) {
                return '0.0.0';
            }

            $lastMigration = end($applied);
            return $this->extractVersionFromId($lastMigration->migrationId);
        } catch (\Throwable $e) {
            Log::warning('SchemaVersionManager: could not get version', [
                'error' => $e->getMessage(),
            ]);
            return '0.0.0';
        }
    }

    /**
     * Lista todas las migraciones disponibles en el directorio.
     *
     * @return array<string, string> [id => path]
     */
    public function getAvailableMigrations(): array
    {
        if (!is_dir($this->migrationsPath)) {
            return [];
        }

        $files = glob($this->migrationsPath . '/*.php');
        $migrations = [];

        foreach ($files as $file) {
            $id = pathinfo($file, PATHINFO_FILENAME);
            $migrations[$id] = $file;
        }

        ksort($migrations);
        return $migrations;
    }

    /**
     * Retorna las migraciones que aún no han sido aplicadas.
     *
     * @return array<string, string> [id => path]
     */
    public function getPendingMigrations(): array
    {
        $available = $this->getAvailableMigrations();
        $connection = $this->getConnection();
        $pending = [];

        foreach ($available as $id => $path) {
            if (!SchemaVersion::isApplied($connection, $id)) {
                $pending[$id] = $path;
            }
        }

        return $pending;
    }

    /**
     * Aplica todas las migraciones pendientes.
     *
     * Las migraciones son callables que reciben la conexión como parámetro.
     * Esto evita el problema de $connection hardcoded en las clases Migration
     * de Laravel, que ejecutaban la migración en el path de config/database.php
     * en lugar del path dinámico configurado por los tests.
     *
     * @return array Resultado con conteos y errores
     */
    public function applyPendingMigrations(): array
    {
        $pending = $this->getPendingMigrations();
        $connection = $this->getConnection();

        $result = [
            'applied' => 0,
            'skipped' => 0,
            'errors' => [],
            'migrations_applied' => [],
        ];

        if (empty($pending)) {
            return $result;
        }

        foreach ($pending as $id => $path) {
            try {
                $migration = require $path;

                if (!is_callable($migration)) {
                    throw new \RuntimeException(
                        "Migration {$id} must return a callable that accepts a Connection"
                    );
                }

                $connection->beginTransaction();

                try {
                    // Ejecutar migración pasando la conexión EXPLÍCITAMENTE
                    // Esto asegura que se ejecute en el path dinámico,
                    // no en el path de config/database.php
                    $migration($connection);

                    $checksum = hash_file('sha256', $path);
                    SchemaVersion::record(
                        $connection,
                        $id,
                        $checksum,
                        "Migration {$id}"
                    );

                    $connection->commit();

                    $result['applied']++;
                    $result['migrations_applied'][] = $id;

                    Log::info('SchemaVersionManager: migration applied', [
                        'migration_id' => $id,
                        'connection' => $this->connectionName,
                    ]);
                } catch (\Throwable $e) {
                    $connection->rollBack();
                    throw $e;
                }
            } catch (\Throwable $e) {
                $result['errors'][] = [
                    'migration_id' => $id,
                    'error' => $e->getMessage(),
                ];

                Log::error('SchemaVersionManager: migration failed', [
                    'migration_id' => $id,
                    'error' => $e->getMessage(),
                    'connection' => $this->connectionName,
                ]);

                // Detener en el primer error para evitar estados inconsistentes
                break;
            }
        }

        return $result;
    }

    /**
     * Verifica si la versión local es compatible con la del servidor.
     */
    public function isCompatibleWith(string $serverVersion): bool
    {
        $localVersion = $this->getCurrentVersion();

        if ($localVersion === '0.0.0') {
            return true;
        }

        $localParts = explode('.', $localVersion);
        $serverParts = explode('.', $serverVersion);

        return ($localParts[0] ?? '0') === ($serverParts[0] ?? '0')
            && ($localParts[1] ?? '0') === ($serverParts[1] ?? '0');
    }

    /**
     * Extrae la versión de un ID de migración.
     * Ejemplo: "001_initial_schema" → "1.0.0"
     */
    protected function extractVersionFromId(string $migrationId): string
    {
        if (preg_match('/^(\d+)/', $migrationId, $matches)) {
            $num = (int) $matches[1];
            return "{$num}.0.0";
        }
        return '0.0.0';
    }
}
