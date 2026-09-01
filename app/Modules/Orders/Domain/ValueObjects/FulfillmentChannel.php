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
  * Implementado en Fase 2-3 (commits 53515af, a7c4289):
 * - Columna `fulfillment_channel` agregada a tabla `orders`
 * - Backfill automático: DINE_IN→onsite, TAKEOUT→pickup, DELIVERY→delivery
 * - Default 'onsite' vía migración
 * - Documentación completa en docs/architecture/decisions/001-fulfillment-model.md
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
