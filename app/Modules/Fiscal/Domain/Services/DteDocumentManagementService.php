<?php

namespace Modules\Fiscal\Domain\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Modules\Fiscal\Domain\Entities\DteCertificate;
use Modules\Fiscal\Domain\Entities\DteDocument;
use Modules\Fiscal\Domain\Exceptions\NoFoliosAvailableException;
use Modules\Fiscal\Domain\ValueObjects\DteStatus;
use Modules\Identity\Domain\Entities\User;
use Modules\Orders\Domain\Entities\Order;

/**
 * Servicio de dominio para gestión operativa de DTEs.
 * 
 * Extraído de DteDocumentController en S3 para cumplir DDD:
 * - Centraliza búsqueda de certificados activos (DRY: usado en issue/cancel/resend)
 * - Valida reglas de negocio (paid status, no duplicado, canBeResent)
 * - Lanza DomainExceptions en lugar de retornar JSON
 * - Separa orquestación HTTP (controller) de lógica fiscal
 * 
 * Nota: Este service ORQUESTA los services existentes (DteIssuingService, DteSendingService)
 * pero NO los reemplaza. Ellos siguen encargados de la generación XML y envío HTTP al SII.
 */
class DteDocumentManagementService
{
    public function __construct(
        private DteIssuingService $issuingService,
        private DteSendingService $sendingService
    ) {}

    /**
     * Lista DTEs con filtros aplicados.
     * 
     * @param array{dte_type?: int, status?: string, start_date?: string, end_date?: string, limit?: int} $filters
     */
    public function listDtes(User $user, array $filters): Collection
    {
        $query = DteDocument::where('company_id', $user->company_id)
            ->where('branch_id', $user->branch_id)
            ->with(['order'])
            ->orderByDesc('issue_date')
            ->orderByDesc('folio');

        if (!empty($filters['dte_type'])) {
            $query->where('dte_type', (int) $filters['dte_type']);
        }
        if (!empty($filters['status'])) {
            $query->where('sii_status', $filters['status']);
        }
        if (!empty($filters['start_date'])) {
            $query->whereDate('issue_date', '>=', $filters['start_date']);
        }
        if (!empty($filters['end_date'])) {
            $query->whereDate('issue_date', '<=', $filters['end_date']);
        }

        $limit = (int) ($filters['limit'] ?? 50);

        return $query->limit(min($limit, 200))->get();
    }

    /**
     * Emite un DTE manualmente para un pedido y lo envía al SII.
     * 
     * @throws \DomainException Si el pedido no está pagado o ya tiene DTE
     * @throws NoFoliosAvailableException Si no hay folios disponibles
     */
    public function issueDte(Order $order, User $user, string $environment, ?string $receiverRut, ?string $receiverName): DteDocument
    {
        // Validación: pedido debe estar pagado
        if ($order->status->value !== 'paid') {
            throw new \DomainException('Solo se pueden emitir DTEs para pedidos pagados.');
        }

        // Validación: no debe existir DTE activo para este pedido
        $existingDte = DteDocument::where('order_id', $order->id)
            ->where('company_id', $user->company_id)
            ->whereNotIn('sii_status', [DteStatus::CANCELLED, DteStatus::REJECTED])
            ->first();

        if ($existingDte) {
            throw new \DomainException('El pedido ya tiene un DTE emitido: ' . $existingDte->identifier());
        }

        // Emitir DTE
        $dte = $this->issuingService->issueForOrder(
            $order,
            $receiverRut,
            $receiverName,
            $environment
        );

        // Intentar envío al SII (no bloquea si falla el certificado)
        $certificate = $this->getActiveCertificate($user->company_id, $environment);
        if ($certificate) {
            $this->sendingService->send($dte, $certificate, $environment);
            $dte->refresh();
        }

        return $dte;
    }

    /**
     * Anula un DTE emitiendo una Nota de Crédito y enviándola al SII.
     * 
     * @throws \DomainException Si no hay certificado activo
     */
    public function cancelDte(DteDocument $dte, User $user, string $reason): array
    {
        $nc = $this->issuingService->issueCancellationNote($dte, $reason);

        $certificate = $this->getActiveCertificate($user->company_id, 'certification');
        if (!$certificate) {
            throw new \DomainException('No hay certificado válido para enviar la Nota de Crédito.');
        }

        $this->sendingService->send($nc, $certificate, 'certification');
        $nc->refresh();

        return [
            'original_dte' => $dte->identifier(),
            'cancellation_note' => $nc->identifier(),
            'nc_status' => $nc->sii_status->value,
        ];
    }

    /**
     * Reintenta envío de un DTE fallido al SII.
     * 
     * @throws \DomainException Si el DTE no puede reenviarse o no hay certificado
     */
    public function resendDte(DteDocument $dte, User $user): array
    {
        if (!$dte->sii_status->canBeResent()) {
            throw new \DomainException(
                'El DTE no puede ser reenviado en estado: ' . $dte->sii_status->label()
            );
        }

        $certificate = $this->getActiveCertificate($user->company_id, 'certification');
        if (!$certificate) {
            throw new \DomainException('No hay certificado válido para reenviar.');
        }

        $sent = $this->sendingService->send($dte, $certificate, 'certification');
        $dte->refresh();

        return [
            'sent' => $sent,
            'dte_status' => $dte->sii_status->value,
            'track_id' => $dte->track_id,
        ];
    }

    /**
     * Obtiene el certificado activo para una empresa y ambiente.
     * Retorna null si no hay certificado válido.
     * 
     * Helper centralizado: elimina duplicación en issue/cancel/resend.
     */
    private function getActiveCertificate(int $companyId, string $environment): ?DteCertificate
    {
        return DteCertificate::where('company_id', $companyId)
            ->where('environment', $environment)
            ->where('is_active', true)
            ->where('valid_until', '>=', now())
            ->first();
    }
}
