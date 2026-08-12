<?php

namespace Modules\Orders\Domain\ValueObjects;

enum OrderStatus: string
{
    case DRAFT = 'draft';
    case CONFIRMED = 'confirmed';
    case PREPARING = 'preparing';
    case READY = 'ready';
    case SERVED = 'served';
    case PAID = 'paid';
    case CLOSED = 'closed';
    case CANCELLED = 'cancelled';

    /**
     * Transiciones válidas desde cada estado.
     */
    public function allowedTransitions(): array
    {
        return match($this) {
            self::DRAFT => [self::CONFIRMED, self::CANCELLED],
            self::CONFIRMED => [self::PREPARING, self::CANCELLED],
            self::PREPARING => [self::READY, self::CANCELLED],
            self::READY => [self::SERVED, self::CANCELLED],
            self::SERVED => [self::PAID],
            self::PAID => [self::CLOSED],
            self::CLOSED => [],
            self::CANCELLED => [],
        };
    }

    /**
     * Verifica si la transición es válida.
     */
    public function canTransitionTo(OrderStatus $newStatus): bool
    {
        return in_array($newStatus, $this->allowedTransitions());
    }

    /**
     * Verifica si el pedido es editable (solo en draft).
     */
    public function isEditable(): bool
    {
        return $this === self::DRAFT;
    }

    /**
     * Verifica si el pedido está activo (no cerrado ni cancelado).
     */
    public function isActive(): bool
    {
        return !in_array($this, [self::CLOSED, self::CANCELLED]);
    }

    /**
     * Verifica si el pedido está en cola de cocina.
     */
    public function isInKitchenQueue(): bool
    {
        return in_array($this, [self::CONFIRMED, self::PREPARING]);
    }

    /**
     * Verifica si el pedido espera pago.
     */
    public function isAwaitingPayment(): bool
    {
        return $this === self::SERVED;
    }

    /**
     * Verifica si el pedido está en un estado final (closed o cancelled).
     */
    public function isFinalState(): bool
    {
        return in_array($this, [self::CLOSED, self::CANCELLED]);
    }
}
