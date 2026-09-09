<?php

namespace Modules\Fiscal\Domain\ValueObjects;

use Modules\Orders\Domain\Entities\Order;

/**
 * Tipos de Documentos Tributarios Electrónicos (DTE) según SII Chile.
 * 
 * Referencia: https://www.sii.cl/pagina/1422/tiposdoc.htm
 */
enum DteType: int
{
    case FACTURA_ELECTRONICA = 33;           // Factura Electrónica (Afecta)
    case FACTURA_EXENTA = 34;                // Factura No Afecta o Exenta
    case LIQUIDACION_FACTURA = 40;           // Liquidación-Factura
    case BOLETA_AFECTA = 39;                 // Boleta Electrónica Afecta
    case BOLETA_EXENTA = 41;                 // Boleta Electrónica Exenta
    case NOTA_CREDITO = 61;                  // Nota de Crédito Electrónica
    case NOTA_DEBITO = 56;                   // Nota de Débito Electrónica
    case GUIA_DESPACHO = 52;                 // Guía de Despacho Electrónica

    public function label(): string
    {
        return match($this) {
            self::FACTURA_ELECTRONICA => 'Factura Electrónica',
            self::FACTURA_EXENTA => 'Factura Exenta',
            self::LIQUIDACION_FACTURA => 'Liquidación-Factura',
            self::BOLETA_AFECTA => 'Boleta Electrónica',
            self::BOLETA_EXENTA => 'Boleta Exenta',
            self::NOTA_CREDITO => 'Nota de Crédito',
            self::NOTA_DEBITO => 'Nota de Débito',
            self::GUIA_DESPACHO => 'Guía de Despacho',
        };
    }

    public function shortLabel(): string
    {
        return match($this) {
            self::FACTURA_ELECTRONICA => 'Factura',
            self::FACTURA_EXENTA => 'Factura Exenta',
            self::LIQUIDACION_FACTURA => 'Liq-Factura',
            self::BOLETA_AFECTA => 'Boleta',
            self::BOLETA_EXENTA => 'Boleta Exenta',
            self::NOTA_CREDITO => 'NC',
            self::NOTA_DEBITO => 'ND',
            self::GUIA_DESPACHO => 'Guía',
        };
    }

    /**
     * Indica si este tipo de DTE aplica impuesto afecto.
     */
    public function isTaxable(): bool
    {
        return in_array($this, [
            self::FACTURA_ELECTRONICA,
            self::BOLETA_AFECTA,
            self::NOTA_CREDITO, // NC de factura afecta
            self::NOTA_DEBITO,
        ]);
    }

    /**
     * Indica si este tipo es usado para consumidores finales (boletas).
     */
    public function isConsumerDocument(): bool
    {
        return in_array($this, [
            self::BOLETA_AFECTA,
            self::BOLETA_EXENTA,
        ]);
    }

    /**
     * Indica si este tipo es una nota (crédito o débito).
     */
    public function isNote(): bool
    {
        return in_array($this, [
            self::NOTA_CREDITO,
            self::NOTA_DEBITO,
        ]);
    }

    /**
     * Obtiene la tasa tributaria desde los snapshots históricos del Order.
     *
     * IMPORTANTE:
     * No existe una tasa universal hardcodeada. La tasa se toma desde
     * order_items.tax_rate_snapshot, que fue calculada al momento de crear
     * el item usando la configuración vigente en la tabla taxes.
     *
     * Esto garantiza que:
     * - Si el impuesto cambia en el futuro, los DTEs antiguos mantienen su tasa histórica.
     * - No hay que modificar código cuando cambia una tasa fiscal.
     * - El documento fiscal refleja exactamente lo cobrado.
     *
     * @return float Tasa en formato decimal: 19.0000% → 0.19, 21.0000% → 0.21
     */
    public function taxRateFromOrder(Order $order): float
    {
        if (!$this->isTaxable()) {
            return 0.0;
        }

        $items = $order->relationLoaded('items')
            ? $order->items
            : $order->items()->get();

        $taxableItem = $items->first(function ($item) {
            return (float) ($item->tax_rate_snapshot ?? 0) > 0;
        });

        if (!$taxableItem) {
            return 0.0;
        }

        return round(((float) $taxableItem->tax_rate_snapshot) / 100, 6);
    }

    /**
     * @deprecated No usar para emisión fiscal.
     *
     * No existe una tasa universal en el sistema. La tasa debe obtenerse
     * desde taxRateFromOrder(), usando los snapshots históricos de la orden.
     *
     * Se conserva este método por compatibilidad, pero retorna 0.0 para evitar
     * asumir una tasa fija hardcodeada.
     */
    public function taxRate(): float
    {
        return 0.0;
    }

    /**
     * Obtiene el tipo de DTE por defecto para una orden según el RUT del receptor.
     * - Sin RUT o público general: Boleta Afecta (39)
     * - Con RUT empresa: Factura (33)
     */
    public static function defaultForOrder(?string $receiverRut, bool $hasExemptItems = false): self
    {
        if (empty($receiverRut)) {
            return $hasExemptItems ? self::BOLETA_EXENTA : self::BOLETA_AFECTA;
        }

        return $hasExemptItems ? self::FACTURA_EXENTA : self::FACTURA_ELECTRONICA;
    }
}
