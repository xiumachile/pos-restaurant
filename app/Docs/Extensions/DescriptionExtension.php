<?php

namespace App\Docs\Extensions;

use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\RouteInfo;

/**
 * Agrega descripciones específicas a endpoints críticos.
 */
class DescriptionExtension extends OperationExtension
{
    private const DESCRIPTIONS = [
        // Orders
        'orders.store' => 'Crea un nuevo pedido en estado DRAFT. Requiere especificar tipo (dine_in/takeaway/delivery) y si es dine_in, requiere table_uuid.',
        'orders.update' => 'Actualiza un pedido. Solo editable si no está pagado o cerrado. Si la empresa NO tiene has_kitchen_display, al confirmar salta directo a READY.',
        
        // Payments
        'payments.store' => 'Registra un pago para un pedido. Requiere sesión de caja abierta (requires_cashier_session). Soporta idempotencia con X-Idempotency-Key header.',
        'orders.split' => 'Divide un pedido en sub-cuentas. Requiere capability can_split_bills. Soporta 3 modalidades: equal_split, by_items, custom_amount.',
        
        // Cash sessions
        'cash-sessions.open' => 'Abre una sesión de caja para la sucursal. Solo permitido si requires_cashier_session está habilitado.',
        'cash-sessions.close' => 'Cierra una sesión de caja con arqueo final. Calcula discrepancia entre amount expected y actual.',
        
        // Fiscal
        'fiscal.dtes.store' => 'Emite un DTE (Documento Tributario Electrónico) manualmente. Genera boleta (sin RUT) o factura (con RUT y razón social). Consume folio del rango activo.',
        'fiscal.dtes.cancel' => 'Anula un DTE aceptado emitiendo una Nota de Crédito electrónica. El DTE original pasa a estado CANCELLED.',
        'fiscal.dtes.resend' => 'Reintenta envío de un DTE fallido (estado ERROR o REJECTED) al SII.',
        'fiscal.folios.store' => 'Carga un nuevo CAF (rango de folios autorizado por SII). Los folios se consumen en orden secuencial con protección de concurrencia (lockForUpdate).',
        'fiscal.certificates.store' => 'Sube un certificado digital .pfx para firmar DTEs. Válido para certification o production environment.',
        
        // Capabilities
        'companies.capabilities.update' => 'Actualiza las capabilities de una empresa. Permite activar/desactivar funcionalidades como can_split_bills, can_accept_tips, has_kitchen_display, etc.',
        
        // Kitchen
        'kitchen.queue' => 'Retorna la cola de pedidos activos para KDS (Kitchen Display System). Solo disponible si has_kitchen_display está activo.',
        'kitchen.ready' => 'Marca un pedido como READY (listo para servir).',
    ];

    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        $routeName = $routeInfo->route->getName();
        
        if ($routeName && isset(self::DESCRIPTIONS[$routeName])) {
            // Usar el método setter summary()
            $operation->summary(self::DESCRIPTIONS[$routeName]);
        }
    }
}
