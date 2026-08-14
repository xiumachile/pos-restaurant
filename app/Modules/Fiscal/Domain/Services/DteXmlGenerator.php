<?php

namespace Modules\Fiscal\Domain\Services;

use Modules\Fiscal\Domain\Entities\DteCertificate;
use Modules\Fiscal\Domain\Entities\DteDocument;
use Modules\Fiscal\Domain\ValueObjects\DteType;

/**
 * Generador de XML de DTE según formato oficial del SII Chile.
 * 
 * Formato basado en la especificación oficial:
 * https://www.sii.cl/pagina/1422/formatos_xml.htm
 * 
 * Estructura:
 * <DTE version="1.0">
 *   <Documento ID="F{folio}T{type}">
 *     <Encabezado>...</Encabezado>
 *     <Detalle>...</Detalle>
 *     <Totales>...</Totales>
 *   </Documento>
 *   <TED version="1.0">...</TED>
 *   <TmstFirma>...</TmstFirma>
 * </DTE>
 */
class DteXmlGenerator
{
    private const SII_NAMESPACE = 'http://www.sii.cl/SiiDte';
    private const DTE_VERSION = '1.0';

    /**
     * Genera el XML completo del DTE sin firmar (pre-timbrado).
     * 
     * @param DteDocument $dte Documento a emitir
     * @param array $detailItems Items del detalle [{name, qty, unit_price, amount}]
     * @param string $issuerRut RUT del emisor (empresa)
     * @param string $issuerBusinessName Razón social del emisor
     * @param int $branchCode Código de sucursal del SII
     * @return string XML del DTE
     */
    public function generateDocumentXml(
        DteDocument $dte,
        array $detailItems,
        string $issuerRut,
        string $issuerBusinessName,
        int $branchCode = 0
    ): string {
        $xml = '<?xml version="1.0" encoding="ISO-8859-1"?>' . "\n";
        $xml .= '<DTE version="' . self::DTE_VERSION . '" xmlns="' . self::SII_NAMESPACE . '">' . "\n";
        
        $documentId = 'F' . $dte->folio . 'T' . $dte->dte_type->value;
        $xml .= '  <Documento ID="' . $documentId . '">' . "\n";
        $xml .= $this->generateEncabezado($dte, $issuerRut, $issuerBusinessName, $branchCode);
        $xml .= $this->generateDetalle($dte, $detailItems);
        $xml .= $this->generateTotales($dte);
        $xml .= '  </Documento>' . "\n";
        
        $xml .= '</DTE>';
        
        return $xml;
    }

    /**
     * Genera el TED (Timbre Electrónico de Documento).
     * En producción, esto lo genera el SII tras validar el DTE.
     * Aquí simulamos el formato para pruebas.
     */
    public function generateTimbre(
        DteDocument $dte,
        string $issuerRut,
        string $issuerBusinessName
    ): string {
        $xml = '<TED version="1.0">' . "\n";
        $xml .= '  <DD>' . "\n";
        $xml .= '    <RE>' . $this->cleanRut($issuerRut) . '</RE>' . "\n";
        $xml .= '    <TD>' . $dte->dte_type->value . '</TD>' . "\n";
        $xml .= '    <F>' . $dte->folio . '</F>' . "\n";
        $xml .= '    <FE>' . $dte->issue_date->format('Y-m-d') . '</FE>' . "\n";
        $xml .= '    <RR>' . ($dte->receiver_rut ? $this->cleanRut($dte->receiver_rut) : '66666666-6') . '</RR>' . "\n";
        $xml .= '    <RSR>' . htmlspecialchars($dte->receiver_business_name ?? 'Consumidor Final') . '</RSR>' . "\n";
        $xml .= '    <MNT>' . (int) $dte->total_amount . '</MNT>' . "\n";
        $xml .= '    <IT1>' . htmlspecialchars('Productos') . '</IT1>' . "\n";
        $xml .= '  </DD>' . "\n";
        $xml .= '  <FRMT algoritmo="01">' . base64_encode(random_bytes(40)) . '</FRMT>' . "\n";
        $xml .= '</TED>';
        
        return $xml;
    }

    /**
     * Genera el XML completo firmado (preparado para envío al SII).
     * En esta implementación simulamos la firma. En producción se usaría OpenSSL + certificado .pfx
     */
    public function generateSignedXml(
        DteDocument $dte,
        array $detailItems,
        string $issuerRut,
        string $issuerBusinessName,
        int $branchCode = 0,
        ?DteCertificate $certificate = null
    ): string {
        // Generar XML base
        $xml = $this->generateDocumentXml($dte, $detailItems, $issuerRut, $issuerBusinessName, $branchCode);
        
        // Generar timbre (en producción vendría del SII tras validación)
        $timbre = $this->generateTimbre($dte, $issuerRut, $issuerBusinessName);
        
        // Insertar timbre y marca de tiempo antes del cierre de </DTE>
        $timestamp = date('Y-m-d\TH:i:s');
        $replacement = '  ' . $timbre . "\n" . '  <TmstFirma>' . $timestamp . '</TmstFirma>' . "\n</DTE>";
        
        $xml = str_replace('</DTE>', $replacement, $xml);
        
        // En producción: firmar con certificado usando openssl_pkcs7_sign
        // Por ahora retornamos el XML con timbre simulado
        
        if ($certificate) {
            $certificate->recordUsage();
        }
        
        return $xml;
    }

