<?php

namespace Modules\Cashier\Interfaces\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Cashier\Domain\Entities\CashCount;
use Modules\Cashier\Domain\Entities\TipPayout;
use Modules\Cashier\Domain\Entities\CashMovement;
use Modules\Payments\Domain\Entities\CashSession;
use Modules\Payments\Domain\ValueObjects\CashSessionStatus;

/**
 * Controlador de reportes de caja (X-Report y Z-Report).
 * 
 * X-Report: Reporte parcial de la sesión abierta (no cierra caja).
 * Z-Report: Reporte final de una sesión cerrada (incluye arqueo).
 */
class CashierReportController extends Controller
{
    /**
     * GET /api/v1/cashier/reports/x-report
     * 
     * Genera reporte parcial (X-Report) de la sesión actualmente abierta.
     * El cajero puede imprimirlo en cualquier momento sin cerrar la caja.
     */
    public function xReport(Request $request): JsonResponse
    {
        $user = $request->user();

        $session = CashSession::where('company_id', $user->company_id)
            ->where('branch_id', $user->branch_id)
            ->where('status', CashSessionStatus::OPEN)
            ->with(['user', 'register'])
            ->first();

        if (!$session) {
            return response()->json([
                'error' => 'no_open_session',
                'message' => 'No hay una sesión de caja abierta.',
            ], 422);
        }

        $report = $this->buildReport($session, 'X');

        return response()->json(['data' => $report]);
    }

    /**
     * GET /api/v1/cashier/reports/z-report/{uuid}
     * 
     * Genera reporte final (Z-Report) de una sesión cerrada.
     */
    public function zReport(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();

        $session = CashSession::where('uuid', $uuid)
            ->where('company_id', $user->company_id)
            ->where('branch_id', $user->branch_id)
            ->with(['user', 'register'])
            ->firstOrFail();

        if ($session->status !== CashSessionStatus::CLOSED) {
            return response()->json([
                'error' => 'session_not_closed',
                'message' => 'Solo se puede generar Z-Report de sesiones cerradas.',
            ], 422);
        }

        $report = $this->buildReport($session, 'Z');

        return response()->json(['data' => $report]);
    }

    /**
     * GET /api/v1/cashier/sessions/history
     * 
     * Historial de sesiones de caja cerradas (paginado).
     */
    public function history(Request $request): JsonResponse
    {
        $user = $request->user();
        $limit = (int) $request->query('limit', 20);

        $sessions = CashSession::where('company_id', $user->company_id)
            ->where('branch_id', $user->branch_id)
            ->where('status', CashSessionStatus::CLOSED)
            ->with(['user', 'register'])
            ->withCount(['payments', 'counts', 'movements'])
            ->orderByDesc('closed_at')
            ->limit(min($limit, 100))
            ->get()
            ->map(function ($session) {
                return [
                    'uuid' => $session->uuid,
                    'session_number' => $session->session_number,
                    'user_name' => $session->user?->name,
                    'register_name' => $session->register?->name,
                    'opened_at' => $session->opened_at?->toIso8601String(),
                    'closed_at' => $session->closed_at?->toIso8601String(),
                    'opening_amount' => (float) $session->opening_amount,
                    'closing_amount' => (float) $session->closing_amount,
                    'expected_amount' => (float) $session->expected_amount,
                    'difference' => (float) $session->difference,
                    'has_discrepancy' => abs((float) $session->difference) > 0.01,
                    'payments_count' => $session->payments_count,
                    'counts_count' => $session->counts_count,
                    'movements_count' => $session->movements_count,
                ];
            });

        return response()->json(['data' => $sessions]);
    }

