<?php

namespace App\Shared\Domain\Exceptions;

use Exception;
use Throwable;

/**
 * Excepción lanzada cuando se intenta consultar un modelo multi-tenant
 * sin tener configurado el contexto de tenant.
 * 
 * Implementa el principio fail-closed para evitar fugas de datos
 * entre empresas/sucursales en jobs, listeners y comandos.
 */
class TenantContextNotSetException extends Exception
{
    private ?string $modelClass;

    public function __construct(
        ?string $message = null,
        ?string $modelClass = null,
        int $code = 0,
        ?Throwable $previous = null
    ) {
        $this->modelClass = $modelClass;
        
        $defaultMessage = $modelClass
            ? "TenantContext not set: cannot query model {$modelClass} without company/branch context. " .
              "This query was blocked to prevent cross-tenant data access."
            : "TenantContext not set: operation requires company/branch context. " .
              "Operation blocked to prevent cross-tenant data access.";
        
        parent::__construct($message ?? $defaultMessage, $code, $previous);
    }

    public function getModelClass(): ?string
    {
        return $this->modelClass;
    }
}
