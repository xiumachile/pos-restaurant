<?php

namespace Modules\Catalog\Interfaces\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Modules\Catalog\Application\UseCases\SetComboItemSubstitutionPolicy;
use Modules\Catalog\Domain\Entities\Category;
use Modules\Catalog\Domain\Entities\MenuItem;
use Modules\Catalog\Domain\Entities\MenuItemProduct;
use Modules\Catalog\Domain\Entities\MenuItemReplacementRule;
use Modules\Catalog\Domain\Entities\Product;
use Modules\Catalog\Interfaces\Requests\SetSubstitutionPolicyRequest;
use Modules\Branches\Domain\Entities\Branch;

/**
 * API REST para configurar políticas de sustitución en combos.
 *
 * Endpoints:
 * - GET    /{menuItemUuid}/substitution-policies
 * - PUT    /{menuItemUuid}/items/{productUuid}/substitution-policy
 * - DELETE /{menuItemUuid}/items/{productUuid}/substitution-policy
 */
class ComboReplacementRuleController extends Controller
{
    public function __construct(
        protected SetComboItemSubstitutionPolicy $setPolicyUseCase
    ) {}

    /**
     * GET /{menuItemUuid}/substitution-policies
     *
     * Lista las políticas efectivas (resueltas por jerarquía sucursal > empresa)
     * para cada producto del combo.
     */
    public function index(string $menuItemUuid): JsonResponse
    {
        // Sin global scopes: admin/manager pueden ver cualquier combo
        $menuItem = MenuItem::withoutGlobalScopes()
            ->where('uuid', $menuItemUuid)
            ->with('components.product')
            ->firstOrFail();

        $items = $menuItem->components->map(function (MenuItemProduct $component) use ($menuItem) {
            // Cargar el producto explícitamente si el eager load falló
            $product = $component->product;
            if (!$product) {
                $product = Product::withoutGlobalScopes()->find($component->product_id);
            }

            if (!$product) {
                return null; // Skip productos sin relación
            }

            $policy = $this->resolveEffectivePolicy($menuItem, $component);

            return [
                'product_id' => $product->uuid,
                'product_name' => $product->name_translations['es']
                    ?? $product->name_translations['zh']
                    ?? 'N/A',
                'quantity' => $component->quantity,
                'mode' => $policy['mode'],
                'allowed_category' => $policy['allowed_category'],
                'max_price_delta' => $policy['max_price_delta'],
                'requires_authorization' => $policy['requires_authorization'],
                'scope' => $policy['scope'],
            ];
        })->filter()->values();

        return response()->json([
            'success' => true,
            'data' => [
                'menu_item_id' => $menuItem->uuid,
                'items' => $items,
            ],
        ]);
    }

