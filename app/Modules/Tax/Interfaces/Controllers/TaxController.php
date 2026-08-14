<?php

namespace Modules\Tax\Interfaces\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Tax\Domain\Entities\Tax;
use Modules\Tax\Interfaces\Requests\CreateTaxRequest;
use Modules\Tax\Interfaces\Requests\UpdateTaxRequest;
use Modules\Tax\Interfaces\Resources\TaxResource;

class TaxController extends Controller
{
    /**
     * GET /api/v1/taxes
     * Lista todos los impuestos de la empresa.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Tax::where('company_id', $user->company_id)
            ->withCount(['products', 'categories'])
            ->ordered();

        if ($request->boolean('active_only')) {
            $query->active();
        }

        $taxes = $query->get();

        return TaxResource::collection($taxes)->response();
    }

    /**
     * POST /api/v1/taxes
     * Crea un nuevo impuesto.
     */
    public function store(CreateTaxRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $tax = Tax::create(array_merge($validated, [
            'company_id' => $user->company_id,
        ]));

        // Si se marcó como default, desmarcar otros
        if ($validated['is_default'] ?? false) {
            $tax->markAsDefault();
        }

        $tax->loadCount(['products', 'categories']);

        return TaxResource::make($tax)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /api/v1/taxes/{uuid}
     * Detalle de un impuesto.
     */
    public function show(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();

        $tax = Tax::where('uuid', $uuid)
            ->where('company_id', $user->company_id)
            ->withCount(['products', 'categories'])
            ->firstOrFail();

        return TaxResource::make($tax)->response();
    }

    /**
     * PATCH /api/v1/taxes/{uuid}
     * Actualiza un impuesto.
     */
    public function update(UpdateTaxRequest $request, string $uuid): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $tax = Tax::where('uuid', $uuid)
            ->where('company_id', $user->company_id)
            ->firstOrFail();

        $tax->update($validated);

        // Si se marcó como default, desmarcar otros
        if ($validated['is_default'] ?? false) {
            $tax->markAsDefault();
        }

        $tax->refresh();
        $tax->loadCount(['products', 'categories']);

        return TaxResource::make($tax)->response();
    }

    /**
     * DELETE /api/v1/taxes/{uuid}
     * Elimina un impuesto (soft delete).
     */
    public function destroy(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();

        $tax = Tax::where('uuid', $uuid)
            ->where('company_id', $user->company_id)
            ->firstOrFail();

        // No permitir eliminar si está en uso
        $productsCount = $tax->products()->count();
        $categoriesCount = $tax->categories()->count();

        if ($productsCount > 0 || $categoriesCount > 0) {
            return response()->json([
                'success' => false,
                'message' => "No se puede eliminar: {$productsCount} productos y {$categoriesCount} categorías usan este impuesto. Reasígnelos primero.",
            ], 422);
        }

        $tax->delete();

        return response()->json([
            'success' => true,
            'message' => 'Impuesto eliminado correctamente.',
        ]);
    }

    /**
     * POST /api/v1/taxes/{uuid}/mark-default
     * Marca un impuesto como el default de la empresa.
     */
    public function markDefault(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();

        $tax = Tax::where('uuid', $uuid)
            ->where('company_id', $user->company_id)
            ->firstOrFail();

        $tax->markAsDefault();
        $tax->refresh();
        $tax->loadCount(['products', 'categories']);

        return TaxResource::make($tax)->response();
    }
}
