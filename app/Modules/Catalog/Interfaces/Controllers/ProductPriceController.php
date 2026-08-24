<?php

namespace Modules\Catalog\Interfaces\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Catalog\Domain\Entities\PriceList;
use Modules\Catalog\Domain\Entities\Product;
use Modules\Catalog\Domain\Entities\ProductPrice;
use Modules\Catalog\Interfaces\Requests\UpsertProductPricesRequest;

class ProductPriceController extends Controller
{
    /**
     * GET /api/v1/catalog/products/{uuid}/prices
     */
    public function index(string $uuid): JsonResponse
    {
        $product = Product::where('uuid', $uuid)->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $product->prices()->with('priceList')->get(),
        ]);
    }

    /**
     * PUT /api/v1/catalog/products/{uuid}/prices
     * Upsert masivo: actualiza o crea los precios enviados.
     */
    public function upsert(UpsertProductPricesRequest $request, string $uuid): JsonResponse
    {
        $product = Product::where('uuid', $uuid)->firstOrFail();

        foreach ($request->input('prices', []) as $item) {
            $list = PriceList::where('uuid', $item['price_list_id'])->firstOrFail();

            ProductPrice::updateOrCreate(
                ['product_id' => $product->id, 'price_list_id' => $list->id],
                ['price' => $item['price'], 'currency' => $list->currency]
            );
        }

        return response()->json([
            'success' => true,
            'data' => $product->prices()->with('priceList')->get(),
        ]);
    }

    /**
     * DELETE /api/v1/catalog/products/{productUuid}/prices/{priceListUuid}
     */
    public function destroy(string $productUuid, string $priceListUuid): JsonResponse
    {
        $product = Product::where('uuid', $productUuid)->firstOrFail();
        $list = PriceList::where('uuid', $priceListUuid)->firstOrFail();

        $deleted = ProductPrice::where('product_id', $product->id)
            ->where('price_list_id', $list->id)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => $deleted > 0
                ? 'Precio eliminado correctamente.'
                : 'El producto no tenía precio en esa lista.',
        ]);
    }
}
