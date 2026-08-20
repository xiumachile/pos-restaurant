import { describe, it, expect, beforeAll, beforeEach, vi, afterEach } from "vitest";

vi.mock("@tauri-apps/plugin-sql", async () => {
  const mod = await import("../mocks/tauriSql");
  return { default: mod.default };
});

vi.mock("../../services/apiClient", () => ({
  apiClient: {
    get: vi.fn(),
  },
}));

import { localDb } from "../../db/localDb";
import { runMigrations } from "../../db/schema";
import { pullEngine } from "../../services/sync/PullEngine";
import { apiClient } from "../../services/apiClient";

describe("PullEngine", () => {
  beforeAll(async () => {
    await localDb.getConnection();
    await runMigrations();
  });

  beforeEach(async () => {
    await localDb.execute("DELETE FROM local_categories");
    await localDb.execute("DELETE FROM local_products");
    await localDb.execute("DELETE FROM local_tables");
    await localDb.execute("DELETE FROM local_payment_methods");
    await localDb.execute("DELETE FROM sync_state");
    vi.clearAllMocks();
  });

  afterEach(() => {
    vi.clearAllMocks();
  });

  it("debería descargar y aplicar snapshot completo", async () => {
    // Mock de respuestas del servidor
    (apiClient.get as any)
      .mockResolvedValueOnce({
        data: {
          data: [
            { uuid: "cat-1", name_translations: { es: "Bebidas" }, sort_order: 1, is_active: true },
            { uuid: "cat-2", name_translations: { es: "Comidas" }, sort_order: 2, is_active: true },
          ],
        },
      })
      .mockResolvedValueOnce({
        data: {
          data: [
            {
              uuid: "prod-1",
              category_id: "cat-1",
              name_translations: { es: "Coca Cola" },
              base_price: 2000,
              tax_rate: 19,
              is_active: true,
            },
          ],
        },
      })
      .mockResolvedValueOnce({
        data: {
          data: [
            { uuid: "table-1", table_number: "1", area_name: "Terraza", capacity: 4, status: "available" },
          ],
        },
      })
      .mockResolvedValueOnce({
        data: {
          data: [
            { uuid: "pm-1", code: "CASH", type: "cash", is_active: true },
            { uuid: "pm-2", code: "CARD", type: "card", is_active: true },
          ],
        },
      });

    const stats = await pullEngine.pullAll();

    expect(stats.success).toBe(true);
    expect(stats.categories).toBe(2);
    expect(stats.products).toBe(1);
    expect(stats.tables).toBe(1);
    expect(stats.paymentMethods).toBe(2);

    // Verificar que los datos están en SQLite
    const categories = await localDb.select("SELECT * FROM local_categories");
    expect(categories).toHaveLength(2);

    const products = await localDb.select("SELECT * FROM local_products");
    expect(products).toHaveLength(1);
    expect(products[0].name_translations).toContain("Coca Cola");

    const tables = await localDb.select("SELECT * FROM local_tables");
    expect(tables).toHaveLength(1);
    expect(tables[0].table_number).toBe("1");

    const methods = await localDb.select("SELECT * FROM local_payment_methods");
    expect(methods).toHaveLength(2);

    // Verificar timestamp de último pull
    const lastPull = await pullEngine.getLastPullTimestamp();
    expect(lastPull).toBeDefined();
  });

  it("debería aplicar política Cloud wins (sobrescribe datos locales)", async () => {
    // Insertar datos locales antiguos
    await localDb.execute(
      `INSERT INTO local_products (uuid, name_translations, base_price, is_active) 
       VALUES ('prod-1', '{"es": "Nombre Antiguo"}', 1000, 1)`
    );

    // Mock: servidor retorna producto con precio actualizado
    (apiClient.get as any)
      .mockResolvedValueOnce({ data: { data: [] } }) // categories
      .mockResolvedValueOnce({
        data: {
          data: [
            {
              uuid: "prod-1",
              name_translations: { es: "Nombre Nuevo" },
              base_price: 1500,
              is_active: true,
            },
          ],
        },
      })
      .mockResolvedValueOnce({ data: { data: [] } }) // tables
      .mockResolvedValueOnce({ data: { data: [] } }); // payment_methods

    await pullEngine.pullAll();

    // Verificar que el producto fue sobrescrito (Cloud wins)
    const product = await localDb.selectOne<any>(
      "SELECT * FROM local_products WHERE uuid = ?",
      ["prod-1"]
    );
    expect(product).toBeDefined();
    expect(product?.name_translations).toContain("Nombre Nuevo");
    expect(product?.base_price).toBe(1500);
  });

  it("debería manejar errores de red gracefully", async () => {
    // PullEngine hace 4 llamadas GET en paralelo (Promise.all)
    // Mockear todas para que fallen con el mismo error
    const error = new Error("Network error");
    (apiClient.get as any).mockRejectedValue(error);

    const stats = await pullEngine.pullAll();

    expect(stats.success).toBe(false);
    expect(stats.error).toContain("Network error");
  });

  it("debería manejar snapshot vacío", async () => {
    (apiClient.get as any)
      .mockResolvedValueOnce({ data: { data: [] } })
      .mockResolvedValueOnce({ data: { data: [] } })
      .mockResolvedValueOnce({ data: { data: [] } })
      .mockResolvedValueOnce({ data: { data: [] } });

    const stats = await pullEngine.pullAll();

    expect(stats.success).toBe(true);
    expect(stats.categories).toBe(0);
    expect(stats.products).toBe(0);
    expect(stats.tables).toBe(0);
    expect(stats.paymentMethods).toBe(0);
  });
});