    /**
     * Construye el reporte completo (X o Z) de una sesión.
     */
    private function buildReport(CashSession $session, string $type): array
    {
        // Pagos por método
        $paymentsByMethod = DB::table('payments')
            ->where('cash_session_id', $session->id)
            ->where('status', 'completed')
            ->select(
                'method_code',
                DB::raw('SUM(amount) as total_amount'),
                DB::raw('SUM(tip_amount) as total_tips'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('method_code')
            ->get()
            ->keyBy('method_code');

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

        $totalSales = array_sum(array_column($breakdown, 'amount'));
        $totalTips = array_sum(array_column($breakdown, 'tips'));
        $totalTransactions = array_sum(array_column($breakdown, 'count'));

        // Propinas entregadas durante la sesión (salen de caja)
        $tipPayouts = TipPayout::where('cash_session_id', $session->id)
            ->valid()
            ->get();

        $totalTipsPaidOut = (float) $tipPayouts->sum('amount');
        $cashTipsPaidOut = (float) $tipPayouts->where('payment_method', 'cash')->sum('amount');
        
        // Propinas con tarjeta que se entregan en efectivo (salen de caja)
        // Según política, pueden salir de caja o ir a nómina
        $policy = \Modules\Cashier\Domain\Entities\TipPolicy::resolveForBranch(
            $session->company_id, 
            $session->branch_id
        );
        
        // Si la política es cash_payout, las propinas de tarjeta también salen de caja
        $cardTipsAsCash = $policy->cardTipsLeaveRegister() 
            ? (float) $tipPayouts->where('payment_method', 'cash')->sum('amount') 
            : 0;

        // Esperado en caja:
        // Inicial + Ventas efectivo + Propinas efectivo recibidas - Propinas entregadas
        // Las propinas efectivo recibidas ENTRAN a caja cuando el cliente paga
        // Las propinas entregadas SALEN de caja cuando se dan al garzón
        $totalCashExpected = (float) $session->opening_amount
            + $breakdown['cash']['amount']        // Ventas efectivo
            + $breakdown['cash']['tips']           // + Propinas efectivo recibidas
            - $cashTipsPaidOut;                    // - Propinas entregadas

        // Movimientos de caja
        $movements = DB::table('cash_movements')
            ->where('cash_session_id', $session->id)
            ->orderBy('created_at', 'asc')
            ->get(['type', 'amount', 'reason', 'created_at'])
            ->map(fn($m) => [
                'type' => $m->type,
                'amount' => (float) $m->amount,
                'reason' => $m->reason,
                'created_at' => $m->created_at,
            ]);

        $totalWithdrawals = $movements->where('type', 'withdrawal')->sum('amount');
        $totalDeposits = $movements->where('type', 'deposit')->sum('amount');

        // Arqueos realizados
        $counts = DB::table('cash_counts')
            ->where('cash_session_id', $session->id)
            ->orderBy('created_at', 'asc')
            ->get(['type', 'counted_amount', 'difference', 'has_discrepancy', 'created_at', 'notes'])
            ->map(fn($c) => [
                'type' => $c->type,
                'counted_amount' => (float) $c->counted_amount,
                'difference' => (float) $c->difference,
                'has_discrepancy' => (bool) $c->has_discrepancy,
                'created_at' => $c->created_at,
                'notes' => $c->notes,
            ]);

        // Calcular diferencia según tipo de reporte
        if ($type === 'Z' && $session->closing_amount !== null) {
            $difference = (float) $session->closing_amount - $totalCashExpected;
        } else {
            $difference = null;
        }

        return [
            'type' => $type,
            'generated_at' => now()->toIso8601String(),
            'session' => [
                'uuid' => $session->uuid,
                'session_number' => $session->session_number,
                'user_name' => $session->user?->name,
                'register_name' => $session->register?->name,
                'opened_at' => $session->opened_at?->toIso8601String(),
                'closed_at' => $session->closed_at?->toIso8601String(),
                'opening_amount' => (float) $session->opening_amount,
                'closing_amount' => $session->closing_amount ? (float) $session->closing_amount : null,
            ],
            'sales' => [
                'breakdown' => $breakdown,
                'total_sales' => $totalSales,
                'total_tips' => $totalTips,
                'total_transactions' => $totalTransactions,
                'total_grand' => $totalSales + $totalTips,
            ],
            'cash' => [
                'opening' => (float) $session->opening_amount,
                'sales' => $breakdown['cash']['amount'],
                'tips' => $breakdown['cash']['tips'],
                'tips_paid_out' => $cashTipsPaidOut,
                'withdrawals' => $totalWithdrawals,
                'deposits' => $totalDeposits,
                'expected' => $totalCashExpected + $totalDeposits - $totalWithdrawals,
                'counted' => $session->closing_amount ? (float) $session->closing_amount : null,
                'difference' => $difference,
            ],
            'tips' => [
                'total_received' => $totalTips,
                'total_paid_out' => $totalTipsPaidOut,
                'pending' => max(0, $totalTips - $totalTipsPaidOut),
                'policy_type' => $policy->policy_type->value,
            ],
            'movements' => $movements,
            'counts' => $counts,
        ];
    }
}
