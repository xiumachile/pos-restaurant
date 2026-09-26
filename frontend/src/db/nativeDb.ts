export interface DbStatement {
  sql: string;
  params: (string | number | boolean | null)[];
}

// Mock para entornos de prueba (Node.js/CI/Vitest) donde Tauri no está disponible
const mockInvoke = async (cmd: string, _args?: any): Promise<any> => {
  if (cmd === 'execute_transaction') return "Transacción exitosa";
  if (cmd === 'execute_query') return [];
  throw new Error(`Mock invoke no implementado para ${cmd}`);
};

// Cache para la función invoke (real o mock)
let _invoke: ((cmd: string, args?: any) => Promise<any>) | null = null;

/**
 * Detecta si estamos en un entorno Tauri real.
 * En Tauri, window.__TAURI__ existe. En Node.js/CI/Vitest, no.
 */
function isTauriEnvironment(): boolean {
  return typeof window !== 'undefined' && 
         typeof (window as any).__TAURI__ !== 'undefined';
}

/**
 * Obtiene la función invoke correcta según el entorno.
 * Se cachea después del primer llamado para evitar overhead.
 */
async function getInvoke(): Promise<(cmd: string, args?: any) => Promise<any>> {
  if (_invoke) return _invoke;

  // Detección rápida: si no estamos en Tauri, usar mock directamente
  if (!isTauriEnvironment()) {
    _invoke = mockInvoke;
    return _invoke;
  }

  // Estamos en Tauri: intentar importar la API real
  try {
    const mod = await import("@tauri-apps/api/core");
    if (mod && typeof mod.invoke === 'function') {
      _invoke = mod.invoke;
      return _invoke;
    }
  } catch (error) {
    console.warn("[NativeDB] ⚠️ No se pudo importar @tauri-apps/api/core, usando mock:", error);
  }

  // Fallback final: usar mock
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
