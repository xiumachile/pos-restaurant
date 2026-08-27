<?php

namespace Modules\Cashier\Interfaces\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Cashier\Domain\Entities\CashCount;
use Modules\Cashier\Domain\Entities\CashRegister;
use Modules\Payments\Domain\Contracts\PaymentQueryServiceInterface;
use Modules\Payments\Domain\Entities\CashSession;
use Modules\Payments\Domain\ValueObjects\CashSessionStatus;

/**
 * Controller para dashboard de caja.
 * 
 * F1.4a: Usa PaymentQueryServiceInterface en lugar de DB::table('payments').
 * F2.3: Estructura JSON alineada con tests.
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

        // Sesión actual
        $currentSession = CashSession::where('branch_id', $branchId)
            ->where('status', CashSessionStatus::OPEN)
            ->first();

        // Cajas registradoras de la sucursal
        $registers = CashRegister::where('branch_id', $branchId)
            ->where('is_active', true)
            ->get()
            ->map(function ($register) {
                return [
                    'uuid' => $register->uuid,
                    'name' => $register->name,
                    'code' => $register->code,
                    'is_active' => $register->is_active,
                ];
            });

        // Estadísticas del día
        $todayStart = Carbon::today()->startOfDay();
        $todayEnd = Carbon::today()->endOfDay();

        $sessionsToday = CashSession::where('branch_id', $branchId)
            ->whereBetween('opened_at', [$todayStart, $todayEnd])
            ->count();

        $sessionsOpen = CashSession::where('branch_id', $branchId)
            ->where('status', CashSessionStatus::OPEN)
            ->count();

        $countsToday = CashCount::whereHas('session', function ($q) use ($branchId, $todayStart, $todayEnd) {
            $q->where('branch_id', $branchId)
              ->whereBetween('opened_at', [$todayStart, $todayEnd]);
        })->count();

        // Balance de la sesión actual
        $currentBalance = 0;
        $sessionData = null;
        if ($currentSession) {
            $paymentsByMethod = $this->paymentQueryService->getPaymentsByMethodInSession($currentSession->id);
            $totalPayments = $paymentsByMethod->sum('total_amount');
            $currentBalance = (float) $currentSession->opening_amount + (float) $totalPayments;

            $sessionData = [
                'uuid' => $currentSession->uuid,
                'session_number' => $currentSession->session_number,
                'opening_amount' => (float) $currentSession->opening_amount,
                'current_balance' => $currentBalance,
            ];
        }

        return response()->json([
            'data' => [
                'current_session' => $sessionData,
                'registers' => $registers,
                'statistics_today' => [
                    'sessions_today' => $sessionsToday,
                    'sessions_open' => $sessionsOpen,
                    'counts_today' => $countsToday,
                ],
            ],
        ]);
    }
}
