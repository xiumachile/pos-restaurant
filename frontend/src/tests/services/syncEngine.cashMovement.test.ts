import { describe, it, expect, beforeAll, beforeEach, vi, afterEach } from "vitest";

vi.mock("@tauri-apps/plugin-sql", async () => {
  const mod = await import("../mocks/tauriSql");
  return { default: mod.default };
});

vi.mock("../../services/apiClient", () => ({
  apiClient: {
    post: vi.fn(),
    put: vi.fn(),
    delete: vi.fn(),
    patch: vi.fn(),
    get: vi.fn(),
  },
}));

import { localDb } from "../../db/localDb";
import { runMigrations } from "../../db/schema";
import { SyncQueueRepository } from "../../db/repositories/SyncQueueRepository";
import { CashSessionRepository } from "../../db/repositories/CashSessionRepository";
import { CashMovementRepository } from "../../db/repositories/CashMovementRepository";
import { syncEngine } from "../../services/sync/SyncEngine";
import { apiClient } from "../../services/apiClient";

describe("SyncEngine - Cash Movements", () => {
  let sessionUuid: string;
  let sessionCloudId: string;

  beforeAll(async () => {
    await localDb.getConnection();
    await runMigrations();
  });

  beforeEach(async () => {
    await localDb.execute("DELETE FROM sync_queue");
    await localDb.execute("DELETE FROM local_cash_movements");
    await localDb.execute("DELETE FROM local_cash_sessions");
    vi.clearAllMocks();

    // Crear sesión sincronizada
    const session = await CashSessionRepository.create({
      company_id: "company-1",
      branch_id: "branch-1",
      terminal_id: "terminal-1",
      user_id: "user-1",
      opening_amount: 50000,
      cloud_id: "cloud-session-123",
      sync_status: "synced",
    });
    sessionUuid = session.local_uuid;
    sessionCloudId = session.cloud_id!;
  });

  afterEach(() => {
    vi.clearAllMocks();
  });

  it("debería sincronizar withdrawal exitosamente", async () => {
    const movement = await CashMovementRepository.create(sessionUuid, {
      type: "withdrawal",
      amount: 10000,
      reason: "Retiro a caja fuerte",
    });

    (apiClient.post as any).mockResolvedValueOnce({
      data: { data: { uuid: "cloud-movement-1" } },
    });

    const stats = await syncEngine.processBatch();

    expect(stats.success).toBe(1);
    expect(stats.failed).toBe(0);
    expect(apiClient.post).toHaveBeenCalledWith(
      "/cashier/movements",
      expect.objectContaining({
        session_uuid: sessionCloudId,
        type: "withdrawal",
        amount: 10000,
        reason: "Retiro a caja fuerte",
      }),
      expect.objectContaining({
        headers: { "Idempotency-Key": expect.any(String) },
      })
    );

    const updated = await CashMovementRepository.findByLocalUuid(movement.local_uuid);
    expect(updated?.sync_status).toBe("synced");
    expect(updated?.cloud_id).toBe("cloud-movement-1");
  });

  it("debería sincronizar deposit exitosamente", async () => {
    await CashMovementRepository.create(sessionUuid, {
      type: "deposit",
      amount: 5000,
      reason: "Aporte para cambio",
    });

    (apiClient.post as any).mockResolvedValueOnce({
      data: { data: { uuid: "cloud-movement-2" } },
    });

    const stats = await syncEngine.processBatch();
    expect(stats.success).toBe(1);
    expect(apiClient.post).toHaveBeenCalledWith(
      "/cashier/movements",
      expect.objectContaining({
        type: "deposit",
        amount: 5000,
      }),
      expect.any(Object)
    );
  });

  it("debería sincronizar adjustment exitosamente", async () => {
    await CashMovementRepository.create(sessionUuid, {
      type: "adjustment",
      amount: 2000,
      reason: "Ajuste por error de conteo",
    });

    (apiClient.post as any).mockResolvedValueOnce({
      data: { data: { uuid: "cloud-movement-3" } },
    });

    const stats = await syncEngine.processBatch();
    expect(stats.success).toBe(1);
    expect(apiClient.post).toHaveBeenCalledWith(
      "/cashier/movements",
      expect.objectContaining({
        type: "adjustment",
        amount: 2000,
        reason: "Ajuste por error de conteo",
      }),
      expect.any(Object)
    );
  });

  it("NO debería sincronizar opening (se sync como cash_session)", async () => {
    await CashMovementRepository.create(sessionUuid, {
      type: "opening",
      amount: 50000,
    });

    const stats = await syncEngine.processBatch();
    expect(apiClient.post).not.toHaveBeenCalled();
    expect(stats.processed).toBe(0);
  });

  it("NO debería sincronizar payment (se sync como billing/payments)", async () => {
    await CashMovementRepository.create(sessionUuid, {
      type: "payment",
      amount: 10000,
    });

    const stats = await syncEngine.processBatch();
    expect(apiClient.post).not.toHaveBeenCalled();
    expect(stats.processed).toBe(0);
  });

  it("NO debería sincronizar closing (se sync como cash_session)", async () => {
    await CashMovementRepository.create(sessionUuid, {
      type: "closing",
      amount: 50000,
    });

    const stats = await syncEngine.processBatch();
    expect(apiClient.post).not.toHaveBeenCalled();
    expect(stats.processed).toBe(0);
  });

  it("debería fallar si session no tiene cloud_id", async () => {
    const unsyncedSession = await CashSessionRepository.create({
      company_id: "company-1",
      branch_id: "branch-1",
      terminal_id: "terminal-1",
      user_id: "user-2",
      opening_amount: 30000,
    });

    await CashMovementRepository.create(unsyncedSession.local_uuid, {
      type: "withdrawal",
      amount: 5000,
      reason: "Retiro",
    });

    const stats = await syncEngine.processBatch();

    expect(stats.failed).toBe(1);
    expect(apiClient.post).not.toHaveBeenCalled();
  });

  it("debería convertir amount negativo a positivo (backend espera positivo)", async () => {
    await CashMovementRepository.create(sessionUuid, {
      type: "withdrawal",
      amount: 10000,
    });

    (apiClient.post as any).mockResolvedValueOnce({
      data: { data: { uuid: "cloud-movement-4" } },
    });

    await syncEngine.processBatch();

    expect(apiClient.post).toHaveBeenCalledWith(
      "/cashier/movements",
      expect.objectContaining({
        amount: 10000,
      }),
      expect.any(Object)
    );
  });

  it("debería manejar error del backend y marcar como failed", async () => {
    const movement = await CashMovementRepository.create(sessionUuid, {
      type: "withdrawal",
      amount: 10000,
      reason: "Retiro",
    });

    (apiClient.post as any).mockRejectedValueOnce(new Error("Network error"));

    const stats = await syncEngine.processBatch();
    expect(stats.failed).toBe(1);

    const updated = await CashMovementRepository.findByLocalUuid(movement.local_uuid);
    expect(updated?.sync_status).toBe("pending");
  });
});
