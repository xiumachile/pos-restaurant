<?php

namespace Modules\Orders\Domain\ValueObjects;

/**
 * Enum de canales de cumplimiento (cómo se entrega el pedido al cliente).
 *
 * Semántica:
 * - ONSITE:  Cliente consume en el local (dine_in, counter service)
 * - PICKUP:  Cliente retira en el local (takeout, curbside)
 * - DELIVERY: Pedido se envía a domicilio del cliente
 *
 * Relación con OrderType:
 * - DINE_IN  → default ONSITE (puede sobreescribirse a PICKUP para "comer en barra")
 * - TAKEOUT  → default PICKUP
 * - DELIVERY → default DELIVERY
 *
 * Nota: Este enum se definió como parte de Fase 2 (Order Core sin Mesa).
 * El campo `fulfillment_channel` en la tabla `orders` se agregará en una
 * fase posterior cuando se requiera distinguir fulfillment del tipo.
 */
enum FulfillmentChannel: string
{
    case ONSITE = 'onsite';
    case PICKUP = 'pickup';
    case DELIVERY = 'delivery';

    public function label(): string
    {
        return match($this) {
            self::ONSITE => 'En el local',
            self::PICKUP => 'Retiro en tienda',
            self::DELIVERY => 'Entrega a domicilio',
        };
    }
}
