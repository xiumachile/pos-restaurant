<?php

namespace Modules\Fiscal\Interfaces\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Fiscal\Domain\Entities\DteFolioRange;
use Modules\Fiscal\Domain\ValueObjects\DteType;
use Modules\Fiscal\Interfaces\Requests\LoadCafRequest;
use Modules\Fiscal\Interfaces\Resources\DteFolioRangeResource;

class DteFolioRangeController extends Controller
{
    /**
     * GET /api/v1/fiscal/folios
     * Lista rangos de folios de la sucursal.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = DteFolioRange::where('company_id', $user->company_id)
            ->where('branch_id', $user->branch_id)
            ->orderBy('dte_type')
            ->orderByDesc('folio_initial');

        if ($dteType = $request->query('dte_type')) {
            $query->where('dte_type', (int) $dteType);
        }
        if ($request->query('active_only')) {
            $query->where('is_active', true);
        }

        $ranges = $query->get();

        return DteFolioRangeResource::collection($ranges)->response();
    }

    /**
     * POST /api/v1/fiscal/folios
     * Carga un nuevo CAF (rango de folios autorizado por SII).
     */
    public function store(LoadCafRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        // Verificar que no exista un rango activo con los mismos folios
        $existing = DteFolioRange::where('company_id', $user->company_id)
            ->where('branch_id', $user->branch_id)
            ->where('dte_type', $validated['dte_type'])
            ->where('folio_initial', $validated['folio_initial'])
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Ya existe un rango de folios con ese folio inicial.',
            ], 422);
        }

        $range = DteFolioRange::create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'dte_type' => DteType::from((int) $validated['dte_type']),
            'folio_initial' => $validated['folio_initial'],
            'folio_final' => $validated['folio_final'],
            'folio_current' => $validated['folio_initial'] - 1,
            'caf_xml' => $validated['caf_xml'],
            'authorization_date' => $validated['authorization_date'],
            'authorized_rut' => $validated['authorized_rut'] ?? null,
            'is_active' => true,
        ]);

        return DteFolioRangeResource::make($range)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /api/v1/fiscal/folios/summary
     * Resumen de folios por tipo de DTE.
     */
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();

        $ranges = DteFolioRange::where('company_id', $user->company_id)
            ->where('branch_id', $user->branch_id)
            ->where('is_active', true)
            ->get()
            ->groupBy('dte_type');

        $summary = [];
        foreach (DteType::cases() as $type) {
            $typeRanges = $ranges->get($type->value, collect());
            // Usar closure porque availableFolios() es un método, no una relación
            $totalAvailable = $typeRanges->sum(fn($range) => $range->availableFolios());
            
            $summary[] = [
                'dte_type' => $type->value,
                'dte_type_label' => $type->label(),
                'total_available' => $totalAvailable,
                'ranges_count' => $typeRanges->count(),
                'is_running_low' => $totalAvailable > 0 && $totalAvailable < 100,
                'is_exhausted' => $totalAvailable === 0,
            ];
        }

        return response()->json(['data' => $summary]);
    }
}
