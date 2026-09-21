import { describe, it, expect, beforeEach } from 'vitest';
import { validateRequestMoney, MoneyContractViolation, getMoneyContractMode } from './apiClientMoneyGuard';
import type { AxiosRequestConfig } from 'axios';

describe('apiClientMoneyGuard - Request Validation', () => {
  beforeEach(() => {
    // Limpiar estado entre tests
  });

  describe('getMoneyContractMode', () => {
    it('retorna el modo configurado', () => {
      const mode = getMoneyContractMode();
      expect(['strict', 'telemetry']).toContain(mode);
    });
  });

  describe('validateRequestMoney', () => {
    it('permite GET requests sin validar', () => {
      const config: AxiosRequestConfig = {
        method: 'GET',
        url: '/api/orders',
      };

      const result = validateRequestMoney(config as any);
      expect(result).toBe(config);
    });

    it('permite POST sin body', () => {
      const config: AxiosRequestConfig = {
        method: 'POST',
        url: '/api/orders',
      };

      const result = validateRequestMoney(config as any);
      expect(result).toBe(config);
    });

    it('permite POST con dinero válido (integer)', () => {
      const config: AxiosRequestConfig = {
        method: 'POST',
        url: '/api/orders',
        data: {
          subtotal: 10000,
          tax_amount: 1900,
          total: 11900,
        },
      };

      const result = validateRequestMoney(config as any);
      expect(result).toBe(config);
    });

    it('rechaza POST con dinero inválido (decimal)', () => {
      const config: AxiosRequestConfig = {
        method: 'POST',
        url: '/api/orders',
        data: {
          subtotal: 10000.50, // Inválido
          tax_amount: 1900,
          total: 11900,
        },
      };

      expect(() => validateRequestMoney(config as any)).toThrow(MoneyContractViolation);
    });

    it('rechaza POST con dinero negativo', () => {
      const config: AxiosRequestConfig = {
        method: 'POST',
        url: '/api/payments',
        data: {
          amount: -10000, // Inválido
          payment_method_uuid: 'uuid-123',
        },
      };

      expect(() => validateRequestMoney(config as any)).toThrow(MoneyContractViolation);
    });

    it('valida PUT requests', () => {
      const config: AxiosRequestConfig = {
        method: 'PUT',
        url: '/api/orders/123',
        data: {
          total: 11900.75, // Inválido
        },
      };

      expect(() => validateRequestMoney(config as any)).toThrow(MoneyContractViolation);
    });

    it('valida PATCH requests', () => {
      const config: AxiosRequestConfig = {
        method: 'PATCH',
        url: '/api/payments/456',
        data: {
          amount: 5000.50, // Inválido
        },
      };

      expect(() => validateRequestMoney(config as any)).toThrow(MoneyContractViolation);
    });

    it('permite null/undefined en campos opcionales', () => {
      const config: AxiosRequestConfig = {
        method: 'POST',
        url: '/api/orders',
        data: {
          subtotal: 10000,
          tax_amount: null, // Permitido
          discount_amount: undefined, // Permitido
          total: 10000,
        },
      };

      const result = validateRequestMoney(config as any);
      expect(result).toBe(config);
    });

    it('valida arrays de items', () => {
      const config: AxiosRequestConfig = {
        method: 'POST',
        url: '/api/order-items',
        data: [
          { unit_price: 5000, subtotal: 5000 }, // Válido
          { unit_price: 5000.50, subtotal: 5000 }, // Inválido
        ],
      };

      expect(() => validateRequestMoney(config as any)).toThrow(MoneyContractViolation);
    });

    it('incluye dirección "request" en el error', () => {
      const config: AxiosRequestConfig = {
        method: 'POST',
        url: '/api/payments',
        data: {
          amount: 10000.50, // Inválido
        },
      };

      try {
        validateRequestMoney(config as any);
        expect.fail('Debería lanzar MoneyContractViolation');
      } catch (error) {
        expect(error).toBeInstanceOf(MoneyContractViolation);
        const violation = error as MoneyContractViolation;
        expect(violation.direction).toBe('request');
        expect(violation.entityType).toBe('payments');
      }
    });
  });
});
