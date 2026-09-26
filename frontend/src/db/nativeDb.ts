import { invoke } from "@tauri-apps/api/core";

export interface DbStatement {
  sql: string;
  params: (string | number | boolean | null)[];
}

/**
 * Ejecuta una transacción atómica en Rust.
 * Detecta automáticamente statements SELECT y los ejecuta con executeQuery.
 */
export async function executeTransaction(
  statements: DbStatement[], 
  options?: { ignoreDuplicateErrors?: boolean }
): Promise<void> {
  for (const stmt of statements) {
    const isSelect = /^\s*SELECT\s/i.test(stmt.sql);
    
    if (isSelect) {
      // Ejecutar SELECT con executeQuery
      try {
        await executeQuery(stmt.sql, stmt.params);
      } catch (error: any) {
        console.error("[NativeDB] ❌ Error en SELECT:", error);
        throw error;
      }
    } else {
      // Ejecutar escritura con transacción atómica
      try {
        await invoke<string>("execute_transaction", { statements: [stmt] });
      } catch (error: any) {
        const msg = error.message || error.toString();
        
        // Si está habilitado, ignorar errores de duplicación
        if (options?.ignoreDuplicateErrors && (
          msg.includes("duplicate column") ||
          msg.includes("already exists") ||
          msg.includes("UNIQUE constraint")
        )) {
          console.warn("[NativeDB] ⚠️ Duplicado ignorado (migración idempotente)");
          continue;
        }
        
        console.error("[NativeDB] ❌ Error en transacción:", error);
        throw new Error(msg);
      }
    }
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
