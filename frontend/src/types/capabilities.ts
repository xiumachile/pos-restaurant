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
  descriptionKey: string;
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
    descriptionKey: 'capabilities.descriptions.CAN_SPLIT_BILLS',
    icon: '📋',
    category: 'payments',
  },
  [CapabilityKey.CAN_MANAGE_INVENTORY]: {
    key: CapabilityKey.CAN_MANAGE_INVENTORY,
    descriptionKey: 'capabilities.descriptions.CAN_MANAGE_INVENTORY',
    icon: '📦',
    category: 'operations',
  },
  [CapabilityKey.REQUIRES_CASHIER_SESSION]: {
    key: CapabilityKey.REQUIRES_CASHIER_SESSION,
    descriptionKey: 'capabilities.descriptions.REQUIRES_CASHIER_SESSION',
    icon: '💵',
    category: 'payments',
  },
  [CapabilityKey.CAN_ACCEPT_TIPS]: {
    key: CapabilityKey.CAN_ACCEPT_TIPS,
    descriptionKey: 'capabilities.descriptions.CAN_ACCEPT_TIPS',
    icon: '💰',
    category: 'payments',
  },
  [CapabilityKey.HAS_KITCHEN_DISPLAY]: {
    key: CapabilityKey.HAS_KITCHEN_DISPLAY,
    descriptionKey: 'capabilities.descriptions.HAS_KITCHEN_DISPLAY',
    icon: '👨‍🍳',
    category: 'operations',
  },
  [CapabilityKey.CAN_PRINT_RECEIPTS]: {
    key: CapabilityKey.CAN_PRINT_RECEIPTS,
    descriptionKey: 'capabilities.descriptions.CAN_PRINT_RECEIPTS',
    icon: '🖨️',
    category: 'operations',
  },
  [CapabilityKey.SUPPORTS_LOYALTY_PROGRAM]: {
    key: CapabilityKey.SUPPORTS_LOYALTY_PROGRAM,
    descriptionKey: 'capabilities.descriptions.SUPPORTS_LOYALTY_PROGRAM',
    icon: '⭐',
    category: 'marketing',
  },
  [CapabilityKey.CAN_MANAGE_RESERVATIONS]: {
    key: CapabilityKey.CAN_MANAGE_RESERVATIONS,
    descriptionKey: 'capabilities.descriptions.CAN_MANAGE_RESERVATIONS',
    icon: '📅',
    category: 'operations',
  },
};
