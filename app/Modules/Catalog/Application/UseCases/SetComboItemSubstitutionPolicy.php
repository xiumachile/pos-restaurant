<?php

namespace Modules\Catalog\Application\UseCases;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Modules\Audit\Domain\Services\AuditService;
use Modules\Catalog\Domain\Entities\Category;
use Modules\Catalog\Domain\Entities\MenuItem;
use Modules\Catalog\Domain\Entities\MenuItemProduct;
use Modules\Catalog\Domain\Entities\MenuItemReplacementRule;
use Modules\Catalog\Domain\Entities\Product;

/**
 * Caso de uso de alto nivel para configurar la política de sustitución
 * de un producto dentro de un combo en una sola operación atómica.
 *
 * Modos soportados:
 *  - no_substitution:  is_substitutable = false (sin regla activa)
 *  - any_product:      is_substitutable = true + regla tipo any_product
 *  - allowed_category: is_substitutable = true + regla tipo allowed_category
 */
class SetComboItemSubstitutionPolicy
{
    public const MODE_NO_SUBSTITUTION = 'no_substitution';
    public const MODE_ANY_PRODUCT = 'any_product';
    public const MODE_ALLOWED_CATEGORY = 'allowed_category';

    public const ALLOWED_MODES = [
        self::MODE_NO_SUBSTITUTION,
        self::MODE_ANY_PRODUCT,
        self::MODE_ALLOWED_CATEGORY,
    ];

    public function __construct(
        protected AuditService $auditService
    ) {}

    public function execute(
        MenuItem $menuItem,
        Product $targetProduct,
        string $mode,
        ?int $allowedCategoryId = null,
        ?int $branchId = null,
        ?float $maxPriceDelta = null,
        bool $requiresAuthorization = false,
    ): ?MenuItemReplacementRule {
        $this->validateMode($mode);

        $menuItemProduct = $this->findComponent($menuItem, $targetProduct);

        $category = null;
        if ($mode === self::MODE_ALLOWED_CATEGORY) {
            $category = $this->validateCategory($allowedCategoryId, $menuItem->company_id);
        }

        $this->validateConsistency($mode, $allowedCategoryId, $maxPriceDelta, $requiresAuthorization);

        return DB::transaction(function () use (
            $menuItem,
            $targetProduct,
            $menuItemProduct,
            $mode,
            $allowedCategoryId,
            $branchId,
            $maxPriceDelta,
            $requiresAuthorization,
        ) {
            $previousState = $this->captureCurrentState($menuItem, $targetProduct, $branchId);

            $this->deactivatePreviousRules($menuItem->id, $targetProduct->id, $branchId);

            $newRule = match ($mode) {
                self::MODE_NO_SUBSTITUTION => $this->applyNoSubstitution($menuItemProduct),
                self::MODE_ANY_PRODUCT => $this->applyAnyProduct(
                    $menuItem, $targetProduct, $branchId, $maxPriceDelta, $requiresAuthorization
                ),
                self::MODE_ALLOWED_CATEGORY => $this->applyAllowedCategory(
                    $menuItem, $targetProduct, $branchId, $allowedCategoryId, $maxPriceDelta, $requiresAuthorization
                ),
            };

            // Refrescar el componente para asegurar que tiene los datos actualizados de BD
            $menuItemProduct->refresh();

            // Construir estado directamente desde el objeto en memoria (evita problemas de timing)
            $newState = $this->buildStateFromRule($newRule, $menuItemProduct);
            $this->logAudit($menuItem, $targetProduct, $branchId, $previousState, $newState);

            return $newRule;
        });
    }

    private function validateMode(string $mode): void
    {
        if (!in_array($mode, self::ALLOWED_MODES, true)) {
            throw new InvalidArgumentException(
                "Modo inválido: {$mode}. Debe ser uno de: " . implode(', ', self::ALLOWED_MODES)
            );
        }
    }

    private function findComponent(MenuItem $menuItem, Product $targetProduct): MenuItemProduct
    {
        $component = MenuItemProduct::withoutGlobalScopes()
            ->where('menu_item_id', $menuItem->id)
            ->where('product_id', $targetProduct->id)
            ->first();

        if (!$component) {
            throw new InvalidArgumentException(
                "El producto '{$targetProduct->id}' no forma parte del combo '{$menuItem->id}'."
            );
        }

        return $component;
    }

    private function validateCategory(?int $allowedCategoryId, int $companyId): Category
    {
        if ($allowedCategoryId === null) {
            throw new InvalidArgumentException(
                "El modo 'allowed_category' requiere allowed_category_id."
            );
        }

        $category = Category::withoutGlobalScopes()
            ->where('id', $allowedCategoryId)
            ->where('company_id', $companyId)
            ->first();

        if (!$category) {
            throw new InvalidArgumentException(
                "La categoría {$allowedCategoryId} no existe o no pertenece a la empresa."
            );
        }

        return $category;
    }

    private function validateConsistency(
        string $mode,
        ?int $allowedCategoryId,
        ?float $maxPriceDelta,
        bool $requiresAuthorization,
    ): void {
        if ($mode === self::MODE_NO_SUBSTITUTION) {
            if ($allowedCategoryId !== null || $maxPriceDelta !== null || $requiresAuthorization) {
                throw new InvalidArgumentException(
                    "El modo 'no_substitution' no acepta allowed_category_id, max_price_delta ni requires_authorization."
                );
            }
        }

        if ($maxPriceDelta !== null && $maxPriceDelta < 0) {
            throw new InvalidArgumentException("max_price_delta no puede ser negativo.");
        }
    }

