/**
 * Money utilities for POS system
 * 
 * Estrategia (ADR-010):
 * - Chile usa CLP (peso chileno) sin centavos fraccionarios
 * - SQLite local: INTEGER para evitar errores de punto flotante
 * - TypeScript: number con validación de precisión
 * - Operaciones críticas usan helpers con redondeo explícito
 */

/**
 * Redondea a entero (CLP no tiene centavos)
 * 
 * @example
 * roundToCents(9999.999999) // 10000
 * roundToCents(10000.01)    // 10000
 */
export function roundToCents(amount: number): number {
  return Math.round(amount);
}

/**
 * Calcula impuesto con redondeo
 * 
 * @example
 * calculateTax(10000, 0.19) // 1900
 */
export function calculateTax(subtotal: number, rate: number = 0.19): number {
  return roundToCents(subtotal * rate);
}

/**
 * Divide monto en partes iguales, distribuyendo el residuo
 * 
 * @example
 * splitAmount(100, 3) // [34, 33, 33]
 */
export function splitAmount(total: number, parts: number): number[] {
  if (parts <= 0) {
    throw new Error('Parts must be greater than 0');
  }
  
  const base = Math.floor(total / parts);
  const remainder = total % parts;
  
  return Array(parts).fill(base).map((val, i) => 
    i < remainder ? val + 1 : val
  );
}

/**
 * Calcula cambio (vuelto) asegurando que sea positivo
 * 
 * @example
 * calculateChange(10000, 8000) // 2000
 * calculateChange(8000, 10000) // 0 (no negativo)
 */
export function calculateChange(paid: number, total: number): number {
  return Math.max(0, roundToCents(paid - total));
}

/**
 * Calcula propina con redondeo
 * 
 * @example
 * calculateTip(10000, 0.10) // 1000
 */
export function calculateTip(subtotal: number, percentage: number): number {
  return roundToCents(subtotal * (percentage / 100));
}

/**
 * Formatea monto como CLP
 * 
 * @example
 * formatCLP(10000) // "$10.000"
 */
export function formatCLP(amount: number): string {
  return new Intl.NumberFormat('es-CL', {
    style: 'currency',
    currency: 'CLP',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(amount);
}

/**
 * Valida que el monto sea entero (CLP no tiene centavos)
 * 
 * @example
 * validateMoneyAmount(10000)    // OK
 * validateMoneyAmount(10000.50) // Warning
 */
export function validateMoneyAmount(amount: number): void {
  if (!Number.isInteger(amount)) {
    console.warn(
      `[Money] Amount ${amount} is not an integer. ` +
      `CLP should not have fractional cents.`
    );
  }
}

/**
 * Suma montos con redondeo
 * 
 * @example
 * sumMoney([1000, 2000, 3000]) // 6000
 */
export function sumMoney(amounts: number[]): number {
  return roundToCents(amounts.reduce((sum, val) => sum + val, 0));
}

/**
 * Resta montos con redondeo
 * 
 * @example
 * subtractMoney(10000, 3000) // 7000
 */
export function subtractMoney(a: number, b: number): number {
  return roundToCents(a - b);
}
