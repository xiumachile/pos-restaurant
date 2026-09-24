import { describe, it, expect } from 'vitest';
import { validateLocalMoneyPayload, LocalMoneyContractViolation } from '@/db/guards/localMoneyGuard';

describe('Local Money Guard (P2-003)', () => {
  it('debería permitir payloads con valores monetarios enteros válidos', () => {
    const validPayload = {
      amount: 10000,
      tip_amount: 1500,
      subtotal: 8403,
      tax_amount: 1597,
      discount_total: 0,
    };

    // No debería lanzar error
    expect(() => validateLocalMoneyPayload(validPayload, 'payment')).not.toThrow();
  });

  it('debería permitir null/undefined en campos opcionales', () => {
    const validPayload = {
      amount: 10000,
      tip_amount: null, // Opcional
      discount_total: undefined, // Opcional
    };

    expect(() => validateLocalMoneyPayload(validPayload, 'payment')).not.toThrow();
  });

  it('debería RECHAZAR floats (pérdida de precisión)', () => {
    const invalidPayload = {
      amount: 10000.50, // Float inválido para CLP
      tip_amount: 0,
    };

    expect(() => validateLocalMoneyPayload(invalidPayload, 'payment')).toThrow(LocalMoneyContractViolation);
    expect(() => validateLocalMoneyPayload(invalidPayload, 'payment')).toThrow('not a safe integer');
  });

  it('debería RECHAZAR strings en campos monetarios', () => {
    const invalidPayload = {
      amount: "10000", // String inválido
      tip_amount: 0,
    };

    expect(() => validateLocalMoneyPayload(invalidPayload, 'payment')).toThrow(LocalMoneyContractViolation);
    expect(() => validateLocalMoneyPayload(invalidPayload, 'payment')).toThrow('type is string, expected number');
  });

  it('debería RECHAZAR NaN o Infinity', () => {
    const invalidPayload = {
      amount: NaN,
      tip_amount: 0,
    };

    expect(() => validateLocalMoneyPayload(invalidPayload, 'payment')).toThrow(LocalMoneyContractViolation);
  });

  it('debería RECHAZAR números fuera del rango de safe integer', () => {
    const invalidPayload = {
      amount: Number.MAX_SAFE_INTEGER + 1,
      tip_amount: 0,
    };

    expect(() => validateLocalMoneyPayload(invalidPayload, 'payment')).toThrow(LocalMoneyContractViolation);
  });
});
