<?php

namespace Modules\Orders\Interfaces\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Orders\Interfaces\Requests\CreateOrderRequest;
use Modules\Orders\Interfaces\Requests\UpdateOrderRequest;
use Modules\Orders\Interfaces\Resources\OrderResource;

class OrderController extends Controller
{
    /**
     * GET /api/v1/orders
     * Lista pedidos con filtros opcionales.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $orders = Order::with(['items', 'table', 'waiter'])
            ->where('branch_id', $user->branch_id)
            ->when($request->filled('status'), function ($q) use ($request) {
                $status = $request->input('status');
                if (is_string($status) && in_array($status, array_column(OrderStatus::cases(), 'value'))) {
                    $q->where('status', OrderStatus::from($status));
                }
            })
            ->when($request->filled('table_uuid'), function ($q) use ($request) {
                $tableUuid = $request->input('table_uuid');
                $q->whereHas('table', fn($sub) => $sub->where('uuid', $tableUuid));
            })
            ->when($request->boolean('today_only'), function ($q) {
                $q->whereDate('created_at', today());
            })
            ->orderBy('created_at', 'desc')
            ->limit($request->integer('limit', 50))
            ->get();

        return OrderResource::collection($orders)->response();
    }

    /**
     * POST /api/v1/orders
     */
    public function store(CreateOrderRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $tableId = null;
        if (!empty($validated['table_uuid'])) {
            $table = \Modules\Tables\Domain\Entities\RestaurantTable::where('uuid', $validated['table_uuid'])->first();
            $tableId = $table?->id;
        }

        $order = Order::create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'order_number' => $this->generateOrderNumber($user->branch_id),
            'type' => \Modules\Orders\Domain\ValueObjects\OrderType::from($validated['type']),
            'status' => OrderStatus::DRAFT,
            'table_id' => $tableId,
            'waiter_id' => $user->id,
            'notes' => $validated['notes'] ?? null,
            'subtotal' => 0,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total' => 0,
        ]);

        $order->load(['items', 'table', 'waiter']);

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
        $order = Order::where('uuid', $uuid)->firstOrFail();

        if (!$order->isEditable()) {
            return response()->json([
                'error' => 'order_not_modifiable',
                'message' => 'No se pueden modificar pedidos ya confirmados.',
            ], 422);
        }

        $validated = $request->validated();

        // Actualizar mesa si se proporcionó
        if (array_key_exists('table_uuid', $validated)) {
            $table = null;
            if ($validated['table_uuid']) {
                $table = \Modules\Tables\Domain\Entities\RestaurantTable::where('uuid', $validated['table_uuid'])->first();
            }
            $order->table_id = $table?->id;
        }

        // Actualizar campos permitidos
        if (isset($validated['status'])) {
            $order->status = OrderStatus::from($validated['status']);
        }

        if (isset($validated['notes'])) {
            $order->notes = $validated['notes'];
        }

        if (isset($validated['guest_count'])) {
            $order->guest_count = $validated['guest_count'];
        }

        $order->save();
        $order->load(['items', 'table', 'waiter']);

        return OrderResource::make($order)->response();
    }


    /**
     * GET /api/v1/orders/{uuid}
     */
    public function show(string $uuid): JsonResponse
    {
        $order = Order::with(['items', 'table', 'waiter'])
            ->where('uuid', $uuid)
            ->firstOrFail();

        return OrderResource::make($order)->response();
    }

    /**
     * DELETE /api/v1/orders/{uuid}
     */
    public function destroy(string $uuid): JsonResponse
    {
        $order = Order::where('uuid', $uuid)->firstOrFail();

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

    /**
     * Genera número de orden único para la sucursal/día.
     */
    private function generateOrderNumber(int $branchId): string
    {
        $date = now()->format('Ymd');
        $lastOrder = Order::where('branch_id', $branchId)
            ->whereDate('created_at', today())
            ->orderBy('id', 'desc')
            ->first();

        $seq = $lastOrder ? (intval(substr($lastOrder->order_number, -4)) + 1) : 1;

        return sprintf('ORD-%03d-%s-%04d', $branchId, $date, $seq);
    }
}
