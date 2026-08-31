<?php

namespace Modules\Orders\Domain\ValueObjects;

use Modules\Orders\Domain\Entities\Order;

/**
 * Enum de estados del pedido.
 *
 * FLUJO CANÓNICO ONSITE (dine_in tradicional):
 *   DRAFT → CONFIRMED → PREPARING → READY → SERVED → PAID → CLOSED
 *
 * FLUJO PICKUP (takeout):
 *   DRAFT → CONFIRMED → PREPARING → READY → READY_FOR_PICKUP → PICKED_UP → PAID → CLOSED
 *
 * FLUJO DELIVERY:
 *   DRAFT → CONFIRMED → PREPARING → READY → DISPATCHED → DELIVERED → PAID → CLOSED
 *
 * BACKWARD COMPATIBILITY:
 * El flujo legacy READY → SERVED sigue siendo válido para cualquier canal
 * (para no romper tests existentes y clientes API que usen el flujo tradicional).
 *
 * CANCELLED es terminal y puede alcanzarse desde cualquier estado no-final
 * (requiere razón).
 */
enum OrderStatus: string
{
    case DRAFT = 'draft';
    case CONFIRMED = 'confirmed';
    case PREPARING = 'preparing';
    case READY = 'ready';

    // ─── Estados específicos por canal de fulfillment ───
    case READY_FOR_PICKUP = 'ready_for_pickup';  // pickup: listo para retirar
    case PICKED_UP = 'picked_up';                // pickup: cliente retiró
    case DISPATCHED = 'dispatched';              // delivery: salió a entregar
    case DELIVERED = 'delivered';                // delivery: entregado al cliente

    // ─── Estados compartidos ───
    case SERVED = 'served';      // onsite: servido en mesa (legacy en otros canales)
    case PAID = 'paid';
    case CLOSED = 'closed';
    case CANCELLED = 'cancelled';

    /**
     * Transiciones válidas SIN considerar el canal de fulfillment.
     * Usado por código legacy o cuando no se tiene contexto del pedido.
     *
     * DEVUELVE LA UNIÓN de todas las transiciones posibles de todos los canales,
     * lo cual es permisivo pero seguro (la validación estricta ocurre en
     * allowedTransitionsFor cuando se tiene el Order).
     */
    public function allowedTransitions(): array
    {
        return match($this) {
            self::DRAFT => [self::CONFIRMED, self::CANCELLED],
            self::CONFIRMED => [self::PREPARING, self::CANCELLED],
            self::PREPARING => [self::READY, self::CANCELLED],
            self::READY => [
                self::SERVED,               // legacy (cualquier canal)
                self::READY_FOR_PICKUP,     // pickup
                self::DISPATCHED,           // delivery
                self::CANCELLED,
            ],
            self::READY_FOR_PICKUP => [self::PICKED_UP, self::CANCELLED],
            self::PICKED_UP => [self::PAID],
            self::DISPATCHED => [self::DELIVERED, self::CANCELLED],
            self::DELIVERED => [self::PAID],
            self::SERVED => [self::PAID],
            self::PAID => [self::CLOSED],
            self::CLOSED => [],
            self::CANCELLED => [],
        };
    }

    /**
     * Transiciones válidas CONSIDERANDO el canal de fulfillment del pedido.
     *
     * Es la validación ESTRICTA que debe usarse en OrderStateMachine.
     * Si el pedido no tiene fulfillment_channel definido (pedidos antiguos),
     * asume onsite por defecto.
     */
    public function allowedTransitionsFor(Order $order): array
    {
        $channel = $order->fulfillment_channel ?? FulfillmentChannel::ONSITE;

        return match($this) {
            self::DRAFT => [self::CONFIRMED, self::CANCELLED],
            self::CONFIRMED => [self::PREPARING, self::CANCELLED],
            self::PREPARING => [self::READY, self::CANCELLED],

            self::READY => match($channel) {
                FulfillmentChannel::ONSITE => [self::SERVED, self::CANCELLED],
                FulfillmentChannel::PICKUP => [self::READY_FOR_PICKUP, self::SERVED, self::CANCELLED],
                FulfillmentChannel::DELIVERY => [self::DISPATCHED, self::SERVED, self::CANCELLED],
            },

            self::READY_FOR_PICKUP => [self::PICKED_UP, self::CANCELLED],
            self::PICKED_UP => [self::PAID],
            self::DISPATCHED => [self::DELIVERED, self::CANCELLED],
            self::DELIVERED => [self::PAID],
            self::SERVED => [self::PAID],
            self::PAID => [self::CLOSED],
            self::CLOSED => [],
            self::CANCELLED => [],
        };
    }

    /**
     * Verifica si la transición es válida (SIN contexto del pedido).
     * Usar solo cuando no se tenga el Order disponible.
     */
    public function canTransitionTo(OrderStatus $newStatus): bool
    {
        return in_array($newStatus, $this->allowedTransitions());
    }

    /**
     * Verifica si la transición es válida CON contexto del pedido.
     * ESTE es el método preferido para OrderStateMachine.
     */
    public function canTransitionToFor(OrderStatus $newStatus, Order $order): bool
    {
        return in_array($newStatus, $this->allowedTransitionsFor($order));
    }

    public function isEditable(): bool
    {
        return $this === self::DRAFT;
    }

    public function isActive(): bool
    {
        return !in_array($this, [self::CLOSED, self::CANCELLED]);
    }

    public function isInKitchenQueue(): bool
    {
        return in_array($this, [self::CONFIRMED, self::PREPARING]);
    }

    /**
     * Verifica si el pedido espera pago.
     * Para pickup: después de PICKED_UP.
     * Para delivery: después de DELIVERED.
     * Para onsite: en SERVED.
     */
    public function isAwaitingPayment(): bool
    {
        return in_array($this, [self::SERVED, self::PICKED_UP, self::DELIVERED]);
    }

    public function isFinalState(): bool
    {
        return in_array($this, [self::CLOSED, self::CANCELLED]);
    }

    /**
     * Verifica si el pedido está en estado cobrable (enviado a cocina o listo para cobro).
     *
     * Incluye todos los estados activos después de CONFIRMED donde el pedido
     * ya tiene contenido cobrable (items confirmados, en cocina, o listos).
     */
    public function isChargeable(): bool
    {
        return in_array($this, [
            self::CONFIRMED,
            self::PREPARING,
            self::READY,
            self::READY_FOR_PICKUP,
            self::PICKED_UP,
            self::DISPATCHED,
            self::DELIVERED,
            self::SERVED,
        ]);
    }

    public static function chargeableStatuses(): array
    {
        return [
            self::CONFIRMED,
            self::PREPARING,
            self::READY,
            self::READY_FOR_PICKUP,
            self::PICKED_UP,
            self::DISPATCHED,
            self::DELIVERED,
            self::SERVED,
        ];
    }

    /**
     * Label en español para UI.
     */
    public function label(): string
    {
        return match($this) {
            self::DRAFT => 'Borrador',
            self::CONFIRMED => 'Confirmado',
            self::PREPARING => 'En preparación',
            self::READY => 'Listo',
            self::READY_FOR_PICKUP => 'Listo para retirar',
            self::PICKED_UP => 'Retirado',
            self::DISPATCHED => 'En camino',
            self::DELIVERED => 'Entregado',
            self::SERVED => 'Servido',
            self::PAID => 'Pagado',
            self::CLOSED => 'Cerrado',
            self::CANCELLED => 'Cancelado',
        };
    }
}
