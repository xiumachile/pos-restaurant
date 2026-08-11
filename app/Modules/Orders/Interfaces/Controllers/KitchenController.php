<?php

namespace Modules\Orders\Interfaces\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Interfaces\Resources\OrderResource;

class KitchenController extends Controller
{
    /**
     * GET /api/v1/kitchen/queue
     * Cola de cocina: pedidos confirmed + preparing.
     */
    public function queue(): JsonResponse
    {
        $orders = Order::with(['items', 'table', 'waiter'])
            ->inKitchenQueue()
            ->orderBy('confirmed_at', 'asc')
            ->get();

        return OrderResource::collection($orders)->response();
    }
}
