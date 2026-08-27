<?php

namespace Modules\Sync\Domain\Services;

use Illuminate\Database\Connection;
use Illuminate\Database\Migrations\Migration;
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

    protected string $connectionName = 'sqlite_local';
    protected string $migrationsPath;

    public function __construct(?string $migrationsPath = null)
    {
        $this->migrationsPath = $migrationsPath
            ?? base_path('app/Modules/Sync/Database/Migrations');
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

            // La versión es el ID de la última migración aplicada
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

                if (!($migration instanceof Migration)) {
                    throw new \RuntimeException(
                        "Migration {$id} must return an instance of Migration"
                    );
                }

                $connection->beginTransaction();

                try {
                    $migration->up();

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
                ]);

                // Detener en el primer error para evitar estados inconsistentes
                break;
            }
        }

        return $result;
    }

    /**
     * Verifica si la versión local es compatible con la del servidor.
     *
     * Política actual: compatible si versiones son iguales (major.minor).
     * En el futuro podemos soportar n-1 para backward compatibility.
     */
    public function isCompatibleWith(string $serverVersion): bool
    {
        $localVersion = $this->getCurrentVersion();

        if ($localVersion === '0.0.0') {
            // Schema no inicializado, será compatible después de aplicar migraciones
            return true;
        }

        $localParts = explode('.', $localVersion);
        $serverParts = explode('.', $serverVersion);

        // Compatible si major.minor coinciden
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
