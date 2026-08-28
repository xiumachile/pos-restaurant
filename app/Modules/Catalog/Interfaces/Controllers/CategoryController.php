<?php

namespace Modules\Catalog\Interfaces\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Catalog\Domain\Entities\Category;
use Modules\Catalog\Domain\Services\CategoryManagementService;
use Modules\Catalog\Interfaces\Requests\CreateCategoryRequest;
use Modules\Catalog\Interfaces\Requests\UpdateCategoryRequest;

/**
 * Controller para gestión de categorías.
 *
 * Refactorizado en S5: toda la lógica de negocio delegada a
 * CategoryManagementService.
 *
 * Este controller solo orquesta HTTP:
 * - valida inputs mediante Form Requests
 * - delega al servicio de dominio
 * - retorna respuestas JSON
 */
class CategoryController extends Controller
{
    public function __construct(
        private CategoryManagementService $categoryService
    ) {
    }

    /**
     * GET /api/v1/catalog/categories
     */
    public function index(Request $request): JsonResponse
    {
        $activeOnly = $request->has('active_only') ? true : null;

        $categories = $this->categoryService->listCategories(
            $activeOnly
        );

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
        try {
            $category = $this->categoryService->createCategory(
                $request->validated(),
                $request->user()
            );

            return response()->json([
                'success' => true,
                'data' => $category->load('parent'),
            ], 201);
        } catch (\DomainException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * PUT /api/v1/catalog/categories/{uuid}
     */
    public function update(
        UpdateCategoryRequest $request,
        string $uuid
    ): JsonResponse {
        $category = Category::where(
            'uuid',
            $uuid
        )->firstOrFail();

        try {
            $updatedCategory = $this->categoryService->updateCategory(
                $category,
                $request->validated()
            );

            return response()->json([
                'success' => true,
                'data' => $updatedCategory,
            ]);
        } catch (\DomainException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * DELETE /api/v1/catalog/categories/{uuid}
     */
    public function destroy(string $uuid): JsonResponse
    {
        $category = Category::where(
            'uuid',
            $uuid
        )->firstOrFail();

        try {
            $this->categoryService->deleteCategory($category);

            return response()->json([
                'success' => true,
                'message' => 'Categoría eliminada correctamente.',
            ]);
        } catch (\DomainException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }
}
