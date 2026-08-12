<?php

namespace Modules\Orders\Interfaces\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Domain\ValueObjects\OrderStatus;
use Modules\Orders\Domain\ValueObjects\OrderType;
use Modules\Orders\Interfaces\Requests\CreateOrderRequest;
use Modules\Orders\Interfaces\Resources\OrderResource;
use Modules\Tables\Domain\Entities\RestaurantTable;

class OrderController extends Controller
{
    /**
     * GET /api/v1/orders
     * Lista pedidos filtrados según el rol del usuario.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Order::query()->with(['items', 'table', 'waiter']);

        // Filtros por rol
        if ($user->role === 'waiter') {
            // waiter solo ve sus propios pedidos
            $query->where('waiter_id', $user->id);
        } elseif ($user->role === 'kitchen') {
            // kitchen solo ve cola de cocina
            $query->inKitchenQueue();
        } elseif ($user->role === 'cashier') {
            // cashier ve pedidos esperando pago
            $query->awaitingPayment();
        }
        // admin/manager ven todos

        // Filtros opcionales
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('table_uuid')) {
            $table = RestaurantTable::where('uuid', $request->input('table_uuid'))->first();
            if ($table) {
                $query->where('table_id', $table->id);
            }
        }

        if ($request->boolean('active_only', true)) {
            $query->active();
        }

        $orders = $query->latest()->paginate($request->integer('per_page', 15));

        return OrderResource::collection($orders)->response();
    }

    /**
     * POST /api/v1/orders
     * Crea un nuevo pedido en estado draft.
     */
    public function store(CreateOrderRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $tableId = null;
        if (!empty($validated['table_uuid'])) {
            $table = RestaurantTable::where('uuid', $validated['table_uuid'])->first();
            $tableId = $table?->id;
        }

        $order = Order::create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'order_number' => $this->generateOrderNumber($user->branch_id),
            'type' => OrderType::from($validated['type']),
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
     * GET /api/v1/orders/{uuid}
     * Detalle de un pedido. Usa el policy para autorizar.
     */
    public function show(string $uuid): JsonResponse
    {
        $order = Order::with(['items.modifiers', 'table', 'waiter', 'cashier'])
            ->where('uuid', $uuid)
            ->firstOrFail();

        $this->authorize('view', $order);

        return OrderResource::make($order)->response();
    }

    /**
     * DELETE /api/v1/orders/{uuid}
     * Elimina un pedido draft. Usa el policy para autorizar.
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

    protected function generateOrderNumber(int $branchId): string
    {
        $prefix = 'ORD-' . str_pad($branchId, 3, '0', STR_PAD_LEFT);
        $date = now()->format('Ymd');

        $count = Order::where('branch_id', $branchId)
            ->whereDate('created_at', today())
            ->count() + 1;

        return "{$prefix}-{$date}-" . str_pad($count, 4, '0', STR_PAD_LEFT);
    }
}
