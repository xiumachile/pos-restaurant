<?php

namespace Modules\Catalog\Application\Services;

use Illuminate\Support\Str;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Catalog\Domain\Entities\Menu;
use Modules\Catalog\Domain\Entities\PriceList;

/**
 * Servicio que garantiza la existencia de PriceList + Menu default en una sucursal.
 * Idempotente: puede ejecutarse múltiples veces sin duplicar defaults.
 */
class BranchDefaultProvisioner
{
    public function ensureDefaults(Branch $branch): array
    {
        $result = [
            'priceListCreated' => false,
            'menuCreated' => false,
            'priceList' => null,
            'menu' => null,
        ];

        // 1. PriceList default scoped a la sucursal
        // Operación administrativa cross-tenant: saltar scopes multi-tenant
        $priceList = PriceList::withoutGlobalScopes()
            ->where('company_id', $branch->company_id)
            ->where('branch_id', $branch->id)
            ->where('is_default', true)
            ->whereNull('deleted_at')
            ->first();

        if (!$priceList) {
            $priceList = PriceList::create([
                'company_id' => $branch->company_id,
                'branch_id' => $branch->id,
                'name' => 'default_' . Str::slug($branch->name ?? 'branch_' . $branch->id),
                'display_name' => 'Lista default - ' . ($branch->name ?? 'Sucursal'),
                'currency' => 'CLP',
                'is_default' => true,
                'is_active' => true,
            ]);
            $result['priceListCreated'] = true;
        }
        $result['priceList'] = $priceList;

        // 2. Menu default apuntando a esa PriceList
        $menu = Menu::withoutGlobalScopes()
            ->where('branch_id', $branch->id)
            ->where('is_default', true)
            ->whereNull('deleted_at')
            ->first();

        if (!$menu) {
            $menu = Menu::create([
                'company_id' => $branch->company_id,
                'branch_id' => $branch->id,
                'name' => 'Carta default - ' . ($branch->name ?? 'Sucursal'),
                'description' => 'Carta default generada automáticamente por el sistema',
                'price_list_id' => $priceList->id,
                'is_default' => true,
                'is_active' => true,
            ]);
            $result['menuCreated'] = true;
        }
        $result['menu'] = $menu;

        return $result;
    }
}
