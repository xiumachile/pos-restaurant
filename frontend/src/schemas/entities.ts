/**
 * Schemas de entidades con Money Contract aplicado.
 * Validación nativa (sin Zod).
 */
import { validateMoney, type MoneyValidationResult } from './money';

/**
 * Resultado de validación de entidad.
 */
export interface EntityValidationResult {
  valid: boolean;
  errors: string[];
}

// ═══════════════════════════════════════════════════════════════
// CAMPOS MONETARIOS POR ENTIDAD
// ═══════════════════════════════════════════════════════════════
// Definición declarativa de qué campos son dinero en cada entidad.

export const moneyFieldsByEntity = {
  orders: ['subtotal', 'tax_amount', 'discount_amount', 'total'],
  'order-items': ['unit_price', 'subtotal'],
  bills: ['subtotal', 'tax_amount', 'total', 'paid_amount', 'remaining_amount', 'tip_amount'],
  payments: ['amount', 'tip_amount', 'total_amount'],
  'cash-sessions': ['opening_amount', 'closing_amount', 'expected_amount', 'difference'],
  'cash-movements': ['amount', 'balance_after'],
  'cash-counts': ['cash_amount', 'card_amount', 'transfer_amount', 'other_amount', 'counted_amount', 'expected_amount', 'difference'],
  'tip-payouts': ['amount'],
} as const;

// ═══════════════════════════════════════════════════════════════
// VALIDADOR GENÉRICO
// ═══════════════════════════════════════════════════════════════

/**
 * Valida que los campos monetarios de un objeto cumplan ADR-018.
 */
export function validateMoneyFields(
  obj: unknown,
  entityType: keyof typeof moneyFieldsByEntity,
  path: string = ''
): EntityValidationResult {
  const errors: string[] = [];
  
  if (obj === null || obj === undefined || typeof obj !== 'object') {
    return { valid: false, errors: [`${path}: expected object, got ${typeof obj}`] };
  }
  
  const record = obj as Record<string, unknown>;
  const moneyFields = moneyFieldsByEntity[entityType] || [];
  
  for (const field of moneyFields) {
    const value = record[field];
    const fieldPath = path ? `${path}.${field}` : field;
    
    // Saltar campos null/undefined (pueden ser opcionales)
    if (value === null || value === undefined) {
      continue;
    }
    
    const result: MoneyValidationResult = validateMoney(value, fieldPath);
    if (!result.valid) {
      errors.push(result.error);
    }
  }
  
  // Validar recursivamente items anidados
  if (Array.isArray(record.items)) {
    record.items.forEach((item, idx) => {
      const itemResult = validateMoneyFields(item, 'order-items', `${path}.items[${idx}]`);
      if (!itemResult.valid) {
        errors.push(...itemResult.errors);
      }
    });
  }
  
  return { valid: errors.length === 0, errors };
}

// ═══════════════════════════════════════════════════════════════
// DETECCIÓN DE TIPO DE ENTIDAD POR URL
// ═══════════════════════════════════════════════════════════════

/**
 * Detecta el tipo de entidad según la URL del request.
 * Retorna null si no aplica validación monetaria.
 */
export function detectEntityType(url: string): keyof typeof moneyFieldsByEntity | null {
  const normalizedUrl = url.toLowerCase();
  
  // Orden importa: URLs más específicas primero
  if (normalizedUrl.includes('/order-items') || normalizedUrl.includes('/items')) {
    return 'order-items';
  }
  if (normalizedUrl.includes('/orders')) return 'orders';
  if (normalizedUrl.includes('/bills')) return 'bills';
  if (normalizedUrl.includes('/payments')) return 'payments';
  if (normalizedUrl.includes('/cash-sessions') || normalizedUrl.includes('/session')) {
    return 'cash-sessions';
  }
  if (normalizedUrl.includes('/cash-movements') || normalizedUrl.includes('/movements')) {
    return 'cash-movements';
  }
  if (normalizedUrl.includes('/cash-counts') || normalizedUrl.includes('/counts')) {
    return 'cash-counts';
  }
  if (normalizedUrl.includes('/tip-payouts') || normalizedUrl.includes('/payouts')) {
    return 'tip-payouts';
  }
  
  return null;
}
