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

/**
 * Máquina de estados de pedidos.
 *
 * Gestiona las transiciones de estado de un pedido considerando el canal
 * de fulfillment (onsite, pickup, delivery) para aplicar reglas específicas.
 */
class OrderStateMachine
{
    /**
     * Ejecuta una transición de estado con validación condicional por canal.
     */
    public function transition(Order $order, OrderStatus $newStatus, ?string $reason = null): Order
    {
        $this->assertCanTransitionForOrder($order, $newStatus);

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

    /**
     * Valida si una transición es posible (legacy, sin contexto del pedido).
     *
     * @deprecated Usar assertCanTransitionForOrder() cuando se tenga el Order.
     */
    public function assertCanTransition(OrderStatus $from, OrderStatus $to): void
    {
        if (!$from->canTransitionTo($to)) {
            throw InvalidOrderTransitionException::fromTo($from, $to);
        }
    }

    /**
     * Valida si una transición es posible CON contexto del pedido.
     * Usa allowedTransitionsFor() para aplicar reglas específicas por canal.
     */
    public function assertCanTransitionForOrder(Order $order, OrderStatus $to): void
    {
        if (!$order->status->canTransitionToFor($to, $order)) {
            throw InvalidOrderTransitionException::fromTo($order->status, $to);
        }
    }

    /**
     * Actualiza el timestamp correspondiente al nuevo estado.
     */
    protected function updateTimestamp(Order $order, OrderStatus $status): void
    {
        $now = Carbon::now();

        match($status) {
            OrderStatus::CONFIRMED => $order->confirmed_at = $now,
            OrderStatus::SERVED => $order->served_at = $now,
            // Nuevos timestamps específicos por canal (Fase 4)
            OrderStatus::PICKED_UP => $order->picked_up_at = $now,
            OrderStatus::DISPATCHED => $order->dispatched_at = $now,
            OrderStatus::DELIVERED => $order->delivered_at = $now,
            // Timestamps compartidos
            OrderStatus::PAID => $order->paid_at = $now,
            OrderStatus::CLOSED => $order->closed_at = $now,
            OrderStatus::CANCELLED => $order->cancelled_at = $now,
            default => null,
        };
    }

    /**
     * Despacha eventos de dominio según el nuevo estado.
     */
    protected function dispatchEvent(Order $order, OrderStatus $status): void
    {
        match($status) {
            OrderStatus::CONFIRMED => OrderConfirmed::dispatch($order),
            OrderStatus::READY => OrderReady::dispatch($order),
            OrderStatus::PAID => OrderPaid::dispatch($order),
            OrderStatus::CLOSED => OrderClosed::dispatch($order),
            OrderStatus::CANCELLED => OrderCancelled::dispatch($order),
            // PICKED_UP, DISPATCHED, DELIVERED, SERVED no disparan eventos
            // (se pueden agregar en el futuro si se requiere)
            default => null,
        };
    }

    public function canModifyItems(Order $order): bool
    {
        return $order->isEditable();
    }

    /**
     * Aplica un descuento a un pedido y dispara el evento para auditoría.
     * 
     * Validaciones:
     * - amount debe ser positivo
     * - discount no puede exceder el subtotal (previene total negativo)
     * 
     * Comportamiento:
     * - Si hay items: recalcula totales desde items
     * - Si no hay items: preserva subtotal/tax y solo actualiza discount/total
     */
    public function applyDiscount(Order $order, float $amount, string $reason): Order
    {
        if ($amount <= 0) {
            throw InvalidOrderTransitionException::fromTo($order->status, $order->status);
        }

        // Validación de negocio: discount no puede exceder subtotal
        $currentSubtotal = $order->subtotal_gross ?? $order->subtotal ?? 0;
        if ($amount > $currentSubtotal) {
            throw new \InvalidArgumentException(
                "Discount amount ({$amount}) cannot exceed subtotal ({$currentSubtotal})"
            );
        }

        $order->discount_amount = (int) $amount;

        // Si hay items, recalcular desde ellos (fuente de verdad)
        if ($order->items()->count() > 0 && method_exists($order, 'recalculateTotals')) {
            $order->recalculateTotals();
        } else {
            // Sin items: preservar subtotal/tax, solo actualizar discount y total
            $order->total = ($order->subtotal ?? 0) + ($order->tax_amount ?? 0) - (int) $amount;
            $order->amount_due = $order->total + ($order->tip_amount ?? 0);
        }

        $order->save();

        OrderDiscountApplied::dispatch($order, (int) $amount, $reason);

        return $order;
    }

    public function canCancel(Order $order): bool
    {
        return $order->status->canTransitionToFor(OrderStatus::CANCELLED, $order);
    }
}
