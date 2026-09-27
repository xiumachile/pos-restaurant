import { localDb } from "../localDb";

export async function ensureChileanColumns(): Promise<void> {
  console.log("[Migrations] 🛠️  Verificando y reparando columnas chilenas faltantes...");
  const db = await localDb.getConnection();

  const tablesToCheck = [
    { name: "local_orders", columns: ["terminal_id", "net_amount", "amount_due", "guest_count", "idempotency_key"] },
    { name: "local_bills", columns: ["terminal_id", "net_amount", "amount_due"] },
    { name: "local_payments", columns: ["sale_amount"] },
    { name: "table_local_mutations", columns: ["action", "payload", "company_id", "branch_id", "created_at"] }
  ];

  for (const table of tablesToCheck) {
    const columns: any[] = await db.select(`PRAGMA table_info(${table.name})`);
    const existingNames = columns.map((c: any) => c.name);

    for (const col of table.columns) {
      if (!existingNames.includes(col)) {
        console.warn(`[Migrations] ⚠️  Columna faltante en ${table.name}: ${col}. Reparando...`);
        let defaultVal = "NULL";
        if (col === "guest_count") defaultVal = "1";
        if (col === "net_amount" || col === "amount_due" || col === "sale_amount") defaultVal = "0";
        if (col === "idempotency_key") defaultVal = "''";
        
        await db.execute(`ALTER TABLE ${table.name} ADD COLUMN ${col} DEFAULT ${defaultVal}`);
        console.log(`[Migrations] ✅ Columna ${col} agregada a ${table.name}`);
      }
    }
  }
  console.log("[Migrations] 🎉 Esquema chileno verificado y reparado.");
}
