<?php

namespace Modules\Tables\Interfaces\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Tables\Domain\Services\FloorPlanService;
use Modules\Tables\Interfaces\Requests\StoreDiningZoneRequest;
use Modules\Tables\Interfaces\Requests\UpdateDiningZoneRequest;
use Modules\Tables\Interfaces\Resources\DiningZoneResource;

/**
 * Controller de zonas del restaurante.
 *
 * Endpoints:
 * - POST   /api/v1/dining-zones          Crear zona
 * - PATCH  /api/v1/dining-zones/{uuid}   Actualizar zona
 * - DELETE /api/v1/dining-zones/{uuid}   Eliminar zona (si no tiene mesas)
 */
class DiningZoneController extends Controller
{
    public function __construct(
        private FloorPlanService $floorPlanService
    ) {
    }

    /**
     * POST /api/v1/dining-zones
     */
    public function store(StoreDiningZoneRequest $request): JsonResponse
    {
        $user = $request->user();

        $zone = $this->floorPlanService->createZone(
            $user->company_id,
            $user->branch_id,
            $request->validated()
        );

        return (new DiningZoneResource($zone))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * PATCH /api/v1/dining-zones/{uuid}
     */
    public function update(UpdateDiningZoneRequest $request, string $uuid): JsonResponse
    {
        try {
            $zone = $this->floorPlanService->updateZone(
                $uuid,
                $request->user()->branch_id,
                $request->validated()
            );

            return (new DiningZoneResource($zone))->response();
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Zona no encontrada en esta sucursal.',
            ], 404);
        }
    }

    /**
     * DELETE /api/v1/dining-zones/{uuid}
     */
    public function destroy(\Illuminate\Http\Request $request, string $uuid): JsonResponse
    {
        try {
            $this->floorPlanService->deleteZone(
                $uuid,
                $request->user()->branch_id
            );

            return response()->json([
                'message' => 'Zona eliminada correctamente.',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Zona no encontrada en esta sucursal.',
            ], 404);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 409); // Conflict
        }
    }
}
