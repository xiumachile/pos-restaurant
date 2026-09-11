import { describe, it, expect } from "vitest";
import {
  formatReceipt,
  formatKitchenTicket,
  formatCustomerTicket,
  formatCashCopy,
  ticketToBase64,
  type ReceiptData,
  type KitchenTicketData,
  type CustomerTicketData,
  type CashCopyData,
} from "@/services/printing/ticketFormatters";

describe("ticketFormatters", () => {
  describe("formatReceipt", () => {
    const receiptData: ReceiptData = {
      companyName: "WOK & MESA",
      branchName: "Sucursal Centro",
      billNumber: "42-1",
      items: [
        { name: "Pad Thai", quantity: 2, unitPrice: 8500, subtotal: 17000 },
        { name: "Spring Rolls", quantity: 1, unitPrice: 4500, subtotal: 4500 },
        { name: "Cerveza", quantity: 2, unitPrice: 3000, subtotal: 6000, notes: "Sin hielo" },
      ],
      subtotal: 27500,
      taxTotal: 5225,
      tipAmount: 2000,
      grandTotal: 34725,
      paymentMethod: "cash",
      paidAmount: 35000,
      remainingAmount: 0,
      cashierName: "Juan Pérez",
      createdAt: new Date(2026, 8, 11, 22, 45),
    };

    it("retorna un EscPosBuilder", () => {
      const builder = formatReceipt(receiptData);
      expect(builder).toBeDefined();
      expect(typeof builder.build).toBe("function");
    });

    it("genera bytes no vacíos", () => {
      const bytes = formatReceipt(receiptData).build();
      expect(bytes.length).toBeGreaterThan(100);
    });

    it("incluye el nombre de la empresa", () => {
      const debug = formatReceipt(receiptData).buildDebug();
      expect(debug).toContain("WOK & MESA");
    });

    it("incluye el número de cuenta", () => {
      const debug = formatReceipt(receiptData).buildDebug();
      expect(debug).toContain("#42-1");
    });

    it("incluye items con cantidades", () => {
      const debug = formatReceipt(receiptData).buildDebug();
      expect(debug).toContain("2x Pad Thai");
      expect(debug).toContain("1x Spring Rolls");
      expect(debug).toContain("2x Cerveza");
    });

    it("incluye notas de items", () => {
      const debug = formatReceipt(receiptData).buildDebug();
      expect(debug).toContain("Sin hielo");
    });

    it("incluye total formateado", () => {
      const debug = formatReceipt(receiptData).buildDebug();
      expect(debug).toContain("$34.725");
    });

    it("traduce método de pago a español", () => {
      const debug = formatReceipt(receiptData).buildDebug();
      expect(debug).toContain("Efectivo");
    });

    it("incluye nombre del cajero", () => {
      const debug = formatReceipt(receiptData).buildDebug();
      expect(debug).toContain("Juan Pérez");
    });

    it("termina con corte de papel", () => {
      const bytes = formatReceipt(receiptData).build();
      // Últimos 3 bytes deben ser GS V 0 (0x1D 0x56 0x00)
      expect(bytes[bytes.length - 3]).toBe(0x1d);
      expect(bytes[bytes.length - 2]).toBe(0x56);
      expect(bytes[bytes.length - 1]).toBe(0x00);
    });
  });

  describe("formatKitchenTicket", () => {
    const kitchenData: KitchenTicketData = {
      orderNumber: "42",
      tableNumber: "7",
      waiterName: "María",
      items: [
        { name: "Pad Thai", quantity: 2, notes: "Sin maní" },
        { name: "Arroz frito", quantity: 1 },
      ],
      createdAt: new Date(2026, 8, 11, 22, 45),
    };

    it("incluye header COCINA", () => {
      const debug = formatKitchenTicket(kitchenData).buildDebug();
      expect(debug).toContain("COCINA");
    });

    it("incluye número de orden", () => {
      const debug = formatKitchenTicket(kitchenData).buildDebug();
      expect(debug).toContain("ORDEN #42");
    });

    it("incluye mesa", () => {
      const debug = formatKitchenTicket(kitchenData).buildDebug();
      expect(debug).toContain("MESA: 7");
    });

    it("incluye mesero", () => {
      const debug = formatKitchenTicket(kitchenData).buildDebug();
      expect(debug).toContain("Mesero: María");
    });

    it("incluye items con notas", () => {
      const debug = formatKitchenTicket(kitchenData).buildDebug();
      expect(debug).toContain("2x Pad Thai");
      expect(debug).toContain("Sin maní");
      expect(debug).toContain("1x Arroz frito");
    });

    it("calcula total de items", () => {
      const debug = formatKitchenTicket(kitchenData).buildDebug();
      expect(debug).toContain("Total items: 3");
    });

    it("omite mesa si es null", () => {
      const data: KitchenTicketData = {
        ...kitchenData,
        tableNumber: null,
      };
      const debug = formatKitchenTicket(data).buildDebug();
      expect(debug).not.toContain("MESA:");
    });
  });

  describe("formatCustomerTicket", () => {
    const customerData: CustomerTicketData = {
      companyName: "WOK & MESA",
      billNumber: "42-1",
      items: [
        { name: "Pad Thai", quantity: 2, unitPrice: 8500, subtotal: 17000 },
      ],
      subtotal: 17000,
      taxTotal: 3230,
      grandTotal: 20230,
      createdAt: new Date(2026, 8, 11, 22, 45),
    };

    it("incluye header", () => {
      const debug = formatCustomerTicket(customerData).buildDebug();
      expect(debug).toContain("Detalle de cuenta");
    });

    it("NO incluye método de pago", () => {
      const debug = formatCustomerTicket(customerData).buildDebug();
      expect(debug).not.toContain("Efectivo");
      expect(debug).not.toContain("Tarjeta");
    });

    it("sugiere pedir boleta al cajero", () => {
      const debug = formatCustomerTicket(customerData).buildDebug();
      expect(debug).toContain("Solicite su boleta");
    });

    it("incluye items y totales", () => {
      const debug = formatCustomerTicket(customerData).buildDebug();
      expect(debug).toContain("2x Pad Thai");
      expect(debug).toContain("$20.230");
    });
  });

  describe("formatCashCopy", () => {
    const cashData: CashCopyData = {
      companyName: "WOK & MESA",
      cashierName: "Juan Pérez",
      sessionOpenedAt: new Date(2026, 8, 11, 9, 0),
      sessionClosedAt: new Date(2026, 8, 11, 22, 45),
      totalSales: 150000,
      cashSales: 80000,
      cardSales: 50000,
      transferSales: 20000,
      movements: [
        { type: "withdrawal", amount: 10000, reason: "Cambio", createdAt: new Date() },
        { type: "deposit", amount: 5000, reason: "Ajuste", createdAt: new Date() },
      ],
      expectedAmount: 145000,
      actualAmount: 145000,
      difference: 0,
    };

    it("incluye header CIERRE DE CAJA", () => {
      const debug = formatCashCopy(cashData).buildDebug();
      expect(debug).toContain("CIERRE DE CAJA");
    });

    it("incluye cajero y horarios", () => {
      const debug = formatCashCopy(cashData).buildDebug();
      expect(debug).toContain("Juan Pérez");
      expect(debug).toContain("Apertura:");
      expect(debug).toContain("Cierre:");
    });

    it("desglosa ventas por método", () => {
      const debug = formatCashCopy(cashData).buildDebug();
      expect(debug).toContain("Efectivo:");
      expect(debug).toContain("Tarjeta:");
      expect(debug).toContain("Transferencia:");
      expect(debug).toContain("TOTAL VENTAS:");
    });

    it("incluye movimientos", () => {
      const debug = formatCashCopy(cashData).buildDebug();
      expect(debug).toContain("MOVIMIENTOS:");
      expect(debug).toContain("Retiro");
      expect(debug).toContain("Depósito");
      expect(debug).toContain("Cambio");
      expect(debug).toContain("Ajuste");
    });

    it("incluye arqueo con diferencia", () => {
      const debug = formatCashCopy(cashData).buildDebug();
      expect(debug).toContain("ARQUEO:");
      expect(debug).toContain("Esperado:");
      expect(debug).toContain("Contado:");
      expect(debug).toContain("Diferencia:");
    });

    it("omite cierre si es null", () => {
      const data: CashCopyData = {
        ...cashData,
        sessionClosedAt: null,
      };
      const debug = formatCashCopy(data).buildDebug();
      expect(debug).not.toContain("Cierre:");
    });

    it("omite arqueo si no hay conteo", () => {
      const data: CashCopyData = {
        ...cashData,
        actualAmount: null,
        difference: null,
      };
      const debug = formatCashCopy(data).buildDebug();
      expect(debug).toContain("Esperado:");
      expect(debug).not.toContain("Contado:");
    });
  });

  describe("ticketToBase64", () => {
    it("genera base64 de receipt", () => {
      const base64 = ticketToBase64.receipt({
        billNumber: "42-1",
        items: [],
        subtotal: 10000,
        taxTotal: 1900,
        tipAmount: 0,
        grandTotal: 11900,
        paymentMethod: "cash",
        paidAmount: 11900,
        remainingAmount: 0,
        createdAt: new Date(),
      });

      expect(typeof base64).toBe("string");
      expect(base64.length).toBeGreaterThan(0);
      // Debe ser decodificable
      expect(() => atob(base64)).not.toThrow();
    });

    it("genera base64 de kitchen", () => {
      const base64 = ticketToBase64.kitchen({
        orderNumber: "42",
        items: [{ name: "Test", quantity: 1 }],
        createdAt: new Date(),
      });

      expect(typeof base64).toBe("string");
      expect(base64.length).toBeGreaterThan(0);
    });

    it("genera base64 de customer", () => {
      const base64 = ticketToBase64.customer({
        billNumber: "42-1",
        items: [],
        subtotal: 10000,
        taxTotal: 1900,
        grandTotal: 11900,
        createdAt: new Date(),
      });

      expect(typeof base64).toBe("string");
    });

    it("genera base64 de cashCopy", () => {
      const base64 = ticketToBase64.cashCopy({
        cashierName: "Test",
        sessionOpenedAt: new Date(),
        totalSales: 100000,
        cashSales: 50000,
        cardSales: 30000,
        transferSales: 20000,
        movements: [],
        expectedAmount: 100000,
      });

      expect(typeof base64).toBe("string");
    });
  });
});
