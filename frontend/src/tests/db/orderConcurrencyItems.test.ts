import { describe, it, expect, beforeAll, afterAll, beforeEach, vi } from "vitest";

vi.mock("@tauri-apps/plugin-sql", async () => {
  const mod = await import("../mocks/tauriSql");
  return { default: mod.default };
});

import { localDb } from "../../db/localDb";
import { runMigrations } from "../../db/schema";
import { OrderRepository } from "../../db/repositories/OrderRepository";

describe("OrderRepository - Escenario 2: Tormenta de Items", () => {
  beforeAll(async () => {
    localDb;
    await runMigrations();
  });

  afterAll(async () => {});

  beforeEach(async () => {
    await localDb.execute("DELETE FROM sync_queue");
    await localDb.execute("DELETE FROM local_order_items");
    await localDb.execute("DELETE FROM local_orders");
    await localDb.execute("DELETE FROM local_tables");
    
    // Crear una mesa y un pedido base
    await localDb.execute(
      `INSERT INTO local_tables (uuid, company_id, branch_id, table_number, status) VALUES (?, ?, ?, ?, 'available')`,
      ['table-2', '10', '5', 2]
    );
  });

  it("debe calcular correctamente el total al agregar 50 items simultáneamente", async () => {
    const companyId = '10';
    const branchId = '5';
    const tableId = 'table-2';

    // 1. Crear el pedido inicial
    const order = await OrderRepository.create({
      company_id: companyId,
      branch_id: branchId,
      table_id: tableId,
      order_type: 'dine_in',
    });

    console.log(`📦 Pedido creado: ${order.local_uuid}`);

    // 2. Preparar 50 items para agregar simultáneamente
    const numItems = 50;
    const itemPrice = 1500; // Precio en centavos (15.00 CLP)
    const expectedTotal = numItems * itemPrice;

    console.log(`🚀 Agregando ${numItems} items simultáneamente (precio: ${itemPrice} c/u)...`);

    // 3. Lanzar todas las adiciones al mismo tiempo
    const addPromises = [];
    for (let i = 0; i < numItems; i++) {
      addPromises.push(
        OrderRepository.addItem(order.local_uuid, {
          product_id: `prod-${i}`,
          product_name: `Producto ${i}`,
          quantity: 1,
          unit_price: itemPrice,
        })
      );
    }

    // Ejecutar todas simultáneamente
    const results = await Promise.allSettled(addPromises);
    const successes = results.filter(r => r.status === 'fulfilled').length;
    const failures = results.filter(r => r.status === 'rejected').length;

    console.log(`✅ Items agregados exitosamente: ${successes}`);
    console.log(`❌ Items fallidos: ${failures}`);

    // 4. Verificar integridad final
    const items = await localDb.select('SELECT * FROM local_order_items WHERE order_local_uuid = ?', [order.local_uuid]);
    console.log(`📊 Total de items en BD: ${items.length}`);

    const finalOrder = await OrderRepository.findByLocalUuid(order.local_uuid);
    console.log(`💰 Grand Total calculado: ${finalOrder?.grand_total}`);
    console.log(`🎯 Grand Total esperado: ${expectedTotal}`);

    // ==========================================
    // EXPECTATIVAS DE INTEGRIDAD
    // ==========================================
    
    // 1. Todos los items deben haberse agregado (sin pérdidas por race condition)
    expect(successes).toBe(numItems);
    expect(failures).toBe(0);
    expect(items.length).toBe(numItems);

    // 2. El total debe ser EXACTAMENTE la suma de los items (sin lost updates)
    expect(finalOrder?.grand_total).toBe(expectedTotal);
  });
});
