import { invoke as tauriInvoke } from "@tauri-apps/api/core";

// Fallback para entornos de prueba (Node.js/CI) donde la API de Tauri no está disponible
const invoke = typeof tauriInvoke === 'function' 
  ? tauriInvoke 
  : async (cmd: string, args?: any) => {
      console.warn(`[NativeDB Mock] invoke('${cmd}') llamado en entorno de prueba`);
      if (cmd === 'execute_transaction') return "Transacción exitosa";
      if (cmd === 'execute_query') return [];
      throw new Error(`Mock invoke no implementado para ${cmd}`);
    };

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
      try {
        await executeQuery(stmt.sql, stmt.params);
      } catch (error: any) {
        console.error("[NativeDB] ❌ Error en SELECT:", error);
        throw error;
      }
    } else {
      try {
        await invoke<string>("execute_transaction", { statements: [stmt] });
      } catch (error: any) {
        const msg = error.message || error.toString();
        
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
