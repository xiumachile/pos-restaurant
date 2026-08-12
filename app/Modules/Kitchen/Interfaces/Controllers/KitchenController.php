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
     * POST /api/v1/kitchen/orders/{uuid}/assign-cook
     * Asigna un cocinero responsable al pedido.
     */
    public function assignCook(AssignCookRequest $request, string $uuid): JsonResponse
    {
        $validated = $request->validated();
        $order = Order::where('uuid', $uuid)->firstOrFail();

        // Validar que el pedido esté en estado de cocina
        if (!$order->status->isInKitchenQueue()) {
            return response()->json([
                'error' => 'invalid_state',
                'message' => 'Solo se pueden asignar cocineros a pedidos en cola de cocina (confirmed o preparing).',
            ], 422);
        }

        // Buscar el cocinero por UUID
        $cook = User::where('uuid', $validated['cook_uuid'])
            ->where('role', 'kitchen')
            ->where('company_id', $order->company_id)
            ->firstOrFail();

        // Asignar el cocinero
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

        // Solo se puede cambiar prioridad en pedidos activos
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
