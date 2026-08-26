<?php

namespace Modules\Cashier\Interfaces\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Cashier\Domain\Entities\CashSession;
use Modules\Payments\Domain\Contracts\PaymentQueryServiceInterface;

/**
 * Controller para dashboard de caja.
 * 
 * F1.4a: Refactorizado para usar PaymentQueryServiceInterface
 * en lugar de acceder directamente a DB::table('payments').
 */
class CashierDashboardController extends Controller
{
    public function __construct(
        private PaymentQueryServiceInterface $paymentQueryService
    ) {
    }

    /**
     * GET /api/v1/cashier/dashboard
     * Dashboard completo de caja
     */
    public function index(Request $request): JsonResponse
    {
        $branchId = $request->user()->branch_id;
        $openSession = CashSession::where('branch_id', $branchId)
            ->where('status', 'open')
            ->first();

        if (!$openSession) {
            return response()->json([
                'session_open' => false,
                'message' => 'No hay sesión de caja abierta',
            ]);
        }

        // USAR EL SERVICIO en lugar de DB::table('payments')
        $paymentsByMethod = $this->paymentQueryService->getPaymentsByMethodInSession($openSession->id);

        $sessionPayments = [
            'cash' => [
                'amount' => (float) ($paymentsByMethod['CASH']->total_amount ?? 0),
                'count' => (int) ($paymentsByMethod['CASH']->count ?? 0),
            ],
            'card' => [
                'amount' => (float) ($paymentsByMethod['CARD']->total_amount ?? 0),
                'count' => (int) ($paymentsByMethod['CARD']->count ?? 0),
            ],
            'transfer' => [
                'amount' => (float) ($paymentsByMethod['TRANSFER']->total_amount ?? 0),
                'count' => (int) ($paymentsByMethod['TRANSFER']->count ?? 0),
            ],
        ];

        // Pagos del día
        $todayStart = Carbon::today()->startOfDay()->toDateTimeString();
        $todayEnd = Carbon::today()->endOfDay()->toDateTimeString();

        // USAR EL SERVICIO en lugar de DB::table('payments')
        $todayPayments = $this->paymentQueryService->getDailyPaymentsByMethod(
            $branchId,
            $todayStart,
            $todayEnd
        );

        $dailyTotals = [
            'cash' => (float) ($todayPayments['CASH']->total_amount ?? 0),
            'card' => (float) ($todayPayments['CARD']->total_amount ?? 0),
            'transfer' => (float) ($todayPayments['TRANSFER']->total_amount ?? 0),
        ];

        return response()->json([
            'session_open' => true,
            'session' => [
                'id' => $openSession->id,
                'opened_at' => $openSession->opened_at,
                'opening_amount' => (float) $openSession->opening_amount,
            ],
            'session_payments' => $sessionPayments,
            'daily_totals' => $dailyTotals,
        ]);
    }
}
