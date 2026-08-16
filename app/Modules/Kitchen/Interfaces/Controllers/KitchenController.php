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
     * Cola de cocina agrupada por zona.
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
     * Estadísticas de la cocina.
     */
    public function stats(Request $request): JsonResponse
    {
        $branchId = $request->user()->branch_id;
        $stats = $this->queueService->getStats($branchId);

        return response()->json(['data' => $stats]);
    }

    /**
     * GET /api/v1/kitchen/history
     * Historial de pedidos completados hoy.
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
     * Historial completo de pedidos de una mesa específica del día actual.
     * Incluye todos los estados (confirmed, preparing, ready, served, paid, closed).
     */
    public function tableHistory(Request $request, string $tableUuid): JsonResponse
    {
        $branchId = $request->user()->branch_id;
        
        // Buscar la mesa
        $table = RestaurantTable::where('uuid', $tableUuid)
            ->where('branch_id', $branchId)
            ->firstOrFail();

        // Obtener todos los pedidos de esta mesa del día actual
        $orders = Order::with(['items.menuItem.product', 'waiter'])
            ->where('table_id', $table->id)
            ->where('branch_id', $branchId)
            ->whereDate('created_at', today())
            ->orderBy('created_at', 'asc')
            ->get();

        // Transformar para el frontend
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
                'total_amount' => $orders->sum('total'),
                'first_order_at' => $orders->first()?->created_at?->toIso8601String(),
                'last_order_at' => $orders->last()?->created_at?->toIso8601String(),
            ],
        ];

        return response()->json(['data' => $response]);
    }

    /**
     * POST /api/v1/kitchen/orders/{uuid}/assign-cook
     * Asigna un cocinero responsable al pedido.
     */
    public function assignCook(AssignCookRequest $request, string $uuid): JsonResponse
    {
        $validated = $request->validated();
        $order = Order::where('uuid', $uuid)->firstOrFail();

        if (!$order->status->isInKitchenQueue()) {
            return response()->json([
                'error' => 'invalid_state',
                'message' => 'Solo se pueden asignar cocineros a pedidos en cola de cocina (confirmed o preparing).',
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
     * Cambia la prioridad de un pedido.
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
