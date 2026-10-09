<?php

namespace Modules\Orders\Domain\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Excepción lanzada cuando un update de orden falla por conflicto de versión.
 * Implementa Optimistic Concurrency Control para escenarios offline.
 */
class OrderConflictException extends Exception
{
    protected array $currentOrderData;
    protected int $currentVersion;

    public function __construct(array $currentOrderData, int $currentVersion)
    {
        $this->currentOrderData = $currentOrderData;
        $this->currentVersion = $currentVersion;
        parent::__construct('Conflict: la orden fue modificada por otro terminal');
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'error' => 'order_conflict',
            'message' => 'La orden fue modificada por otro terminal. Versión esperada no coincide.',
            'current_version' => $this->currentVersion,
            'current_data' => $this->currentOrderData,
        ], 409);
    }
}