<?php

namespace Modules\Catalog\Interfaces\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Catalog\Domain\Entities\Category;
use Modules\Catalog\Interfaces\Requests\CreateCategoryRequest;
use Modules\Catalog\Interfaces\Requests\UpdateCategoryRequest;

class CategoryController extends Controller
{
    /**
     * GET /api/v1/catalog/categories
     */
    public function index(Request $request): JsonResponse
    {
        $categories = Category::query()
            ->with('parent')
            ->when($request->has('active_only'), fn($q) => $q->where('is_active', true))
            ->orderBy('sort_order')
            ->orderBy('name_translations->es')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    /**
     * GET /api/v1/catalog/categories/{uuid}
     */
    public function show(string $uuid): JsonResponse
    {
        $category = Category::with('parent')
            ->where('uuid', $uuid)
            ->firstOrFail();
        
        return response()->json([
            'success' => true,
            'data' => $category,
        ]);
    }

    /**
     * POST /api/v1/catalog/categories
     */
    public function store(CreateCategoryRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();

        // Resolver parent_id si viene
        $parentId = null;
        $depth = 0;
        
        if (!empty($data['parent_id'])) {
            $parent = Category::where('uuid', $data['parent_id'])->firstOrFail();
            
            // Validar profundidad máxima (2 niveles: raíz + subcategoría)
            if ($parent->depth >= 1) {
                return response()->json([
                    'success' => false,
                    'error' => 'No se permiten más de 2 niveles de categorías.',
                ], 422);
            }
            
            $parentId = $parent->id;
            $depth = $parent->depth + 1;
        }

        $category = Category::create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'parent_id' => $parentId,
            'depth' => $depth,
            'name_translations' => $data['name_translations'],
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
            'tax_id' => $data['tax_id'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'data' => $category->load('parent'),
        ], 201);
    }

    /**
     * PUT /api/v1/catalog/categories/{uuid}
     */
    public function update(UpdateCategoryRequest $request, string $uuid): JsonResponse
    {
        $category = Category::where('uuid', $uuid)->firstOrFail();
        $data = $request->validated();

        // Resolver parent_id si viene
        if (array_key_exists('parent_id', $data)) {
            if (!empty($data['parent_id'])) {
                // No permitir asignarse a sí mismo como padre
                if ($data['parent_id'] === $category->uuid) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Una categoría no puede ser su propio padre.',
                    ], 422);
                }

                $parent = Category::where('uuid', $data['parent_id'])->firstOrFail();
                
                // Validar profundidad máxima
                if ($parent->depth >= 1) {
                    return response()->json([
                        'success' => false,
                        'error' => 'No se permiten más de 2 niveles de categorías.',
                    ], 422);
                }

                $data['parent_id'] = $parent->id;
                $data['depth'] = $parent->depth + 1;
            } else {
                // parent_id = null (convertir a raíz)
                $data['parent_id'] = null;
                $data['depth'] = 0;
            }
        }

        $category->update($data);

        return response()->json([
            'success' => true,
            'data' => $category->fresh('parent'),
        ]);
    }

    /**
     * DELETE /api/v1/catalog/categories/{uuid}
     */
    public function destroy(string $uuid): JsonResponse
    {
        $category = Category::where('uuid', $uuid)->firstOrFail();

        // Validar que no tenga productos activos
        if ($category->products()->where('is_active', true)->exists()) {
            return response()->json([
                'success' => false,
                'error' => 'No se puede eliminar una categoría con productos activos.',
                'active_products_count' => $category->products()->where('is_active', true)->count(),
            ], 422);
        }

        // Validar que no tenga subcategorías
        if ($category->children()->exists()) {
            return response()->json([
                'success' => false,
                'error' => 'No se puede eliminar una categoría con subcategorías.',
                'children_count' => $category->children()->count(),
            ], 422);
        }

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Categoría eliminada correctamente.',
        ]);
    }
}