    /**
     * PUT /{menuItemUuid}/items/{productUuid}/substitution-policy
     */
    public function update(
        SetSubstitutionPolicyRequest $request,
        string $menuItemUuid,
        string $productUuid
    ): JsonResponse {
        $menuItem = MenuItem::withoutGlobalScopes()
            ->where('uuid', $menuItemUuid)
            ->firstOrFail();
        $targetProduct = Product::withoutGlobalScopes()
            ->where('uuid', $productUuid)
            ->firstOrFail();

        try {
            $allowedCategoryUuid = $request->input('allowed_category_id');
            $allowedCategoryId = null;
            $categoryName = null;

            if ($allowedCategoryUuid) {
                $category = Category::withoutGlobalScopes()
                    ->where('uuid', $allowedCategoryUuid)
                    ->firstOrFail();
                $allowedCategoryId = $category->id;
                $categoryName = $category->name_translations['es'] ?? 'N/A';
            }

            $branchUuid = $request->input('branch_id');
            $branchId = null;
            if ($branchUuid) {
                $branch = Branch::withoutGlobalScopes()
                    ->where('uuid', $branchUuid)
                    ->firstOrFail();
                $branchId = $branch->id;
            }

            $this->setPolicyUseCase->execute(
                menuItem: $menuItem,
                targetProduct: $targetProduct,
                mode: $request->input('mode'),
                allowedCategoryId: $allowedCategoryId,
                branchId: $branchId,
                maxPriceDelta: $request->input('max_price_delta'),
                requiresAuthorization: $request->boolean('requires_authorization', false),
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'menu_item_id' => $menuItem->uuid,
                    'product_id' => $targetProduct->uuid,
                    'mode' => $request->input('mode'),
                    'is_substitutable' => $request->input('mode') !== SetComboItemSubstitutionPolicy::MODE_NO_SUBSTITUTION,
                    'allowed_category' => $allowedCategoryUuid ? [
                        'id' => $allowedCategoryUuid,
                        'name' => $categoryName,
                    ] : null,
                    'branch_id' => $branchUuid,
                    'max_price_delta' => $request->input('max_price_delta'),
                    'requires_authorization' => $request->boolean('requires_authorization', false),
                    'updated_by' => auth()->user()?->name ?? 'System',
                    'updated_at' => now()->toIso8601String(),
                ],
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * DELETE /{menuItemUuid}/items/{productUuid}/substitution-policy
     */
    public function destroy(
        Request $request,
        string $menuItemUuid,
        string $productUuid
    ): JsonResponse {
        $menuItem = MenuItem::withoutGlobalScopes()
            ->where('uuid', $menuItemUuid)
            ->firstOrFail();
        $targetProduct = Product::withoutGlobalScopes()
            ->where('uuid', $productUuid)
            ->firstOrFail();

        $branchUuid = $request->input('branch_id');
        $branchId = null;
        if ($branchUuid) {
            $branch = Branch::withoutGlobalScopes()
                ->where('uuid', $branchUuid)
                ->firstOrFail();
            $branchId = $branch->id;
        }

        $deactivated = MenuItemReplacementRule::withoutGlobalScopes()
            ->where('menu_item_id', $menuItem->id)
            ->where('target_product_id', $targetProduct->id)
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'data' => [
                'message' => $deactivated > 0
                    ? "Override de sucursal eliminado. Ahora se aplica la política de empresa."
                    : "No había override de sucursal. La política de empresa ya estaba activa.",
                'deactivated_rules' => $deactivated,
            ],
        ]);
    }

    /**
     * Resuelve la política efectiva aplicando jerarquía sucursal > empresa.
     */
    private function resolveEffectivePolicy(MenuItem $menuItem, MenuItemProduct $component): array
    {
        $branchRule = MenuItemReplacementRule::withoutGlobalScopes()
            ->where('menu_item_id', $menuItem->id)
            ->where('target_product_id', $component->product_id)
            ->where('branch_id', $menuItem->branch_id)
            ->where('is_active', true)
            ->first();

        if ($branchRule) {
            return $this->ruleToPolicy($branchRule, 'branch');
        }

        $companyRule = MenuItemReplacementRule::withoutGlobalScopes()
            ->where('menu_item_id', $menuItem->id)
            ->where('target_product_id', $component->product_id)
            ->whereNull('branch_id')
            ->where('is_active', true)
            ->first();

        if ($companyRule) {
            return $this->ruleToPolicy($companyRule, 'company');
        }

        return [
            'mode' => $component->is_substitutable ? null : SetComboItemSubstitutionPolicy::MODE_NO_SUBSTITUTION,
            'allowed_category' => null,
            'max_price_delta' => null,
            'requires_authorization' => false,
            'scope' => 'none',
        ];
    }

    private function ruleToPolicy(MenuItemReplacementRule $rule, string $scope): array
    {
        $mode = match ($rule->rule_type) {
            MenuItemReplacementRule::RULE_TYPE_ANY => SetComboItemSubstitutionPolicy::MODE_ANY_PRODUCT,
            MenuItemReplacementRule::RULE_TYPE_CATEGORY => SetComboItemSubstitutionPolicy::MODE_ALLOWED_CATEGORY,
            default => $rule->rule_type,
        };

        $allowedCategory = null;
        if ($mode === SetComboItemSubstitutionPolicy::MODE_ALLOWED_CATEGORY && $rule->allowed_category_id) {
            $category = Category::withoutGlobalScopes()->find($rule->allowed_category_id);
            if ($category) {
                $allowedCategory = [
                    'id' => $category->uuid,
                    'name' => $category->name_translations['es'] ?? 'N/A',
                ];
            }
        }

        return [
            'mode' => $mode,
            'allowed_category' => $allowedCategory,
            'max_price_delta' => $rule->max_price_delta,
            'requires_authorization' => $rule->requires_authorization,
            'scope' => $scope,
        ];
    }
}
