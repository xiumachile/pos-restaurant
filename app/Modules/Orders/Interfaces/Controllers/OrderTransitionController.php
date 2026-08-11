<?php

namespace Modules\Orders\Interfaces\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\Exceptions\InvalidOrderTransitionException;
use Modules\Orders\Domain\Services\OrderStateMachine;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Orders\Interfaces\Requests\CancelOrderRequest;
use Modules\Orders\Interfaces\Resources\OrderResource;

class OrderTransitionController extends Controller
{
    public function __construct(
        private OrderStateMachine $stateMachine
    ) {}

    /**
     * POST /api/v1/orders/{uuid}/confirm
     */
    public function confirm(Request $request, string $uuid): JsonResponse
    {
        return $this->transition($uuid, OrderStatus::CONFIRMED);
    }

    /**
     * POST /api/v1/orders/{uuid}/prepare
     */
    public function prepare(Request $request, string $uuid): JsonResponse
    {
        return $this->transition($uuid, OrderStatus::PREPARING);
    }

    /**
     * POST /api/v1/orders/{uuid}/ready
     */
    public function ready(Request $request, string $uuid): JsonResponse
    {
        return $this->transition($uuid, OrderStatus::READY);
    }

    /**
     * POST /api/v1/orders/{uuid}/serve
     */
    public function serve(Request $request, string $uuid): JsonResponse
    {
        return $this->transition($uuid, OrderStatus::SERVED);
    }

    /**
     * POST /api/v1/orders/{uuid}/pay
     */
    public function pay(Request $request, string $uuid): JsonResponse
    {
        $order = $this->getOrder($uuid);
        
        // Asignar cajero al pagar
        $order->cashier_id = $request->user()->id;
        $order->save();

        return $this->transition($uuid, OrderStatus::PAID);
    }

    /**
     * POST /api/v1/orders/{uuid}/close
     */
    public function close(Request $request, string $uuid): JsonResponse
    {
        return $this->transition($uuid, OrderStatus::CLOSED);
    }

    /**
     * POST /api/v1/orders/{uuid}/cancel
     */
    public function cancel(CancelOrderRequest $request, string $uuid): JsonResponse
    {
        try {
            $order = $this->getOrder($uuid);
            $order = $this->stateMachine->transition(
                $order,
                OrderStatus::CANCELLED,
                $request->input('reason')
            );

            $order->load(['items', 'table', 'waiter']);

            return OrderResource::make($order)->response();
        } catch (InvalidOrderTransitionException $e) {
            return response()->json([
                'error' => 'invalid_transition',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Realiza una transición genérica.
     */
    protected function transition(string $uuid, OrderStatus $newStatus): JsonResponse
    {
        try {
            $order = $this->getOrder($uuid);
            $order = $this->stateMachine->transition($order, $newStatus);

            $order->load(['items', 'table', 'waiter']);

            return OrderResource::make($order)->response();
        } catch (InvalidOrderTransitionException $e) {
            return response()->json([
                'error' => 'invalid_transition',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    protected function getOrder(string $uuid): Order
    {
        return Order::where('uuid', $uuid)->firstOrFail();
    }
}
