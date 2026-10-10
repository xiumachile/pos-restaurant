<?php

namespace Modules\Orders\Domain\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\Events\OrderCancelled;
use Modules\Orders\Domain\Events\OrderClosed;
use Modules\Orders\Domain\Events\OrderConfirmed;
use Modules\Orders\Domain\Events\OrderDiscountApplied;
use Modules\Orders\Domain\Events\OrderPaid;
use Modules\Orders\Domain\Events\OrderPreparationStarted;
use Modules\Orders\Domain\Events\OrderReady;
use Modules\Orders\Domain\Events\OrderServed;
use Modules\Orders\Domain\Exceptions\InvalidOrderTransitionException;
use Modules\Orders\Domain\ValueObjects\OrderStatus;

class OrderStateMachine
{
    public function transition(Order $order, OrderStatus $newStatus, ?string $reason = null): Order
    {
        $this->assertCanTransitionForOrder($order, $newStatus);

        if ($newStatus === OrderStatus::CANCELLED && empty($reason)) {
            throw InvalidOrderTransitionException::requiresReason();
        }

        return DB::transaction(function () use ($order, $newStatus, $reason) {
            $order->status = $newStatus;
            $this->updateTimestamp($order, $newStatus);

            if ($newStatus === OrderStatus::CANCELLED) {
                $order->cancellation_reason = $reason;
            }

            $order->save();

            // HALLAZGO C-01 & M-03: DB::afterCommit garantiza que el evento solo se despache
            // si la transacción de base de datos se confirma exitosamente.
            DB::afterCommit(function () use ($order, $newStatus) {
                $this->dispatchEvent($order, $newStatus);
            });

            return $order;
        });
    }

    public function assertCanTransition(OrderStatus $from, OrderStatus $to): void
    {
        if (!$from->canTransitionTo($to)) {
            throw InvalidOrderTransitionException::fromTo($from, $to);
        }
    }

    public function assertCanTransitionForOrder(Order $order, OrderStatus $to): void
    {
        if (!$order->status->canTransitionToFor($to, $order)) {
            throw InvalidOrderTransitionException::fromTo($order->status, $to);
        }
    }

    protected function updateTimestamp(Order $order, OrderStatus $status): void
    {
        $now = Carbon::now();

        match($status) {
            OrderStatus::CONFIRMED => $order->confirmed_at = $now,
            OrderStatus::PREPARING => $order->preparing_at = $now,
            OrderStatus::READY => $order->ready_at = $now,
            OrderStatus::SERVED => $order->served_at = $now,
            OrderStatus::PICKED_UP => $order->picked_up_at = $now,
            OrderStatus::DISPATCHED => $order->dispatched_at = $now,
            OrderStatus::DELIVERED => $order->delivered_at = $now,
            OrderStatus::PAID => $order->paid_at = $now,
            OrderStatus::CLOSED => $order->closed_at = $now,
            OrderStatus::CANCELLED => $order->cancelled_at = $now,
            default => null,
        };
    }

    /**
     * HALLAZGO C-01: Despacha eventos de dominio para TODAS las transiciones operacionales significativas.
     */
    protected function dispatchEvent(Order $order, OrderStatus $status): void
    {
        $event = match($status) {
            OrderStatus::CONFIRMED => new OrderConfirmed($order),
            OrderStatus::PREPARING => new OrderPreparationStarted($order),
            OrderStatus::READY => new OrderReady($order),
            OrderStatus::SERVED => new OrderServed($order),
            OrderStatus::PAID => new OrderPaid($order),
            OrderStatus::CLOSED => new OrderClosed($order),
            OrderStatus::CANCELLED => new OrderCancelled($order),
            default => null,
        };

        if ($event) {
            // Usar la función helper event() para despachar una instancia ya creada
            event($event);
        }
    }

    public function canModifyItems(Order $order): bool
    {
        return $order->status === OrderStatus::DRAFT;
    }

    public function applyDiscount(Order $order, float $amount, string $reason): Order
    {
        if ($amount <= 0) {
            throw InvalidOrderTransitionException::fromTo($order->status, $order->status);
        }

        $currentSubtotal = $order->subtotal_gross ?? $order->subtotal ?? 0;
        if ($amount > $currentSubtotal) {
            throw new \InvalidArgumentException(
                "Discount amount ({$amount}) cannot exceed subtotal ({$currentSubtotal})"
            );
        }

        return DB::transaction(function () use ($order, $amount, $reason) {
            $order->discount_amount = (int) $amount;

            if ($order->items()->count() > 0 && method_exists($order, 'recalculateTotals')) {
                $order->recalculateTotals();
            } else {
                $order->total = ($order->subtotal ?? 0) + ($order->tax_amount ?? 0) - (int) $amount;
                $order->amount_due = $order->total + ($order->tip_amount ?? 0);
            }

            $order->save();

            DB::afterCommit(function () use ($order, $amount, $reason) {
                event(new OrderDiscountApplied($order, (int) $amount, $reason));
            });

            return $order;
        });
    }

    public function canCancel(Order $order): bool
    {
        return $order->status->canTransitionToFor(OrderStatus::CANCELLED, $order);
    }
}
