import { describe, it, expect, beforeEach, vi } from "vitest";
import { localDb } from "@/db/localDb";
import { runMigrations } from "@/db/schema";
import { PrinterConfigRepository } from "@/db/repositories/PrinterConfigRepository";
import { useAuthStore } from "@/store/useAuthStore";

vi.mock("@tauri-apps/plugin-sql", async () => {
  const mod = await import("../mocks/tauriSql");
  return { default: mod.default };
});

describe("PrinterConfigRepository", () => {
  beforeEach(async () => {
    // Mock useAuthStore para que PrinterConfigRepository.getContext() funcione
    useAuthStore.setState({
      user: {
        uuid: "user-123",
        name: "Test User",
        company: { uuid: "company-1", name: "Test Company" },
        branch_id: "branch-1",
      },
    });

    await localDb.getConnection();
    await runMigrations();
    await localDb.execute("DELETE FROM printer_configs");
  });

  describe("create", () => {
    it("crea impresora con puerto default 9100", async () => {
      const config = await PrinterConfigRepository.create({
        printer_type: "receipt",
        name: "Caja Principal",
        ip: "192.168.1.100",
      });

      expect(config.local_uuid).toBeTruthy();
      expect(config.printer_type).toBe("receipt");
      expect(config.name).toBe("Caja Principal");
      expect(config.ip).toBe("192.168.1.100");
      expect(config.port).toBe(9100);
      expect(config.is_active).toBe(true);
    });

    it("crea impresora con puerto custom", async () => {
      const config = await PrinterConfigRepository.create({
        printer_type: "kitchen",
        name: "Cocina",
        ip: "192.168.1.101",
        port: 9200,
      });

      expect(config.port).toBe(9200);
    });

    it("marca como default cuando is_default=true", async () => {
      const config = await PrinterConfigRepository.create({
        printer_type: "receipt",
        name: "Caja 1",
        ip: "192.168.1.100",
        is_default: true,
      });

      expect(config.is_default).toBe(true);
    });
  });

  describe("findByType", () => {
    it("retorna solo impresoras del tipo solicitado", async () => {
      await PrinterConfigRepository.create({
        printer_type: "receipt",
        name: "Caja",
        ip: "192.168.1.100",
      });
      await PrinterConfigRepository.create({
        printer_type: "kitchen",
        name: "Cocina",
        ip: "192.168.1.101",
      });

      const receiptPrinters = await PrinterConfigRepository.findByType("receipt");
      expect(receiptPrinters).toHaveLength(1);
      expect(receiptPrinters[0].printer_type).toBe("receipt");
    });

    it("no retorna impresoras inactivas", async () => {
      const config = await PrinterConfigRepository.create({
        printer_type: "receipt",
        name: "Caja",
        ip: "192.168.1.100",
      });
      await PrinterConfigRepository.update(config.local_uuid, { is_active: false });

      const active = await PrinterConfigRepository.findByType("receipt");
      expect(active).toHaveLength(0);
    });
  });

  describe("getDefault", () => {
    it("retorna null si no hay default", async () => {
      const def = await PrinterConfigRepository.getDefault("receipt");
      expect(def).toBeNull();
    });

    it("retorna la impresora default del tipo solicitado", async () => {
      await PrinterConfigRepository.create({
        printer_type: "receipt",
        name: "Caja",
        ip: "192.168.1.100",
      });
      const kitchenDefault = await PrinterConfigRepository.create({
        printer_type: "kitchen",
        name: "Cocina",
        ip: "192.168.1.101",
        is_default: true,
      });

      const def = await PrinterConfigRepository.getDefault("kitchen");
      expect(def?.local_uuid).toBe(kitchenDefault.local_uuid);
    });
  });

  describe("update", () => {
    it("actualiza campos específicos", async () => {
      const config = await PrinterConfigRepository.create({
        printer_type: "receipt",
        name: "Caja 1",
        ip: "192.168.1.100",
      });

      const updated = await PrinterConfigRepository.update(config.local_uuid, {
        name: "Caja Principal",
        ip: "192.168.1.200",
      });

      expect(updated.name).toBe("Caja Principal");
      expect(updated.ip).toBe("192.168.1.200");
      expect(updated.port).toBe(9100);
    });

    it("al marcar como default, desmarca otros defaults del mismo tipo", async () => {
      const caja1 = await PrinterConfigRepository.create({
        printer_type: "receipt",
        name: "Caja 1",
        ip: "192.168.1.100",
        is_default: true,
      });
      await PrinterConfigRepository.create({
        printer_type: "receipt",
        name: "Caja 2",
        ip: "192.168.1.101",
      });

      const all = await PrinterConfigRepository.findByType("receipt");
      const caja2 = all.find((c) => c.name === "Caja 2")!;

      await PrinterConfigRepository.update(caja2.local_uuid, { is_default: true });

      const updatedCaja1 = await PrinterConfigRepository.findByLocalUuid(caja1.local_uuid);
      const updatedCaja2 = await PrinterConfigRepository.findByLocalUuid(caja2.local_uuid);

      expect(updatedCaja1?.is_default).toBe(false);
      expect(updatedCaja2?.is_default).toBe(true);
    });

    it("lanza error si impresora no existe", async () => {
      await expect(
        PrinterConfigRepository.update("non-existent", { name: "Nuevo" })
      ).rejects.toThrow("no encontrada");
    });
  });

  describe("delete", () => {
    it("elimina impresora", async () => {
      const config = await PrinterConfigRepository.create({
        printer_type: "receipt",
        name: "Caja",
        ip: "192.168.1.100",
      });

      await PrinterConfigRepository.delete(config.local_uuid);

      const found = await PrinterConfigRepository.findByLocalUuid(config.local_uuid);
      expect(found).toBeNull();
    });
  });

  describe("findAll", () => {
    it("retorna todas las impresoras ordenadas por tipo", async () => {
      await PrinterConfigRepository.create({
        printer_type: "bar",
        name: "Bar",
        ip: "192.168.1.102",
      });
      await PrinterConfigRepository.create({
        printer_type: "receipt",
        name: "Caja",
        ip: "192.168.1.100",
      });
      await PrinterConfigRepository.create({
        printer_type: "kitchen",
        name: "Cocina",
        ip: "192.168.1.101",
      });

      const all = await PrinterConfigRepository.findAll();
      expect(all).toHaveLength(3);
      expect(all[0].printer_type).toBe("bar");
      expect(all[1].printer_type).toBe("kitchen");
      expect(all[2].printer_type).toBe("receipt");
    });
  });
});
