import { describe, it, expect, beforeAll, afterAll, beforeEach, vi } from "vitest";

vi.mock("@tauri-apps/plugin-sql", async () => {
  const mod = await import("../mocks/tauriSql");
  return { default: mod.default };
});

import { localDb } from "../../db/localDb";
import { runMigrations } from "../../db/schema";
import { CashSessionRepository } from "../../db/repositories/CashSessionRepository";
import { CashMovementRepository } from "../../db/repositories/CashMovementRepository";
import { SyncQueueRepository } from "../../db/repositories/SyncQueueRepository";

describe("CashMovementRepository", () => {
  let sessionUuid: string;

  beforeAll(async () => {
    await localDb.getConnection();
    await runMigrations();
  });

  afterAll(async () => {
    await localDb.close();
  });

  beforeEach(async () => {
    await localDb.execute("DELETE FROM sync_queue");
    await localDb.execute("DELETE FROM local_cash_movements");
    await localDb.execute("DELETE FROM local_cash_sessions");

    const session = await CashSessionRepository.create({
      company_id: "company-1",
      branch_id: "branch-1",
      terminal_id: "terminal-1",
      user_id: "user-1",
      user_name: "Juan",
      opening_amount: 50000,
    });
    sessionUuid = session.local_uuid;
  });

  describe("create - tipos de movimiento", () => {
    it("debería crear apertura (opening) con monto positivo", async () => {
      const movement = await CashMovementRepository.create(sessionUuid, {
        type: "opening",
        amount: 50000,
        reason: "Apertura inicial",
      });

      expect(movement.type).toBe("opening");
      expect(movement.amount).toBe(50000); // positivo
      expect(movement.balance_after).toBe(100000); // 50000 + 50000
    });

    it("debería crear pago (payment) con monto positivo", async () => {
      const movement = await CashMovementRepository.create(sessionUuid, {
        type: "payment",
        amount: 15000,
        reason: "Pago en efectivo mesa 5",
      });

      expect(movement.type).toBe("payment");
      expect(movement.amount).toBe(15000); // positivo
      expect(movement.balance_after).toBe(65000); // 50000 + 15000
    });

    it("debería crear depósito (deposit) con monto positivo", async () => {
      const movement = await CashMovementRepository.create(sessionUuid, {
        type: "deposit",
        amount: 20000,
        reason: "Aporte para cambio",
      });

      expect(movement.type).toBe("deposit");
      expect(movement.amount).toBe(20000); // positivo
      expect(movement.balance_after).toBe(70000); // 50000 + 20000
    });

    it("debería crear retiro (withdrawal) con monto negativo", async () => {
      const movement = await CashMovementRepository.create(sessionUuid, {
        type: "withdrawal",
        amount: 10000,
        reason: "Retiro a caja fuerte",
      });

      expect(movement.type).toBe("withdrawal");
      expect(movement.amount).toBe(-10000); // negativo
      expect(movement.balance_after).toBe(40000); // 50000 - 10000
    });

    it("debería crear ajuste (adjustment) con monto negativo", async () => {
      const movement = await CashMovementRepository.create(sessionUuid, {
        type: "adjustment",
        amount: 5000,
        reason: "Ajuste por error de conteo",
      });

      expect(movement.type).toBe("adjustment");
      expect(movement.amount).toBe(-5000); // negativo
      expect(movement.balance_after).toBe(45000); // 50000 - 5000
    });

    it("debería crear cierre (closing) con monto negativo", async () => {
      const movement = await CashMovementRepository.create(sessionUuid, {
        type: "closing",
        amount: 50000,
        reason: "Cierre de caja",
      });

      expect(movement.type).toBe("closing");
      expect(movement.amount).toBe(-50000); // negativo
      expect(movement.balance_after).toBe(0); // 50000 - 50000
    });
  });

  describe("create - validaciones", () => {
    it("debería rechazar movimiento si sesión no existe", async () => {
      await expect(
        CashMovementRepository.create("non-existent-uuid", {
          type: "payment",
          amount: 1000,
        })
      ).rejects.toThrow(/no encontrada/);
    });

    it("debería rechazar movimiento si sesión está cerrada", async () => {
      await CashSessionRepository.close(sessionUuid, 100000);

      await expect(
        CashMovementRepository.create(sessionUuid, {
          type: "payment",
          amount: 1000,
        })
      ).rejects.toThrow(/no está abierta/);
    });

    it("debería calcular balance_after acumulativo", async () => {
      await CashMovementRepository.create(sessionUuid, {
        type: "payment",
        amount: 10000,
      });
      const m2 = await CashMovementRepository.create(sessionUuid, {
        type: "payment",
        amount: 5000,
      });
      const m3 = await CashMovementRepository.create(sessionUuid, {
        type: "withdrawal",
        amount: 3000,
      });

      expect(m2.balance_after).toBe(65000); // 50000 + 10000 + 5000
      expect(m3.balance_after).toBe(62000); // 65000 - 3000
    });
  });

  describe("create - sincronización", () => {
    it("debería encolar withdrawal/deposit/adjustment en sync_queue", async () => {
      await CashMovementRepository.create(sessionUuid, {
        type: "withdrawal",
        amount: 10000,
        reason: "Retiro",
      });
      await CashMovementRepository.create(sessionUuid, {
        type: "deposit",
        amount: 5000,
        reason: "Depósito",
      });
      await CashMovementRepository.create(sessionUuid, {
        type: "adjustment",
        amount: 2000,
        reason: "Ajuste",
      });

      const pending = await SyncQueueRepository.getPending();
      const movementItems = pending.filter(p => p.entity_type === "cash_movement");

      expect(movementItems).toHaveLength(3);
    });

    it("NO debería encolar opening/closing/payment en sync_queue", async () => {
      await CashMovementRepository.create(sessionUuid, {
        type: "opening",
        amount: 50000,
      });
      await CashMovementRepository.create(sessionUuid, {
        type: "payment",
        amount: 10000,
      });
      await CashMovementRepository.create(sessionUuid, {
        type: "closing",
        amount: 50000,
      });

      const pending = await SyncQueueRepository.getPending();
      const movementItems = pending.filter(p => p.entity_type === "cash_movement");

      expect(movementItems).toHaveLength(0);
    });
  });

  describe("consultas", () => {
    beforeEach(async () => {
      await CashMovementRepository.create(sessionUuid, {
        type: "payment",
        amount: 10000,
      });
      await CashMovementRepository.create(sessionUuid, {
        type: "withdrawal",
        amount: 5000,
      });
    });

    it("findBySession debería listar todos los movimientos", async () => {
      const movements = await CashMovementRepository.findBySession(sessionUuid);
      expect(movements).toHaveLength(2);
    });

    it("findBySessionAndType debería filtrar por tipo", async () => {
      const payments = await CashMovementRepository.findBySessionAndType(sessionUuid, "payment");
      const withdrawals = await CashMovementRepository.findBySessionAndType(sessionUuid, "withdrawal");

      expect(payments).toHaveLength(1);
      expect(withdrawals).toHaveLength(1);
    });

    it("findByLocalUuid debería retornar movimiento específico", async () => {
      const all = await CashMovementRepository.findBySession(sessionUuid);
      const found = await CashMovementRepository.findByLocalUuid(all[0].local_uuid);

      expect(found).toBeDefined();
      expect(found?.local_uuid).toBe(all[0].local_uuid);
    });
  });

  describe("sync status", () => {
    it("markAsSynced debería actualizar status y cloud_id", async () => {
      const movement = await CashMovementRepository.create(sessionUuid, {
        type: "withdrawal",
        amount: 5000,
      });

      await CashMovementRepository.markAsSynced(movement.local_uuid, "cloud-movement-123");

      const updated = await CashMovementRepository.findByLocalUuid(movement.local_uuid);
      expect(updated?.sync_status).toBe("synced");
      expect(updated?.cloud_id).toBe("cloud-movement-123");
    });

    it("markAsFailed debería guardar error", async () => {
      const movement = await CashMovementRepository.create(sessionUuid, {
        type: "deposit",
        amount: 5000,
      });

      await CashMovementRepository.markAsFailed(movement.local_uuid, "Network error");

      const updated = await CashMovementRepository.findByLocalUuid(movement.local_uuid);
      expect(updated?.sync_status).toBe("failed");
      expect(updated?.sync_error).toBe("Network error");
    });

    it("findPendingSync debería listar movimientos pendientes", async () => {
      // Limpiar movimientos de tests anteriores
      await localDb.execute("DELETE FROM local_cash_movements");

      const m1 = await CashMovementRepository.create(sessionUuid, {
        type: "withdrawal",
        amount: 5000,
      });
      const m2 = await CashMovementRepository.create(sessionUuid, {
        type: "deposit",
        amount: 5000,
      });

      await CashMovementRepository.markAsSynced(m1.local_uuid, "cloud-1");

      const pending = await CashMovementRepository.findPendingSync();
      expect(pending).toHaveLength(1);
      expect(pending[0].local_uuid).toBe(m2.local_uuid);
    });
  });
});
