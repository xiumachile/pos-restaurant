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

class RestaurantTableController extends Controller
{
    /**
     * GET /api/v1/tables
     * Lista todas las mesas de la sucursal agrupadas por área.
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
     * Crea una nueva mesa en la sucursal.
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
     * Actualiza una mesa existente.
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
     * Cambia el estado de la mesa usando la máquina de estados.
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
}
