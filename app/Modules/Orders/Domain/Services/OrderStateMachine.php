<?php

namespace Modules\Orders\Domain\Services;

use Illuminate\Support\Carbon;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\Events\OrderCancelled;
use Modules\Orders\Domain\Events\OrderClosed;
use Modules\Orders\Domain\Events\OrderConfirmed;
use Modules\Orders\Domain\Events\OrderPaid;
use Modules\Orders\Domain\Exceptions\InvalidOrderTransitionException;
use Modules\Orders\Domain\ValueObjects\OrderStatus;

class OrderStateMachine
{
    /**
     * Realiza una transición de estado del pedido.
     * Valida la transición, actualiza timestamps y dispara eventos.
     */
    public function transition(Order $order, OrderStatus $newStatus, ?string $reason = null): Order
    {
        $this->assertCanTransition($order->status, $newStatus);

        // La cancelación requiere razón
        if ($newStatus === OrderStatus::CANCELLED && empty($reason)) {
            throw InvalidOrderTransitionException::requiresReason();
        }

        $order->status = $newStatus;
        $this->updateTimestamp($order, $newStatus);

        if ($newStatus === OrderStatus::CANCELLED) {
            $order->cancellation_reason = $reason;
        }

        $order->save();

        // Disparar evento correspondiente
        $this->dispatchEvent($order, $newStatus);

        return $order;
    }

    /**
     * Valida que la transición sea permitida.
     */
    public function assertCanTransition(OrderStatus $from, OrderStatus $to): void
    {
        if (!$from->canTransitionTo($to)) {
            throw InvalidOrderTransitionException::fromTo($from, $to);
        }
    }

    /**
     * Actualiza el timestamp correspondiente según el estado.
     */
    protected function updateTimestamp(Order $order, OrderStatus $status): void
    {
        $now = Carbon::now();

        match($status) {
            OrderStatus::CONFIRMED => $order->confirmed_at = $now,
            OrderStatus::SERVED => $order->served_at = $now,
            OrderStatus::PAID => $order->paid_at = $now,
            OrderStatus::CLOSED => $order->closed_at = $now,
            OrderStatus::CANCELLED => $order->cancelled_at = $now,
            default => null,
        };
    }

    /**
     * Dispara el evento correspondiente al nuevo estado.
     */
    protected function dispatchEvent(Order $order, OrderStatus $status): void
    {
        match($status) {
            OrderStatus::CONFIRMED => OrderConfirmed::dispatch($order),
            OrderStatus::PAID => OrderPaid::dispatch($order),
            OrderStatus::CLOSED => OrderClosed::dispatch($order),
            OrderStatus::CANCELLED => OrderCancelled::dispatch($order),
            default => null,
        };
    }

    /**
     * Verifica si el pedido puede ser modificado.
     */
    public function canModifyItems(Order $order): bool
    {
        return $order->isEditable();
    }

    /**
     * Verifica si el pedido puede ser cancelado.
     */
    public function canCancel(Order $order): bool
    {
        return $order->status->canTransitionTo(OrderStatus::CANCELLED);
    }
}
