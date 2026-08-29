<?php

namespace Modules\Catalog\Interfaces\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Catalog\Domain\Entities\Category;
use Modules\Catalog\Domain\Entities\Product;
use Modules\Catalog\Interfaces\Requests\CreateProductRequest;
use Modules\Catalog\Interfaces\Requests\UpdateProductRequest;
use Modules\Orders\Domain\Entities\OrderItem;

class ProductController extends Controller
{
    /**
     * GET /api/v1/catalog/products
     */
    public function index(Request $request): JsonResponse
    {
        $products = Product::query()
            ->with(['category', 'menuItem'])
            ->when($request->filled('category_id'), fn($q) => $q->where('category_id', $request->input('category_id')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->input('search');
                $q->where(function ($sub) use ($search) {
                    $sub->where('sku', 'ilike', "%{$search}%")
                        ->orWhereRaw("name_translations->>'es' ILIKE ?", ["%{$search}%"])
                        ->orWhereRaw("name_translations->>'zh' ILIKE ?", ["%{$search}%"]);
                });
            })
            ->when($request->has('active_only'), fn($q) => $q->where('is_active', true))
            ->orderBy('name_translations->es')
            ->get();

        // Transformar para incluir menu_item_uuid en cada producto
        $data = $products->map(function ($product) {
            $arr = $product->toArray();
            $arr['menu_item_uuid'] = $product->menuItem?->uuid;
            return $arr;
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * GET /api/v1/catalog/products/{uuid}
     */
    public function show(string $uuid): JsonResponse
    {
        $product = Product::with(['category', 'menuItem'])
            ->where('uuid', $uuid)
            ->where('company_id', request()->user()->company_id)
            ->firstOrFail();

        $arr = $product->toArray();
        $arr['menu_item_uuid'] = $product->menuItem?->uuid;

        return response()->json([
            'success' => true,
            'data' => $arr,
        ]);
    }

    /**
     * POST /api/v1/catalog/products
     */
    public function store(CreateProductRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();

        // Resolver category_id desde UUID
        $category = Category::where('uuid', $data['category_id'])
            ->where('company_id', $user->company_id)
            ->firstOrFail();
        $data['category_id'] = $category->id;

        $data['company_id'] = $user->company_id;
        $data['branch_id'] = $user->branch_id;

        $product = Product::create($data);

        return response()->json([
            'success' => true,
            'data' => $product->load(['category', 'menuItem']),
        ], 201);
    }

    /**
     * PUT /api/v1/catalog/products/{uuid}
     */
    public function update(UpdateProductRequest $request, string $uuid): JsonResponse
    {
        $product = Product::where('uuid', $uuid)
            ->where('company_id', $request->user()->company_id)
            ->firstOrFail();
        $data = $request->validated();

        // Resolver category_id si viene
        if (isset($data['category_id'])) {
            $category = Category::where('uuid', $data['category_id'])
                ->where('company_id', $request->user()->company_id)
                ->firstOrFail();
            $data['category_id'] = $category->id;
        }

        $product->update($data);

        return response()->json([
            'success' => true,
            'data' => $product->fresh(['category', 'menuItem']),
        ]);
    }

    /**
     * DELETE /api/v1/catalog/products/{uuid}
     */
    public function destroy(string $uuid): JsonResponse
    {
        $product = Product::where('uuid', $uuid)
            ->where('company_id', request()->user()->company_id)
            ->firstOrFail();

        // Validar que no tenga órdenes activas
        $activeOrders = OrderItem::where('product_id', $product->id)
            ->whereHas('order', function ($q) {
                $q->whereIn('status', ['pending', 'confirmed', 'preparing', 'ready']);
            })
            ->exists();

        if ($activeOrders) {
            return response()->json([
                'success' => false,
                'error' => 'No se puede eliminar un producto con órdenes activas.',
            ], 422);
        }

        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Producto eliminado correctamente.',
        ]);
    }
}
