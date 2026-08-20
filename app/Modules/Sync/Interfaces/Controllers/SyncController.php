<?php

namespace Modules\Sync\Interfaces\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Sync\Domain\Services\SyncService;
use Modules\Sync\Domain\Services\SyncAdapter;
use Modules\Sync\Domain\Services\LocalDatabaseManager;
use Modules\Sync\Domain\Enums\ResolutionStrategy;
use Modules\Catalog\Domain\Entities\Category;
use Modules\Catalog\Domain\Entities\Product;
use Modules\Tables\Domain\Entities\RestaurantTable;
use Modules\Payments\Domain\Entities\PaymentMethod;
use Carbon\Carbon;

/**
 * API REST para sincronización offline-first.
 * 
 * Endpoints para que el cliente desktop (Tauri) sincronice
 * con el servidor central.
 */
class SyncController extends Controller
{
    protected SyncService $syncService;
    protected SyncAdapter $syncAdapter;

    public function __construct(SyncService $syncService, SyncAdapter $syncAdapter)
    {
        $this->syncService = $syncService;
        $this->syncAdapter = $syncAdapter;
    }

    /**
     * POST /api/v1/sync/push
     * 
     * Cliente envía cambios locales al servidor.
     * Procesa la cola sync_queue y aplica cambios.
     */
    public function push(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => 'required|integer|exists:branches,id',
            'limit' => 'nullable|integer|min:1|max:1000',
        ]);

        $branchId = $validated['branch_id'];
        $limit = $validated['limit'] ?? 100;

        // Verificar que el usuario tenga acceso a esta sucursal
        $user = $request->user();
        if ($user->branch_id !== $branchId && $user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'error' => 'No tienes acceso a esta sucursal',
            ], 403);
        }

        $result = $this->syncService->pushChanges($branchId, $limit);

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * POST /api/v1/sync/pull
     * 
     * Cliente descarga cambios del servidor.
     * Aplica cambios a entidades locales.
     */
    public function pull(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => 'required|integer|exists:branches,id',
            'strategy' => 'nullable|string|in:server_wins,client_wins,merge,manual',
        ]);

        $branchId = $validated['branch_id'];
        $strategyValue = $validated['strategy'] ?? 'server_wins';
        $strategy = ResolutionStrategy::from($strategyValue);

        // Verificar acceso (casting a int para comparación correcta)
        $user = $request->user();
        if ((int) $user->branch_id !== (int) $branchId && $user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'error' => 'No tienes acceso a esta sucursal',
            ], 403);
        }

        $result = $this->syncService->pullChanges($branchId, $strategy);

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * GET /api/v1/sync/status
     * 
     * Obtiene estadísticas de sincronización para una sucursal.
     */
    public function status(Request $request): JsonResponse
    {
        $branchId = $request->query('branch_id', $request->user()->branch_id);

        // Verificar acceso (casting a int para comparación correcta)
        $user = $request->user();
        if ((int) $user->branch_id !== (int) $branchId && $user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'error' => 'No tienes acceso a esta sucursal',
            ], 403);
        }

        $stats = $this->syncService->getSyncStats($branchId);

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * GET /api/v1/sync/health
     * 
     * Verifica salud del sistema de sincronización.
     */
    public function health(): JsonResponse
    {
        $localDb = new LocalDatabaseManager();
        
        return response()->json([
            'success' => true,
            'data' => [
                'sync_service' => 'operational',
                'local_database' => $localDb->isAvailable() ? 'available' : 'unavailable',
                'local_database_size' => $localDb->getDatabaseSize(),
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * GET /api/v1/sync/changes
     * 
     * Retorna cambios incrementales desde last_pull_at.
     * Incluye registros soft-deleted para que el cliente los elimine localmente.
     * 
     * Query params:
     * - last_pull_at: ISO 8601 timestamp (opcional, si no se envía retorna todo)
     */
    public function changes(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'last_pull_at' => 'nullable|date',
        ]);

        $user = $request->user();
        $companyId = $user->company_id;
        $branchId = $user->branch_id;

        $lastPullAt = isset($validated['last_pull_at']) 
            ? Carbon::parse($validated['last_pull_at']) 
            : null;

        $changes = [
            'categories' => $this->getChangedCategories($companyId, $branchId, $lastPullAt),
            'products' => $this->getChangedProducts($companyId, $branchId, $lastPullAt),
            'tables' => $this->getChangedTables($companyId, $branchId, $lastPullAt),
            'payment_methods' => $this->getChangedPaymentMethods($companyId, $branchId, $lastPullAt),
        ];

        $totalChanges = array_sum(array_map('count', $changes));

        return response()->json([
            'success' => true,
            'data' => [
                'changes' => $changes,
                'total' => $totalChanges,
                'timestamp' => now()->toIso8601String(),
                'incremental' => $lastPullAt !== null,
            ],
        ]);
    }

    /**
     * Obtiene categorías modificadas desde last_pull_at.
     * Incluye soft-deleted con flag deleted=true.
     */
    private function getChangedCategories($companyId, $branchId, ?Carbon $since): array
    {
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

        return $query->get()->map(function ($cat) {
            return [
                'uuid' => $cat->uuid,
                'name_translations' => $cat->name_translations,
                'sort_order' => $cat->sort_order ?? 0,
                'is_active' => (bool) $cat->is_active,
                'deleted' => $cat->deleted_at !== null,
                'updated_at' => $cat->updated_at?->toIso8601String(),
            ];
        })->values()->toArray();
    }

    /**
     * Obtiene productos modificados desde last_pull_at.
     */
    private function getChangedProducts($companyId, $branchId, ?Carbon $since): array
    {
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

        return $query->get()->map(function ($prod) {
            return [
                'uuid' => $prod->uuid,
                'category_id' => $prod->category_id,
                'sku' => $prod->sku,
                'name_translations' => $prod->name_translations,
                'description_translations' => $prod->description_translations ?? (object)[],
                'base_price' => (float) $prod->base_price,
                'tax_rate' => (float) $prod->tax_rate,
                'is_combo' => (bool) $prod->is_combo,
                'kitchen_zone_id' => $prod->kitchen_zone_id,
                'is_active' => (bool) $prod->is_active,
                'deleted' => $prod->deleted_at !== null,
                'updated_at' => $prod->updated_at?->toIso8601String(),
            ];
        })->values()->toArray();
    }

    /**
     * Obtiene mesas modificadas desde last_pull_at.
     */
    private function getChangedTables($companyId, $branchId, ?Carbon $since): array
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
        })->values()->toArray();
    }

    /**
     * Obtiene métodos de pago modificados desde last_pull_at.
     * Nota: payment_methods no tiene soft delete, se usa is_active=false.
     */
    private function getChangedPaymentMethods($companyId, $branchId, ?Carbon $since): array
    {
        $query = PaymentMethod::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where(function ($q) use ($branchId) {
                $q->where('branch_id', $branchId)
                  ->orWhereNull('branch_id');
            });

        if ($since) {
            $query->where('updated_at', '>', $since);
        }

        return $query->get()->map(function ($method) {
            return [
                'uuid' => $method->uuid,
                'code' => $method->code,
                'name_translations' => $method->name_translations,
                'type' => $method->type,
                'icon' => $method->icon,
                'max_amount' => $method->max_amount ? (float) $method->max_amount : null,
                'requires_reference' => (bool) $method->requires_reference,
                'is_active' => (bool) $method->is_active,
                'sort_order' => $method->sort_order ?? 0,
                'deleted' => !(bool) $method->is_active,
                'updated_at' => $method->updated_at?->toIso8601String(),
            ];
        })->values()->toArray();
    }

}
