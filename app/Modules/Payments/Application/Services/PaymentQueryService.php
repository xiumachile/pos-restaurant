<?php

namespace Modules\Payments\Application\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Payments\Domain\Contracts\PaymentQueryServiceInterface;
use App\Shared\Application\TenantContext;

/**
 * Implementación del servicio de consultas de pagos.
 * 
 * F1.4a: Esta clase encapsula toda la lógica de consulta a la tabla payments,
 * permitiendo que otros módulos obtengan información sin conocer la estructura interna.
 * 
 * S1.3: Defensa en profundidad - valida que todos los IDs pertenezcan al tenant
 * del usuario autenticado antes de ejecutar queries.
 */
class PaymentQueryService implements PaymentQueryServiceInterface
{
    private TenantContext $tenantContext;

    public function __construct(TenantContext $tenantContext)
    {
        $this->tenantContext = $tenantContext;
    }

    public function getPaymentsByMethodInSession(int $cashSessionId): Collection
    {
        $this->validateSessionOwnership($cashSessionId);

        return DB::table('payments')
            ->where('cash_session_id', $cashSessionId)
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
    }

    public function getWaiterTipsInSession(int $cashSessionId, int $waiterId): float
    {
        $this->validateSessionOwnership($cashSessionId);

        return (float) DB::table('payments')
            ->where('payments.cash_session_id', $cashSessionId)
            ->where('payments.status', 'completed')
            ->where('payments.tip_amount', '>', 0)
            ->join('orders', 'payments.order_id', '=', 'orders.id')
            ->where('orders.waiter_id', $waiterId)
            ->sum('payments.tip_amount');
    }

    public function getTipsByMethodInSession(int $cashSessionId): Collection
    {
        $this->validateSessionOwnership($cashSessionId);

        return DB::table('payments')
            ->where('cash_session_id', $cashSessionId)
            ->where('status', 'completed')
            ->where('tip_amount', '>', 0)
            ->select(
                'method_code',
                DB::raw('SUM(tip_amount) as total_tips'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('method_code')
            ->get()
            ->keyBy('method_code');
    }

    public function getTipsByWaiterAndMethod(int $cashSessionId): Collection
    {
        $this->validateSessionOwnership($cashSessionId);

        $payments = DB::table('payments')
            ->where('payments.cash_session_id', $cashSessionId)
            ->where('payments.status', 'completed')
            ->where('payments.tip_amount', '>', 0)
            ->join('orders', 'payments.order_id', '=', 'orders.id')
            ->whereNotNull('orders.waiter_id')
            ->select('orders.waiter_id', 'payments.method_code', 'payments.tip_amount')
            ->get();

        return $payments->groupBy('waiter_id')
            ->map(function ($group) {
                return $group->groupBy('method_code')
                    ->map(fn($items) => (float) $items->sum('tip_amount'));
            });
    }

    public function getDailyPaymentsByMethod(int $branchId, string $dateStart, string $dateEnd): Collection
    {
        $this->validateBranchOwnership($branchId);

        return DB::table('payments')
            ->join('cash_sessions', 'payments.cash_session_id', '=', 'cash_sessions.id')
            ->where('cash_sessions.branch_id', $branchId)
            ->whereBetween('payments.paid_at', [$dateStart, $dateEnd])
            ->where('payments.status', 'completed')
            ->select(
                'payments.method_code',
                DB::raw('SUM(payments.amount) as total_amount'),
                DB::raw('SUM(payments.tip_amount) as total_tips'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('payments.method_code')
            ->get()
            ->keyBy('method_code');
    }

    public function getAllPaymentsInSession(int $cashSessionId): Collection
    {
        $this->validateSessionOwnership($cashSessionId);

        return DB::table('payments')
            ->where('cash_session_id', $cashSessionId)
            ->where('status', 'completed')
            ->get();
    }

    /**
     * Valida que la sesión de caja pertenezca al tenant del usuario.
     * 
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    private function validateSessionOwnership(int $cashSessionId): void
    {
        if (!$this->tenantContext->hasCompany()) {
            throw new \RuntimeException('TenantContext no establecido');
        }

        $exists = DB::table('cash_sessions')
            ->where('id', $cashSessionId)
            ->where('company_id', $this->tenantContext->companyId())
            ->exists();

        if (!$exists) {
            abort(403, 'No autorizado para acceder a esta sesión de caja');
        }
    }

    /**
     * Valida que la sucursal pertenezca al tenant del usuario.
     * 
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    private function validateBranchOwnership(int $branchId): void
    {
        if (!$this->tenantContext->hasCompany()) {
            throw new \RuntimeException('TenantContext no establecido');
        }

        $exists = DB::table('branches')
            ->where('id', $branchId)
            ->where('company_id', $this->tenantContext->companyId())
            ->exists();

        if (!$exists) {
            abort(403, 'No autorizado para acceder a esta sucursal');
        }
    }
}
