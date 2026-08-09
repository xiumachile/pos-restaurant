<?php

namespace Modules\Catalog\Interfaces\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Catalog\Application\UseCases\ValidateComboSubstitution;
use Modules\Catalog\Domain\Entities\MenuItem;
use Modules\Catalog\Domain\Entities\Product;

class ComboSubstitutionController extends Controller
{
    public function __construct(
        private ValidateComboSubstitution $validator
    ) {}

    /**
     * Valida si un producto puede sustituir a otro en un combo.
     *
     * POST /api/v1/catalog/combos/check-substitution
     */
    public function check(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'menu_item_uuid' => 'required|uuid',
            'original_product_uuid' => 'required|uuid',
            'replacement_product_uuid' => 'required|uuid',
            'quantity' => 'required|integer|min:1|max:100',
        ]);

        $menuItem = MenuItem::where('uuid', $validated['menu_item_uuid'])->firstOrFail();
        $originalProduct = Product::where('uuid', $validated['original_product_uuid'])->firstOrFail();
        $replacementProduct = Product::where('uuid', $validated['replacement_product_uuid'])->firstOrFail();

        $result = $this->validator->execute(
            $menuItem,
            $originalProduct,
            $replacementProduct,
            (int) $validated['quantity']
        );

        return response()->json([
            'allowed' => $result->isAllowed(),
            'unit_price_delta' => $result->unitPriceDelta,
            'total_extra_charge' => $result->totalExtraCharge,
            'requires_authorization' => $result->needsAuthorization(),
            'error_code' => $result->errorCode,
            'error_message' => $result->errorMessage,
        ], $result->isAllowed() ? 200 : 422);
    }
}
