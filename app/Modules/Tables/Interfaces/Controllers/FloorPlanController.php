<?php

namespace Modules\Tables\Interfaces\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Tables\Domain\Services\FloorPlanService;
use Modules\Tables\Interfaces\Requests\SaveFloorPlanRequest;
use Modules\Tables\Interfaces\Resources\FloorPlanResource;

class FloorPlanController extends Controller
{
    private FloorPlanService $service;

    public function __construct(FloorPlanService $service)
    {
        $this->service = $service;
    }

    /**
     * GET /api/v1/floor-plan
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $floorPlan = $this->service->getFloorPlan($user->company_id, $user->branch_id);
        
        return response()->json([
            'data' => new FloorPlanResource($floorPlan),
        ]);
    }

    /**
     * PUT /api/v1/floor-plan
     */
    public function update(SaveFloorPlanRequest $request): JsonResponse
    {
        $user = $request->user();
        
        $result = $this->service->saveFloorPlan(
            $user->branch_id,
            $request->input('tables', []),
            $request->input('decorations', [])
        );
        
        return response()->json([
            'message' => 'Floor plan guardado correctamente',
            'data' => [
                'tables_updated' => $result['tables']->count(),
            ],
        ]);
    }
}
