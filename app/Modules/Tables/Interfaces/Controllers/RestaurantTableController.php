<?php

namespace Modules\Tables\Interfaces\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Tables\Application\Queries\GetActiveOrdersForTableQuery;
use Modules\Tables\Application\Queries\GetAllTablesQuery;
use Modules\Tables\Application\UseCases\ChangeTableStatusUseCase;
use Modules\Tables\Application\UseCases\CreateTableUseCase;
use Modules\Tables\Application\UseCases\UpdateTableUseCase;
use Modules\Tables\Domain\Exceptions\InvalidTableStatusTransition;
use Modules\Tables\Domain\ValueObjects\TableStatus;
use Modules\Tables\Interfaces\Requests\StoreTableRequest;
use Modules\Tables\Interfaces\Requests\UpdateTableRequest;
use Modules\Tables\Interfaces\Requests\UpdateTableStatusRequest;
use Modules\Tables\Interfaces\Resources\TableCollection;
use Modules\Tables\Interfaces\Resources\TableResource;
use Modules\Orders\Interfaces\Resources\OrderResource;

/**
 * Controller de mesas.
 * 
 * RESPONSABILIDAD:
 * - Recibir requests HTTP
 * - Delegar a Application Services
 * - Retornar responses JSON
 * 
 * NO DEBE:
 * - Contener lógica de negocio
 * - Acceder directamente a modelos (excepto para queries simples)
 * - Manejar transacciones de base de datos
 */
class RestaurantTableController extends Controller
{
    public function __construct(
        private GetAllTablesQuery $getAllTablesQuery,
        private CreateTableUseCase $createTableUseCase,
        private UpdateTableUseCase $updateTableUseCase,
        private ChangeTableStatusUseCase $changeTableStatusUseCase,
        private GetActiveOrdersForTableQuery $getActiveOrdersForTableQuery
    ) {
    }

    /**
     * GET /api/v1/tables
     */
    public function index(): JsonResponse
    {
        $tables = $this->getAllTablesQuery->execute();
        return (new TableCollection($tables))->response();
    }

    /**
     * POST /api/v1/tables
     */
    public function store(StoreTableRequest $request): JsonResponse
    {
        $table = $this->createTableUseCase->execute([
            'company_id' => $request->user()->company_id,
            'branch_id' => $request->user()->branch_id,
            'area_code' => $request->area_code,
            'area_name_translations' => $request->area_name_translations,
            'table_number' => $request->table_number,
            'capacity' => $request->capacity,
        ]);

        return (new TableResource($table))->response()->setStatusCode(201);
    }

    /**
     * PUT /api/v1/tables/{uuid}
     */
    public function update(UpdateTableRequest $request, string $uuid): JsonResponse
    {
        $table = $this->updateTableUseCase->execute($uuid, $request->validated());
        return (new TableResource($table))->response();
    }

    /**
     * PUT /api/v1/tables/{uuid}/status
     */
    public function updateStatus(UpdateTableStatusRequest $request, string $uuid): JsonResponse
    {
        try {
            $table = $this->changeTableStatusUseCase->execute(
                $uuid,
                $request->status,
                $request->current_order_id
            );

            return (new TableResource($table))->response();

        } catch (InvalidTableStatusTransition $e) {
            return response()->json([
                'error' => 'invalid_status_transition',
                'message' => $e->getMessage(),
                'current_status' => $request->current_status ?? 'unknown',
                'requested_status' => $request->status,
            ], 422);
        }
    }

    /**
     * GET /api/v1/tables/{uuid}/orders
     */
    public function orders(string $uuid): JsonResponse
    {
        $orders = $this->getActiveOrdersForTableQuery->execute($uuid);

        return OrderResource::collection($orders)
            ->response()
            ->setStatusCode(200);
    }
}
