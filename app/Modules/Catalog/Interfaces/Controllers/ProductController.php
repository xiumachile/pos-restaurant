<?php

namespace Modules\Catalog\Interfaces\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Catalog\Domain\Entities\Product;

class ProductController extends Controller
{
    /**
     * GET /api/v1/catalog/products
     */
    public function index(Request $request): JsonResponse
    {
        $products = Product::query()
            ->with('category')
            ->when($request->filled('category_id'), fn($q) => $q->where('category_id', $request->input('category_id')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->input('search');
                $q->where(function ($sub) use ($search) {
                    $sub->where('sku', 'like', "%{$search}%")
                        ->orWhereRaw("name_translations->>'$.es' LIKE ?", ["%{$search}%"])
                        ->orWhereRaw("name_translations->>'$.zh' LIKE ?", ["%{$search}%"]);
                });
            })
            ->when($request->has('active_only'), fn($q) => $q->where('is_active', true))
            ->orderBy('name_translations->es')
            ->get();

        return response()->json(['data' => $products]);
    }

    /**
     * GET /api/v1/catalog/products/{uuid}
     */
    public function show(string $uuid): JsonResponse
    {
        $product = Product::with('category')
            ->where('uuid', $uuid)
            ->firstOrFail();

        return response()->json(['data' => $product]);
    }
}
