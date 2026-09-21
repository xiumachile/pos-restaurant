import { describe, it, expect, beforeEach, vi } from 'vitest';
import { validateResponseMoney, MoneyContractViolation, getMoneyContractMode } from './apiClientMoneyGuard';
import type { AxiosResponse } from 'axios';

describe('apiClientMoneyGuard', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  describe('getMoneyContractMode', () => {
    it('retorna el modo configurado', () => {
      const mode = getMoneyContractMode();
      expect(['strict', 'telemetry']).toContain(mode);
    });
  });

  describe('validateResponseMoney', () => {
    it('permite response válida sin campos monetarios', () => {
      const response = {
        data: { id: 1, name: 'Test' },
        config: { url: '/api/users' },
      } as AxiosResponse;

      const result = validateResponseMoney(response);
      expect(result).toBe(response);
    });

    it('permite response con dinero válido (integer)', () => {
      const response = {
        data: {
          data: {
            id: 1,
            subtotal: 10000,
            tax_amount: 1900,
            total: 11900,
          },
        },
        config: { url: '/api/orders' },
      } as AxiosResponse;

      const result = validateResponseMoney(response);
      expect(result).toBe(response);
    });

    it('rechaza response con dinero inválido (decimal)', () => {
      const response = {
        data: {
          data: {
            id: 1,
            subtotal: 10000.50, // Inválido: decimal
            tax_amount: 1900,
            total: 11900,
          },
        },
        config: { url: '/api/orders' },
      } as AxiosResponse;

      expect(() => validateResponseMoney(response)).toThrow(MoneyContractViolation);
    });

    it('rechaza response con dinero negativo', () => {
      const response = {
        data: {
          data: {
            id: 1,
            subtotal: 10000,
            tax_amount: -1900, // Inválido: negativo
            total: 8100,
          },
        },
        config: { url: '/api/orders' },
      } as AxiosResponse;

      expect(() => validateResponseMoney(response)).toThrow(MoneyContractViolation);
    });

    it('rechaza array con items inválidos', () => {
      const response = {
        data: {
          data: [
            { id: 1, amount: 1000 }, // Válido
            { id: 2, amount: 2000.50 }, // Inválido
          ],
        },
        config: { url: '/api/payments' },
      } as AxiosResponse;

      expect(() => validateResponseMoney(response)).toThrow(MoneyContractViolation);
    });

    it('valida múltiples campos monetarios', () => {
      const response = {
        data: {
          data: {
            id: 1,
            subtotal: 10000,
            tax_amount: 1900.50, // Inválido
            total: 11900.75, // Inválido
          },
        },
        config: { url: '/api/orders' },
      } as AxiosResponse;

      try {
        validateResponseMoney(response);
        expect.fail('Debería lanzar MoneyContractViolation');
      } catch (error) {
        expect(error).toBeInstanceOf(MoneyContractViolation);
        const violation = error as MoneyContractViolation;
        expect(violation.errors.length).toBe(2); // Dos campos inválidos
        expect(violation.entityType).toBe('orders');
      }
    });

    it('permite campos null/undefined (opcionales)', () => {
      const response = {
        data: {
          data: {
            id: 1,
            subtotal: 10000,
            tax_amount: null, // Permitido
            discount_amount: undefined, // Permitido
            total: 10000,
          },
        },
        config: { url: '/api/orders' },
      } as AxiosResponse;

      const result = validateResponseMoney(response);
      expect(result).toBe(response);
    });

    it('valida order-items anidados en orders', () => {
      const response = {
        data: {
          data: {
            id: 1,
            subtotal: 10000,
            total: 11900,
            items: [
              { id: 1, unit_price: 5000, subtotal: 5000 }, // Válido
              { id: 2, unit_price: 5000.50, subtotal: 5000 }, // Inválido
            ],
          },
        },
        config: { url: '/api/orders' },
      } as AxiosResponse;

      expect(() => validateResponseMoney(response)).toThrow(MoneyContractViolation);
    });
  });

  describe('MoneyContractViolation', () => {
    it.skip('incluye información completa del error', () => {
      const violation = new MoneyContractViolation('response', 
                'orders',
        '/api/orders',
        ['subtotal: expected integer, got 10000.50']
      );

      expect(violation.entityType).toBe('orders');
      expect(violation.url).toBe('/api/orders');
      expect(violation.errors).toHaveLength(1);
      expect(violation.message).toContain('orders');
      expect(violation.message).toContain('/api/orders');
      expect(violation.message).toContain('subtotal');
    });
  });
});
