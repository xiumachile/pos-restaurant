<?php

namespace Modules\Sync\Interfaces\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Controlador para sincronización incremental.
 * Retorna solo los cambios desde la última sincronización.
 */
class SyncIncrementalController extends Controller
{
    /**
     * GET /api/v1/sync/changes
     * 
     * Retorna cambios incrementales desde last_pull_at.
     */
    public function changes(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => 'required|integer|exists:branches,id',
            'last_pull_at' => 'nullable|date',
        ]);

        $branchId = $validated['branch_id'];
        $lastPullAt = isset($validated['last_pull_at']) 
            ? Carbon::parse($validated['last_pull_at']) 
            : null;

        // Verificar acceso
        $user = $request->user();
        if ((int) $user->branch_id !== (int) $branchId && $user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'error' => 'No tienes acceso a esta sucursal',
            ], 403);
        }

        $changes = [
            'categories' => $this->getChangedCategories($branchId, $lastPullAt),
            'products' => $this->getChangedProducts($branchId, $lastPullAt),
            'tables' => $this->getChangedTables($branchId, $lastPullAt),
            'payment_methods' => $this->getChangedPaymentMethods($branchId, $lastPullAt),
        ];

        $totalChanges = array_sum(array_map('count', $changes));

        return response()->json([
            'success' => true,
            'data' => [
                'changes' => $changes,
                'total' => $totalChanges,
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }

    private function getChangedCategories(int $branchId, ?Carbon $since): array
    {
        $query = DB::table('categories')
            ->where('branch_id', $branchId);

        if ($since) {
            $query->where('updated_at', '>', $since);
        }

        return $query->get()->map(function ($cat) {
            return [
                'uuid' => $cat->uuid,
                'name_translations' => json_decode($cat->name_translations, true),
                'sort_order' => $cat->sort_order,
                'is_active' => (bool) $cat->is_active,
                'updated_at' => $cat->updated_at,
            ];
        })->toArray();
    }

    private function getChangedProducts(int $branchId, ?Carbon $since): array
    {
        $query = DB::table('products')
            ->where('branch_id', $branchId);

        if ($since) {
            $query->where('updated_at', '>', $since);
        }

        return $query->get()->map(function ($prod) {
            return [
                'uuid' => $prod->uuid,
                'category_id' => $prod->category_id,
                'sku' => $prod->sku,
                'name_translations' => json_decode($prod->name_translations, true),
                'description_translations' => json_decode($prod->description_translations ?? '{}', true),
                'base_price' => (float) $prod->base_price,
                'tax_rate' => (float) $prod->tax_rate,
                'is_combo' => (bool) $prod->is_combo,
                'kitchen_zone_id' => $prod->kitchen_zone_id,
                'is_active' => (bool) $prod->is_active,
                'updated_at' => $prod->updated_at,
            ];
        })->toArray();
    }

    private function getChangedTables(int $branchId, ?Carbon $since): array
    {
        $query = DB::table('restaurant_tables')
            ->where('branch_id', $branchId);

        if ($since) {
            $query->where('updated_at', '>', $since);
        }

        return $query->get()->map(function ($table) {
            return [
                'uuid' => $table->uuid,
                'table_number' => $table->table_number,
                'area_name' => $table->area_name,
                'capacity' => $table->capacity,
                'status' => $table->status,
                'current_order_uuid' => $table->current_order_id,
                'updated_at' => $table->updated_at,
            ];
        })->toArray();
    }

    private function getChangedPaymentMethods(int $branchId, ?Carbon $since): array
    {
        $query = DB::table('payment_methods')
            ->where('branch_id', $branchId);

        if ($since) {
            $query->where('updated_at', '>', $since);
        }

        return $query->get()->map(function ($method) {
            return [
                'uuid' => $method->uuid,
                'code' => $method->code,
                'type' => $method->type,
                'is_active' => (bool) $method->is_active,
                'updated_at' => $method->updated_at,
            ];
        })->toArray();
    }
}
