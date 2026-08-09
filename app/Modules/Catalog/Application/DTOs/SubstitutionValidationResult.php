<?php

namespace Modules\Catalog\Application\DTOs;

use Modules\Catalog\Domain\Entities\MenuItemReplacementRule;

class SubstitutionValidationResult
{
    private function __construct(
        public readonly bool $allowed,
        public readonly ?float $unitPriceDelta,
        public readonly ?float $totalExtraCharge,
        public readonly bool $requiresAuthorization,
        public readonly ?MenuItemReplacementRule $matchedRule,
        public readonly ?string $errorCode,
        public readonly ?string $errorMessage
    ) {
    }

    /**
     * Crea un resultado de validación exitoso.
     */
    public static function allowed(
        float $unitPriceDelta,
        float $totalExtraCharge,
        bool $requiresAuthorization,
        MenuItemReplacementRule $matchedRule
    ): self {
        return new self(
            allowed: true,
            unitPriceDelta: $unitPriceDelta,
            totalExtraCharge: $totalExtraCharge,
            requiresAuthorization: $requiresAuthorization,
            matchedRule: $matchedRule,
            errorCode: null,
            errorMessage: null
        );
    }

    /**
     * Crea un resultado de validación fallido.
     */
    public static function denied(string $errorCode, string $errorMessage): self
    {
        return new self(
            allowed: false,
            unitPriceDelta: null,
            totalExtraCharge: null,
            requiresAuthorization: false,
            matchedRule: null,
            errorCode: $errorCode,
            errorMessage: $errorMessage
        );
    }

    /**
     * Verifica si la validación fue exitosa.
     */
    public function isAllowed(): bool
    {
        return $this->allowed;
    }

    /**
     * Verifica si requiere autorización (PIN de encargado).
     */
    public function needsAuthorization(): bool
    {
        return $this->requiresAuthorization;
    }

    /**
     * Obtiene el recargo total formateado.
     */
    public function formattedTotalExtraCharge(): string
    {
        if ($this->totalExtraCharge === null) {
            return '0';
        }

        return number_format($this->totalExtraCharge, 0, ',', '.');
    }
}
