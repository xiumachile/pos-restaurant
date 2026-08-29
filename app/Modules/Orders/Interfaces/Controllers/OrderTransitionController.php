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

    public function confirm(Request $request, string $uuid): JsonResponse
    {
        return $this->transition($request, $uuid, OrderStatus::CONFIRMED, 'confirm');
    }

    public function prepare(Request $request, string $uuid): JsonResponse
    {
        return $this->transition($request, $uuid, OrderStatus::PREPARING, 'prepare');
    }

    public function ready(Request $request, string $uuid): JsonResponse
    {
        return $this->transition($request, $uuid, OrderStatus::READY, 'ready');
    }

    public function serve(Request $request, string $uuid): JsonResponse
    {
        return $this->transition($request, $uuid, OrderStatus::SERVED, 'serve');
    }

    public function pay(Request $request, string $uuid): JsonResponse
    {
        $order = $this->getOrder($uuid);
        $this->authorize('pay', $order);

        // Asignar cajero al pagar
        $order->cashier_id = $request->user()->id;
        $order->save();

        return $this->transition($request, $uuid, OrderStatus::PAID, 'pay', skipAuth: true);
    }

    public function close(Request $request, string $uuid): JsonResponse
    {
        return $this->transition($request, $uuid, OrderStatus::CLOSED, 'close');
    }

    public function cancel(CancelOrderRequest $request, string $uuid): JsonResponse
    {
        try {
            $order = $this->getOrder($uuid);
            $this->authorize('cancel', $order);

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
     * Realiza una transición genérica con autorización.
     */
    protected function transition(
        Request $request,
        string $uuid,
        OrderStatus $newStatus,
        string $policyMethod,
        bool $skipAuth = false
    ): JsonResponse {
        try {
            $order = $this->getOrder($uuid);

            if (!$skipAuth) {
                $this->authorize($policyMethod, $order);
            }

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
        return Order::where('uuid', $uuid)
            ->where('company_id', request()->user()->company_id)
            ->firstOrFail();
    }
}
