<?php

namespace Modules\Fiscal\Domain\Listeners;

use Illuminate\Support\Facades\Log;
use Modules\Fiscal\Domain\Entities\DteCertificate;
use Modules\Fiscal\Domain\Services\DteIssuingService;
use Modules\Fiscal\Domain\Services\DteSendingService;
use Modules\Orders\Domain\Events\OrderPaid;

/**
 * Listener que emite automáticamente un DTE cuando un pedido es pagado.
 * 
 * Flujo:
 * 1. Recibe evento OrderPaid
 * 2. Emite DTE usando DteIssuingService
 * 3. Envía DTE al SII usando DteSendingService
 * 4. (En Bloque 4) Imprime boleta con QR
 */
class IssueDteOnOrderPaid
{
    public function __construct(
        private DteIssuingService $issuingService = new DteIssuingService(),
        private DteSendingService $sendingService = new DteSendingService()
    ) {}

    public function handle(OrderPaid $event): void
    {
        $order = $event->order;
        $order->load(['company', 'branch', 'items']);

        Log::info('IssueDteOnOrderPaid: procesando pedido pagado', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'total' => $order->total,
        ]);

        try {
            // 1. Determinar ambiente (certificación por defecto)
            $environment = 'certification'; // TODO: obtener de configuración de empresa

            // 2. Verificar que haya certificado válido
            $certificate = DteCertificate::where('company_id', $order->company_id)
                ->where('environment', $environment)
                ->where('is_active', true)
                ->where('valid_until', '>=', now())
                ->first();

            if (!$certificate) {
                Log::warning('No hay certificado DTE válido, no se emitirá boleta', [
                    'order_id' => $order->id,
                    'environment' => $environment,
                ]);
                return;
            }

            // 3. Emitir DTE (sin RUT = consumidor final = boleta)
            // TODO: obtener RUT del cliente si existe
            $receiverRut = null; // Por ahora siempre boleta
            $receiverName = null;

            $dte = $this->issuingService->issueForOrder(
                $order,
                $receiverRut,
                $receiverName,
                $environment
            );

            Log::info('DTE emitido localmente', [
                'dte_id' => $dte->id,
                'identifier' => $dte->identifier(),
                'order_id' => $order->id,
            ]);

            // 4. Enviar al SII
            $sent = $this->sendingService->send($dte, $certificate, $environment);

            if ($sent) {
                Log::info('DTE enviado y aceptado por SII', [
                    'dte_id' => $dte->id,
                    'identifier' => $dte->identifier(),
                    'track_id' => $dte->track_id,
                ]);
            } else {
                Log::warning('DTE emitido pero no aceptado por SII', [
                    'dte_id' => $dte->id,
                    'identifier' => $dte->identifier(),
                    'status' => $dte->sii_status->value,
                ]);
            }

        } catch (\Modules\Fiscal\Domain\Exceptions\NoFoliosAvailableException $e) {
            Log::error('No hay folios disponibles para emitir DTE', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        } catch (\Exception $e) {
            Log::error('Error emitiendo DTE', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
