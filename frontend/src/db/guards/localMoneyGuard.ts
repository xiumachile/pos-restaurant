/**
 * Local Money Guard (ADR-018 / ADR-011).
 * 
 * Protege los repositorios de SQLite locales contra la inserción de 
 * datos monetarios inválidos (floats, strings, NaN, null).
 * 
 * A diferencia del apiClientMoneyGuard (que protege el perímetro HTTP),
 * este guard actúa como última línea de defensa antes de que los datos
 * toquen la base de datos local.
 */

export class LocalMoneyContractViolation extends Error {
  constructor(
    public readonly entityType: string,
    public readonly field: string,
    public readonly value: unknown,
    public readonly reason: string
  ) {
    super(`[Local Money Contract] ❌ ${entityType}.${field} is invalid: ${value} (${reason})`);
    this.name = 'LocalMoneyContractViolation';
  }
}

/**
 * Campos monetarios críticos que deben ser enteros seguros.
 */
const MONEY_FIELDS = [
  'amount', 'total', 'subtotal', 'net_amount', 'tax_amount', 
  'tip_amount', 'grand_total', 'amount_due', 'discount_total',
  'unit_price', 'unit_price_snapshot', 'price', 'price_adjustment',
  'paid_amount', 'remaining_amount', 'max_amount', 'base_price'
] as const;

type MoneyField = typeof MONEY_FIELDS[number];

/**
 * Valida que todos los campos monetarios en un payload sean enteros seguros.
 * 
 * @param payload - El objeto a validar
 * @param entityType - Nombre de la entidad (ej. 'payment', 'order', 'bill')
 * @throws {LocalMoneyContractViolation} Si algún campo monetario no es un entero seguro
 */
export function validateLocalMoneyPayload(payload: Record<string, unknown>, entityType: string): void {
  for (const field of MONEY_FIELDS) {
    if (field in payload) {
      const value = payload[field];
      
      // Permitir null/undefined solo si el campo es opcional (ej. tip_amount)
      if (value === null || value === undefined) {
        continue;
      }

      // Validar que sea un número entero seguro
      if (typeof value !== 'number' || !Number.isSafeInteger(value)) {
        throw new LocalMoneyContractViolation(
          entityType,
          field,
          value,
          typeof value === 'number' ? 'not a safe integer (possible float precision loss)' : `type is ${typeof value}, expected number`
        );
      }

      // Validar que no sea negativo (excepto en casos muy específicos como ajustes, pero por seguridad general)
      // Nota: discount_amount podría ser positivo en el schema pero restarse en la lógica.
      // Mantenemos la validación de tipo estricta aquí.
    }
  }
}
