<?php

namespace Modules\Kitchen\Interfaces\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Kitchen\Domain\Services\KitchenQueueService;
use Modules\Kitchen\Interfaces\Resources\KitchenOrderResource;
use Modules\Orders\Domain\Entities\Order;

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

        // Convertir Collection agrupada a estructura JSON
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
    public function assignCook(Request $request, string $uuid): JsonResponse
    {
        $validated = $request->validate([
            'cook_uuid' => 'required|uuid|exists:users,uuid',
        ]);

        $order = Order::where('uuid', $uuid)->firstOrFail();

        // Validar que el pedido esté en estado de cocina
        if (!$order->status->isInKitchenQueue()) {
            return response()->json([
                'error' => 'invalid_state',
                'message' => 'Solo se pueden asignar cocineros a pedidos en cola de cocina.',
            ], 422);
        }

        // Aquí podrías agregar el campo assigned_cook_id en una migración futura
        // Por ahora retornamos el pedido actualizado
        $order->load(['items', 'table', 'waiter']);

        return KitchenOrderResource::make($order)->response();
    }
}
