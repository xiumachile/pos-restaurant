import { describe, it, expect, beforeAll, afterAll, beforeEach, vi } from "vitest";

vi.mock("@tauri-apps/plugin-sql", async () => {
  const mod = await import("../mocks/tauriSql");
  return { default: mod.default };
});

import { localDb } from "../../db/localDb";
import { runMigrations } from "../../db/schema";
import { OrderRepository } from "../../db/repositories/OrderRepository";

describe("OrderRepository - Escenario 1: Guerra por la Mesa", () => {
  beforeAll(async () => {
    localDb;
    await runMigrations();
  });

  afterAll(async () => {
    // localDb.close() gestionado por Rust
  });

  beforeEach(async () => {
    await localDb.execute("DELETE FROM sync_queue");
    await localDb.execute("DELETE FROM local_order_items");
    await localDb.execute("DELETE FROM local_orders");
    await localDb.execute("DELETE FROM local_tables");
    await localDb.execute("DELETE FROM table_local_mutations");
    
    // Preparar una mesa disponible (incluyendo table_number que es NOT NULL)
    await localDb.execute(
      `INSERT INTO local_tables (uuid, company_id, branch_id, table_number, status) VALUES (?, ?, ?, ?, 'available')`,
      ['table-1', '10', '5', 1]
    );
  });

  it("debe serializar intentos simultáneos de crear pedido en la misma mesa", async () => {
    const tableId = 'table-1';
    const companyId = '10';
    const branchId = '5';

    console.log("🚀 Lanzando 2 peticiones simultáneas de creación de pedido...");

    // Simular dos cajas intentando crear un pedido en la misma mesa al mismo tiempo
    const promise1 = OrderRepository.create({
      company_id: companyId,
      branch_id: branchId,
      table_id: tableId,
      order_type: 'dine_in',
    });

    const promise2 = OrderRepository.create({
      company_id: companyId,
      branch_id: branchId,
      table_id: tableId,
      order_type: 'dine_in',
    });

    // Ejecutar simultáneamente
    const results = await Promise.allSettled([promise1, promise2]);

    // Contar éxitos y fallos
    const successes = results.filter(r => r.status === 'fulfilled');
    const failures = results.filter(r => r.status === 'rejected');

    console.log(`✅ Éxitos: ${successes.length}`);
    console.log(`❌ Fallos: ${failures.length}`);

    if (failures.length > 0) {
      console.log('Razones de fallo:');
      failures.forEach((f, i) => {
        const reason = (f as PromiseRejectedResult).reason;
        console.log(`  ${i + 1}. ${reason?.message || reason}`);
      });
    }

    // Verificar integridad final de la base de datos
    const orders = await localDb.select('SELECT * FROM local_orders WHERE table_id = ?', [tableId]);
    console.log(`📊 Pedidos creados para la mesa: ${orders.length}`);

    const table = await localDb.select('SELECT status FROM local_tables WHERE uuid = ?', [tableId]);
    console.log(`🪑 Estado final de la mesa: ${table[0]?.status}`);

    // ==========================================
    // EXPECTATIVAS DE INTEGRIDAD
    // ==========================================
    
    // 1. Debe haber creado exactamente 1 pedido (el primero que ganó)
    // Si hay 2, es un bug: la lógica no está validando la mesa ocupada
    expect(orders.length).toBe(1);

    // 2. La mesa debe quedar ocupada al final
    expect(table[0]?.status).toBe('occupied');

    // 3. El pedido creado debe tener los datos correctos
    const order = orders[0] as any;
    expect(order.company_id).toBe(companyId);
    expect(order.branch_id).toBe(branchId);
    expect(order.status).toBe('confirmed');
  });
});
