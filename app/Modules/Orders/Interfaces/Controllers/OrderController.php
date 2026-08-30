<?php

namespace Modules\Orders\Interfaces\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Orders\Domain\Services\OrderService;
use Modules\Orders\Interfaces\Requests\CreateOrderRequest;
use Modules\Orders\Interfaces\Requests\UpdateOrderRequest;
use Modules\Orders\Interfaces\Resources\OrderResource;

class OrderController extends Controller
{
    public function __construct(
        private OrderService $orderService
    ) {}

    /**
     * GET /api/v1/orders
     * Lista pedidos con filtros opcionales.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $filters = [
            'status' => $request->input('status'),
            'table_uuid' => $request->input('table_uuid'),
            'today_only' => $request->boolean('today_only'),
            'limit' => $request->integer('limit', 50),
        ];

        $orders = $this->orderService->listOrders($user->branch_id, $filters);

        return OrderResource::collection($orders)->response();
    }

    /**
     * POST /api/v1/orders
     */
    public function store(CreateOrderRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $order = $this->orderService->createOrder(
            $user->branch_id,
            $user->company_id,
            $user->id,
            $validated
        );

        return OrderResource::make($order)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * PUT /api/v1/orders/{uuid}
     * Actualiza un pedido existente (last-write-wins).
     */
    public function update(UpdateOrderRequest $request, string $uuid): JsonResponse
    {
        $validated = $request->validated();
        
        try {
            $order = $this->orderService->updateOrder(
                $uuid,
                $request->user()->company_id,
                $validated
            );

            return OrderResource::make($order)->response();
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return response()->json([
                'error' => 'order_not_modifiable',
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        }
    }

    /**
     * GET /api/v1/orders/{uuid}
     */
    public function show(string $uuid): JsonResponse
    {
        $order = \Modules\Orders\Domain\Entities\Order::with(["items", "table", "waiter"])
            ->where("uuid", $uuid)
            ->firstOrFail();

        $this->authorize("view", $order);

        return OrderResource::make($order)->response();
    }

    /**
     * DELETE /api/v1/orders/{uuid}
     */
    public function destroy(string $uuid): JsonResponse
    {
        $order = \Modules\Orders\Domain\Entities\Order::where('uuid', $uuid)
            ->where('company_id', request()->user()->company_id)
            ->firstOrFail();

        $this->authorize('delete', $order);

        if (!$order->isEditable()) {
            return response()->json([
                'error' => 'order_not_modifiable',
                'message' => 'Solo se pueden eliminar pedidos en estado draft.',
            ], 422);
        }

        $order->delete();

        return response()->json(['message' => 'Pedido eliminado correctamente.']);
    }
}
