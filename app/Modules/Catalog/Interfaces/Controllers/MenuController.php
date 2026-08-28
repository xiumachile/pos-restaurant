<?php

namespace Modules\Catalog\Interfaces\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Catalog\Domain\Entities\Menu;
use Modules\Catalog\Domain\Entities\MenuActivation;
use Modules\Catalog\Domain\Services\MenuManagementService;
use Modules\Catalog\Interfaces\Requests\AssignMenuProductsRequest;
use Modules\Catalog\Interfaces\Requests\CreateMenuRequest;
use Modules\Catalog\Interfaces\Requests\UpdateMenuRequest;
use Modules\Catalog\Interfaces\Requests\UpsertMenuActivationsRequest;

/**
 * Controller para gestión de cartas (menús).
 * 
 * Refactorizado en S3: toda la lógica de negocio delegada a MenuManagementService.
 * Este controller solo orquesta HTTP: valida inputs, delega al service, retorna JSON.
 */
class MenuController extends Controller
{
    public function __construct(
        protected MenuManagementService $menuService
    ) {}

    /**
     * GET /api/v1/catalog/menus
     */
    public function index(Request $request): JsonResponse
    {
        $activeOnly = $request->has('active_only') ? true : null;
        $menus = $this->menuService->listMenus($activeOnly);

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
        $data = $this->menuService->getMenuWithItems($menu);

        return response()->json([
            'success' => true,
            'data' => $data,
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

        $data = $this->menuService->getActiveMenuWithItems($branchId, $channelType);

        if (!$data) {
            return response()->json([
                'success' => false,
                'error' => 'No hay carta activa para este contexto. Configure una carta default en administración.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * POST /api/v1/catalog/menus
     */
    public function store(CreateMenuRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();
        $isDefault = $request->boolean('is_default');

        $menu = $this->menuService->createMenu($data, $user, $isDefault);

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
        $isDefault = $request->boolean('is_default');

        $updatedMenu = $this->menuService->updateMenu($menu, $data, $user, $isDefault);

        return response()->json([
            'success' => true,
            'data' => $updatedMenu,
        ]);
    }

    /**
     * DELETE /api/v1/catalog/menus/{uuid}
     */
    public function destroy(string $uuid): JsonResponse
    {
        $menu = Menu::where('uuid', $uuid)->firstOrFail();

        $this->menuService->deleteMenu($menu);

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
        $created = $this->menuService->replaceActivations($menu, $request->input('activations', []));

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
        $assigned = $this->menuService->assignProducts($menu, $request->input('products', []));

        return response()->json([
            'success' => true,
            'data' => $assigned,
        ]);
    }
}
