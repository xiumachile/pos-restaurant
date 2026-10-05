/**
 * Migración 022: Corregir naming de takeout
 * 
 * Problema: LocalOrder usaba "take_out" (con guion) pero el backend espera "takeout" (sin guion).
 * Esto causaba error 422 al sincronizar pedidos takeout/delivery.
 * 
 * Fix: Convertir todos los valores "take_out" a "takeout" en la tabla local_orders.
 */
export async function up(db: any): Promise<void> {
  console.log("[Migration 022] 🔄 Convirtiendo take_out → takeout en local_orders...");
  
  try {
    const result = db.run(`
      UPDATE local_orders 
      SET order_type = 'takeout' 
      WHERE order_type = 'take_out'
    `);
    
    console.log(`[Migration 022] ✅ Migración ejecutada`);
  } catch (error) {
    console.error("[Migration 022] ❌ Error:", error);
    throw error;
  }
}

export async function down(db: any): Promise<void> {
  console.log("[Migration 022] 🔄 Revert: takeout → take_out...");
  
  try {
    const result = db.run(`
      UPDATE local_orders 
      SET order_type = 'take_out' 
      WHERE order_type = 'takeout'
    `);
    
    console.log(`[Migration 022] ✅ Revert ejecutado`);
  } catch (error) {
    console.error("[Migration 022] ❌ Error:", error);
    throw error;
  }
}
