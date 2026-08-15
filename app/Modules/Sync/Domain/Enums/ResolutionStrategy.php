<?php

namespace Modules\Sync\Domain\Enums;

/**
 * Estrategias de resolución de conflictos de sincronización.
 */
enum ResolutionStrategy: string
{
    case SERVER_WINS = 'server_wins';
    case CLIENT_WINS = 'client_wins';
    case MERGE = 'merge';
    case MANUAL = 'manual';

    public function label(): string
    {
        return match($this) {
            self::SERVER_WINS => 'Servidor gana',
            self::CLIENT_WINS => 'Cliente gana',
            self::MERGE => 'Fusionar cambios',
            self::MANUAL => 'Revisión manual',
        };
    }

    public function description(): string
    {
        return match($this) {
            self::SERVER_WINS => 'Los datos del servidor sobrescriben los cambios del cliente',
            self::CLIENT_WINS => 'Los datos del cliente sobrescriben el servidor en el próximo push',
            self::MERGE => 'Intenta fusionar campos no conflictivos automáticamente',
            self::MANUAL => 'Requiere intervención humana para decidir',
        };
    }
}
