<?php

namespace Modules\Cashier\Domain\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Cashier\Domain\Entities\CashMovement;
use Modules\Cashier\Domain\Exceptions\CashierException;
use Modules\Cashier\Domain\ValueObjects\MovementType;
use Modules\Identity\Domain\Entities\User;
use Modules\Payments\Domain\Entities\CashSession;
use Modules\Payments\Domain\ValueObjects\CashSessionStatus;

/**
 * Servicio para gestionar movimientos de caja (retiros, depósitos, ajustes).
 * 
 * Casos de uso:
 * - Retiro de exceso de efectivo (llevar a caja fuerte)
 * - Depósito de cambio (cuando falta efectivo para dar vuelto)
 * - Ajustes por errores de conteo
 */
class CashMovementService
{
    // Monto mínimo que requiere autorización de supervisor
    private const AUTHORIZATION_THRESHOLD = 50000.0; // $50.000 CLP

    /**
     * Registra un retiro de efectivo de la caja.
     * 
     * @param CashSession $session Sesión abierta
     * @param User $user Cajero que realiza el retiro
     * @param float $amount Monto a retirar (siempre positivo)
     * @param string $reason Razón del retiro
     * @param string|null $notes Notas adicionales
     * @param User|null $authorizer Supervisor que autoriza (si aplica)
     * @return CashMovement
     */
    public function withdrawal(
        CashSession $session,
        User $user,
        float $amount,
        string $reason,
        ?string $notes = null,
        ?User $authorizer = null
    ): CashMovement {
        return $this->createMovement(
            $session,
            $user,
            MovementType::WITHDRAWAL,
            $amount,
            $reason,
            $notes,
            $authorizer
        );
    }

    /**
     * Registra un depósito de efectivo en la caja.
     */
    public function deposit(
        CashSession $session,
        User $user,
        float $amount,
        string $reason,
        ?string $notes = null
    ): CashMovement {
        return $this->createMovement(
            $session,
            $user,
            MovementType::DEPOSIT,
            $amount,
            $reason,
            $notes
        );
    }

    /**
     * Registra un ajuste de caja (corrección de errores).
     * Siempre requiere autorización.
     */
    public function adjustment(
        CashSession $session,
        User $user,
        float $amount,
        string $reason,
        ?User $authorizer = null,
        ?string $notes = null
    ): CashMovement {
        if (!$authorizer) {
            throw new CashierException('Los ajustes siempre requieren autorización de supervisor.');
        }

        return $this->createMovement(
            $session,
            $user,
            MovementType::ADJUSTMENT,
            $amount,
            $reason,
            $notes,
            $authorizer
        );
    }

    /**
     * Crea un movimiento genérico con validaciones.
     */
    private function createMovement(
        CashSession $session,
        User $user,
        MovementType $type,
        float $amount,
        string $reason,
        ?string $notes,
        ?User $authorizer = null
    ): CashMovement {
        // Validaciones
        if ($session->status !== CashSessionStatus::OPEN) {
            throw new CashierException(
                'No se pueden registrar movimientos en una sesión cerrada o suspendida.'
            );
        }

        if ($amount <= 0) {
            throw new CashierException('El monto del movimiento debe ser positivo.');
        }

        if ($type->requiresAuthorization() && !$authorizer) {
            if ($amount >= self::AUTHORIZATION_THRESHOLD) {
                throw new CashierException(
                    "Movimientos de " . $type->label() . " por montos >= \$" . 
                    number_format(self::AUTHORIZATION_THRESHOLD, 0, ',', '.') . 
                    " requieren autorización de supervisor."
                );
            }
        }

        // Verificar que el autorizador no sea el mismo cajero
        if ($authorizer && $authorizer->id === $user->id) {
            throw new CashierException(
                'El supervisor que autoriza no puede ser el mismo cajero que realiza el movimiento.'
            );
        }

        return DB::transaction(function () use ($session, $user, $type, $amount, $reason, $notes, $authorizer) {
            // Calcular balance actual ANTES del movimiento
            $currentBalance = $session->calculateCurrentBalance();
            
            // Calcular balance después del movimiento
            $impact = $amount * $type->balanceSign();
            $balanceAfter = round($currentBalance + $impact, 2);

            // Validar que no haya balance negativo para retiros
            if ($type === MovementType::WITHDRAWAL && $balanceAfter < 0) {
                throw new CashierException(
                    "El retiro de \$" . number_format($amount, 0, ',', '.') . 
                    " dejaría la caja con balance negativo (\$" . 
                    number_format($balanceAfter, 0, ',', '.') . ")."
                );
            }

            // Crear movimiento
            $movement = CashMovement::create([
                'company_id' => $session->company_id,
                'branch_id' => $session->branch_id,
                'cash_session_id' => $session->id,
                'user_id' => $user->id,
                'type' => $type,
                'amount' => $amount,
                'reason' => $reason,
                'notes' => $notes,
                'balance_after' => $balanceAfter,
                'authorized_by' => $authorizer?->id,
                'authorized_at' => $authorizer ? now() : null,
            ]);

            Log::info('Movimiento de caja registrado', [
                'movement_id' => $movement->id,
                'type' => $type->value,
                'amount' => $amount,
                'session_id' => $session->id,
                'user_id' => $user->id,
                'balance_after' => $balanceAfter,
                'authorized' => !is_null($authorizer),
            ]);

            return $movement;
        });
    }

    /**
     * Obtiene el resumen de movimientos de una sesión.
     */
    public function getSessionSummary(CashSession $session): array
    {
        $movements = $session->movements()->get();

        return [
            'withdrawals_count' => $movements->where('type', MovementType::WITHDRAWAL)->count(),
            'withdrawals_total' => (float) $movements->where('type', MovementType::WITHDRAWAL)->sum('amount'),
            'deposits_count' => $movements->where('type', MovementType::DEPOSIT)->count(),
            'deposits_total' => (float) $movements->where('type', MovementType::DEPOSIT)->sum('amount'),
            'adjustments_count' => $movements->where('type', MovementType::ADJUSTMENT)->count(),
            'adjustments_total' => (float) $movements->where('type', MovementType::ADJUSTMENT)->sum('amount'),
            'net_impact' => (float) $movements->sum(fn($m) => $m->balanceImpact()),
            'movements' => $movements,
        ];
    }
}
