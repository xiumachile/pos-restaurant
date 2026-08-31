<?php

namespace Modules\Cashier\Interfaces\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Cashier\Domain\Entities\CashCount;
use Modules\Cashier\Domain\Entities\CashMovement;
use Modules\Payments\Domain\Entities\CashSession;
use Modules\Payments\Domain\Contracts\PaymentQueryServiceInterface;

/**
 * Controller para reportes de caja.
 * 
 * F1.4a: Refactorizado para usar PaymentQueryServiceInterface
 * en lugar de acceder directamente a DB::table('payments').
 */
class CashierReportController extends Controller
{
    public function __construct(
        private PaymentQueryServiceInterface $paymentQueryService
    ) {
    }

    /**
     * GET /api/v1/cashier/reports/x-report
     * Reporte X: Cierre parcial de caja (sin cerrar sesión)
     */
    public function xReport(Request $request): JsonResponse
    {
        $session = CashSession::where('branch_id', $request->user()->branch_id)
            ->where('status', 'open')
            ->latest('opened_at')
            ->first();

        if (!$session) {
            return response()->json(['error' => 'No hay sesión de caja abierta'], 404);
        }

        $report = $this->buildSessionReport($session);
        $report['report_type'] = 'x-report';
        $report['session_status'] = 'open';

        return response()->json($report);
    }

    /**
     * GET /api/v1/cashier/reports/z-report/{uuid}
     * Reporte Z: Cierre final de caja
     */
    public function zReport(Request $request, string $uuid): JsonResponse
    {
        $session = CashSession::where('uuid', $uuid)
            ->where('branch_id', $request->user()->branch_id)
            ->first();

        if (!$session) {
            return response()->json(['error' => 'Sesión no encontrada'], 404);
        }

        $report = $this->buildSessionReport($session);
        $report['report_type'] = 'z-report';
        $report['session_status'] = $session->status->value;

        return response()->json($report);
    }

    /**
     * GET /api/v1/cashier/sessions/history
     * Historial de sesiones de caja
     */
    public function history(Request $request): JsonResponse
    {
        $sessions = CashSession::where('branch_id', $request->user()->branch_id)
            ->withCount('payments')
            ->orderBy('opened_at', 'desc')
            ->limit(50)
            ->get();

        return response()->json(['data' => $sessions]);
    }

    /**
     * GET /api/v1/cashier/reports/session/{sessionId}
     * Reporte completo de una sesión de caja (legacy)
     */
    public function sessionReport(Request $request, int $sessionId): JsonResponse
    {
        $session = CashSession::where('branch_id', $request->user()->branch_id)
            ->findOrFail($sessionId);

        $report = $this->buildSessionReport($session);
        return response()->json($report);
    }

    /**
     * Construye el reporte completo de una sesión.
     * Este método usa PaymentQueryService que filtra por cash_session_id,
     * por lo que el fix de trazabilidad (pagos vinculados a sesión) funciona correctamente.
     */
    private function buildSessionReport(CashSession $session): array
    {
        // USAR EL SERVICIO en lugar de DB::table('payments')
        $paymentsByMethod = $this->paymentQueryService->getPaymentsByMethodInSession($session->id);

        $breakdown = [
            'cash' => [
                'amount' => (float) ($paymentsByMethod['CASH']->total_amount ?? 0),
                'tips' => (float) ($paymentsByMethod['CASH']->total_tips ?? 0),
                'count' => (int) ($paymentsByMethod['CASH']->count ?? 0),
            ],
            'card' => [
                'amount' => (float) ($paymentsByMethod['CARD']->total_amount ?? 0),
                'tips' => (float) ($paymentsByMethod['CARD']->total_tips ?? 0),
                'count' => (int) ($paymentsByMethod['CARD']->count ?? 0),
            ],
            'transfer' => [
                'amount' => (float) ($paymentsByMethod['TRANSFER']->total_amount ?? 0),
                'tips' => (float) ($paymentsByMethod['TRANSFER']->total_tips ?? 0),
                'count' => (int) ($paymentsByMethod['TRANSFER']->count ?? 0),
            ],
            'gift_card' => [
                'amount' => (float) ($paymentsByMethod['GIFT_CARD']->total_amount ?? 0),
                'tips' => (float) ($paymentsByMethod['GIFT_CARD']->total_tips ?? 0),
                'count' => (int) ($paymentsByMethod['GIFT_CARD']->count ?? 0),
            ],
        ];

        $movements = CashMovement::where('cash_session_id', $session->id)
            ->orderBy('created_at', 'desc')
            ->get();

        $movementSummary = [
            'withdrawals' => (float) $movements->where('type', 'withdrawal')->sum('amount'),
            'deposits' => (float) $movements->where('type', 'deposit')->sum('amount'),
            'adjustments' => (float) $movements->where('type', 'adjustment')->sum('amount'),
        ];

        $counts = CashCount::where('cash_session_id', $session->id)
            ->orderBy('created_at', 'asc')
            ->get();

        $expectedCash = (float) $session->opening_amount
            + $breakdown['cash']['amount']
            + $breakdown['cash']['tips']
            - $movementSummary['withdrawals']
            + $movementSummary['deposits']
            + $movementSummary['adjustments'];

        $lastCount = $counts->last();
        $actualCash = $lastCount ? (float) $lastCount->total_counted : null;
        $discrepancy = $actualCash !== null ? $actualCash - $expectedCash : null;

        return [
            'session' => [
                'id' => $session->id,
                'uuid' => $session->uuid,
                'status' => $session->status,
                'opened_at' => $session->opened_at,
                'closed_at' => $session->closed_at,
                'opening_amount' => (float) $session->opening_amount,
            ],
            'payments' => $breakdown,
            'total_payments' => array_sum(array_column($breakdown, 'amount')),
            'total_tips' => array_sum(array_column($breakdown, 'tips')),
            'movements' => $movementSummary,
            'cash_counts' => $counts,
            'expected_cash' => $expectedCash,
            'actual_cash' => $actualCash,
            'discrepancy' => $discrepancy,
        ];
    }
}
