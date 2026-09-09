import { describe, it, expect, beforeAll, afterAll, beforeEach, vi } from "vitest";

vi.mock("@tauri-apps/plugin-sql", async () => {
  const mod = await import("../mocks/tauriSql");
  return { default: mod.default };
});

import { localDb } from "../../db/localDb";
import { runMigrations } from "../../db/schema";
import { CashSessionRepository } from "../../db/repositories/CashSessionRepository";

describe("CashSessionRepository", () => {
  beforeAll(async () => {
    await localDb.getConnection();
    await runMigrations();
  });

  afterAll(async () => {
    await localDb.close();
  });

  beforeEach(async () => {
    await localDb.execute("DELETE FROM local_cash_movements");
    await localDb.execute("DELETE FROM local_cash_sessions");
  });

  describe("create", () => {
    it("debería crear sesión con company_id y branch_id", async () => {
      const session = await CashSessionRepository.create({
        company_id: "company-1",
        branch_id: "branch-1",
        user_id: "user-1",
        user_name: "Juan",
        opening_amount: 50000,
      });

      expect(session).toBeDefined();
      expect(session.local_uuid).toMatch(/^[a-f0-9-]{36}$/);
      expect(session.company_id).toBe("company-1");
      expect(session.branch_id).toBe("branch-1");
      expect(session.user_id).toBe("user-1");
      expect(session.opening_amount).toBe(50000);
      expect(session.status).toBe("open");
      expect(session.sync_status).toBe("pending");
    });
  });

  describe("findActive", () => {
    it("debería retornar sesión activa para branch + user específico", async () => {
      await CashSessionRepository.create({
        company_id: "company-1",
        branch_id: "branch-1",
        user_id: "user-1",
        opening_amount: 50000,
      });

      await CashSessionRepository.create({
        company_id: "company-1",
        branch_id: "branch-2",
        user_id: "user-1",
        opening_amount: 30000,
      });

      const session1 = await CashSessionRepository.findActive("branch-1", "user-1");
      expect(session1).toBeDefined();
      expect(session1?.branch_id).toBe("branch-1");
      expect(session1?.opening_amount).toBe(50000);

      const session2 = await CashSessionRepository.findActive("branch-2", "user-1");
      expect(session2).toBeDefined();
      expect(session2?.branch_id).toBe("branch-2");
      expect(session2?.opening_amount).toBe(30000);
    });

    it("debería retornar null si no hay sesión para ese user", async () => {
      await CashSessionRepository.create({
        company_id: "company-1",
        branch_id: "branch-1",
        user_id: "user-1",
        opening_amount: 50000,
      });

      const session = await CashSessionRepository.findActive("branch-1", "user-2");
      expect(session).toBeNull();
    });

    it("debería retornar null si no hay sesión para ese branch", async () => {
      await CashSessionRepository.create({
        company_id: "company-1",
        branch_id: "branch-1",
        user_id: "user-1",
        opening_amount: 50000,
      });

      const session = await CashSessionRepository.findActive("branch-2", "user-1");
      expect(session).toBeNull();
    });

    it("no debería retornar sesiones cerradas", async () => {
      const session = await CashSessionRepository.create({
        company_id: "company-1",
        branch_id: "branch-1",
        user_id: "user-1",
        opening_amount: 50000,
      });

      await CashSessionRepository.close(session.local_uuid, 150000);

      const active = await CashSessionRepository.findActive("branch-1", "user-1");
      expect(active).toBeNull();
    });
  });

  describe("close", () => {
    it("debería cerrar sesión con monto final", async () => {
      const session = await CashSessionRepository.create({
        company_id: "company-1",
        branch_id: "branch-1",
        user_id: "user-1",
        opening_amount: 50000,
      });

      await CashSessionRepository.close(session.local_uuid, 150000);

      const closed = await CashSessionRepository.findByLocalUuid(session.local_uuid);
      expect(closed?.status).toBe("closed");
      expect(closed?.closing_amount).toBe(150000);
      expect(closed?.closed_at).toBeDefined();
      expect(closed?.sync_status).toBe("pending");
    });
  });

  describe("getBalance", () => {
    it("debería calcular balance actual (por ahora solo opening_amount)", async () => {
      const session = await CashSessionRepository.create({
        company_id: "company-1",
        branch_id: "branch-1",
        user_id: "user-1",
        opening_amount: 50000,
      });

      const balance = await CashSessionRepository.getBalance(session.local_uuid);
      expect(balance.opening_amount).toBe(50000);
      expect(balance.current_balance).toBe(50000);
      expect(balance.cash_payments).toBe(0);
      expect(balance.withdrawals).toBe(0);
      expect(balance.deposits).toBe(0);
    });
  });

  describe("findByBranch", () => {
    it("debería listar todas las sesiones de un branch", async () => {
      await CashSessionRepository.create({
        company_id: "company-1",
        branch_id: "branch-1",
        user_id: "user-1",
        opening_amount: 50000,
      });

      const session2 = await CashSessionRepository.create({
        company_id: "company-1",
        branch_id: "branch-1",
        user_id: "user-2",
        opening_amount: 30000,
      });

      await CashSessionRepository.close(session2.local_uuid, 80000);

      const sessions = await CashSessionRepository.findByBranch("branch-1");
      expect(sessions).toHaveLength(2);
      
      // Verificar que hay una cerrada y una abierta (sin asumir orden)
      const closedSession = sessions.find(s => s.local_uuid === session2.local_uuid);
      const openSession = sessions.find(s => s.local_uuid !== session2.local_uuid);
      
      expect(closedSession?.status).toBe("closed");
      expect(closedSession?.closing_amount).toBe(80000);
      expect(openSession?.status).toBe("open");
    });
  });

  describe("findByCloudId", () => {
    it("debería buscar sesión por cloud_id", async () => {
      const session = await CashSessionRepository.create({
        company_id: "company-1",
        branch_id: "branch-1",
        user_id: "user-1",
        opening_amount: 50000,
        cloud_id: "cloud-session-123",
        sync_status: "synced",
      });

      const found = await CashSessionRepository.findByCloudId("cloud-session-123");
      expect(found).toBeDefined();
      expect(found?.local_uuid).toBe(session.local_uuid);
    });
  });
});
