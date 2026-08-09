<?php

namespace Modules\Catalog\Application\UseCases;

use Modules\Catalog\Domain\Entities\MenuItem;
use Modules\Catalog\Domain\Entities\MenuItemProduct;
use Modules\Catalog\Domain\Entities\MenuItemReplacementRule;
use Modules\Catalog\Domain\Entities\Product;
use InvalidArgumentException;
use Modules\Catalog\Application\DTOs\SubstitutionValidationResult;

class ValidateComboSubstitution
{
    /**
     * Valida si un producto puede sustituir a otro en un combo y calcula el delta de precio.
     *
     * @param MenuItem $menuItem El combo
     * @param Product $originalProduct Producto original a reemplazar
     * @param Product $replacementProduct Producto de reemplazo
     * @param int $quantity Cantidad a sustituir
     * @return SubstitutionValidationResult
     */
    public function execute(
        MenuItem $menuItem,
        Product $originalProduct,
        Product $replacementProduct,
        int $quantity = 1
    ): SubstitutionValidationResult {
        // 1. Validar que el producto original está en el combo
        $component = $menuItem->components()
            ->where('product_id', $originalProduct->id)
            ->first();

        if (!$component) {
            return SubstitutionValidationResult::denied(
                'original_product_not_in_combo',
                'El producto original no forma parte de este combo.'
            );
        }

        // 2. Validar que el producto original es sustituible
        if (!$component->is_substitutable) {
            return SubstitutionValidationResult::denied(
                'product_not_substitutable',
                'Este producto no puede ser sustituido en este combo.'
            );
        }

        // 3. Validar que la cantidad no excede la disponible en el combo
        if ($quantity > $component->quantity) {
            return SubstitutionValidationResult::denied(
                'quantity_exceeds_available',
                "La cantidad solicitada ({$quantity}) excede la disponible en el combo ({$component->quantity})."
            );
        }

        // 4. Validar que el producto de reemplazo está activo
        if (!$replacementProduct->is_active) {
            return SubstitutionValidationResult::denied(
                'replacement_product_inactive',
                'El producto de reemplazo no está disponible.'
            );
        }

        // 5. Validar que el producto de reemplazo es diferente al original
        if ($replacementProduct->id === $originalProduct->id) {
            return SubstitutionValidationResult::denied(
                'same_product',
                'El producto de reemplazo debe ser diferente al original.'
            );
        }

        // 6. Buscar reglas de sustitución aplicables
        $applicableRules = $this->getApplicableRules($menuItem, $originalProduct);

        // 7. Si no hay reglas, denegar por defecto
        if ($applicableRules->isEmpty()) {
            return SubstitutionValidationResult::denied(
                'no_rules_defined',
                'No hay reglas de sustitución definidas para este producto en este combo.'
            );
        }

        // 8. Validar si el reemplazo cumple con al menos una regla
        $matchedRule = $this->findMatchingRule($applicableRules, $replacementProduct);

        if (!$matchedRule) {
            return SubstitutionValidationResult::denied(
                'replacement_not_allowed_by_rules',
                'El producto de reemplazo no cumple con las reglas de sustitución definidas.'
            );
        }

        // 9. Calcular delta de precio
        $unitPriceDelta = max(0, (float) $replacementProduct->base_price - (float) $originalProduct->base_price);

        // 10. Validar max_price_delta si está definido
        if ($matchedRule->max_price_delta !== null && $unitPriceDelta > (float) $matchedRule->max_price_delta) {
            return SubstitutionValidationResult::denied(
                'exceeds_max_price_delta',
                "El recargo unitario ({$unitPriceDelta}) excede el máximo permitido ({$matchedRule->max_price_delta})."
            );
        }

        // 11. Calcular recargo total
        $totalExtraCharge = $unitPriceDelta * $quantity;

        // 12. Éxito
        return SubstitutionValidationResult::allowed(
            $unitPriceDelta,
            $totalExtraCharge,
            $matchedRule->requires_authorization,
            $matchedRule
        );
    }

    /**
     * Obtiene las reglas aplicables con jerarquía: sucursal > empresa.
     */
    private function getApplicableRules(MenuItem $menuItem, Product $originalProduct)
    {
        // Primero buscar reglas específicas de sucursal
        $branchRules = MenuItemReplacementRule::query()
            ->active()
            ->ordered()
            ->forBranch($menuItem->branch_id)
            ->forProduct($menuItem->id, $originalProduct->id)
            ->get();

        // Si hay reglas de sucursal, usar esas
        if ($branchRules->isNotEmpty()) {
            return $branchRules;
        }

        // Si no, buscar reglas globales de empresa
        return MenuItemReplacementRule::query()
            ->active()
            ->ordered()
            ->global()
            ->forCompany($menuItem->company_id)
            ->forProduct($menuItem->id, $originalProduct->id)
            ->get();
    }

    /**
     * Encuentra la primera regla que coincide con el producto de reemplazo.
     */
    private function findMatchingRule($rules, Product $replacementProduct): ?MenuItemReplacementRule
    {
        foreach ($rules as $rule) {
            if ($rule->matches($replacementProduct)) {
                return $rule;
            }
        }

        return null;
    }
}
