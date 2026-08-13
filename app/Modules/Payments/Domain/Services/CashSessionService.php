<?php

namespace Modules\Payments\Domain\Services;

use Illuminate\Support\Facades\DB;
use Modules\Payments\Domain\Entities\CashSession;
use Modules\Payments\Domain\Exceptions\PaymentException;
use Modules\Payments\Domain\ValueObjects\CashSessionStatus;
use Modules\Payments\Domain\ValueObjects\PaymentMethodType;

/**
 * Servicio de dominio para gestión de sesiones de caja.
 * Según Arquitectura v1.1 Sección 15: apertura/cierre de cajón con arqueo.
 */
class CashSessionService
{
    /**
     * Abre una nueva sesión de caja.
     */
    public function openSession(
        int $companyId,
        int $branchId,
        int $userId,
        float $openingAmount,
        ?string $notes = null
    ): CashSession {
        return DB::transaction(function () use ($companyId, $branchId, $userId, $openingAmount, $notes) {
            // Verificar que no haya una sesión abierta en la sucursal
            $existingOpen = CashSession::where('branch_id', $branchId)
                ->open()
                ->first();

            if ($existingOpen) {
                throw PaymentException::cashSessionNotOpen();
            }

            // Generar número de sesión
            $sessionNumber = sprintf('CS-%s-%s', date('Ymd'), strtoupper(substr(uniqid(), -6)));

            return CashSession::create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'user_id' => $userId,
                'session_number' => $sessionNumber,
                'status' => CashSessionStatus::OPEN,
                'opening_amount' => $openingAmount,
                'opening_notes' => $notes,
                'opened_at' => now(),
            ]);
        });
    }

    /**
     * Cierra una sesión de caja con arqueo.
     */
    public function closeSession(
        CashSession $session,
        float $closingAmount,
        ?string $notes = null
    ): CashSession {
        return DB::transaction(function () use ($session, $closingAmount, $notes) {
            if (!$session->status->isActive()) {
                throw PaymentException::cashSessionNotOpen();
            }

            // Calcular monto esperado
            $expected = $session->calculateExpectedAmount();

            // Calcular diferencia
            $difference = round($closingAmount - $expected, 2);

            $session->status = CashSessionStatus::CLOSED;
            $session->closing_amount = $closingAmount;
            $session->expected_amount = $expected;
            $session->difference = $difference;
            $session->closing_notes = $notes;
            $session->closed_at = now();
            $session->save();

            return $session;
        });
    }

    /**
     * Obtiene la sesión abierta de una sucursal.
     */
    public function getOpenSession(int $branchId): ?CashSession
    {
        return CashSession::where('branch_id', $branchId)
            ->open()
            ->first();
    }
}
