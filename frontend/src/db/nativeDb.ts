import { invoke } from "@tauri-apps/api/core";

export interface DbStatement {
  sql: string;
  params: (string | number | boolean | null)[];
}

/**
 * Ejecuta una transacción atómica en Rust.
 * Si ignoreDuplicateErrors es true, los errores de "duplicate column" o "already exists"
 * se ignoran silenciosamente (útil para migraciones idempotentes).
 */
export async function executeTransaction(
  statements: DbStatement[], 
  options?: { ignoreDuplicateErrors?: boolean }
): Promise<void> {
  try {
    await invoke<string>("execute_transaction", { statements });
  } catch (error: any) {
    const msg = error.message || error.toString();
    
    // Si está habilitado, ignorar errores de duplicación (migraciones idempotentes)
    if (options?.ignoreDuplicateErrors && (
      msg.includes("duplicate column") ||
      msg.includes("already exists") ||
      msg.includes("UNIQUE constraint")
    )) {
      console.warn("[NativeDB] ⚠️ Duplicado ignorado (migración idempotente)");
      return;
    }
    
    console.error("[NativeDB] ❌ Error en transacción:", error);
    throw new Error(msg);
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
