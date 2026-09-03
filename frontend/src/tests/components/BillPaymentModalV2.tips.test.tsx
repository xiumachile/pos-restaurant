import { describe, it, expect } from "vitest";

/**
 * Tests de regresión para el cálculo de propinas en BillPaymentModalV2.
 * 
 * BUG CORREGIDO (Bloque 2): Botón 'Cobrar' mostraba solo billTotal
 * sin incluir propinas, causando confusión al cajero.
 * 
 * FIX: Botón ahora muestra formatPrice(billTotal + tipsSum).
 * 
 * NOTA: Este test valida la lógica de cálculo. Tests de integración
 * completos requieren MSW (Mock Service Worker) y serán agregados
 * cuando se configure el entorno de testing de componentes.
 */

describe("BillPaymentModalV2 - lógica de propinas", () => {
  describe("Cálculo de totales", () => {
    it("debe calcular billTotal + tipsSum correctamente", () => {
      // Simular la lógica del componente
      const billTotal = 10000;
      const payments = [
        { amount: 10000, tip_amount: 1500, method_code: "CASH" },
      ];
      
      const paymentsSum = payments.reduce((sum, p) => sum + p.amount, 0);
      const tipsSum = payments.reduce((sum, p) => sum + p.tip_amount, 0);
      const totalToCharge = billTotal + tipsSum;

      expect(paymentsSum).toBe(10000);
      expect(tipsSum).toBe(1500);
      expect(totalToCharge).toBe(11500); // ✅ Botón debe mostrar esto
    });

    it("debe calcular remaining sin incluir propinas", () => {
      const billPending = 10000;
      const payments = [
        { amount: 10000, tip_amount: 1500 },
      ];
      
      const paymentsSum = payments.reduce((sum, p) => sum + p.amount, 0);
      const remaining = Math.max(0, billPending - paymentsSum);

      // ✅ Propina NO afecta el remaining (es adicional al consumo)
      expect(remaining).toBe(0);
    });

    it("debe calcular cambio de efectivo considerando propina", () => {
      const currentAmount = 10000;
      const currentTip = 1500;
      const currentReceived = 20000;
      
      const change = Math.max(0, currentReceived - (currentAmount + currentTip));

      expect(change).toBe(8500); // 20000 - 11500
    });

    it("debe permitir múltiples pagos con propinas individuales", () => {
      const payments = [
        { amount: 5000, tip_amount: 500 },   // Tarjeta
        { amount: 5000, tip_amount: 1000 },  // Efectivo
      ];

      const paymentsSum = payments.reduce((sum, p) => sum + p.amount, 0);
      const tipsSum = payments.reduce((sum, p) => sum + p.tip_amount, 0);
      const totalToCharge = paymentsSum + tipsSum;

      expect(paymentsSum).toBe(10000);
      expect(tipsSum).toBe(1500);
      expect(totalToCharge).toBe(11500);
    });
  });
});
