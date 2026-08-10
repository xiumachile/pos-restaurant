<?php

namespace Modules\Tables\Domain\Services;

use Modules\Tables\Domain\Exceptions\InvalidTableStatusTransition;
use Modules\Tables\Domain\ValueObjects\TableStatus;

class TableStateMachine
{
    /**
     * Mapa de transiciones válidas.
     * Clave: estado actual. Valor: estados a los que puede transicionar.
     */
    private const TRANSITIONS = [
        'available'   => ['occupied', 'maintenance'],
        'occupied'    => ['billing'],
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
