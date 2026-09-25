import { invoke } from "@tauri-apps/api/core";

export interface DbStatement {
  sql: string;
  params: (string | number | boolean | null)[];
}

/**
 * Ejecuta una transacción atómica en Rust.
 * Todos los statements se ejecutan en una sola llamada IPC con BEGIN IMMEDIATE y COMMIT.
 */
export async function executeTransaction(statements: DbStatement[]): Promise<void> {
  try {
    await invoke<string>("execute_transaction", { statements });
  } catch (error: any) {
    console.error("[NativeDB] ❌ Error en transacción:", error);
    throw new Error(error.message || "Error en transacción nativa");
  }
}

/**
 * Ejecuta una consulta de lectura (SELECT) en Rust.
 */
export async function executeQuery<T = any>(sql: string, params: (string | number | boolean | null)[] = []): Promise<T[]> {
  try {
    const result = await invoke<any>("execute_query", { sql, params });
    return result as T[];
  } catch (error: any) {
    console.error("[NativeDB] ❌ Error en query:", error);
    throw new Error(error.message || "Error en query nativa");
  }
}
