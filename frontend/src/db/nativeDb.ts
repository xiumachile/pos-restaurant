export interface DbStatement {
  sql: string;
  params: (string | number | boolean | null)[];
}

// Mock para entornos de prueba (Node.js/CI) donde Tauri no está disponible
const mockInvoke = async (cmd: string, _args?: any): Promise<any> => {
  if (cmd === 'execute_transaction') return "Transacción exitosa";
  if (cmd === 'execute_query') return [];
  throw new Error(`Mock invoke no implementado para ${cmd}`);
};

// Cache para la función invoke (real o mock)
let _invoke: ((cmd: string, args?: any) => Promise<any>) | null = null;

/**
 * Obtiene la función invoke correcta según el entorno.
 * En Tauri real: usa @tauri-apps/api/core.
 * En Node.js/CI: usa el mock.
 * Se cachea después del primer llamado.
 */
async function getInvoke(): Promise<(cmd: string, args?: any) => Promise<any>> {
  if (_invoke) return _invoke;

  try {
    // Dynamic import: solo se ejecuta si no hay cache
    // En Node.js/CI, esto fallará gracefully y usaremos el mock
    const mod = await import("@tauri-apps/api/core");
    if (typeof mod.invoke === 'function') {
      _invoke = mod.invoke;
      return _invoke;
    }
  } catch {
    // Import falló (entorno Node.js/CI) - usar mock
  }

  _invoke = mockInvoke;
  return _invoke;
}

/**
 * Ejecuta una transacción atómica en Rust.
 * Detecta automáticamente statements SELECT y los ejecuta con executeQuery.
 */
export async function executeTransaction(
  statements: DbStatement[],
  options?: { ignoreDuplicateErrors?: boolean }
): Promise<void> {
  const invoke = await getInvoke();

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
        await invoke("execute_transaction", { statements: [stmt] });
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
export async function executeQuery<T = any>(
  sql: string,
  params: (string | number | boolean | null)[] = []
): Promise<T[]> {
  const invoke = await getInvoke();

  try {
    const result = await invoke("execute_query", { sql, params });
    return (result as T[]) || [];
  } catch (error: any) {
    console.error("[NativeDB] ❌ Error en query:", error);
    throw new Error(error.message || "Error en query nativa");
  }
}
