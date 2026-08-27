<?php

namespace Modules\Sync\Domain\Entities;

/**
 * Representa una migración de schema aplicada a la BD local SQLite.
 *
 * Esta entidad NO es un modelo Eloquent porque vive en SQLite (conexión sqlite_local),
 * no en la BD principal del servidor. Se maneja con queries directos.
 *
 * F3.2: Sistema de versionado de schema para sincronización.
 */
class SchemaVersion
{
    public function __construct(
        public readonly string $migrationId,
        public readonly string $appliedAt,
        public readonly ?string $checksum = null,
        public readonly ?string $description = null,
    ) {
    }

    /**
     * Crea la tabla schema_versions si no existe.
     */
    public static function ensureTable(\Illuminate\Database\Connection $connection): void
    {
        $schema = $connection->getSchemaBuilder();

        if (!$schema->hasTable('schema_versions')) {
            $schema->create('schema_versions', function ($table) {
                $table->string('migration_id', 100)->primary();
                $table->timestamp('applied_at');
                $table->string('checksum', 64)->nullable();
                $table->text('description')->nullable();
            });
        }
    }

    /**
     * Obtiene todas las migraciones aplicadas.
     */
    public static function all(\Illuminate\Database\Connection $connection): array
    {
        self::ensureTable($connection);

        return $connection->table('schema_versions')
            ->orderBy('applied_at', 'asc')
            ->get()
            ->map(fn($row) => new self(
                migrationId: $row->migration_id,
                appliedAt: $row->applied_at,
                checksum: $row->checksum,
                description: $row->description,
            ))
            ->all();
    }

    /**
     * Registra una migración como aplicada.
     */
    public static function record(
        \Illuminate\Database\Connection $connection,
        string $migrationId,
        ?string $checksum = null,
        ?string $description = null,
    ): void {
        self::ensureTable($connection);

        $connection->table('schema_versions')->insert([
            'migration_id' => $migrationId,
            'applied_at' => now()->toDateTimeString(),
            'checksum' => $checksum,
            'description' => $description,
        ]);
    }

    /**
     * Verifica si una migración ya fue aplicada.
     */
    public static function isApplied(\Illuminate\Database\Connection $connection, string $migrationId): bool
    {
        self::ensureTable($connection);

        return $connection->table('schema_versions')
            ->where('migration_id', $migrationId)
            ->exists();
    }
}
