<?php

namespace Modules\Orders\Domain\Services;

use Illuminate\Support\Carbon;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\Events\OrderCancelled;
use Modules\Orders\Domain\Events\OrderDiscountApplied;
use Modules\Orders\Domain\Events\OrderClosed;
use Modules\Orders\Domain\Events\OrderConfirmed;
use Modules\Orders\Domain\Events\OrderPaid;
use Modules\Orders\Domain\Events\OrderReady;
use Modules\Orders\Domain\Exceptions\InvalidOrderTransitionException;
use Modules\Orders\Domain\ValueObjects\OrderStatus;

class OrderStateMachine
{
    public function transition(Order $order, OrderStatus $newStatus, ?string $reason = null): Order
    {
        $this->assertCanTransition($order->status, $newStatus);

        if ($newStatus === OrderStatus::CANCELLED && empty($reason)) {
            throw InvalidOrderTransitionException::requiresReason();
        }

        $order->status = $newStatus;
        $this->updateTimestamp($order, $newStatus);

        if ($newStatus === OrderStatus::CANCELLED) {
            $order->cancellation_reason = $reason;
        }

        $order->save();

        $this->dispatchEvent($order, $newStatus);

        return $order;
    }

    public function assertCanTransition(OrderStatus $from, OrderStatus $to): void
    {
        if (!$from->canTransitionTo($to)) {
            throw InvalidOrderTransitionException::fromTo($from, $to);
        }
    }

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

    protected function dispatchEvent(Order $order, OrderStatus $status): void
    {
        match($status) {
            OrderStatus::CONFIRMED => OrderConfirmed::dispatch($order),
            OrderStatus::READY => OrderReady::dispatch($order),
            OrderStatus::PAID => OrderPaid::dispatch($order),
            OrderStatus::CLOSED => OrderClosed::dispatch($order),
            OrderStatus::CANCELLED => OrderCancelled::dispatch($order),
            default => null,
        };
    }

    public function canModifyItems(Order $order): bool
    {
        return $order->isEditable();
    }


    /**
     * Aplica un descuento a un pedido y dispara el evento para auditoría.
     */
    public function applyDiscount(Order $order, float $amount, string $reason): Order
    {
        if ($amount <= 0) {
            throw InvalidOrderTransitionException::fromTo($order->status, $order->status);
        }

        $order->discount_amount = $amount;
        
        // Recalcular totales si el método existe
        if (method_exists($order, 'recalculateTotals')) {
            $order->recalculateTotals();
        }
        
        $order->save();
        
        OrderDiscountApplied::dispatch($order, $amount, $reason);
        
        return $order;
    }

    public function canCancel(Order $order): bool
    {
        return $order->status->canTransitionTo(OrderStatus::CANCELLED);
    }
}
