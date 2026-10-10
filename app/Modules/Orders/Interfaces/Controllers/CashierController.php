<?php

namespace Modules\Orders\Interfaces\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Interfaces\Resources\OrderResource;

class CashierController extends Controller
{
    /**
     * GET /api/v1/cashier/active
     * Pedidos served esperando pago.
     */
    public function active(Request $request): JsonResponse
    {
        $channel = $request->input('channel'); // dine_in, delivery, takeout, uber_eats, rappi

        $query = Order::with(['items', 'table', 'waiter'])
            ->awaitingPayment()
            ->orderBy('served_at', 'asc');

        // Filtro opcional por tipo de pedido (OrderType)
        if ($channel) {
            $query->where('type', $channel);
        }

        $orders = $query->get();

        return OrderResource::collection($orders)->response();
    }
}
