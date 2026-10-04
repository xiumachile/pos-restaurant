<?php

namespace Modules\Reports\Interfaces\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Reports\Domain\Services\ReportService;
use Modules\Reports\Interfaces\Requests\ReportFilterRequest;

class ReportController extends Controller
{
    public function __construct(
        private ReportService $reportService
    ) {}

    /**
     * GET /api/v1/reports/dashboard
     * KPIs con filtro de fechas
     */
    public function dashboard(ReportFilterRequest $request): JsonResponse
    {
        $user = $request->user();
        $range = $request->getDateRange(7); // Default 7 días

        $kpis = $this->reportService->getDashboardKPIs(
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
     * GET /api/v1/reports/top-products
     * Ranking con filtro de fechas
     */
    public function topProducts(ReportFilterRequest $request): JsonResponse
    {
        $user = $request->user();
        $range = $request->getDateRange(7);
        $limit = min((int) $request->query('limit', 10), 50);

        $products = $this->reportService->getTopProducts(
            $user->company_id,
            $user->branch_id,
            $range['from'],
            $range['to'],
            $limit
        );

        return response()->json(['data' => $products]);
    }

    /**
     * GET /api/v1/reports/sales-by-hour
     * Distribución horaria con filtro de fechas
     */
    public function salesByHour(ReportFilterRequest $request): JsonResponse
    {
        $user = $request->user();
        $range = $request->getDateRange(7);

        $hourly = $this->reportService->getSalesByHour(
            $user->company_id,
            $user->branch_id,
            $range['from'],
            $range['to']
        );

        return response()->json(['data' => $hourly]);
    }

    /**
     * GET /api/v1/reports/payment-methods
     * Métodos de pago con filtro de fechas
     */
    public function paymentMethods(ReportFilterRequest $request): JsonResponse
    {
        $user = $request->user();
        $range = $request->getDateRange(30); // Default 30 días

        $methods = $this->reportService->getPaymentMethods(
            $user->company_id,
            $user->branch_id,
            $range['from'],
            $range['to']
        );

        return response()->json(['data' => $methods]);
    }
}
