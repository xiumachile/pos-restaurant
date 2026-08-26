<?php

namespace Modules\Tables\Application\DTOs;

/**
 * DTO para transferir datos de cambio de estado.
 * 
 * Uso:
 * - Controller crea este DTO desde el Request
 * - Application Service recibe este DTO (no el Request)
 * - Desacopla la capa de presentación de la capa de aplicación
 */
class ChangeTableStatusDTO
{
    public function __construct(
        public readonly string $tableUuid,
        public readonly string $newStatus,
        public readonly ?int $orderId = null
    ) {
    }

    public static function fromRequest(UpdateTableStatusRequest $request, string $uuid): self
    {
        return new self(
            tableUuid: $uuid,
            newStatus: $request->status,
            orderId: $request->current_order_id
        );
    }
}
