<?php

namespace Modules\Fiscal\Domain\ValueObjects;

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
     * Indica si este tipo de DTE aplica IVA (19%).
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
     * Obtiene la tasa de IVA aplicable (0.19 para afectos, 0 para exentos).
     */
    public function taxRate(): float
    {
        return $this->isTaxable() ? 0.19 : 0.0;
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
