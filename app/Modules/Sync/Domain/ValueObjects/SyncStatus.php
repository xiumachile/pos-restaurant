<?php

namespace Modules\Sync\Domain\ValueObjects;

/**
 * Estado de sincronización de un registro.
 * 
 * - PENDING: Modificado localmente, pendiente de sincronizar al servidor
 * - SYNCED: Sincronizado exitosamente con el servidor
 * - CONFLICT: Conflicto detectado (modificado en cliente y servidor)
 * - FAILED: Falló la sincronización (reintentar)
 */
enum SyncStatus: string
{
    case PENDING = 'pending';
    case SYNCED = 'synced';
    case CONFLICT = 'conflict';
    case FAILED = 'failed';

    public function label(): string
    {
        return match($this) {
            self::PENDING => 'Pendiente',
            self::SYNCED => 'Sincronizado',
            self::CONFLICT => 'Conflicto',
            self::FAILED => 'Fallido',
        };
    }

    public function labelZh(): string
    {
        return match($this) {
            self::PENDING => '待同步',
            self::SYNCED => '已同步',
            self::CONFLICT => '冲突',
            self::FAILED => '失败',
        };
    }

    public function needsSync(): bool
    {
        return in_array($this, [self::PENDING, self::FAILED]);
    }

    public function canEdit(): bool
    {
        return $this !== self::CONFLICT;
    }
}
