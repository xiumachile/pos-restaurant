/**
 * Tipos para el sistema de capabilities (feature flags por empresa).
 * Espejo de Modules\Companies\Domain\ValueObjects\CapabilityKey
 */

export enum CapabilityKey {
  CAN_SPLIT_BILLS = 'can_split_bills',
  CAN_MANAGE_INVENTORY = 'can_manage_inventory',
  REQUIRES_CASHIER_SESSION = 'requires_cashier_session',
  CAN_ACCEPT_TIPS = 'can_accept_tips',
  HAS_KITCHEN_DISPLAY = 'has_kitchen_display',
  CAN_PRINT_RECEIPTS = 'can_print_receipts',
  SUPPORTS_LOYALTY_PROGRAM = 'supports_loyalty_program',
  CAN_MANAGE_RESERVATIONS = 'can_manage_reservations',
}

export interface CapabilityInfo {
  key: CapabilityKey;
  is_enabled: boolean;
  settings?: Record<string, unknown>;
  description: string;
  icon: string;
  category: 'operations' | 'payments' | 'marketing';
}

export interface CapabilityResponse {
  key: string;
  is_enabled: boolean;
  settings?: Record<string, unknown>;
}

/**
 * Metadata estática para la UI de configuración.
 * No viene del backend, es conocimiento del frontend.
 */
export const CAPABILITY_META: Record<CapabilityKey, Omit<CapabilityInfo, 'is_enabled' | 'settings'>> = {
  [CapabilityKey.CAN_SPLIT_BILLS]: {
    key: CapabilityKey.CAN_SPLIT_BILLS,
    description: 'Permitir dividir cuentas entre varios clientes',
    icon: '📋',
    category: 'payments',
  },
  [CapabilityKey.CAN_MANAGE_INVENTORY]: {
    key: CapabilityKey.CAN_MANAGE_INVENTORY,
    description: 'Gestionar stock e insumos',
    icon: '📦',
    category: 'operations',
  },
  [CapabilityKey.REQUIRES_CASHIER_SESSION]: {
    key: CapabilityKey.REQUIRES_CASHIER_SESSION,
    description: 'Requerir apertura y cierre de caja por turno',
    icon: '💵',
    category: 'payments',
  },
  [CapabilityKey.CAN_ACCEPT_TIPS]: {
    key: CapabilityKey.CAN_ACCEPT_TIPS,
    description: 'Aceptar propinas en los pagos',
    icon: '💰',
    category: 'payments',
  },
  [CapabilityKey.HAS_KITCHEN_DISPLAY]: {
    key: CapabilityKey.HAS_KITCHEN_DISPLAY,
    description: 'Kitchen Display System (pantalla de cocina)',
    icon: '👨‍🍳',
    category: 'operations',
  },
  [CapabilityKey.CAN_PRINT_RECEIPTS]: {
    key: CapabilityKey.CAN_PRINT_RECEIPTS,
    description: 'Imprimir tickets y comandas',
    icon: '🖨️',
    category: 'operations',
  },
  [CapabilityKey.SUPPORTS_LOYALTY_PROGRAM]: {
    key: CapabilityKey.SUPPORTS_LOYALTY_PROGRAM,
    description: 'Programa de lealtad y puntos',
    icon: '⭐',
    category: 'marketing',
  },
  [CapabilityKey.CAN_MANAGE_RESERVATIONS]: {
    key: CapabilityKey.CAN_MANAGE_RESERVATIONS,
    description: 'Gestionar reservaciones de mesas',
    icon: '📅',
    category: 'operations',
  },
};