    private function deactivatePreviousRules(int $menuItemId, int $targetProductId, ?int $branchId): int
    {
        $query = MenuItemReplacementRule::withoutGlobalScopes()
            ->where('menu_item_id', $menuItemId)
            ->where('target_product_id', $targetProductId)
            ->where('is_active', true);

        if ($branchId === null) {
            $query->whereNull('branch_id');
        } else {
            $query->where('branch_id', $branchId);
        }

        return $query->update(['is_active' => false]);
    }

    private function applyNoSubstitution(MenuItemProduct $menuItemProduct): ?MenuItemReplacementRule
    {
        $menuItemProduct->is_substitutable = false;
        $menuItemProduct->save();
        $menuItemProduct->refresh();

        return null;
    }

    private function applyAnyProduct(
        MenuItem $menuItem,
        Product $targetProduct,
        ?int $branchId,
        ?float $maxPriceDelta,
        bool $requiresAuthorization,
    ): MenuItemReplacementRule {
        $menuItemProduct = MenuItemProduct::withoutGlobalScopes()
            ->where('menu_item_id', $menuItem->id)
            ->where('product_id', $targetProduct->id)
            ->first();

        $menuItemProduct->is_substitutable = true;
        $menuItemProduct->save();
        $menuItemProduct->refresh();

        return MenuItemReplacementRule::create([
            'company_id' => $menuItem->company_id,
            'branch_id' => $branchId,
            'menu_item_id' => $menuItem->id,
            'target_product_id' => $targetProduct->id,
            'rule_type' => MenuItemReplacementRule::RULE_TYPE_ANY,
            'max_price_delta' => $maxPriceDelta,
            'requires_authorization' => $requiresAuthorization,
            'priority' => 1,
            'is_active' => true,
        ]);
    }

    private function applyAllowedCategory(
        MenuItem $menuItem,
        Product $targetProduct,
        ?int $branchId,
        int $allowedCategoryId,
        ?float $maxPriceDelta,
        bool $requiresAuthorization,
    ): MenuItemReplacementRule {
        $menuItemProduct = MenuItemProduct::withoutGlobalScopes()
            ->where('menu_item_id', $menuItem->id)
            ->where('product_id', $targetProduct->id)
            ->first();

        $menuItemProduct->is_substitutable = true;
        $menuItemProduct->save();
        $menuItemProduct->refresh();

        return MenuItemReplacementRule::create([
            'company_id' => $menuItem->company_id,
            'branch_id' => $branchId,
            'menu_item_id' => $menuItem->id,
            'target_product_id' => $targetProduct->id,
            'rule_type' => MenuItemReplacementRule::RULE_TYPE_CATEGORY,
            'allowed_category_id' => $allowedCategoryId,
            'max_price_delta' => $maxPriceDelta,
            'requires_authorization' => $requiresAuthorization,
            'priority' => 1,
            'is_active' => true,
        ]);
    }

    private function captureCurrentState(MenuItem $menuItem, Product $targetProduct, ?int $branchId): array
    {
        $component = MenuItemProduct::withoutGlobalScopes()
            ->where('menu_item_id', $menuItem->id)
            ->where('product_id', $targetProduct->id)
            ->first();

        $query = MenuItemReplacementRule::withoutGlobalScopes()
            ->where('menu_item_id', $menuItem->id)
            ->where('target_product_id', $targetProduct->id)
            ->where('is_active', true);

        if ($branchId === null) {
            $query->whereNull('branch_id');
        } else {
            $query->where('branch_id', $branchId);
        }

        $activeRule = $query->first();

        return [
            'is_substitutable' => $component?->is_substitutable,
            'mode' => $this->ruleToMode($activeRule, $component),
            'rule_type' => $activeRule?->rule_type,
            'allowed_category_id' => $activeRule?->allowed_category_id,
            'max_price_delta' => $activeRule?->max_price_delta,
        ];
    }

    private function ruleToMode(?MenuItemReplacementRule $rule, ?MenuItemProduct $component): ?string
    {
        if ($component !== null && !$component->is_substitutable) {
            return self::MODE_NO_SUBSTITUTION;
        }

        if (!$rule) {
            return null;
        }

        return match ($rule->rule_type) {
            MenuItemReplacementRule::RULE_TYPE_ANY => self::MODE_ANY_PRODUCT,
            MenuItemReplacementRule::RULE_TYPE_CATEGORY => self::MODE_ALLOWED_CATEGORY,
            default => $rule->rule_type,
        };
    }

    /**
     * Construye el estado directamente desde el objeto en memoria.
     * Evita problemas de timing en transacciones donde la query
     * no encuentra el registro recién creado.
     */
    private function buildStateFromRule(?MenuItemReplacementRule $rule, MenuItemProduct $component): array
    {
        return [
            'is_substitutable' => $component->is_substitutable,
            'mode' => $this->ruleToMode($rule, $component),
            'rule_type' => $rule?->rule_type,
            'allowed_category_id' => $rule?->allowed_category_id,
            'max_price_delta' => $rule?->max_price_delta,
        ];
    }

    private function logAudit(
        MenuItem $menuItem,
        Product $targetProduct,
        ?int $branchId,
        array $previousState,
        array $newState,
    ): void {
        try {
            $this->auditService->log(
                action: 'combo_substitution_policy_changed',
                entityType: MenuItem::class,
                entityId: $menuItem->id,
                entityUuid: $menuItem->uuid ?? null,
                payload: [
                    'menu_item_id' => $menuItem->id,
                    'target_product_id' => $targetProduct->id,
                    'branch_id' => $branchId,
                    'scope' => $branchId === null ? 'company' : 'branch',
                ],
                changes: [
                    'before' => $previousState,
                    'after' => $newState,
                ],
                reason: 'Configuración de política de sustitución de combo'
            );
        } catch (\Throwable $e) {
            Log::warning('AuditService failed for combo_substitution_policy_changed', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
