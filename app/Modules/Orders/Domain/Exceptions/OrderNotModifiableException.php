<?php

namespace Modules\Orders\Domain\Exceptions;

use Exception;

/**
 * Excepción de dominio lanzada cuando se intenta modificar un pedido
 * que ya no está en estado editable (confirmado, en preparación, etc.).
 *
 * Reemplaza abort(422) en OrderService para mantener separación de capas
 * y permitir manejo estructurado de errores en el frontend.
 *
 * El frontend puede mapear este error a un mensaje amigable:
 * "No se pueden modificar pedidos ya confirmados."
 */
class OrderNotModifiableException extends Exception
{
    public function __construct(
        string $message = 'No se pueden modificar pedidos ya confirmados.',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Renderiza la excepción como JSON para APIs.
     * Capturado por ExceptionHandler (bootstrap/app.php).
     */
    public function render()
    {
        return response()->json([
            'error' => 'order_not_modifiable',
            'message' => $this->getMessage(),
            'status' => 422,
        ], 422);
    }
}
