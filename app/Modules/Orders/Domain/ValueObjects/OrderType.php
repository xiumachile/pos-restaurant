<?php

namespace Modules\Orders\Domain\ValueObjects;

/**
 * Enum de tipos de pedido (dónde se consume).
 *
 * Semántica:
 * - DINE_IN:  Cliente consume en el local. REQUIERE mesa asignada.
 * - TAKEOUT:  Cliente retira el pedido en el local. NO puede tener mesa.
 * - DELIVERY: Pedido se envía al domicilio del cliente. NO puede tener mesa.
 *
 * Ver también: FulfillmentChannel (cómo se entrega).
 */
enum OrderType: string
{
    case DINE_IN = 'dine_in';
    case TAKEOUT = 'takeout';
    case DELIVERY = 'delivery';

    public function label(): string
    {
        return match($this) {
            self::DINE_IN => 'Para servir en mesa',
            self::TAKEOUT => 'Para llevar',
            self::DELIVERY => 'Delivery',
        };
    }

    /**
     * Indica si este tipo de pedido requiere una mesa asignada.
     *
     * Solo dine_in requiere mesa. takeout y delivery son pedidos sin mesa.
     */
    public function requiresTable(): bool
    {
        return $this === self::DINE_IN;
    }

    /**
     * Indica si este tipo de pedido NO puede tener mesa.
     *
     * takeout y delivery son incompatibles con mesa asignada.
     */
    public function forbidsTable(): bool
    {
        return $this === self::TAKEOUT || $this === self::DELIVERY;
    }

    /**
     * Canal de cumplimiento por defecto para este tipo de pedido.
     *
     * Mapeo canónico type → fulfillment (puede sobreescribirse explícitamente
     * en una fase posterior cuando se agregue el campo fulfillment_channel).
     */
    public function defaultFulfillmentChannel(): FulfillmentChannel
    {
        return match($this) {
            self::DINE_IN => FulfillmentChannel::ONSITE,
            self::TAKEOUT => FulfillmentChannel::PICKUP,
            self::DELIVERY => FulfillmentChannel::DELIVERY,
        };
    }
}
