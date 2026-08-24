<?php

namespace Modules\Catalog\Interfaces\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Catalog\Domain\Entities\PriceList;
use Modules\Catalog\Interfaces\Requests\CreatePriceListRequest;
use Modules\Catalog\Interfaces\Requests\UpdatePriceListRequest;

class PriceListController extends Controller
{
    /**
     * GET /api/v1/catalog/price-lists
     * Alimenta el desplegable de selección de precio en cartas/menús.
     */
    public function index(Request $request): JsonResponse
    {
        $lists = PriceList::query()
            ->when($request->has('active_only'), fn($q) => $q->where('is_active', true))
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $lists,
        ]);
    }

    /**
     * POST /api/v1/catalog/price-lists
     */
    public function store(CreatePriceListRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();

        // Solo una lista default por empresa/sucursal
        if ($request->boolean('is_default')) {
            PriceList::where('company_id', $user->company_id)
                ->where('branch_id', $user->branch_id)
                ->update(['is_default' => false]);
        }

        $list = PriceList::create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'name' => $data['name'],
            'display_name' => $data['display_name'] ?? $data['name'],
            'channel_type' => $data['channel_type'] ?? null,
            'currency' => $data['currency'] ?? 'CLP',
            'is_default' => $request->boolean('is_default'),
            'is_active' => $data['is_active'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'data' => $list,
        ], 201);
    }

    /**
     * PUT /api/v1/catalog/price-lists/{uuid}
     */
    public function update(UpdatePriceListRequest $request, string $uuid): JsonResponse
    {
        $list = PriceList::where('uuid', $uuid)->firstOrFail();
        $data = $request->validated();
        $user = $request->user();

        if ($request->boolean('is_default')) {
            PriceList::where('company_id', $user->company_id)
                ->where('branch_id', $user->branch_id)
                ->where('id', '!=', $list->id)
                ->update(['is_default' => false]);
        }

        $list->update($data);

        return response()->json([
            'success' => true,
            'data' => $list->fresh(),
        ]);
    }

    /**
     * DELETE /api/v1/catalog/price-lists/{uuid}
     */
    public function destroy(string $uuid): JsonResponse
    {
        $list = PriceList::where('uuid', $uuid)->firstOrFail();

        if ($list->prices()->exists()) {
            return response()->json([
                'success' => false,
                'error' => 'No se puede eliminar una lista de precios con productos asociados.',
                'products_count' => $list->prices()->count(),
            ], 422);
        }

        $list->delete();

        return response()->json([
            'success' => true,
            'message' => 'Lista de precios eliminada correctamente.',
        ]);
    }
}