describe("PullEngine - Modo Incremental", () => {
  beforeEach(async () => {
    await localDb.execute("DELETE FROM sync_state");
  });

  it("debería usar modo incremental si existe last_pull_at", async () => {
    // Simular que ya hay una sync previa
    await localDb.execute(
      `INSERT INTO sync_state (key, value) VALUES ('last_pull_at', '2026-08-20T16:00:00Z')`
    );

    (apiClient.get as any)
      .mockResolvedValueOnce({
        data: {
          data: {
            changes: {
              categories: [],
              products: [
                {
                  uuid: "prod-1",
                  name_translations: { es: "Producto Actualizado" },
                  base_price: 2000,
                  is_active: true,
                  deleted: false,
                  updated_at: "2026-08-20T17:00:00Z",
                },
              ],
              tables: [],
              payment_methods: [],
            },
            total: 1,
            timestamp: "2026-08-20T18:00:00Z",
            incremental: true,
          },
        },
      });

    const stats = await pullEngine.pullAll();

    expect(stats.success).toBe(true);
    expect(stats.incremental).toBe(true);
    expect(stats.products).toBe(1);
    expect(stats.categories).toBe(0);

    // Verificar que last_pull_at se actualizó
    const newTimestamp = await pullEngine.getLastPullTimestamp();
    expect(newTimestamp).toBe("2026-08-20T18:00:00Z");
  });

  it("debería eliminar registros localmente cuando deleted=true", async () => {
    // Insertar producto local
    await localDb.execute(
      `INSERT INTO local_products (uuid, name_translations, base_price, is_active) 
       VALUES ('prod-eliminar', '{"es": "Producto Viejo"}', 1000, 1)`
    );

    // Simular sync previa
    await localDb.execute(
      `INSERT INTO sync_state (key, value) VALUES ('last_pull_at', '2026-08-20T16:00:00Z')`
    );

    // Mock respuesta con deleted=true
    (apiClient.get as any).mockResolvedValueOnce({
      data: {
        data: {
          changes: {
            categories: [],
            products: [
              {
                uuid: "prod-eliminar",
                deleted: true,
                updated_at: "2026-08-20T17:00:00Z",
              },
            ],
            tables: [],
            payment_methods: [],
          },
          total: 1,
          timestamp: "2026-08-20T18:00:00Z",
          incremental: true,
        },
      },
    });

    await pullEngine.pullAll();

    // Verificar que el producto fue eliminado
    const product = await localDb.selectOne(
      "SELECT * FROM local_products WHERE uuid = ?",
      ["prod-eliminar"]
    );
    expect(product).toBeNull();
  });

  it("debería usar snapshot completo si no hay last_pull_at", async () => {
    // No insertar last_pull_at (primera sync)

    (apiClient.get as any)
      .mockResolvedValueOnce({
        data: {
          data: [
            { uuid: "cat-1", name_translations: { es: "Categoría 1" }, is_active: true },
          ],
        },
      })
      .mockResolvedValueOnce({
        data: {
          data: [
            { uuid: "prod-1", name_translations: { es: "Producto 1" }, base_price: 1000, is_active: true },
          ],
        },
      })
      .mockResolvedValueOnce({ data: { data: [] } })
      .mockResolvedValueOnce({ data: { data: [] } });

    const stats = await pullEngine.pullAll();

    expect(stats.success).toBe(true);
    expect(stats.incremental).toBe(false);
    expect(stats.categories).toBe(1);
    expect(stats.products).toBe(1);
  });
});
