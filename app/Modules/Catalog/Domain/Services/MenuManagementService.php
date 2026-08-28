<?php

namespace Modules\Catalog\Domain\Services;

use Illuminate\Support\Collection;
use Modules\Catalog\Domain\Entities\Menu;
use Modules\Catalog\Domain\Entities\MenuActivation;
use Modules\Catalog\Domain\Entities\MenuProduct;
use Modules\Catalog\Domain\Entities\PriceList;
use Modules\Catalog\Domain\Entities\Product;
use Modules\Identity\Domain\Entities\User;

/**
 * Servicio de dominio para gestión de cartas (menús).
 * 
 * Extraído de MenuController en S3 para cumplir DDD:
 * - Unifica transformaciones de items (show/active comparten lógica)
 * - Centraliza regla de negocio "solo una carta default por sucursal"
 * - Separa orquestación HTTP (controller) de lógica de negocio
 */
class MenuManagementService
{
    public function __construct(
        protected MenuResolutionService $resolutionService
    ) {}

    /**
     * Lista cartas con filtros y relaciones.
     */
    public function listMenus(?bool $activeOnly = null): Collection
    {
        return Menu::query()
            ->with(['priceList', 'activations'])
            ->withCount('menuProducts')
            ->when($activeOnly === true, fn($q) => $q->where('is_active', true))
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();
    }

    /**
     * Obtiene una carta con sus items y precios resueltos.
     * Reutilizado por show() y active().
     */
    public function getMenuWithItems(Menu $menu): array
    {
        $items = MenuProduct::where('menu_id', $menu->id)
            ->orderBy('position')
            ->get()
            ->map(fn($mp) => $this->buildMenuItemData($mp, $menu))
            ->filter()
            ->values();

        return [
            'menu' => $menu,
            'items' => $items,
        ];
    }

    /**
     * Resuelve la carta activa según contexto (branch + canal) con sus items.
     * Retorna null si no hay carta activa.
     */
    public function getActiveMenuWithItems(int $branchId, string $channelType): ?array
    {
        $menu = $this->resolutionService->resolve($branchId, $channelType);

        if (!$menu) {
            return null;
        }

        $items = MenuProduct::where('menu_id', $menu->id)
            ->where('is_available', true)
            ->orderBy('position')
            ->get()
            ->map(fn($mp) => $this->buildActiveMenuItemData($mp, $menu))
            ->filter()
            ->values();

        return [
            'menu' => $menu->load('priceList'),
            'items' => $items,
        ];
    }

    /**
     * Crea una carta nueva.
     * Si es_default = true, desactiva el default de las demás cartas de la sucursal.
     */
    public function createMenu(array $data, User $user, bool $isDefault): Menu
    {
        $priceList = PriceList::where('uuid', $data['price_list_id'])->firstOrFail();

        if ($isDefault) {
            $this->clearDefaultInBranch($user->company_id, $user->branch_id);
        }

        return Menu::create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'price_list_id' => $priceList->id,
            'is_default' => $isDefault,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /**
     * Actualiza una carta existente.
     * Si se marca como default, desactiva el default de las demás (excluyendo esta).
     */
    public function updateMenu(Menu $menu, array $data, User $user, bool $isDefault): Menu
    {
        if (isset($data['price_list_id'])) {
            $priceList = PriceList::where('uuid', $data['price_list_id'])->firstOrFail();
            $data['price_list_id'] = $priceList->id;
        }

        if ($isDefault) {
            $this->clearDefaultInBranch($user->company_id, $user->branch_id, $menu->id);
        }

        $menu->update($data);

        return $menu->fresh('priceList');
    }

    /**
     * Elimina una carta (cascade borra menu_products y menu_activations).
     */
    public function deleteMenu(Menu $menu): void
    {
        $menu->delete();
    }

    /**
     * Reemplaza todas las reglas de activación de la carta (semántica upsert).
     */
    public function replaceActivations(Menu $menu, array $activations): array
    {
        MenuActivation::where('menu_id', $menu->id)->delete();

        $created = [];
        foreach ($activations as $act) {
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

        return $created;
    }

    /**
     * Asigna productos a la carta (upsert masivo).
     * Skip productos inexistentes o de otro tenant.
     */
    public function assignProducts(Menu $menu, array $products): array
    {
        $assigned = [];
        foreach ($products as $item) {
            $product = Product::where('uuid', $item['product_uuid'])->first();
            if (!$product) {
                continue;
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

        return $assigned;
    }

    /**
     * Desactiva el flag is_default de todas las cartas de la sucursal.
     * Opcionalmente excluye un menu específico (para updates).
     */
    private function clearDefaultInBranch(int $companyId, int $branchId, ?int $excludeMenuId = null): void
    {
        $query = Menu::where('company_id', $companyId)
            ->where('branch_id', $branchId);

        if ($excludeMenuId !== null) {
            $query->where('id', '!=', $excludeMenuId);
        }

        $query->update(['is_default' => false]);
    }

    /**
     * Construye datos de un item de carta (para show).
     */
    private function buildMenuItemData(MenuProduct $mp, Menu $menu): ?array
    {
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
    }

    /**
     * Construye datos de un item de carta activa (para active endpoint).
     * Incluye category_id y filtra productos inactivos.
     */
    private function buildActiveMenuItemData(MenuProduct $mp, Menu $menu): ?array
    {
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
    }
}
