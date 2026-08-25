<?php

namespace Modules\Catalog\Interfaces\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Catalog\Domain\Entities\Menu;
use Modules\Catalog\Domain\Entities\MenuActivation;
use Modules\Catalog\Domain\Entities\MenuProduct;
use Modules\Catalog\Domain\Entities\PriceList;
use Modules\Catalog\Domain\Entities\Product;
use Modules\Catalog\Domain\Services\MenuResolutionService;
use Modules\Catalog\Interfaces\Requests\AssignMenuProductsRequest;
use Modules\Catalog\Interfaces\Requests\CreateMenuRequest;
use Modules\Catalog\Interfaces\Requests\UpdateMenuRequest;
use Modules\Catalog\Interfaces\Requests\UpsertMenuActivationsRequest;

class MenuController extends Controller
{
    public function __construct(
        protected MenuResolutionService $resolutionService
    ) {}

    /**
     * GET /api/v1/catalog/menus
     */
    public function index(Request $request): JsonResponse
    {
        $menus = Menu::query()
            ->with(['priceList', 'activations'])
            ->withCount('menuProducts')
            ->when($request->has('active_only'), fn($q) => $q->where('is_active', true))
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $menus,
        ]);
    }

    /**
     * GET /api/v1/catalog/menus/{uuid}
     * Incluye los productos de la carta con sus precios resueltos.
     */
    public function show(string $uuid): JsonResponse
    {
        $menu = Menu::with(['priceList', 'activations'])->where('uuid', $uuid)->firstOrFail();

        $items = MenuProduct::where('menu_id', $menu->id)
            ->orderBy('position')
            ->get()
            ->map(function ($mp) use ($menu) {
                $product = Product::find($mp->product_id);
                if (!$product) {
                    return null;
                }
                return [
                    'menu_product_uuid' => $mp->uuid,
                    'product_uuid' => $product->uuid,
                    'name' => $product->name_translations['es'] ?? 'N/A',
                    'position' => $mp->position,
                    'is_available' => $mp->is_available,
                    'price' => $product->resolvePrice($menu->priceList),
                ];
            })
            ->filter()
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'menu' => $menu,
                'items' => $items,
            ],
        ]);
    }

    /**
     * GET /api/v1/catalog/menus/active?channel_type=dine_in
     * Disponible para todos los usuarios autenticados (meseros lo necesitan).
     * Resuelve automáticamente la carta según contexto.
     */
    public function active(Request $request): JsonResponse
    {
        $channelType = $request->input('channel_type', MenuActivation::CHANNEL_DINE_IN);
        $branchId = $request->user()->branch_id;

        $menu = $this->resolutionService->resolve($branchId, $channelType);

        if (!$menu) {
            return response()->json([
                'success' => false,
                'error' => 'No hay carta activa para este contexto. Configure una carta default en administración.',
            ], 404);
        }

        $items = MenuProduct::where('menu_id', $menu->id)
            ->where('is_available', true)
            ->orderBy('position')
            ->get()
            ->map(function ($mp) use ($menu) {
                $product = Product::find($mp->product_id);
                if (!$product || !$product->is_active) {
                    return null;
                }
                return [
                    'product_uuid' => $product->uuid,
                    'name' => $product->name_translations['es'] ?? 'N/A',
                    'category_id' => $product->category_id,
                    'position' => $mp->position,
                    'price' => $product->resolvePrice($menu->priceList),
                ];
            })
            ->filter()
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'menu' => $menu->load('priceList'),
                'items' => $items,
            ],
        ]);
    }

    /**
     * POST /api/v1/catalog/menus
     */
    public function store(CreateMenuRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();

        $priceList = PriceList::where('uuid', $data['price_list_id'])->firstOrFail();

        // Solo una carta default por sucursal
        if ($request->boolean('is_default')) {
            Menu::where('company_id', $user->company_id)
                ->where('branch_id', $user->branch_id)
                ->update(['is_default' => false]);
        }

        $menu = Menu::create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'price_list_id' => $priceList->id,
            'is_default' => $request->boolean('is_default'),
            'is_active' => $data['is_active'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'data' => $menu->load('priceList'),
        ], 201);
    }

    /**
     * PUT /api/v1/catalog/menus/{uuid}
     */
    public function update(UpdateMenuRequest $request, string $uuid): JsonResponse
    {
        $menu = Menu::where('uuid', $uuid)->firstOrFail();
        $data = $request->validated();
        $user = $request->user();

        if (isset($data['price_list_id'])) {
            $priceList = PriceList::where('uuid', $data['price_list_id'])->firstOrFail();
            $data['price_list_id'] = $priceList->id;
        }

        if ($request->boolean('is_default')) {
            Menu::where('company_id', $user->company_id)
                ->where('branch_id', $user->branch_id)
                ->where('id', '!=', $menu->id)
                ->update(['is_default' => false]);
        }

        $menu->update($data);

        return response()->json([
            'success' => true,
            'data' => $menu->fresh('priceList'),
        ]);
    }

    /**
     * DELETE /api/v1/catalog/menus/{uuid}
     */
    public function destroy(string $uuid): JsonResponse
    {
        $menu = Menu::where('uuid', $uuid)->firstOrFail();

        $menu->delete(); // cascade borra menu_products y menu_activations

        return response()->json([
            'success' => true,
            'message' => 'Carta eliminada correctamente.',
        ]);
    }

    /**
     * PUT /api/v1/catalog/menus/{uuid}/activations
     * Reemplaza todas las reglas de activación de la carta.
     */
    public function upsertActivations(UpsertMenuActivationsRequest $request, string $uuid): JsonResponse
    {
        $menu = Menu::where('uuid', $uuid)->firstOrFail();

        // Borrar reglas existentes y recrear (semántica de reemplazo)
        MenuActivation::where('menu_id', $menu->id)->delete();

        $created = [];
        foreach ($request->input('activations', []) as $act) {
            $created[] = MenuActivation::create([
                'menu_id' => $menu->id,
                'channel_type' => $act['channel_type'],
                'days_of_week' => $act['days_of_week'] ?? null,
                'time_from' => isset($act['time_from']) ? $act['time_from'] . ':00' : null,
                'time_to' => isset($act['time_to']) ? $act['time_to'] . ':00' : null,
                'priority' => $act['priority'] ?? 1,
                'is_active' => $act['is_active'] ?? true,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $created,
        ]);
    }

    /**
     * PUT /api/v1/catalog/menus/{uuid}/products
     * Asigna productos a la carta (upsert masivo).
     */
    public function assignProducts(AssignMenuProductsRequest $request, string $uuid): JsonResponse
    {
        $menu = Menu::where('uuid', $uuid)->firstOrFail();

        $assigned = [];
        foreach ($request->input('products', []) as $item) {
            $product = Product::where('uuid', $item['product_uuid'])->first();
            if (!$product) {
                continue; // skip productos inexistentes o de otro tenant
            }

            $mp = MenuProduct::updateOrCreate(
                ['menu_id' => $menu->id, 'product_id' => $product->id],
                [
                    'position' => $item['position'] ?? 0,
                    'is_available' => $item['is_available'] ?? true,
                ]
            );
            $assigned[] = $mp;
        }

        return response()->json([
            'success' => true,
            'data' => $assigned,
        ]);
    }
}
