import { describe, it, expect, vi, beforeEach } from "vitest";
import { MOVEMENT_TYPE_CONFIG } from "@/services/inventoryService";

// Mock del apiClient
vi.mock("@/services/apiClient", () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
  },
}));

describe("inventoryService", () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  describe("MOVEMENT_TYPE_CONFIG", () => {
    it("tiene configuración para los 5 tipos de movimiento", () => {
      const types = [
        "in_purchase",
        "in_production",
        "out_consumption",
        "out_waste",
        "adjustment",
      ];

      types.forEach((type) => {
        expect(MOVEMENT_TYPE_CONFIG).toHaveProperty(type);
        expect(MOVEMENT_TYPE_CONFIG[type as keyof typeof MOVEMENT_TYPE_CONFIG]).toHaveProperty("labelEs");
        expect(MOVEMENT_TYPE_CONFIG[type as keyof typeof MOVEMENT_TYPE_CONFIG]).toHaveProperty("color");
        expect(MOVEMENT_TYPE_CONFIG[type as keyof typeof MOVEMENT_TYPE_CONFIG]).toHaveProperty("icon");
      });
    });

    it("los labels están en español", () => {
      expect(MOVEMENT_TYPE_CONFIG.in_purchase.labelEs).toBe("Compra");
      expect(MOVEMENT_TYPE_CONFIG.out_consumption.labelEs).toBe("Consumo");
      expect(MOVEMENT_TYPE_CONFIG.adjustment.labelEs).toBe("Ajuste");
    });

    it("cada tipo tiene un icono emoji", () => {
      Object.values(MOVEMENT_TYPE_CONFIG).forEach((config) => {
        expect(config.icon.length).toBeGreaterThan(0);
      });
    });
  });
});
