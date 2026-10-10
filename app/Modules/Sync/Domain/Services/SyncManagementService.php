<?php

namespace Modules\Sync\Domain\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Catalog\Domain\Entities\Category;
use Modules\Catalog\Domain\Entities\Product;
use Modules\Identity\Domain\Entities\User;
use Modules\Payments\Domain\Entities\PaymentMethod;
use Modules\Sync\Domain\Enums\ResolutionStrategy;
use Modules\Tables\Domain\Entities\RestaurantTable;
use Modules\Branches\Domain\Entities\Branch;

/**
 * Servicio de dominio para operaciones de sincronización.
 *
 * O-02 FIX: Validación estricta de company_id y branch_id como datos de seguridad,
 * no como parámetros confiables enviados por el cliente.
 */
class SyncManagementService
{
    public function __construct(
        private SyncService $syncService,
        private LocalDatabaseManager $localDatabaseManager
    ) {
    }

    /**
     * O-02 FIX: Valida que el usuario tenga acceso a una sucursal.
     * 1. La sucursal DEBE pertenecer a la empresa del usuario.
     * 2. Un rol administrativo NO implica acceso automático a sucursales de otras empresas.
     * 3. Si no es admin, la sucursal debe ser la asignada al usuario.
     *
     * @throws \DomainException Si el usuario no tiene acceso
     */
    public function validateBranchAccess(User $user, int $branchId): void
    {
        // Verificar que la sucursal existe y pertenece a la empresa del usuario
        $isValidBranch = Branch::where('id', $branchId)
            ->where('company_id', $user->company_id)
            ->exists();

        if (!$isValidBranch) {
            // Mensaje genérico para no revelar información de otras empresas
            throw new \DomainException('Acceso denegado: sucursal no válida o no autorizada');
        }

        // Si no es admin, debe ser su sucursal asignada
        if ($user->role !== 'admin' && (int) $user->branch_id !== (int) $branchId) {
            throw new \DomainException('Acceso denegado: no tienes asignada esta sucursal');
        }
    }

    /**
     * Obtiene estadísticas de sincronización para una sucursal.
     */
    public function getSyncStats(int $branchId): array
    {
        return $this->syncService->getSyncStats($branchId);
    }

    /**
     * Procesa cambios enviados por el cliente (push).
     */
    public function pushChanges(int $branchId, int $limit): array
    {
        return $this->syncService->pushChanges($branchId, $limit);
    }

    /**
     * Obtiene cambios del servidor para el cliente (pull).
     */
    public function pullChanges(
        int $branchId,
        ResolutionStrategy $strategy
    ): array {
        return $this->syncService->pullChanges($branchId, $strategy);
    }

    /**
     * Obtiene el estado de salud del sistema de sincronización.
     */
    public function getHealthStatus(): array
    {
        return [
            'sync_service' => 'operational',
            'local_database' => $this->localDatabaseManager->isAvailable()
                ? 'available'
                : 'unavailable',
            'local_database_size' => $this->localDatabaseManager->getDatabaseSize(),
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Obtiene cambios incrementales desde last_pull_at.
     */
    public function getIncrementalChanges(
        int $companyId,
        int $branchId,
        ?Carbon $since
    ): array {
        return [
            'categories' => $this->getChangedCategories($companyId, $branchId, $since),
            'products' => $this->getChangedProducts($companyId, $branchId, $since),
            'tables' => $this->getChangedTables($companyId, $branchId, $since),
            'payment_methods' => $this->getChangedPaymentMethods($companyId, $branchId, $since),
        ];
    }

    private function getChangedCategories(int $companyId, int $branchId, ?Carbon $since): array
    {
        $query = Category::withoutGlobalScopes()
            ->withTrashed()
            ->where('company_id', $companyId)
            ->where(function ($q) use ($branchId) {
                $q->where('branch_id', $branchId)->orWhereNull('branch_id');
            });

        if ($since) {
            $query->where('updated_at', '>', $since);
        }

        return $query->get()->map(function ($cat) {
            return [
                'id' => $cat->id,
                'uuid' => $cat->uuid,
                'name' => $cat->name,
                'is_active' => $cat->is_active,
                'deleted_at' => $cat->deleted_at?->toIso8601String(),
                'updated_at' => $cat->updated_at->toIso8601String(),
            ];
        })->toArray();
    }

    private function getChangedProducts(int $companyId, int $branchId, ?Carbon $since): array
    {
        $query = Product::withoutGlobalScopes()
            ->withTrashed()
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId);

        if ($since) {
            $query->where('updated_at', '>', $since);
        }

        return $query->get()->map(function ($prod) {
            return [
                'id' => $prod->id,
                'uuid' => $prod->uuid,
                'name' => $prod->name,
                'base_price' => $prod->base_price,
                'is_active' => $prod->is_active,
                'deleted_at' => $prod->deleted_at?->toIso8601String(),
                'updated_at' => $prod->updated_at->toIso8601String(),
            ];
        })->toArray();
    }

    private function getChangedTables(int $companyId, int $branchId, ?Carbon $since): array
    {
        $query = RestaurantTable::withoutGlobalScopes()
            ->withTrashed()
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId);

        if ($since) {
            $query->where('updated_at', '>', $since);
        }

        return $query->get()->map(function ($table) {
            return [
                'id' => $table->id,
                'uuid' => $table->uuid,
                'table_number' => $table->table_number,
                'status' => $table->status,
                'deleted_at' => $table->deleted_at?->toIso8601String(),
                'updated_at' => $table->updated_at->toIso8601String(),
            ];
        })->toArray();
    }

    private function getChangedPaymentMethods(int $companyId, int $branchId, ?Carbon $since): array
    {
        $query = PaymentMethod::withoutGlobalScopes()
            ->withTrashed()
            ->where('company_id', $companyId)
            ->where(function ($q) use ($branchId) {
                $q->where('branch_id', $branchId)->orWhereNull('branch_id');
            });

        if ($since) {
            $query->where('updated_at', '>', $since);
        }

        return $query->get()->map(function ($pm) {
            return [
                'id' => $pm->id,
                'uuid' => $pm->uuid,
                'code' => $pm->code,
                'name_translations' => $pm->name_translations,
                'is_active' => $pm->is_active,
                'deleted_at' => $pm->deleted_at?->toIso8601String(),
                'updated_at' => $pm->updated_at->toIso8601String(),
            ];
        })->toArray();
    }
}
