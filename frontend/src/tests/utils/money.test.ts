import { describe, it, expect } from 'vitest';
import {
  roundToCents,
  calculateTax,
  splitAmount,
  calculateChange,
  calculateTip,
  formatCLP,
  sumMoney,
  subtractMoney,
} from '@/utils/money';

describe('Money utilities', () => {
  describe('roundToCents', () => {
    it('redondea correctamente', () => {
      expect(roundToCents(9999.999999)).toBe(10000);
      expect(roundToCents(10000.01)).toBe(10000);
      expect(roundToCents(10000.5)).toBe(10001);
      expect(roundToCents(10000)).toBe(10000);
    });
  });

  describe('calculateTax', () => {
    it('calcula IVA 19% con redondeo', () => {
      expect(calculateTax(10000, 0.19)).toBe(1900);
      expect(calculateTax(10000)).toBe(1900);
      expect(calculateTax(9999, 0.19)).toBe(1900);
    });

    it('maneja tasas personalizadas', () => {
      expect(calculateTax(10000, 0.10)).toBe(1000);
      expect(calculateTax(10000, 0.25)).toBe(2500);
    });
  });

  describe('splitAmount', () => {
    it('divide en partes iguales', () => {
      expect(splitAmount(100, 2)).toEqual([50, 50]);
      expect(splitAmount(100, 4)).toEqual([25, 25, 25, 25]);
    });

    it('distribuye residuo equitativamente', () => {
      expect(splitAmount(100, 3)).toEqual([34, 33, 33]);
      expect(splitAmount(101, 3)).toEqual([34, 34, 33]);
    });

    it('maneja casos edge', () => {
      expect(splitAmount(100, 1)).toEqual([100]);
      expect(() => splitAmount(100, 0)).toThrow();
    });
  });

  describe('calculateChange', () => {
    it('calcula vuelto positivo', () => {
      expect(calculateChange(10000, 8000)).toBe(2000);
      expect(calculateChange(15000, 10000)).toBe(5000);
    });

    it('retorna 0 si pagó menos', () => {
      expect(calculateChange(8000, 10000)).toBe(0);
    });

    it('maneja exacto', () => {
      expect(calculateChange(10000, 10000)).toBe(0);
    });
  });

  describe('calculateTip', () => {
    it('calcula propina con redondeo', () => {
      expect(calculateTip(10000, 10)).toBe(1000);
      expect(calculateTip(10000, 15)).toBe(1500);
      expect(calculateTip(9999, 10)).toBe(1000);
    });
  });

  describe('formatCLP', () => {
    it('formatea como CLP', () => {
      expect(formatCLP(10000)).toBe('$10.000');
      expect(formatCLP(1500)).toBe('$1.500');
      expect(formatCLP(0)).toBe('$0');
    });
  });

  describe('sumMoney', () => {
    it('suma montos con redondeo', () => {
      expect(sumMoney([1000, 2000, 3000])).toBe(6000);
      expect(sumMoney([1000.5, 2000.5])).toBe(3001);
    });

    it('maneja array vacío', () => {
      expect(sumMoney([])).toBe(0);
    });
  });

  describe('subtractMoney', () => {
    it('resta montos con redondeo', () => {
      expect(subtractMoney(10000, 3000)).toBe(7000);
      expect(subtractMoney(10000.5, 3000.5)).toBe(7000);
    });
  });
});
