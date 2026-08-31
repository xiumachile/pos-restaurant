<?php

namespace Modules\Fiscal\Interfaces\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request para emitir un Documento Tributario Electrónico (DTE).
 * 
 * Este endpoint genera una boleta o factura electrónica según los datos del receptor.
 * La lógica de determinación es automática:
 * 
 * - **Sin RUT**: Genera boleta electrónica (tipo 39)
 * - **Con RUT**: Genera factura electrónica (tipo 33)
 * 
 * ## Emisión de Boleta (sin datos del cliente):
 * 
 * ```json
 * {
 *   "order_uuid": "550e8400-e29b-41d4-a716-446655440000",
 *   "environment": "certification"
 * }
 * ```
 * 
 * ## Emisión de Factura (con datos del cliente):
 * 
 * ```json
 * {
 *   "order_uuid": "550e8400-e29b-41d4-a716-446655440000",
 *   "receiver_rut": "76123456-7",
 *   "receiver_business_name": "Empresa Ejemplo SpA",
 *   "environment": "production"
 * }
 * ```
 * 
 * ## Ambientes disponibles:
 * 
 * - **certification**: Ambiente de pruebas del SII. Los DTEs generados NO son válidos fiscalmente.
 * - **production**: Ambiente productivo del SII. Los DTEs generados SON válidos fiscalmente.
 * 
 * Si no se especifica `environment`, se usa el configurado en la empresa.
 * 
 * ## Validaciones:
 * 
 * - El pedido debe estar en estado `paid` (completamente pagado)
 * - El pedido NO debe tener un DTE emitido previamente
 * - Debe existir un rango de folios (CAF) activo para el tipo de DTE
 * - Debe existir un certificado digital válido para firmar el DTE
 * 
 * ## Flujo de emisión:
 * 
 * 1. Valida que el pedido esté pagado y sin DTE previo
 * 2. Determina tipo de DTE (boleta o factura) según presencia de RUT
 * 3. Obtiene folio disponible del rango CAF activo
 * 4. Construye XML del DTE con datos del pedido y receptor
 * 5. Firma el XML con el certificado digital
 * 6. Envía al SII y espera respuesta (aceptado/rechazado)
 * 7. Actualiza estado del DTE según respuesta del SII
 * 
 * @see \Modules\Fiscal\Interfaces\Controllers\DteController::issue()
 */
class IssueDteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()->role, ['manager', 'admin', 'cashier']);
    }

    public function rules(): array
    {
        return [
            'order_uuid' => ['required', 'uuid', 'exists:orders,uuid'],
            'receiver_rut' => ['nullable', 'string', 'max:20', 'regex:/^[0-9]{7,8}-[0-9Kk]$/'],
            'receiver_business_name' => ['nullable', 'string', 'max:200', 'required_with:receiver_rut'],
            'environment' => ['nullable', 'string', 'in:certification,production'],
        ];
    }

    public function messages(): array
    {
        return [
            'order_uuid.required' => 'El UUID del pedido es requerido.',
            'order_uuid.exists' => 'El pedido no existe.',
            'receiver_rut.regex' => 'El RUT debe tener formato válido (ej: 76123456-7).',
            'receiver_business_name.required_with' => 'La razón social es requerida cuando hay RUT.',
            'environment.in' => 'El ambiente debe ser certification o production.',
        ];
    }
}
