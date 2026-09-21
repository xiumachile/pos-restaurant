/**
 * ADR-018: Money Contract
 * 
 * Contrato monetario único para todo el sistema:
 * - Backend: int en CLP (unidades monetarias mínimas)
 * - API JSON: enteros
 * - Frontend TypeScript: number entero validado
 * - Runtime: validación nativa en boundaries
 * 
 * Implementación SIN dependencias externas (no Zod).
 */

// ═══════════════════════════════════════════════════════════════
// BRANDED TYPE (compile-time)
// ═══════════════════════════════════════════════════════════════
declare const CLPMoneyBrand: unique symbol;

/**
 * Tipo semántico para dinero CLP.
 * Internamente es `number`, pero TypeScript lo distingue conceptualmente.
 */
export type CLPMoney = number & { readonly [CLPMoneyBrand]: typeof CLPMoneyBrand };

// ═══════════════════════════════════════════════════════════════
// VALIDACIÓN RUNTIME
// ═══════════════════════════════════════════════════════════════

/**
 * Resultado de validación monetaria.
 */
export type MoneyValidationResult =
  | { valid: true; value: CLPMoney }
  | { valid: false; error: string };

/**
 * Valida que un valor sea un entero no-negativo (CLP).
 */
export function validateMoney(value: unknown, fieldName: string = 'money'): MoneyValidationResult {
  // Debe ser número
  if (typeof value !== 'number') {
    return {
      valid: false,
      error: `${fieldName}: expected number, got ${typeof value} (${JSON.stringify(value)})`,
    };
  }
  
  // No debe ser NaN
  if (Number.isNaN(value)) {
    return {
      valid: false,
      error: `${fieldName}: value is NaN`,
    };
  }
  
  // Debe ser finito
  if (!Number.isFinite(value)) {
    return {
      valid: false,
      error: `${fieldName}: value is not finite (${value})`,
    };
  }
  
  // Debe ser entero
  if (!Number.isInteger(value)) {
    return {
      valid: false,
      error: `${fieldName}: expected integer (CLP), got ${value}`,
    };
  }
  
  // Debe ser no-negativo
  if (value < 0) {
    return {
      valid: false,
      error: `${fieldName}: expected non-negative, got ${value}`,
    };
  }
  
  return { valid: true, value: value as CLPMoney };
}

/**
 * Valida y lanza error si no cumple el contrato.
 * Útil cuando queremos fallar rápido en desarrollo.
 */
export function assertMoney(value: unknown, fieldName: string = 'money'): CLPMoney {
  const result = validateMoney(value, fieldName);
  if (!result.valid) {
    throw new Error(`[Money Contract] ${result.error}`);
  }
  return result.value;
}

/**
 * Valida y retorna null si no cumple (tolerante).
 * Útil para campos opcionales o cuando no queremos romper el flujo.
 */
export function validateMoneyOrNull(value: unknown, fieldName: string = 'money'): CLPMoney | null {
  if (value === null || value === undefined) {
    return null;
  }
  const result = validateMoney(value, fieldName);
  return result.valid ? result.value : null;
}

// ═══════════════════════════════════════════════════════════════
// HELPERS
// ═══════════════════════════════════════════════════════════════

/** Crear CLPMoney validado */
export const toCLP = (value: number): CLPMoney => assertMoney(value, 'toCLP');

/** Extraer number de CLPMoney (unwrap) */
export const fromCLP = (amount: CLPMoney): number => amount;

/** Formatear como CLP (ej: $12.500) */
export const formatCLP = (amount: CLPMoney): string =>
  new Intl.NumberFormat('es-CL', {
    style: 'currency',
    currency: 'CLP',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(fromCLP(amount));
