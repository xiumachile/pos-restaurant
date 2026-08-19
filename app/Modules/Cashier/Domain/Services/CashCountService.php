<?php

namespace Modules\Cashier\Domain\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Cashier\Domain\Entities\CashCount;
use Modules\Cashier\Domain\Exceptions\CashierException;
use Modules\Cashier\Domain\ValueObjects\CashCountType;
use Modules\Cashier\Domain\ValueObjects\Denomination;
use Modules\Identity\Domain\Entities\User;
use Modules\Payments\Domain\Entities\CashSession;
use Modules\Payments\Domain\ValueObjects\CashSessionStatus;

/**
 * Servicio para gestionar arqueos de caja.
 * 
 * Calcula el monto esperado a partir de:
 * - Monto de apertura
 * - Pagos en efectivo recibidos
 * - Movimientos (retiros/depósitos)
 * 
 * Y lo compara con el conteo físico detallado por denominación.
 */
class CashCountService
{
    // Threshold para considerar discrepancia significativa (CLP)
    private const DISCREPANCY_THRESHOLD = 100.0;
    
    // Threshold que requiere supervisión obligatoria
    private const SUPERVISION_THRESHOLD = 5000.0;

    /**
     * Registra un arqueo de apertura.
     */
    public function openingCount(
        CashSession $session,
        User $user,
        array $denominations,
        ?string $notes = null
    ): CashCount {
        return $this->createCount(
            $session,
            $user,
            CashCountType::OPENING,
            $denominations,
            'Conteo inicial de caja',
            $notes
        );
    }

    /**
     * Registra un arqueo de cierre.
     */
    public function closingCount(
        CashSession $session,
        User $user,
        array $denominations,
        ?string $notes = null
    ): CashCount {
        return $this->createCount(
            $session,
            $user,
            CashCountType::CLOSING,
            $denominations,
            'Conteo de cierre de caja',
            $notes
        );
    }

    /**
     * Registra un arqueo parcial (mitad de turno).
     */
    public function partialCount(
        CashSession $session,
        User $user,
        array $denominations,
        string $reason,
        ?string $notes = null
    ): CashCount {
        return $this->createCount(
            $session,
            $user,
            CashCountType::PARTIAL,
            $denominations,
            $reason,
            $notes
        );
    }

    /**
     * Registra un arqueo de auditoría.
     */
    public function auditCount(
        CashSession $session,
        User $auditor,
        array $denominations,
        string $reason,
        ?string $notes = null
    ): CashCount {
        return $this->createCount(
            $session,
            $auditor,
            CashCountType::AUDIT,
            $denominations,
            $reason,
            $notes
        );
    }

    /**
     * Crea un arqueo genérico con cálculo automático.
     */
    private function createCount(
        CashSession $session,
        User $user,
        CashCountType $type,
        array $denominations,
        string $reason,
        ?string $notes
    ): CashCount {
        // Validar estructura de denominaciones
        $this->validateDenominations($denominations);

        return DB::transaction(function () use ($session, $user, $type, $denominations, $reason, $notes) {
            // Calcular monto esperado (apertura + pagos - movimientos)
            $expectedAmount = $session->calculateExpectedCashBalance();

            // Calcular monto contado desde denominaciones
            $bills = $denominations['bills'] ?? [];
            $coins = $denominations['coins'] ?? [];
            $billsTotal = Denomination::calculateTotal($bills);
            $coinsTotal = Denomination::calculateTotal($coins);
            $countedAmount = $billsTotal + $coinsTotal;

            // Calcular diferencia
            $difference = round($countedAmount - $expectedAmount, 2);
            $hasDiscrepancy = abs($difference) > self::DISCREPANCY_THRESHOLD;

            // Crear arqueo
            $count = CashCount::create([
                'company_id' => $session->company_id,
                'branch_id' => $session->branch_id,
                'cash_session_id' => $session->id,
                'user_id' => $user->id,
                'type' => $type,
                'reason' => $reason,
                'expected_amount' => $expectedAmount,
                'counted_amount' => $countedAmount,
                'difference' => $difference,
                'denominations' => $denominations,
                'cash_amount' => $countedAmount,
                'card_amount' => 0,
                'transfer_amount' => 0,
                'other_amount' => 0,
                'notes' => null,
                'has_discrepancy' => $hasDiscrepancy,
            ]);

            Log::info('Arqueo de caja registrado', [
                'count_id' => $count->id,
                'type' => $type->value,
                'session_id' => $session->id,
                'user_id' => $user->id,
                'expected' => $expectedAmount,
                'counted' => $countedAmount,
                'difference' => $difference,
                'has_discrepancy' => $hasDiscrepancy,
            ]);

            return $count;
        });
    }

    /**
     * Valida la estructura de denominaciones.
     */
    private function validateDenominations(array $denominations): void
    {
        if (!isset($denominations['bills']) || !isset($denominations['coins'])) {
            throw new CashierException(
                'La estructura de denominaciones debe tener "bills" y "coins".'
            );
        }

        foreach ($denominations['bills'] as $value => $quantity) {
            if (!Denomination::isBill((int) $value)) {
                throw new CashierException("Denominación de billete inválida: {$value}");
            }
            if (!is_numeric($quantity) || $quantity < 0) {
                throw new CashierException("Cantidad inválida para billete {$value}: {$quantity}");
            }
        }

        foreach ($denominations['coins'] as $value => $quantity) {
            if (!Denomination::isCoin((int) $value)) {
                throw new CashierException("Denominación de moneda inválida: {$value}");
            }
            if (!is_numeric($quantity) || $quantity < 0) {
                throw new CashierException("Cantidad inválida para moneda {$value}: {$quantity}");
            }
        }
    }

    /**
     * Supervisa un arqueo con discrepancia.
     * Requerido cuando la diferencia supera el umbral de supervisión.
     */
    public function superviseDiscrepancy(
        CashCount $count,
        User $supervisor,
        string $explanation
    ): CashCount {
        if (!$count->has_discrepancy) {
            throw new CashierException(
                'El arqueo no tiene discrepancia que requiera supervisión.'
            );
        }

        if ($supervisor->id === $count->user_id) {
            throw new CashierException(
                'El supervisor no puede ser el mismo cajero que realizó el arqueo.'
            );
        }

        if (empty(trim($explanation))) {
            throw new CashierException(
                'La justificación de la discrepancia es requerida.'
            );
        }

        $count->supervise($supervisor, $explanation);

        Log::info('Arqueo supervisado', [
            'count_id' => $count->id,
            'supervisor_id' => $supervisor->id,
            'difference' => $count->difference,
        ]);

        return $count;
    }

    /**
     * Obtiene el último arqueo de una sesión.
     */
    public function getLastCount(CashSession $session, ?CashCountType $type = null): ?CashCount
    {
        $query = $session->counts()->orderByDesc('created_at');
        
        if ($type) {
            $query->where('type', $type);
        }
        
        return $query->first();
    }

    /**
     * Obtiene todos los arqueos con discrepancia de una sesión.
     */
    public function getDiscrepantCounts(CashSession $session)
    {
        return $session->counts()
            ->where('has_discrepancy', true)
            ->orderByDesc('created_at')
            ->get();
    }
}
