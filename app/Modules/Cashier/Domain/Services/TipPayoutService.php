<?php

namespace Modules\Cashier\Domain\Services;

use Illuminate\Support\Collection;
use Modules\Cashier\Domain\Entities\TipPayout;
use Modules\Cashier\Domain\Entities\TipPolicy;
use Modules\Payments\Domain\Contracts\PaymentQueryServiceInterface;
use Modules\Payments\Domain\Entities\CashSession;

class TipPayoutService
{
    public function __construct(
        private PaymentQueryServiceInterface $paymentQueryService
    ) {}

    /**
     * Obtiene la sesión de caja abierta para una branch.
     */
    public function getOpenSession(int $branchId): ?CashSession
    {
        return CashSession::where('branch_id', $branchId)
            ->where('status', 'open')
            ->first();
    }

    /**
     * Calcula las propinas pendientes de un garzón en una sesión.
     */
    public function getWaiterPending(int $sessionId, int $waiterId): array
    {
        $received = $this->paymentQueryService->getWaiterTipsInSession($sessionId, $waiterId);
        $paid = $this->getWaiterPaid($sessionId, $waiterId);

        return [
            'received' => (float) $received,
            'paid' => (float) $paid,
            'pending' => max(0, (float) $received - (float) $paid),
        ];
    }

    /**
     * Obtiene el total pagado a un garzón en una sesión.
     */
    public function getWaiterPaid(int $sessionId, int $waiterId): float
    {
        return (float) TipPayout::where('cash_session_id', $sessionId)
            ->where('waiter_id', $waiterId)
            ->valid()
            ->sum('amount');
    }

    /**
     * Genera resumen de propinas de la sesión.
     */
    public function getSessionSummary(int $sessionId): array
    {
        $tipsByMethod = $this->paymentQueryService->getTipsByMethodInSession($sessionId);

        $byMethod = [
            'cash' => 0,
            'card' => 0,
            'transfer' => 0,
            'gift_card' => 0,
        ];

        foreach ($tipsByMethod as $method => $data) {
            $key = strtolower($method);
            if (isset($byMethod[$key])) {
                $byMethod[$key] = (float) ($data->total_tips ?? 0);
            }
        }

        $total = array_sum($byMethod);
        $paid = (float) TipPayout::where('cash_session_id', $sessionId)
            ->valid()
            ->sum('amount');

        return [
            'tips_by_method' => $byMethod,
            'total_tips' => $total,
            'already_paid_out' => $paid,
            'pending' => max(0, $total - $paid),
        ];
    }

    /**
     * Genera resumen por garzón.
     */
    public function getWaitersSummary(int $sessionId): array
    {
        $tipsByWaiter = $this->paymentQueryService->getTipsByWaiterAndMethod($sessionId);

        $payouts = TipPayout::where('cash_session_id', $sessionId)
            ->valid()
            ->get()
            ->groupBy('waiter_id')
            ->map(fn($group) => (float) $group->sum('amount'));

        $result = [];
        foreach ($tipsByWaiter as $waiterId => $methods) {
            $total = (float) $methods->sum();
            $paid = $payouts[$waiterId] ?? 0;

            $result[] = [
                'waiter_id' => $waiterId,
                'tips_by_method' => $methods->toArray(),
                'total_tips' => $total,
                'already_paid' => $paid,
                'pending' => max(0, $total - $paid),
            ];
        }

        return $result;
    }

    /**
     * Obtiene el máximo pendiente por garzón (para validación rápida).
     */
    public function getMaxByWaiter(int $sessionId): array
    {
        $summary = $this->getWaitersSummary($sessionId);

        return array_map(fn($w) => [
            'waiter_id' => $w['waiter_id'],
            'max_payout' => $w['pending'],
        ], $summary);
    }

    /**
     * Crea una entrega manual de propinas.
     */
    public function createPayout(
        CashSession $session,
        int $waiterId,
        float $amount,
        int $processedBy,
        ?string $paymentMethod = 'cash',
        ?string $notes = null
    ): TipPayout {
        // Validar que no exceda el pending
        $pending = $this->getWaiterPending($session->id, $waiterId)['pending'];

        if ($amount > $pending) {
            throw new \InvalidArgumentException(
                "El monto excede las propinas disponibles. Disponible: {$pending}, solicitado: {$amount}"
            );
        }

        // Obtener política activa
        $policy = TipPolicy::resolveForBranch($session->company_id, $session->branch_id);

        return TipPayout::create([
            'company_id' => $session->company_id,
            'branch_id' => $session->branch_id,
            'cash_session_id' => $session->id,
            'processed_by' => $processedBy,
            'waiter_id' => $waiterId,
            'amount' => $amount,
            'payment_method' => $paymentMethod,
            'policy_type' => $policy->policy_type->value,
            'notes' => $notes,
            'is_voided' => false,
        ]);
    }

    /**
     * Anula una entrega de propinas.
     */
    public function voidPayout(string $uuid, int $companyId): bool
    {
        $payout = TipPayout::where('uuid', $uuid)
            ->where('company_id', $companyId)
            ->firstOrFail();

        if ($payout->is_voided) {
            return false;
        }

        $payout->update([
            'is_voided' => true,
            'voided_at' => now(),
        ]);

        return true;
    }

    /**
     * Genera automáticamente entregas para todos los garzones con propinas pendientes.
     */
    public function generatePayouts(CashSession $session, int $processedBy): array
    {
        $waiters = $this->getWaitersSummary($session->id);
        $policy = TipPolicy::resolveForBranch($session->company_id, $session->branch_id);

        $payouts = [];
        foreach ($waiters as $waiter) {
            if ($waiter['pending'] > 0) {
                $payouts[] = $this->createPayout(
                    $session,
                    $waiter['waiter_id'],
                    $waiter['pending'],
                    $processedBy,
                    'cash',
                    'Generado automáticamente'
                );
            }
        }

        return $payouts;
    }

    /**
     * Lista todas las entregas de una sesión.
     */
    public function listPayouts(int $sessionId): Collection
    {
        return TipPayout::where('cash_session_id', $sessionId)
            ->with(['waiter', 'processor'])
            ->orderByDesc('created_at')
            ->get();
    }
}
