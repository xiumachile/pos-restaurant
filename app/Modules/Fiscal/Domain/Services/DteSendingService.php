<?php

namespace Modules\Fiscal\Domain\Services;

use Illuminate\Support\Facades\Log;
use Modules\Fiscal\Domain\Entities\DteCertificate;
use Modules\Fiscal\Domain\Entities\DteDocument;
use Modules\Fiscal\Domain\Events\DteAccepted;
use Modules\Fiscal\Domain\ValueObjects\DteStatus;

/**
 * Servicio para enviar DTEs al SII y procesar respuestas.
 */
class DteSendingService
{
    public function __construct(
        private SiiWebServiceClient $siiClient = new SiiWebServiceClient()
    ) {}

    /**
     * Envía un DTE pendiente al SII.
     * 
     * @param DteDocument $dte Documento a enviar
     * @param DteCertificate $certificate Certificado para firma
     * @param string $environment Ambiente (certification/production)
     * @return bool true si fue enviado exitosamente
     */
    public function send(DteDocument $dte, DteCertificate $certificate, string $environment = 'certification'): bool
    {
        if (!$dte->sii_status->canBeResent()) {
            Log::warning('DTE no puede ser enviado', [
                'dte_id' => $dte->id,
                'status' => $dte->sii_status->value,
            ]);
            return false;
        }

        try {
            // 1. Enviar al SII
            $trackId = $this->siiClient->sendDte(
                $dte->sent_xml,
                $certificate,
                $environment
            );

            // 2. Marcar como enviado
            $dte->markAsSent($trackId, $dte->sent_xml);

            // 3. Consultar estado (en producción sería asíncrono)
            $statusResponse = $this->siiClient->queryStatus($trackId, $environment);

            // 4. Procesar respuesta
            if ($statusResponse['status'] === 'accepted') {
                $dte->markAsAccepted($statusResponse['timbre'] ?? '');
                
                // Disparar evento para notificaciones/impresión
                event(new DteAccepted($dte));
                
                Log::info('DTE aceptado por SII', [
                    'dte_id' => $dte->id,
                    'identifier' => $dte->identifier(),
                    'track_id' => $trackId,
                ]);
                
                return true;
            } else {
                $dte->markAsRejected($statusResponse['description'] ?? 'Rechazado por SII');
                
                Log::warning('DTE rechazado por SII', [
                    'dte_id' => $dte->id,
                    'identifier' => $dte->identifier(),
                    'track_id' => $trackId,
                    'reason' => $statusResponse['description'],
                ]);
                
                return false;
            }
        } catch (\Exception $e) {
            $dte->markAsError($e->getMessage());
            
            Log::error('Error enviando DTE a SII', [
                'dte_id' => $dte->id,
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }

    /**
     * Reintenta envío de DTEs fallidos.
     */
    public function retryFailed(int $companyId, int $branchId, int $limit = 10): array
    {
        $failedDtes = DteDocument::where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->whereIn('sii_status', [DteStatus::ERROR, DteStatus::REJECTED])
            ->whereNotNull('sent_xml')
            ->orderBy('created_at')
            ->limit($limit)
            ->get();

        $results = [
            'total' => $failedDtes->count(),
            'success' => 0,
            'failed' => 0,
        ];

        foreach ($failedDtes as $dte) {
            $certificate = DteCertificate::where('company_id', $companyId)
                ->where('is_active', true)
                ->where('valid_until', '>=', now())
                ->first();

            if (!$certificate) {
                Log::warning('No hay certificado válido para reintentar', [
                    'dte_id' => $dte->id,
                ]);
                $results['failed']++;
                continue;
            }

            if ($this->send($dte, $certificate)) {
                $results['success']++;
            } else {
                $results['failed']++;
            }
        }

        return $results;
    }
}
