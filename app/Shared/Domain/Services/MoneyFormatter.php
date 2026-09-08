<?php

namespace App\Shared\Domain\Services;

use Modules\Companies\Domain\Entities\Company;

/**
 * Servicio de formateo de montos monetarios.
 *
 * Formatea valores numéricos según la configuración de moneda de la empresa.
 * Stateless: no guarda estado, recibe Company como parámetro.
 *
 * Ver: docs/architecture/money-and-tax.md
 *
 * Ejemplos:
 * - CLP: 12990 → "12.990"
 * - USD: 12.99 → "12.99"
 * - EUR: 12.99 → "12,99"
 */
class MoneyFormatter
{
    /**
     * Formatea un monto según la configuración de moneda de la empresa.
     *
     * @param float|int $amount Monto a formatear
     * @param Company $company Empresa con configuración de moneda
     * @return string Monto formateado sin símbolo (ej: "12.990")
     */
    public function format(float|int $amount, Company $company): string
    {
        $config = $company->getCurrencyConfig();

        return number_format(
            (float) $amount,
            $config['decimals'],
            $config['decimal_separator'],
            $config['thousands_separator']
        );
    }

    /**
     * Formatea un monto con símbolo de moneda.
     *
     * @param float|int $amount Monto a formatear
     * @param Company $company Empresa con configuración de moneda
     * @return string Monto formateado con símbolo (ej: "$ 12.990")
     */
    public function formatWithSymbol(float|int $amount, Company $company): string
    {
        $config = $company->getCurrencyConfig();
        $formatted = $this->format($amount, $company);

        return $config['symbol'] . ' ' . $formatted;
    }

    /**
     * Parsea un string formateado a número float.
     *
     * Inverso de format(): convierte "$ 12.990" a 12990.0
     *
     * @param string $formatted Monto formateado
     * @param Company $company Empresa con configuración de moneda
     * @return float Monto numérico
     */
    public function parse(string $formatted, Company $company): float
    {
        $config = $company->getCurrencyConfig();

        // Remover símbolo si existe
        $cleaned = trim($formatted);
        if (str_starts_with($cleaned, $config['symbol'])) {
            $cleaned = trim(substr($cleaned, strlen($config['symbol'])));
        }

        // Remover separador de miles
        if (!empty($config['thousands_separator'])) {
            $cleaned = str_replace($config['thousands_separator'], '', $cleaned);
        }

        // Reemplazar separador decimal por punto
        if (!empty($config['decimal_separator']) && $config['decimal_separator'] !== '.') {
            $cleaned = str_replace($config['decimal_separator'], '.', $cleaned);
        }

        return (float) $cleaned;
    }

    /**
     * Obtiene configuración de moneda por defecto (Chile).
     *
     * Útil para contextos donde no hay Company disponible.
     *
     * @return array Configuración por defecto
     */
    public static function defaults(): array
    {
        return [
            'code' => 'CLP',
            'symbol' => '$',
            'decimals' => 0,
            'thousands_separator' => '.',
            'decimal_separator' => ',',
        ];
    }
}
