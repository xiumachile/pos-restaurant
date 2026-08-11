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
    public function active(): JsonResponse
    {
        $orders = Order::with(['items', 'table', 'waiter'])
            ->awaitingPayment()
            ->orderBy('served_at', 'asc')
            ->get();

        return OrderResource::collection($orders)->response();
    }
}
