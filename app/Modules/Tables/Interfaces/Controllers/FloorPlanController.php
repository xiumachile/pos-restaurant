<?php

namespace Modules\Tables\Interfaces\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Tables\Domain\Services\FloorPlanService;
use Modules\Tables\Interfaces\Requests\SaveFloorPlanRequest;
use Modules\Tables\Interfaces\Resources\FloorPlanResource;

/**
 * Controller del floor plan.
 *
 * Endpoints:
 * - GET  /api/v1/floor-plan       Carga layout completo
 * - PUT  /api/v1/floor-plan       Guarda posiciones (transacción)
 */
class FloorPlanController extends Controller
{
    public function __construct(
        private FloorPlanService $floorPlanService
    ) {
    }

    /**
     * GET /api/v1/floor-plan
     *
     * Retorna todas las zonas del branch + todas las mesas con sus posiciones.
     */
    public function show(\Illuminate\Http\Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $this->floorPlanService->getFloorPlan(
            $user->company_id,
            $user->branch_id
        );

        return (new FloorPlanResource($data))->response();
    }

    /**
     * PUT /api/v1/floor-plan
     *
     * Guarda las posiciones de todas las mesas en transacción atómica.
     */
    public function update(SaveFloorPlanRequest $request): JsonResponse
    {
        try {
            $tables = $this->floorPlanService->saveFloorPlan(
                $request->user()->branch_id,
                $request->validated()['tables']
            );

            return response()->json([
                'message' => 'Floor plan actualizado correctamente.',
                'data' => [
                    'tables_updated' => $tables->count(),
                ],
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
