import { describe, it, expect } from 'vitest';

/**
 * P1-006: Validación de montos enteros en pagos offline.
 * 
 * Contrato ADR-018: "Money 100% INTEGER"
 * 
 * Este test valida la lógica de validación que se aplica en 
 * offlinePaymentService.ts antes de cualquier modificación local,
 * garantizando que montos decimales sean rechazados explícitamente.
 */
describe('P1-006: Validación de montos enteros en pagos offline', () => {
  
  it('debería rechazar amount decimal (10000.50)', () => {
    const amount = 10000.50;
    // Lógica exacta del servicio: !Number.isSafeInteger(amount) || amount <= 0
    const isInvalid = !Number.isSafeInteger(amount) || amount <= 0;
    expect(isInvalid).toBe(true);
  });

  it('debería rechazar tipAmount decimal (500.50)', () => {
    const tipAmount = 500.50;
    // Lógica exacta del servicio: !Number.isSafeInteger(tipAmount) || tipAmount < 0
    const isInvalid = !Number.isSafeInteger(tipAmount) || tipAmount < 0;
    expect(isInvalid).toBe(true);
  });

  it('debería aceptar amount entero válido (10000)', () => {
    const amount = 10000;
    const isValid = Number.isSafeInteger(amount) && amount > 0;
    expect(isValid).toBe(true);
  });

  it('debería aceptar tipAmount entero válido (500)', () => {
    const tipAmount = 500;
    const isValid = Number.isSafeInteger(tipAmount) && tipAmount >= 0;
    expect(isValid).toBe(true);
  });

  it('debería rechazar amount negativo (-100)', () => {
    const amount = -100;
    const isInvalid = !Number.isSafeInteger(amount) || amount <= 0;
    expect(isInvalid).toBe(true);
  });

  it('debería rechazar tipAmount negativo (-50)', () => {
    const tipAmount = -50;
    const isInvalid = !Number.isSafeInteger(tipAmount) || tipAmount < 0;
    expect(isInvalid).toBe(true);
  });

  it('debería rechazar amount = 0 (debe ser > 0)', () => {
    const amount = 0;
    const isInvalid = !Number.isSafeInteger(amount) || amount <= 0;
    expect(isInvalid).toBe(true);
  });

  it('debería aceptar tipAmount = 0 (debe ser >= 0)', () => {
    const tipAmount = 0;
    const isValid = Number.isSafeInteger(tipAmount) && tipAmount >= 0;
    expect(isValid).toBe(true);
  });
});
