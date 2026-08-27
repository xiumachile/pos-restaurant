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
     * GET /api/v1/cashier/reports/session/{sessionId}
     * Reporte completo de una sesión de caja
     */
    public function sessionReport(Request $request, int $sessionId): JsonResponse
    {
        $session = CashSession::where('branch_id', $request->user()->branch_id)
            ->findOrFail($sessionId);

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

        return response()->json([
            'session' => [
                'id' => $session->id,
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
        ]);
    }
}
