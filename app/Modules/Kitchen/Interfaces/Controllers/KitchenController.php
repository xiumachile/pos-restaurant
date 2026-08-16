<?php

namespace Modules\Kitchen\Interfaces\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Identity\Domain\Entities\User;
use Modules\Kitchen\Domain\Services\KitchenQueueService;
use Modules\Kitchen\Interfaces\Requests\AssignCookRequest;
use Modules\Kitchen\Interfaces\Requests\UpdatePriorityRequest;
use Modules\Kitchen\Interfaces\Resources\KitchenOrderResource;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\ValueObjects\OrderPriority;
use Modules\Tables\Domain\Entities\RestaurantTable;

class KitchenController extends Controller
{
    public function __construct(
        private KitchenQueueService $queueService
    ) {}

    /**
     * GET /api/v1/kitchen/queue
     */
    public function queue(Request $request): JsonResponse
    {
        $branchId = $request->user()->branch_id;
        $queue = $this->queueService->getQueue($branchId);

        $response = $queue->map(function ($orders, $zone) {
            return [
                'zone' => $zone,
                'orders' => KitchenOrderResource::collection($orders)->resolve(),
                'count' => $orders->count(),
            ];
        })->values();

        return response()->json(['data' => $response]);
    }

    /**
     * GET /api/v1/kitchen/stats
     */
    public function stats(Request $request): JsonResponse
    {
        $branchId = $request->user()->branch_id;
        $stats = $this->queueService->getStats($branchId);

        return response()->json(['data' => $stats]);
    }

    /**
     * GET /api/v1/kitchen/history
     */
    public function history(Request $request): JsonResponse
    {
        $branchId = $request->user()->branch_id;
        $limit = $request->integer('limit', 50);
        $history = $this->queueService->getHistory($branchId, $limit);

        return KitchenOrderResource::collection($history)->response();
    }

    /**
     * GET /api/v1/kitchen/table-history/{tableUuid}
     * Historial completo de pedidos de una mesa del día actual.
     */
    public function tableHistory(Request $request, string $tableUuid): JsonResponse
    {
        $branchId = $request->user()->branch_id;
        
        $table = RestaurantTable::where('uuid', $tableUuid)
            ->where('branch_id', $branchId)
            ->firstOrFail();

        $orders = Order::with(['items.menuItem.product', 'waiter'])
            ->where('table_id', $table->id)
            ->where('branch_id', $branchId)
            ->whereDate('created_at', today())
            ->orderBy('created_at', 'asc')
            ->get();

        $response = [
            'table' => [
                'uuid' => $table->uuid,
                'table_number' => $table->table_number,
                'area_code' => $table->area_code,
                'capacity' => $table->capacity,
            ],
            'orders' => KitchenOrderResource::collection($orders)->resolve(),
            'summary' => [
                'total_orders' => $orders->count(),
                'total_items' => $orders->sum(fn($o) => $o->items->sum('quantity')),
                'total_amount' => (float) $orders->sum('total'),
                'first_order_at' => $orders->first()?->created_at?->toIso8601String(),
                'last_order_at' => $orders->last()?->created_at?->toIso8601String(),
            ],
        ];

        return response()->json(['data' => $response]);
    }

    /**
     * GET /api/v1/kitchen/tables-today
     * Lista todas las mesas con actividad hoy.
     */
    public function tablesToday(Request $request): JsonResponse
    {
        $branchId = $request->user()->branch_id;

        $tableIds = Order::where('branch_id', $branchId)
            ->whereDate('created_at', today())
            ->whereNotNull('table_id')
            ->distinct()
            ->pluck('table_id');

        $tables = RestaurantTable::with(['orders' => function ($query) {
            $query->whereDate('created_at', today())
                ->with(['items'])
                ->orderBy('created_at', 'asc');
        }])
            ->whereIn('id', $tableIds)
            ->orderBy('area_code')
            ->orderBy('table_number')
            ->get();

        $response = $tables->map(function ($table) {
            $orders = $table->orders;
            $totalAmount = $orders->sum('total');
            $totalItems = $orders->sum(fn($o) => $o->items->sum('quantity'));
            $lastOrderStatus = $orders->last()?->status?->value;
            
            return [
                'uuid' => $table->uuid,
                'table_number' => $table->table_number,
                'area_code' => $table->area_code,
                'capacity' => $table->capacity,
                'orders_count' => $orders->count(),
                'total_items' => $totalItems,
                'total_amount' => (float) $totalAmount,
                'last_order_status' => $lastOrderStatus,
                'first_order_at' => $orders->first()?->created_at?->toIso8601String(),
                'last_order_at' => $orders->last()?->created_at?->toIso8601String(),
            ];
        });

        return response()->json(['data' => $response]);
    }

    /**
     * POST /api/v1/kitchen/orders/{uuid}/assign-cook
     */
    public function assignCook(AssignCookRequest $request, string $uuid): JsonResponse
    {
        $validated = $request->validated();
        $order = Order::where('uuid', $uuid)->firstOrFail();

        if (!$order->status->isInKitchenQueue()) {
            return response()->json([
                'error' => 'invalid_state',
                'message' => 'Solo se pueden asignar cocineros a pedidos en cola de cocina.',
            ], 422);
        }

        $cook = User::where('uuid', $validated['cook_uuid'])
            ->where('role', 'kitchen')
            ->where('company_id', $order->company_id)
            ->firstOrFail();

        $order->assigned_cook_id = $cook->id;
        $order->save();

        $order->load(['items', 'table', 'waiter', 'assignedCook']);

        return KitchenOrderResource::make($order)->response();
    }

    /**
     * POST /api/v1/kitchen/orders/{uuid}/priority
     */
    public function updatePriority(UpdatePriorityRequest $request, string $uuid): JsonResponse
    {
        $validated = $request->validated();
        $order = Order::where('uuid', $uuid)->firstOrFail();

        if ($order->status->isFinalState()) {
            return response()->json([
                'error' => 'invalid_state',
                'message' => 'No se puede cambiar la prioridad de un pedido en estado final.',
            ], 422);
        }

        $order->priority = OrderPriority::from($validated['priority']);
        $order->save();

        $order->load(['items', 'table', 'waiter', 'assignedCook']);

        return KitchenOrderResource::make($order)->response();
    }
}
