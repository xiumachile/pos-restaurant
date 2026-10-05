import type { SQL } from "bun:sqlite";

/**
 * Migración 022: Corregir naming de takeout
 * 
 * Problema: LocalOrder usaba "take_out" (con guion) pero el backend espera "takeout" (sin guion).
 * Esto causaba error 422 al sincronizar pedidos takeout/delivery.
 * 
 * Fix: Convertir todos los valores "take_out" a "takeout" en la tabla local_orders.
 */
export async function up(sql: SQL): Promise<void> {
  console.log("[Migration 022] 🔄 Convirtiendo take_out → takeout en local_orders...");
  
  const result = sql.run(`
    UPDATE local_orders 
    SET order_type = 'takeout' 
    WHERE order_type = 'take_out'
  `);
  
  console.log(`[Migration 022] ✅ ${result.changes} filas actualizadas`);
}

export async function down(sql: SQL): Promise<void> {
  console.log("[Migration 022] 🔄 Revert: takeout → take_out...");
  
  const result = sql.run(`
    UPDATE local_orders 
    SET order_type = 'take_out' 
    WHERE order_type = 'takeout'
  `);
  
  console.log(`[Migration 022] ✅ ${result.changes} filas revertidas`);
}
