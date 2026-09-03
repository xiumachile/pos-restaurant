<?php

namespace Modules\Tables\Domain\Services;

use Modules\Tables\Domain\Exceptions\InvalidTableStatusTransition;
use Modules\Tables\Domain\ValueObjects\TableStatus;

class TableStateMachine

    /**
     * TODO(post-demo 11 Sep): Refinar máquina de estados
     * 
     * Actualmente permite transición directa occupied → available para
     * liberar mesas tras el pago. Esto es pragmático pero pierde el
     * estado intermedio 'billing' que debería usarse cuando el cliente
     * solicita la cuenta antes de pagar.
     * 
     * Flujo ideal:
     *   occupied → billing (cliente pide cuenta)
     *   billing → available (se completa el pago)
     * 
     * Flujo actual (simplificado):
     *   occupied → available (pago directo)
     * 
     * Implementar:
     *   1. Endpoint POST /tables/{id}/request-billing
     *   2. Frontend: botón "Solicitar Cuenta" en OrderTakingPage
     *   3. Remover 'available' de transiciones de 'occupied'
     *   4. Forzar flujo completo: occupied → billing → available
     */
{
    /**
     * Mapa de transiciones válidas.
     * Clave: estado actual. Valor: estados a los que puede transicionar.
     */
    private const TRANSITIONS = [
        'available'   => ['occupied', 'maintenance'],
        'occupied'    => ['billing', 'available'],  // FIX: permitir pago directo sin billing
        'billing'     => ['available'],
        'maintenance' => ['available'],
    ];

    /**
     * Verifica si una transición es válida.
     */
    public function canTransition(TableStatus $from, TableStatus $to): bool
    {
        $allowed = self::TRANSITIONS[$from->value] ?? [];

        return in_array($to->value, $allowed, true);
    }

    /**
     * Valida una transición y lanza excepción si es inválida.
     */
    public function assertTransition(TableStatus $from, TableStatus $to): void
    {
        if (!$this->canTransition($from, $to)) {
            throw InvalidTableStatusTransition::fromTo($from, $to);
        }
    }

    /**
     * Obtiene los estados a los que puede transicionar un estado dado.
     */
    public function allowedTransitions(TableStatus $from): array
    {
        $allowed = self::TRANSITIONS[$from->value] ?? [];

        return array_map(
            fn (string $status) => TableStatus::from($status),
            $allowed
        );
    }
}
