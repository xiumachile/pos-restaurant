<?php

namespace Modules\Companies\Domain\ValueObjects;

/**
 * Enum de capabilities disponibles en el sistema.
 * 
 * Cada capability representa una funcionalidad que puede ser
 * habilitada o deshabilitada por empresa.
 */
enum CapabilityKey: string
{
    // Gestión de cuentas
    case CAN_SPLIT_BILLS = 'can_split_bills';
    
    // Inventario
    case CAN_MANAGE_INVENTORY = 'can_manage_inventory';
    
    // Caja
    case REQUIRES_CASHIER_SESSION = 'requires_cashier_session';
    
    // Propinas
    case CAN_ACCEPT_TIPS = 'can_accept_tips';
    
    // Cocina
    case HAS_KITCHEN_DISPLAY = 'has_kitchen_display';
    
    // Impresión
    case CAN_PRINT_RECEIPTS = 'can_print_receipts';
    
    // Lealtad
    case SUPPORTS_LOYALTY_PROGRAM = 'supports_loyalty_program';
    
    // Reservaciones
    case CAN_MANAGE_RESERVATIONS = 'can_manage_reservations';
    
    /**
     * Obtiene todos los capabilities disponibles.
     */
    public static function all(): array
    {
        return array_column(self::cases(), 'value');
    }
    
    /**
     * Verifica si una clave es válida.
     */
    public static function isValid(string $key): bool
    {
        return self::tryFrom($key) !== null;
    }
    
    /**
     * Descripción legible del capability.
     */
    public function description(): string
    {
        return match($this) {
            self::CAN_SPLIT_BILLS => 'Dividir cuentas',
            self::CAN_MANAGE_INVENTORY => 'Gestionar inventario',
            self::REQUIRES_CASHIER_SESSION => 'Requiere apertura/cierre de caja',
            self::CAN_ACCEPT_TIPS => 'Aceptar propinas',
            self::HAS_KITCHEN_DISPLAY => 'Kitchen Display System',
            self::CAN_PRINT_RECEIPTS => 'Imprimir recibos',
            self::SUPPORTS_LOYALTY_PROGRAM => 'Programa de lealtad',
            self::CAN_MANAGE_RESERVATIONS => 'Gestionar reservaciones',
        };
    }
}
