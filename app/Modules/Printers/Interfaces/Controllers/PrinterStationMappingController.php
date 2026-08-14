<?php

namespace Modules\Printers\Interfaces\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Catalog\Domain\Entities\Category;
use Modules\Printers\Domain\Entities\Printer;
use Modules\Printers\Domain\Entities\PrinterStationMapping;
use Modules\Printers\Interfaces\Requests\CreatePrinterStationMappingRequest;
use Modules\Printers\Interfaces\Resources\PrinterStationMappingResource;

class PrinterStationMappingController extends Controller
{
    /**
     * GET /api/v1/printers/mappings
     * Lista todos los mappings de la sucursal.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $mappings = PrinterStationMapping::where('company_id', $user->company_id)
            ->where('branch_id', $user->branch_id)
            ->with(['printer', 'category'])
            ->orderBy('priority')
            ->get();

        return PrinterStationMappingResource::collection($mappings)->response();
    }

    /**
     * POST /api/v1/printers/mappings
     * Crea un nuevo mapping categoría→impresora.
     */
    public function store(CreatePrinterStationMappingRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        // Verificar que la impresora pertenece a la sucursal
        $printer = Printer::where('uuid', $validated['printer_uuid'])
            ->where('company_id', $user->company_id)
            ->where('branch_id', $user->branch_id)
            ->firstOrFail();

        // Si se especifica category_id, verificar que pertenece a la empresa
        // Usar withoutGlobalScopes para evitar conflicto con BelongsToTenant de Category
        if (!empty($validated['category_id'])) {
            Category::withoutGlobalScopes()
                ->where('id', $validated['category_id'])
                ->where('company_id', $user->company_id)
                ->firstOrFail();
        }

        $mapping = PrinterStationMapping::create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'printer_id' => $printer->id,
            'category_id' => $validated['category_id'] ?? null,
            'product_keywords' => $validated['product_keywords'] ?? null,
            'priority' => $validated['priority'] ?? 1,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        $mapping->load(['printer', 'category']);

        return PrinterStationMappingResource::make($mapping)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * DELETE /api/v1/printers/mappings/{uuid}
     * Elimina un mapping.
     */
    public function destroy(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();

        $mapping = PrinterStationMapping::where('uuid', $uuid)
            ->where('company_id', $user->company_id)
            ->firstOrFail();

        $mapping->delete();

        return response()->json(['message' => 'Mapping eliminado correctamente.'], 200);
    }
}
