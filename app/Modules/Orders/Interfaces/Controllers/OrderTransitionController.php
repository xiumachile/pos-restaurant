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
use Modules\Orders\Interfaces\Requests\CheckoutRequest;
use Modules\Payments\Domain\Entities\Bill;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

    public function readyForPickup(Request $request, string $uuid): JsonResponse
    {
        return $this->transition($request, $uuid, OrderStatus::READY_FOR_PICKUP, 'readyForPickup');
    }

    public function pickup(Request $request, string $uuid): JsonResponse
    {
        return $this->transition($request, $uuid, OrderStatus::PICKED_UP, 'pickup');
    }

    public function dispatch(Request $request, string $uuid): JsonResponse
    {
        return $this->transition($request, $uuid, OrderStatus::DISPATCHED, 'dispatch');
    }

    public function deliver(Request $request, string $uuid): JsonResponse
    {
        return $this->transition($request, $uuid, OrderStatus::DELIVERED, 'deliver');
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

            // IDEMPOTENCIA DE DOMINIO: si el pedido YA está cancelado,
            // retornar éxito sin disparar eventos de nuevo.
            if ($order->status === OrderStatus::CANCELLED) {
                $order->load(['items', 'table', 'waiter']);
                return OrderResource::make($order)->response();
            }

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

            // IDEMPOTENCIA DE DOMINIO: si el pedido YA está en el estado objetivo,
            // retornar éxito sin disparar eventos de nuevo.
            // Esto permite reintentos seguros después de timeout o pérdida de respuesta,
            // incluso si el cliente usa una idempotency_key diferente.
            // Complementa (no reemplaza) el IdempotencyKeyMiddleware a nivel HTTP.
            if ($order->status === $newStatus) {
                $order->load(['items', 'table', 'waiter']);
                return OrderResource::make($order)->response();
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

    /**
     * Checkout universal para pedidos sin mesa (delivery/takeout).
     * 
     * Flujo:
     * 1. Valida que el pedido sea cobrable (isChargeable)
     * 2. Crea una bill si no existe
     * 3. Procesa el pago
     * 4. Transiciona al estado apropiado según el canal:
     *    - takeout: picked_up → paid
     *    - delivery: delivered → paid
     */
    public function checkout(CheckoutRequest $request, string $uuid): JsonResponse
    {
        $order = $this->getOrder($uuid);
        $this->authorize('pay', $order);
        $validated = $request->validated();

        // Validar que el pedido sea cobrable
        if (!$order->status->isChargeable()) {
            return response()->json([
                'error' => 'order_not_chargeable',
                'message' => "El pedido no puede ser cobrado en estado: {$order->status->value}",
                'current_status' => $order->status->value,
            ], 422);
        }

        DB::beginTransaction();

        try {
            // 1. Crear bill si no existe
            $bill = $order->bills()->first();
            
            if (!$bill) {
                $bill = Bill::create([
                    'company_id' => $order->company_id,
                    'branch_id' => $order->branch_id,
                    'order_id' => $order->id,
                    'bill_number' => 'BILL-' . strtoupper(Str::random(8)),
                    'type' => 'single',
                    'subtotal' => $order->subtotal,
                    'tax_amount' => $order->tax_amount,
                    'discount_amount' => $order->discount_amount,
                    'tip_amount' => $validated['tip_amount'] ?? 0,
                    'total' => $order->total + ($validated['tip_amount'] ?? 0),
                    'paid_amount' => 0,
                    'remaining_amount' => $order->total + ($validated['tip_amount'] ?? 0),
                    'status' => 'open',
                    'guest_count' => 1,
                    'idempotency_key' => $request->header('Idempotency-Key'),
                ]);
            }

            // 2. Determinar estado objetivo según el canal
            $targetStatus = match($order->fulfillment_channel?->value) {
                'delivery' => OrderStatus::DELIVERED,
                'pickup', 'takeout' => OrderStatus::PICKED_UP,
                default => OrderStatus::SERVED,
            };

            // 3. Transicionar paso a paso hasta llegar al estado objetivo
            // El StateMachine exige el camino completo:
            // delivery: confirmed → preparing → ready → dispatched → delivered
            // pickup:   confirmed → preparing → ready → ready_for_pickup → picked_up
            // onsite:   confirmed → preparing → ready → served
            while ($order->status !== $targetStatus && $order->status !== OrderStatus::PAID) {
                $nextStatus = $this->getNextStatusTowardsTarget($order, $targetStatus);
                if (!$nextStatus) {
                    throw new \Exception("No se puede determinar el siguiente estado para checkout desde: {$order->status->value}");
                }
                $order = $this->stateMachine->transition($order, $nextStatus);
            }

            // 4. Procesar pago (transicionar a PAID)
            $order->cashier_id = $request->user()->id;
            $order->save();
            
            $order = $this->stateMachine->transition($order, OrderStatus::PAID);

            // 5. Actualizar bill como pagada
            $bill->update([
                'status' => 'paid',
                'paid_amount' => $bill->total,
                'remaining_amount' => 0,
            ]);

            DB::commit();

            $order->load(['items', 'table', 'waiter']);

            return response()->json([
                'data' => OrderResource::make($order),
                'bill' => [
                    'uuid' => $bill->uuid,
                    'bill_number' => $bill->bill_number,
                    'total' => $bill->total,
                    'status' => $bill->status,
                ],
                'message' => 'Pedido cobrado exitosamente',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Checkout error', [
                'order_uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'error' => 'checkout_failed',
                'message' => 'Error al procesar el cobro: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Determina el siguiente estado válido en el camino hacia el objetivo.
     * Respeta el flujo del StateMachine según el canal de fulfillment.
     */
    protected function getNextStatusTowardsTarget(Order $order, OrderStatus $target): ?OrderStatus
    {
        $current = $order->status;
        
        // Definir el camino según el canal de fulfillment
        $path = match($order->fulfillment_channel?->value) {
            'delivery' => [
                OrderStatus::CONFIRMED,
                OrderStatus::PREPARING,
                OrderStatus::READY,
                OrderStatus::DISPATCHED,
                OrderStatus::DELIVERED,
                OrderStatus::PAID,
            ],
            'pickup', 'takeout' => [
                OrderStatus::CONFIRMED,
                OrderStatus::PREPARING,
                OrderStatus::READY,
                OrderStatus::READY_FOR_PICKUP,
                OrderStatus::PICKED_UP,
                OrderStatus::PAID,
            ],
            default => [
                OrderStatus::CONFIRMED,
                OrderStatus::PREPARING,
                OrderStatus::READY,
                OrderStatus::SERVED,
                OrderStatus::PAID,
            ],
        };
        
        // Encontrar la posición del estado actual en el path
        $currentIdx = array_search($current, $path);
        
        if ($currentIdx === false) {
            // Estado no está en el path (ej: draft, cancelled, ya pagado)
            return null;
        }
        
        // Retornar el siguiente estado del path (o null si llegamos al final)
        return $path[$currentIdx + 1] ?? null;
    }

}
