<?php

namespace Modules\Tables\Interfaces\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Tables\Domain\Entities\RestaurantTable;
use Modules\Tables\Domain\Exceptions\InvalidTableStatusTransition;
use Modules\Tables\Domain\ValueObjects\TableStatus;
use Modules\Tables\Interfaces\Requests\StoreTableRequest;
use Modules\Tables\Interfaces\Requests\UpdateTableRequest;
use Modules\Tables\Interfaces\Requests\UpdateTableStatusRequest;
use Modules\Tables\Interfaces\Resources\TableCollection;
use Modules\Tables\Interfaces\Resources\TableResource;
use Modules\Orders\Domain\Entities\Order;
use Modules\Orders\Interfaces\Resources\OrderResource;

class RestaurantTableController extends Controller
{
    /**
     * GET /api/v1/tables
     */
    public function index(Request $request): JsonResponse
    {
        $tables = RestaurantTable::query()
            ->ordered()
            ->get();

        return (new TableCollection($tables))->response();
    }

    /**
     * POST /api/v1/tables
     */
    public function store(StoreTableRequest $request): JsonResponse
    {
        $table = RestaurantTable::create([
            'company_id' => $request->user()->company_id,
            'branch_id' => $request->user()->branch_id,
            'area_code' => $request->area_code,
            'area_name_translations' => $request->area_name_translations,
            'table_number' => $request->table_number,
            'capacity' => $request->capacity,
            'status' => TableStatus::Available->value,
        ]);

        return (new TableResource($table))->response()->setStatusCode(201);
    }

    /**
     * PUT /api/v1/tables/{uuid}
     */
    public function update(UpdateTableRequest $request, string $uuid): JsonResponse
    {
        $table = RestaurantTable::where('uuid', $uuid)->firstOrFail();

        $table->update($request->only([
            'area_code',
            'area_name_translations',
            'table_number',
            'capacity',
        ]));

        return (new TableResource($table))->response();
    }

    /**
     * PUT /api/v1/tables/{uuid}/status
     */
    public function updateStatus(UpdateTableStatusRequest $request, string $uuid): JsonResponse
    {
        $table = RestaurantTable::where('uuid', $uuid)->firstOrFail();
        $newStatus = TableStatus::from($request->status);

        try {
            match ($newStatus) {
                TableStatus::Occupied => $table->occupy($request->current_order_id),
                TableStatus::Billing => $table->requestBilling(),
                TableStatus::Available => $table->hasActiveOrder() ? $table->free() : $table->enable(),
                TableStatus::Maintenance => $table->setMaintenance(),
            };

            $table->save();

            return (new TableResource($table))->response();

        } catch (InvalidTableStatusTransition $e) {
            return response()->json([
                'error' => 'invalid_status_transition',
                'message' => $e->getMessage(),
                'current_status' => $table->status->value,
                'requested_status' => $newStatus->value,
            ], 422);
        }
    }

    /**
     * GET /api/v1/tables/{uuid}/orders
     * 
     * Lista pedidos EN CURSO de una mesa.
     * 
     * Estados considerados "en curso":
     * - draft: recién creado (garzón tomando pedido)
     * - confirmed: confirmado en cocina
     * - preparing: en preparación
     * - ready: listo para servir
     * - served: servido al cliente, esperando cobro
     * 
     * Estados EXCLUIDOS (ya no son "en curso"):
     * - paid: ya fue cobrado ✅ FIX
     * - closed: cuenta cerrada
     * - cancelled: cancelado
     * 
     * Esto garantiza que al abrir una mesa pagada, el carrito esté vacío.
     */
    public function orders(string $uuid): JsonResponse
    {
        $table = RestaurantTable::where('uuid', $uuid)->firstOrFail();

        $orders = Order::query()
            ->where('table_id', $table->id)
            ->whereNotIn('status', ['paid', 'closed', 'cancelled'])
            ->with(['items', 'waiter'])
            ->orderBy('created_at', 'asc')
            ->get();

        return OrderResource::collection($orders)
            ->response()
            ->setStatusCode(200);
    }
}
