<?php

namespace Modules\Catalog\Interfaces\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Catalog\Domain\Entities\Category;

class CategoryController extends Controller
{
    /**
     * GET /api/v1/catalog/categories
     */
    public function index(Request $request): JsonResponse
    {
        $categories = Category::query()
            ->when($request->has('active_only'), fn($q) => $q->where('is_active', true))
            ->orderBy('sort_order')
            ->orderBy('name_translations->es')
            ->get();

        return response()->json(['data' => $categories]);
    }

    /**
     * GET /api/v1/catalog/categories/{uuid}
     */
    public function show(string $uuid): JsonResponse
    {
        $category = Category::where('uuid', $uuid)->firstOrFail();
        return response()->json(['data' => $category]);
    }
}
