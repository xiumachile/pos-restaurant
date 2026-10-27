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

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $floorPlanData = $this->service->getFloorPlan($user->company_id, $user->branch_id);
        
        return response()->json([
            'data' => new FloorPlanResource($floorPlanData),
        ]);
    }

    public function update(SaveFloorPlanRequest $request): JsonResponse
    {
        $user = $request->user();
        
        // Guardamos y obtenemos el conteo de mesas actualizadas
        $result = $this->service->saveFloorPlan($user->branch_id, $request->validated());
        
        // Obtenemos el estado actualizado para devolverlo al frontend
        $updatedData = $this->service->getFloorPlan($user->company_id, $user->branch_id);
        
        // Fusionamos el recurso con el dato específico que esperan las pruebas
        $resource = new FloorPlanResource($updatedData);
        $responseData = $resource->toArray($request);
        $responseData['tables_updated'] = $result['tables_updated'] ?? 0;

        return response()->json([
            'message' => 'Plano guardado y sincronizado correctamente',
            'data' => $responseData,
        ]);
    }
}
