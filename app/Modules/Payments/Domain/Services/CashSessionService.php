<?php

namespace Modules\Payments\Domain\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Payments\Domain\Entities\CashSession;
use Modules\Payments\Domain\Exceptions\PaymentException;
use Modules\Payments\Domain\ValueObjects\CashSessionStatus;
use Modules\Cashier\Domain\Events\DrawerOpened;

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
        int $openingAmount,
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

            $session = CashSession::create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'user_id' => $userId,
                'session_number' => $sessionNumber,
                'status' => CashSessionStatus::OPEN,
                'opening_amount' => $openingAmount,
                'opening_notes' => $notes,
                'opened_at' => now(),
            ]);

            // Disparar evento de auditoría
            DrawerOpened::dispatch($session, $notes ?? 'Apertura de sesión de caja / 打开收银会话');

            return $session;
        });
    }

    /**
     * CA-05 FIX: Cierra una sesión de caja con arqueo, bloqueando la sesión para evitar
     * race conditions con pagos concurrentes.
     */
    public function closeSession(
        CashSession $session,
        int $closingAmount,
        ?string $notes = null
    ): CashSession {
        return DB::transaction(function () use ($session, $closingAmount, $notes) {
            // CA-05: Bloquear la sesión para evitar que se registren pagos concurrentemente
            $lockedSession = CashSession::where('id', $session->id)
                ->lockForUpdate()
                ->firstOrFail();

            // CA-05: Verificar que sigue abierta después del bloqueo
            if (!$lockedSession->status->isActive()) {
                throw PaymentException::cashSessionNotOpen();
            }

            // CA-05: Calcular el monto esperado con la sesión bloqueada
            $expected = $lockedSession->calculateExpectedAmountForClose();
            
            Log::info('Cierre de caja / 关闭收银会话', [
                'session' => $lockedSession->session_number,
                'expected' => $expected,
                'closing_amount' => $closingAmount,
            ]);

            // Calcular diferencia
            $difference = $closingAmount - $expected;

            $lockedSession->status = CashSessionStatus::CLOSED;
            $lockedSession->closing_amount = $closingAmount;
            $lockedSession->expected_amount = $expected;
            $lockedSession->difference = $difference;
            $lockedSession->closing_notes = $notes;
            $lockedSession->closed_at = now();
            $lockedSession->save();

            return $lockedSession;
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
