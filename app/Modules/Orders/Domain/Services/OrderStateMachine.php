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
    /**
     * HALLAZGO C-02: Protección contra transiciones concurrentes.
     * Utiliza bloqueo pesimista (lockForUpdate) para garantizar que dos cocineros
     * no procesen la misma transición simultáneamente sobre un estado desactualizado.
     */
    public function transition(Order $order, OrderStatus $newStatus, ?string $reason = null): Order
    {
        return DB::transaction(function () use ($order, $newStatus, $reason) {
            // 1. Bloquear la fila del pedido para lectura/escritura exclusiva
            // Se incluye company_id para mantener el aislamiento de tenant incluso en el lock
            $lockedOrder = Order::where('id', $order->id)
                ->where('company_id', $order->company_id)
                ->lockForUpdate()
                ->firstOrFail();

            // 2. Validar la transición con el estado REAL y actualizado de la base de datos
            // Si otro proceso ya cambió el estado, esta validación fallará de forma controlada
            $this->assertCanTransitionForOrder($lockedOrder, $newStatus);

            if ($newStatus === OrderStatus::CANCELLED && empty($reason)) {
                throw InvalidOrderTransitionException::requiresReason();
            }

            // 3. Aplicar cambios
            $lockedOrder->status = $newStatus;
            $this->updateTimestamp($lockedOrder, $newStatus);

            if ($newStatus === OrderStatus::CANCELLED) {
                $lockedOrder->cancellation_reason = $reason;
            }

            $lockedOrder->save();

            // 4. Despachar eventos solo si el commit es exitoso (Hallazgo M-03)
            DB::afterCommit(function () use ($lockedOrder, $newStatus) {
                $this->dispatchEvent($lockedOrder, $newStatus);
            });

            return $lockedOrder;
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
