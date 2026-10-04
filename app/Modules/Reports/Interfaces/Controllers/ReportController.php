<?php

namespace Modules\Reports\Interfaces\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Reports\Domain\Services\ReportService;

class ReportController extends Controller
{
    public function __construct(
        private ReportService $reportService
    ) {}

    /**
     * GET /api/v1/reports/dashboard
     * KPIs del día (ventas, órdenes, ticket promedio, propinas)
     */
    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        $kpis = $this->reportService->getDashboardKPIs(
            $user->company_id,
            $user->branch_id
        );

        return response()->json(['data' => $kpis]);
    }

    /**
     * GET /api/v1/reports/top-products
     * Ranking de productos más vendidos
     */
    public function topProducts(Request $request): JsonResponse
    {
        $user = $request->user();
        $days = (int) $request->query('days', 7);
        $limit = min((int) $request->query('limit', 10), 50);

        $products = $this->reportService->getTopProducts(
            $user->company_id,
            $user->branch_id,
            $days,
            $limit
        );

        return response()->json(['data' => $products]);
    }

    /**
     * GET /api/v1/reports/sales-by-hour
     * Distribución horaria de ventas
     */
    public function salesByHour(Request $request): JsonResponse
    {
        $user = $request->user();
        $days = (int) $request->query('days', 7);

        $hourly = $this->reportService->getSalesByHour(
            $user->company_id,
            $user->branch_id,
            $days
        );

        return response()->json(['data' => $hourly]);
    }

    /**
     * GET /api/v1/reports/payment-methods
     * Distribución por método de pago
     */
    public function paymentMethods(Request $request): JsonResponse
    {
        $user = $request->user();
        $days = (int) $request->query('days', 30);

        $methods = $this->reportService->getPaymentMethods(
            $user->company_id,
            $user->branch_id,
            $days
        );

        return response()->json(['data' => $methods]);
    }
}
