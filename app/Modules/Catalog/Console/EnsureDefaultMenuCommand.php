<?php

namespace Modules\Catalog\Console;

use Illuminate\Console\Command;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Catalog\Application\Services\BranchDefaultProvisioner;

class EnsureDefaultMenuCommand extends Command
{
    protected $signature = 'catalog:ensure-default-menu {branch_id? : ID de la sucursal a reparar}';
    protected $description = 'Garantiza que las sucursales tengan PriceList + Menu default';

    public function handle(BranchDefaultProvisioner $provisioner): int
    {
        $branchId = $this->argument('branch_id');

        if ($branchId !== null) {
            $branch = Branch::find($branchId);
            if (!$branch) {
                $this->error("Branch #{$branchId} no encontrada.");
                return self::FAILURE;
            }
            $branches = collect([$branch]);
        } else {
            // Operación administrativa cross-tenant: saltar scopes multi-tenant
            $branches = Branch::withoutGlobalScopes()
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->get();
            $this->info("Revisando {$branches->count()} sucursales activas...");
        }

        $createdLists = 0;
        $createdMenus = 0;
        $alreadyOk = 0;
        $rows = [];

        foreach ($branches as $branch) {
            $result = $provisioner->ensureDefaults($branch);

            if ($result['priceListCreated'] || $result['menuCreated']) {
                $status = 'REPARADA';
                if ($result['priceListCreated']) $createdLists++;
                if ($result['menuCreated']) $createdMenus++;
            } else {
                $status = 'OK (ya tenía defaults)';
                $alreadyOk++;
            }

            $rows[] = [
                'id' => $branch->id,
                'name' => $branch->name,
                'price_list' => $result['priceList']->display_name ?? $result['priceList']->name,
                'menu' => $result['menu']->name,
                'status' => $status,
            ];
        }

        $this->table(
            ['Branch ID', 'Nombre', 'PriceList Default', 'Menu Default', 'Estado'],
            $rows
        );

        $this->newLine();
        $this->info("Resumen:");
        $this->line("  - Sucursales OK: {$alreadyOk}");
        $this->line("  - PriceLists creadas: {$createdLists}");
        $this->line("  - Menus creados: {$createdMenus}");

        return self::SUCCESS;
    }
}
