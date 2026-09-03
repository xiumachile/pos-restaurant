import { describe, it, expect } from "vitest";
import {
  backendToLocalOrder,
  localToBackendOrder,
  itemToOrderStatus,
  isValidLocalStatus,
} from "@/services/sync/statusMapper";

/**
 * Tests del mapeador bidireccional de estados.
 * Garantiza que no hay pérdida de información al sincronizar
 * entre SQLite local y PostgreSQL cloud.
 */

describe("statusMapper", () => {
  describe("backendToLocalOrder", () => {
    it("debe mapear todos los estados del backend sin pérdida", () => {
      const backendStates = [
        "draft", "confirmed", "preparing", "ready",
        "ready_for_pickup", "picked_up", "dispatched", "delivered",
        "served", "paid", "closed", "cancelled",
      ] as const;

      backendStates.forEach((state) => {
        const local = backendToLocalOrder(state);
        expect(local).toBe(state);
      });
    });
  });

  describe("localToBackendOrder", () => {
    it("debe mapear pending (legacy) a draft", () => {
      // pending es un estado legacy que no existe en backend
      const result = localToBackendOrder("pending" as any);
      expect(result).toBe("draft");
    });

    it("debe pasar otros estados sin modificación", () => {
      expect(localToBackendOrder("confirmed")).toBe("confirmed");
      expect(localToBackendOrder("ready_for_pickup")).toBe("ready_for_pickup");
      expect(localToBackendOrder("dispatched")).toBe("dispatched");
    });
  });

  describe("itemToOrderStatus", () => {
    it("debe mapear pending del item a draft del order", () => {
      expect(itemToOrderStatus("pending")).toBe("draft");
    });

    it("debe mapear estados de cocina a estados equivalentes", () => {
      expect(itemToOrderStatus("preparing")).toBe("preparing");
      expect(itemToOrderStatus("ready")).toBe("ready");
      expect(itemToOrderStatus("served")).toBe("served");
    });
  });

  describe("isValidLocalStatus", () => {
    it("debe reconocer todos los estados válidos", () => {
      const valid = [
        "draft", "confirmed", "preparing", "ready",
        "ready_for_pickup", "picked_up", "dispatched", "delivered",
        "served", "paid", "closed", "cancelled",
      ];

      valid.forEach((s) => expect(isValidLocalStatus(s)).toBe(true));
    });

    it("debe rechazar estados inválidos (defensa contra corrupción)", () => {
      expect(isValidLocalStatus("unknown")).toBe(false);
      expect(isValidLocalStatus("")).toBe(false);
      expect(isValidLocalStatus("PENDING")).toBe(false); // case-sensitive
      expect(isValidLocalStatus(null as any)).toBe(false);
    });
  });
});
