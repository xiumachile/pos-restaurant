<?php

namespace Modules\Sync\Domain\Services;

use Carbon\Carbon;
use Modules\Catalog\Domain\Entities\Category;
use Modules\Catalog\Domain\Entities\Product;
use Modules\Identity\Domain\Entities\User;
use Modules\Payments\Domain\Entities\PaymentMethod;
use Modules\Sync\Domain\Enums\ResolutionStrategy;
use Modules\Tables\Domain\Entities\RestaurantTable;
use Modules\Sync\Domain\Services\SyncService;
use Modules\Sync\Domain\Services\LocalDatabaseManager;

/**
 * Servicio de dominio para operaciones de sincronización.
 *
 * Extraído de SyncController en S5 para cumplir DDD:
 * - Centraliza validación de acceso a branches (DRY: usada en push/pull/status)
 * - Contiene transformaciones de cambios incrementales (getChanged*)
 * - Separa lógica de negocio de orquestación HTTP
 *
 * Nota: Este service ORQUESTA SyncService y SyncAdapter existentes.
 * Ellos siguen encargados del procesamiento real de cambios.
 */
class SyncManagementService
{
    public function __construct(
        private SyncService $syncService,
        private LocalDatabaseManager $localDatabaseManager
    ) {
    }

    /**
     * Valida que el usuario tenga acceso a una sucursal.
     * Admin puede acceder a cualquier sucursal.
     *
     * @throws \DomainException Si el usuario no tiene acceso
     */
    public function validateBranchAccess(User $user, int $branchId): void
    {
        if ((int) $user->branch_id !== (int) $branchId && $user->role !== 'admin') {
            throw new \DomainException('No tienes acceso a esta sucursal');
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
     * Incluye registros soft-deleted para que el cliente los elimine localmente.
     *
     * @return array{
     *     categories: array,
     *     products: array,
     *     tables: array,
     *     payment_methods: array
     * }
     */
    public function getIncrementalChanges(
        int $companyId,
        int $branchId,
        ?Carbon $since
    ): array {
        return [
            'categories' => $this->getChangedCategories(
                $companyId,
                $branchId,
                $since
            ),
            'products' => $this->getChangedProducts(
                $companyId,
                $branchId,
                $since
            ),
            'tables' => $this->getChangedTables(
                $companyId,
                $branchId,
                $since
            ),
            'payment_methods' => $this->getChangedPaymentMethods(
                $companyId,
                $branchId,
                $since
            ),
        ];
    }

    /**
     * Obtiene categorías modificadas desde last_pull_at.
     * Incluye soft-deleted con flag deleted=true.
     */
    private function getChangedCategories(
        int $companyId,
        int $branchId,
        ?Carbon $since
    ): array {
        $query = Category::withoutGlobalScopes()
            ->withTrashed()
            ->where('company_id', $companyId)
            ->where(function ($q) use ($branchId) {
                $q->where('branch_id', $branchId)
                    ->orWhereNull('branch_id');
            });

        if ($since) {
            $query->where('updated_at', '>', $since);
        }

        return $query->get()
            ->map(function ($cat) {
                return [
                    'uuid' => $cat->uuid,
                    'name_translations' => $cat->name_translations,
                    'sort_order' => $cat->sort_order ?? 0,
                    'is_active' => (bool) $cat->is_active,
                    'deleted' => $cat->deleted_at !== null,
                    'updated_at' => $cat->updated_at?->toIso8601String(),
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Obtiene productos modificados desde last_pull_at.
     */
    private function getChangedProducts(
        int $companyId,
        int $branchId,
        ?Carbon $since
    ): array {
        $query = Product::withoutGlobalScopes()
            ->withTrashed()
            ->where('company_id', $companyId)
            ->where(function ($q) use ($branchId) {
                $q->where('branch_id', $branchId)
                    ->orWhereNull('branch_id');
            });

        if ($since) {
            $query->where('updated_at', '>', $since);
        }

        return $query->get()
            ->map(function ($prod) {
                return [
                    'uuid' => $prod->uuid,
                    'category_id' => $prod->category_id,
                    'sku' => $prod->sku,
                    'name_translations' => $prod->name_translations,
                    'description_translations' =>
                        $prod->description_translations ?? (object) [],
                    'base_price' => (float) $prod->base_price,
                    'tax_rate' => (float) $prod->tax_rate,
                    'is_combo' => (bool) $prod->is_combo,
                    'kitchen_zone_id' => $prod->kitchen_zone_id,
                    'is_active' => (bool) $prod->is_active,
                    'deleted' => $prod->deleted_at !== null,
                    'updated_at' => $prod->updated_at?->toIso8601String(),
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Obtiene mesas modificadas desde last_pull_at.
     */
    private function getChangedTables(
        int $companyId,
        int $branchId,
        ?Carbon $since
    ): array {
        $query = RestaurantTable::withoutGlobalScopes()
            ->withTrashed()
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId);

        if ($since) {
            $query->where('updated_at', '>', $since);
        }

        return $query->get()
            ->map(function ($table) {
                return [
                    'uuid' => $table->uuid,
                    'table_number' => $table->table_number,
                    'area_code' => $table->area_code,
                    'area_name_translations' => $table->area_name_translations,
                    'capacity' => $table->capacity ?? 4,
                    'status' => $table->status ?? 'available',
                    'current_order_id' => $table->current_order_id,
                    'deleted' => $table->deleted_at !== null,
                    'updated_at' => $table->updated_at?->toIso8601String(),
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Obtiene métodos de pago modificados desde last_pull_at.
     *
     * Nota: payment_methods no tiene soft delete,
     * se usa is_active=false.
     */
    private function getChangedPaymentMethods(
        int $companyId,
        int $branchId,
        ?Carbon $since
    ): array {
        $query = PaymentMethod::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where(function ($q) use ($branchId) {
                $q->where('branch_id', $branchId)
                    ->orWhereNull('branch_id');
            });

        if ($since) {
            $query->where('updated_at', '>', $since);
        }

        return $query->get()
            ->map(function ($method) {
                return [
                    'uuid' => $method->uuid,
                    'code' => $method->code,
                    'name_translations' => $method->name_translations,
                    'type' => $method->type,
                    'icon' => $method->icon,
                    'max_amount' => $method->max_amount
                        ? (float) $method->max_amount
                        : null,
                    'requires_reference' => (bool) $method->requires_reference,
                    'is_active' => (bool) $method->is_active,
                    'sort_order' => $method->sort_order ?? 0,
                    'deleted' => !(bool) $method->is_active,
                    'updated_at' => $method->updated_at?->toIso8601String(),
                ];
            })
            ->values()
            ->toArray();
    }
}
