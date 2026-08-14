<?php

namespace Modules\Fiscal\Domain\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Fiscal\Domain\Entities\DteCertificate;
use Modules\Fiscal\Domain\Entities\DteDocument;
use Modules\Fiscal\Domain\Entities\DteFolioRange;
use Modules\Fiscal\Domain\Exceptions\NoFoliosAvailableException;
use Modules\Fiscal\Domain\ValueObjects\DteStatus;
use Modules\Fiscal\Domain\ValueObjects\DteType;
use Modules\Orders\Domain\Entities\Order;

/**
 * Servicio para emitir Documentos Tributarios Electrónicos (DTE).
 * 
 * Flujo completo:
 * 1. Determinar tipo de DTE según RUT del receptor
 * 2. Consumir folio del rango activo
 * 3. Generar XML del DTE
 * 4. Firmar XML con certificado digital
 * 5. Crear registro en BD
 * 6. (En Bloque 3) Enviar al SII vía WebService
 */
class DteIssuingService
{
    public function __construct(
        private DteXmlGenerator $xmlGenerator = new DteXmlGenerator()
    ) {}

    /**
     * Emite un DTE para un pedido pagado.
     * 
     * @param Order $order Pedido pagado
     * @param string|null $receiverRut RUT del receptor (null = consumidor final)
     * @param string|null $receiverBusinessName Razón social (para facturas)
     * @param string $environment Ambiente: 'certification' o 'production'
     * @return DteDocument Documento emitido
     * @throws NoFoliosAvailableException Si no hay folios disponibles
     * @throws \RuntimeException Si no hay certificado válido
     */
    public function issueForOrder(
        Order $order,
        ?string $receiverRut = null,
        ?string $receiverBusinessName = null,
        string $environment = 'certification'
    ): DteDocument {
        // 1. Determinar tipo de DTE
        $hasExempt = $this->orderHasExemptItems($order);
        $dteType = DteType::defaultForOrder($receiverRut, $hasExempt);
        
        // 2. Obtener rango de folios activo
        $folioRange = DteFolioRange::getActiveForType(
            $order->company_id,
            $order->branch_id,
            $dteType
        );
        
        if (!$folioRange) {
            throw new NoFoliosAvailableException($dteType, 0);
        }
        
        // 3. Obtener certificado válido
        $certificate = $this->getValidCertificate($order->company_id, $environment);
        
        // 4. Consumir folio y crear DTE en transacción
        return DB::transaction(function () use ($order, $dteType, $folioRange, $receiverRut, $receiverBusinessName, $certificate) {
            // Consumir folio (puede lanzar excepción si no hay disponibles)
            $folio = $folioRange->consumeFolio();
            
            // Calcular montos
            $netAmount = (float) $order->subtotal;
            $taxAmount = (float) $order->tax_amount;
            $totalAmount = (float) $order->total;
            
            // Crear documento en estado pendiente
            $dte = DteDocument::create([
                'company_id' => $order->company_id,
                'branch_id' => $order->branch_id,
                'dte_type' => $dteType,
                'folio' => $folio,
                'order_id' => $order->id,
                'receiver_rut' => $receiverRut,
                'receiver_business_name' => $receiverBusinessName,
                'net_amount' => $netAmount,
                'tax_amount' => $taxAmount,
                'exempt_amount' => 0,
                'total_amount' => $totalAmount,
                'sii_status' => DteStatus::PENDING,
                'issue_date' => now()->toDateString(),
            ]);
            
            // 5. Generar XML firmado
            $issuerRut = $order->company->tax_id ?? '76.000.000-0';
            $issuerName = $order->company->trade_name ?? 'Empresa';
            
            $detailItems = $this->buildDetailItems($order);
            
            $signedXml = $this->xmlGenerator->generateSignedXml(
                dte: $dte,
                detailItems: $detailItems,
                issuerRut: $issuerRut,
                issuerBusinessName: $issuerName,
                branchCode: 0,
                certificate: $certificate
            );
            
            // 6. Guardar XML firmado (aún no enviado al SII)
            $dte->sent_xml = $signedXml;
            $dte->save();
            
            Log::info('DTE emitido localmente', [
                'dte_id' => $dte->id,
                'identifier' => $dte->identifier(),
                'order_id' => $order->id,
                'type' => $dteType->label(),
                'total' => $totalAmount,
                'xml_size' => strlen($signedXml),
            ]);
            
            return $dte;
        });
    }

