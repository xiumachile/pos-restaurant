<?php

namespace Modules\Reports\Interfaces\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Reports\Domain\Services\PurchaseReportService;
use Modules\Reports\Interfaces\Requests\ReportFilterRequest;

class PurchaseReportController extends Controller
{
    public function __construct(
        private PurchaseReportService $service
    ) {}

    /**
     * GET /api/v1/reports/purchases/kpis
     */
    public function kpis(ReportFilterRequest $request): JsonResponse
    {
        $user = $request->user();
        $range = $request->getDateRange(30); // Default 30 días

        $kpis = $this->service->getKPIs(
            $user->company_id,
            $user->branch_id,
            $range['from'],
            $range['to']
        );

        return response()->json([
            'data' => $kpis,
            'filters' => [
                'from' => $range['from']->toIso8601String(),
                'to' => $range['to']->toIso8601String(),
            ],
        ]);
    }

    /**
     * GET /api/v1/reports/purchases/by-document-type
     */
    public function byDocumentType(ReportFilterRequest $request): JsonResponse
    {
        $user = $request->user();
        $range = $request->getDateRange(30);

        $data = $this->service->getByDocumentType(
            $user->company_id,
            $user->branch_id,
            $range['from'],
            $range['to']
        );

        return response()->json(['data' => $data]);
    }

    /**
     * GET /api/v1/reports/purchases/top-suppliers
     */
    public function topSuppliers(ReportFilterRequest $request): JsonResponse
    {
        $user = $request->user();
        $range = $request->getDateRange(30);
        $limit = min((int) $request->query('limit', 10), 50);

        $data = $this->service->getTopSuppliers(
            $user->company_id,
            $user->branch_id,
            $range['from'],
            $range['to'],
            $limit
        );

        return response()->json(['data' => $data]);
    }

    /**
     * GET /api/v1/reports/purchases/top-ingredients
     */
    public function topIngredients(ReportFilterRequest $request): JsonResponse
    {
        $user = $request->user();
        $range = $request->getDateRange(30);
        $limit = min((int) $request->query('limit', 10), 50);

        $data = $this->service->getTopIngredients(
            $user->company_id,
            $user->branch_id,
            $range['from'],
            $range['to'],
            $limit
        );

        return response()->json(['data' => $data]);
    }
}
