<?php

namespace Modules\Sync\Domain\Exceptions;

use Exception;
use Throwable;

/**
 * Excepción base para errores de sincronización.
 */
class SyncException extends Exception
{
    protected ?string $entityType;
    protected ?int $entityId;

    public function __construct(
        string $message = 'Sync operation failed',
        ?string $entityType = null,
        ?int $entityId = null,
        int $code = 0,
        ?Throwable $previous = null
    ) {
        $this->entityType = $entityType;
        $this->entityId = $entityId;
        
        parent::__construct($message, $code, $previous);
    }

    public function getEntityType(): ?string
    {
        return $this->entityType;
    }

    public function getEntityId(): ?int
    {
        return $this->entityId;
    }
}