    /**
     * Genera la sección <Encabezado> del DTE.
     */
    private function generateEncabezado(
        DteDocument $dte,
        string $issuerRut,
        string $issuerBusinessName,
        int $branchCode
    ): string {
        $xml = '    <Encabezado>' . "\n";
        
        // Identificación del documento
        $xml .= '      <IdDoc>' . "\n";
        $xml .= '        <TipoDTE>' . $dte->dte_type->value . '</TipoDTE>' . "\n";
        $xml .= '        <Folio>' . $dte->folio . '</Folio>' . "\n";
        $xml .= '        <FchEmis>' . $dte->issue_date->format('Y-m-d') . '</FchEmis>' . "\n";
        
        // Tipo de despacho y traslado (0 = no aplica para boletas)
        if (!$dte->dte_type->isConsumerDocument()) {
            $xml .= '        <TipoDespacho>0</TipoDespacho>' . "\n";
            $xml .= '        <IndTraslado>1</IndTraslado>' . "\n";
        }
        
        $xml .= '      </IdDoc>' . "\n";
        
        // Emisor
        $xml .= '      <Emisor>' . "\n";
        $xml .= '        <RUTEmisor>' . $this->cleanRut($issuerRut) . '</RUTEmisor>' . "\n";
        $xml .= '        <RznSoc>' . htmlspecialchars($issuerBusinessName) . '</RznSoc>' . "\n";
        $xml .= '        <GiroEmis>Restaurant</GiroEmis>' . "\n";
        $xml .= '        <CdgSIISucur>' . $branchCode . '</CdgSIISucur>' . "\n";
        $xml .= '      </Emisor>' . "\n";
        
        // Receptor
        $xml .= '      <RUTRecep>' . ($dte->receiver_rut ? $this->cleanRut($dte->receiver_rut) : '66666666-6') . '</RUTRecep>' . "\n";
        if ($dte->receiver_business_name) {
            $xml .= '      <RznSocRecep>' . htmlspecialchars($dte->receiver_business_name) . '</RznSocRecep>' . "\n";
        }
        
        $xml .= '    </Encabezado>' . "\n";
        
        return $xml;
    }

    /**
     * Genera la sección <Detalle> con los ítems.
     */
    private function generateDetalle(DteDocument $dte, array $detailItems): string
    {
        $xml = '    <Detalle>' . "\n";
        
        $lineNumber = 1;
        foreach ($detailItems as $item) {
            $xml .= '      <NroLinDet>' . $lineNumber . '</NroLinDet>' . "\n";
            $xml .= '      <NmbItem>' . htmlspecialchars($item['name'] ?? 'Producto') . '</NmbItem>' . "\n";
            $xml .= '      <QtyItem>' . ($item['qty'] ?? 1) . '</QtyItem>' . "\n";
            $xml .= '      <PrcItem>' . number_format($item['unit_price'] ?? 0, 2, '.', '') . '</PrcItem>' . "\n";
            $xml .= '      <MontoItem>' . number_format($item['amount'] ?? 0, 2, '.', '') . '</MontoItem>' . "\n";
            $lineNumber++;
        }
        
        $xml .= '    </Detalle>' . "\n";
        
        return $xml;
    }

    /**
     * Genera la sección <Totales> con montos e IVA.
     */
    private function generateTotales(DteDocument $dte): string
    {
        $xml = '    <Totales>' . "\n";
        $xml .= '      <MntNeto>' . (int) $dte->net_amount . '</MntNeto>' . "\n";
        
        if ((float) $dte->exempt_amount > 0) {
            $xml .= '      <MntExento>' . (int) $dte->exempt_amount . '</MntExento>' . "\n";
        }
        
        if ($dte->dte_type->isTaxable()) {
            $xml .= '      <TasaIVA>19</TasaIVA>' . "\n";
            $xml .= '      <IVA>' . (int) $dte->tax_amount . '</IVA>' . "\n";
        }
        
        $xml .= '      <MntTotal>' . (int) $dte->total_amount . '</MntTotal>' . "\n";
        $xml .= '    </Totales>' . "\n";
        
        return $xml;
    }

    /**
     * Limpia un RUT: elimina puntos y mantiene guión.
     * Ej: "76.123.456-7" → "76123456-7"
     */
    private function cleanRut(string $rut): string
    {
        return str_replace(['.', ' '], '', $rut);
    }
}
