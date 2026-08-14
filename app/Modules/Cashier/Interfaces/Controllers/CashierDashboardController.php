<?php

namespace Modules\Cashier\Interfaces\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Cashier\Domain\Entities\CashCount;
use Modules\Cashier\Domain\Entities\CashMovement;
use Modules\Cashier\Domain\Entities\CashRegister;
use Modules\Cashier\Domain\ValueObjects\MovementType;
use Modules\Payments\Domain\Entities\CashSession;
use Modules\Payments\Domain\ValueObjects\CashSessionStatus;

class CashierDashboardController extends Controller
{
    /**
     * GET /api/v1/cashier/dashboard
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $companyId = $user->company_id;
        $branchId = $user->branch_id;

        // Sesión activa
        $openSession = CashSession::where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->where('status', CashSessionStatus::OPEN)
            ->with(['user', 'register'])
            ->first();

        $sessionData = null;
        if ($openSession) {
            $movementsSummary = $openSession->movements()->get();
            $sessionData = [
                'uuid' => $openSession->uuid,
                'session_number' => $openSession->session_number,
                'user_name' => $openSession->user?->name,
                'register_name' => $openSession->register?->name,
                'opening_amount' => (float) $openSession->opening_amount,
                'current_balance' => $openSession->calculateCurrentBalance(),
                'expected_amount' => $openSession->calculateExpectedAmount(),
                'opened_at' => $openSession->opened_at?->toIso8601String(),
                'hours_open' => now()->diffInHours($openSession->opened_at, false),
                'withdrawals_total' => (float) $movementsSummary
                    ->where('type', MovementType::WITHDRAWAL)
                    ->sum('amount'),
                'deposits_total' => (float) $movementsSummary
                    ->where('type', MovementType::DEPOSIT)
                    ->sum('amount'),
                'movements_count' => $movementsSummary->count(),
            ];
        }

        // Cajas registradoras
        $registers = CashRegister::where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->active()
            ->get();

        $registersData = $registers->map(function ($register) {
            return [
                'uuid' => $register->uuid,
                'name' => $register->name,
                'code' => $register->code,
                'is_available' => $register->isAvailable(),
                'is_busy' => $register->isBusy(),
                'current_session_uuid' => $register->currentSession()?->uuid,
            ];
        });

        // Estadísticas del día
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();

        $todaySessions = CashSession::where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->whereBetween('opened_at', [$todayStart, $todayEnd])
            ->get();

        $todayCounts = CashCount::where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->get();

        $todayMovements = CashMovement::where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->get();

        $stats = [
            'sessions_today' => $todaySessions->count(),
            'sessions_open' => $todaySessions->where('status', CashSessionStatus::OPEN)->count(),
            'sessions_closed' => $todaySessions->where('status', CashSessionStatus::CLOSED)->count(),
            'counts_today' => $todayCounts->count(),
            'discrepant_counts_today' => $todayCounts->where('has_discrepancy', true)->count(),
            'movements_today' => $todayMovements->count(),
            'total_withdrawals_today' => (float) $todayMovements
                ->where('type', MovementType::WITHDRAWAL)
                ->sum('amount'),
            'total_deposits_today' => (float) $todayMovements
                ->where('type', MovementType::DEPOSIT)
                ->sum('amount'),
        ];

        return response()->json([
            'data' => [
                'current_session' => $sessionData,
                'registers' => $registersData,
                'statistics_today' => $stats,
            ],
        ]);
    }
}
