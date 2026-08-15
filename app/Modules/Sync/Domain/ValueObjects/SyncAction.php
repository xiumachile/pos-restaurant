<?php

namespace Modules\Sync\Domain\ValueObjects;

/**
 * Tipo de acción de sincronización registrada en la cola.
 */
enum SyncAction: string
{
    case CREATE = 'create';
    case UPDATE = 'update';
    case DELETE = 'delete';

    public function label(): string
    {
        return match($this) {
            self::CREATE => 'Creación',
            self::UPDATE => 'Actualización',
            self::DELETE => 'Eliminación',
        };
    }

    public function labelZh(): string
    {
        return match($this) {
            self::CREATE => '创建',
            self::UPDATE => '更新',
            self::DELETE => '删除',
        };
    }
}