    /**
     * Emite una Nota de Crédito para anular un DTE.
     */
    public function issueCancellationNote(DteDocument $originalDte, string $reason): DteDocument
    {
        if (!$originalDte->sii_status->canBeCancelled()) {
            throw new \RuntimeException(
                "El DTE {$originalDte->identifier()} no puede ser anulado. Estado: {$originalDte->sii_status->label()}"
            );
        }
        
        // Obtener folio para NC
        $folioRange = DteFolioRange::getActiveForType(
            $originalDte->company_id,
            $originalDte->branch_id,
            DteType::NOTA_CREDITO
        );
        
        if (!$folioRange) {
            throw new NoFoliosAvailableException(DteType::NOTA_CREDITO, 0);
        }
        
        return DB::transaction(function () use ($originalDte, $folioRange, $reason) {
            $folio = $folioRange->consumeFolio();
            
            $nc = DteDocument::create([
                'company_id' => $originalDte->company_id,
                'branch_id' => $originalDte->branch_id,
                'dte_type' => DteType::NOTA_CREDITO,
                'folio' => $folio,
                'order_id' => $originalDte->order_id,
                'receiver_rut' => $originalDte->receiver_rut,
                'receiver_business_name' => $originalDte->receiver_business_name,
                'net_amount' => $originalDte->net_amount,
                'tax_amount' => $originalDte->tax_amount,
                'exempt_amount' => $originalDte->exempt_amount,
                'total_amount' => $originalDte->total_amount,
                'sii_status' => DteStatus::PENDING,
                'issue_date' => now()->toDateString(),
                'referenced_dte_id' => $originalDte->id,
            ]);
            
            // Generar XML de NC
            $issuerRut = $originalDte->company->tax_id ?? '76.000.000-0';
            $issuerName = $originalDte->company->trade_name ?? 'Empresa';
            
            $detailItems = [[
                'name' => 'Anulación: ' . $reason,
                'qty' => 1,
                'unit_price' => (float) $originalDte->total_amount,
                'amount' => (float) $originalDte->total_amount,
            ]];
            
            $signedXml = $this->xmlGenerator->generateSignedXml(
                dte: $nc,
                detailItems: $detailItems,
                issuerRut: $issuerRut,
                issuerBusinessName: $issuerName,
                branchCode: 0
            );
            
            $nc->sent_xml = $signedXml;
            $nc->save();
            
            // Marcar original como anulado
            $originalDte->sii_status = DteStatus::CANCELLED;
            $originalDte->sii_status_description = "Anulado por NC folio {$folio}. Razón: {$reason}";
            $originalDte->save();
            
            Log::info('Nota de Crédito emitida', [
                'nc_id' => $nc->id,
                'nc_identifier' => $nc->identifier(),
                'original_dte' => $originalDte->identifier(),
                'reason' => $reason,
            ]);
            
            return $nc;
        });
    }

    /**
     * Obtiene certificado válido para el ambiente especificado.
     */
    private function getValidCertificate(int $companyId, string $environment): ?DteCertificate
    {
        $certificate = DteCertificate::where('company_id', $companyId)
            ->where('environment', $environment)
            ->where('is_active', true)
            ->where('valid_from', '<=', now())
            ->where('valid_until', '>=', now())
            ->orderByDesc('valid_until')
            ->first();
        
        if ($certificate && $certificate->isExpiringSoon()) {
            Log::warning('Certificado DTE próximo a vencer', [
                'certificate_id' => $certificate->id,
                'days_remaining' => $certificate->daysUntilExpiration(),
                'valid_until' => $certificate->valid_until->toDateString(),
            ]);
        }
        
        return $certificate;
    }

    /**
     * Determina si el pedido tiene productos exentos de IVA.
     * Por ahora retornamos false (todos los productos son afectos).
     */
    private function orderHasExemptItems(Order $order): bool
    {
        // En el futuro: revisar category.is_exempt de cada producto
        return false;
    }

    /**
     * Construye el array de items del detalle desde el pedido.
     */
    private function buildDetailItems(Order $order): array
    {
        $order->load('items');
        
        return $order->items->map(function ($item) {
            return [
                'name' => $item->name_snapshot ?? 'Producto',
                'qty' => (int) $item->quantity,
                'unit_price' => (float) $item->unit_price_snapshot,
                'amount' => (float) $item->subtotal,
            ];
        })->toArray();
    }
}
