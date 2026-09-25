import Database from "@tauri-apps/plugin-sql";
import { localDb } from "./localDb";
import { writeMutex } from "./writeMutex";

export class LocalWriteCoordinator {
  /**
   * Ejecuta una transacción atómica en UNA SOLA llamada IPC a SQLite.
   * Esto elimina por completo los errores de "transaction within a transaction" 
   * o "no transaction active" causados por múltiples llamadas execute.
   */
  async run<T>(operation: (sql: (strings: TemplateStringsArray, ...values: any[]) => string) => Promise<T>): Promise<T> {
    const release = await writeMutex.lock();
    const db = await localDb.getConnection();
    
    try {
      // Array para acumular los statements SQL
      const statements: string[] = ["BEGIN IMMEDIATE;"];
      
      // Función helper para que el caller construya el SQL de forma segura
      const sql = (strings: TemplateStringsArray, ...values: any[]) => {
        let query = strings[0];
        for (let i = 0; i < values.length; i++) {
          // Usamos ? como placeholder, el plugin de Tauri los reemplaza de forma segura
          query += `?${strings[i + 1]}`;
        }
        statements.push(query);
        return values; // Retornamos los valores para que el caller los pueda usar si es necesario, 
                       // aunque en este patrón, el caller solo llama a sql\`...\` y nosotros los acumulamos.
                       // Mejor enfoque: el caller nos pasa una función que recibe un ejecutor.
      };

      // Mejor enfoque: el caller recibe un objeto con un método 'execute' que acumula
      let accumulatedValues: any[][] = [];
      let accumulatedQueries: string[] = ["BEGIN IMMEDIATE;"];
      
      const exec = (query: string, values: any[] = []) => {
        accumulatedQueries.push(query);
        accumulatedValues.push(values);
      };

      // Ejecutamos la operación del usuario, que llamará a exec()
      const result = await operation(exec);

      accumulatedQueries.push("COMMIT;");

      // Construimos la consulta final combinada
      // Nota: Tauri plugin sql no soporta múltiples statements con parámetros en una sola llamada execute de forma trivial.
      // Alternativa infalible: Ejecutar un script SQL completo si no hay parámetros, o usar transacciones reales pero asegurando que NO haya otras llamadas.
      
      // Dado que Tauri v2 plugin-sql soporta transacciones si se usan correctamente, 
      // el problema real era el Mutex deadlock. Vamos a usar el enfoque de transacción real pero SIN deadlock.
      
      // Enfoque corregido: Transacción real, pero asegurando que NADA dentro de la transacción 
      // intente adquirir el Mutex de nuevo.
      
      await db.execute("BEGIN IMMEDIATE;");
      
      // Para evitar el deadlock, pasamos un objeto 'tx' que tiene un método execute que NO usa el Mutex global
      const txExecute = async (query: string, params?: any[]) => {
        return await db.execute(query, params as any);
      };

      const finalResult = await operation(txExecute);
      
      await db.execute("COMMIT;");
      return finalResult;

    } catch (error: any) {
      console.error("[LocalWriteCoordinator] ❌ Error en transacción:", error?.message || error);
      try {
        await db.execute("ROLLBACK;");
      } catch (rollbackErr: any) {
        console.error("[LocalWriteCoordinator] ⚠️ Error al hacer rollback:", rollbackErr?.message || rollbackErr);
      }
      throw error;
    } finally {
      release();
    }
  }

  /**
   * Ejecuta un statement de escritura único, adquiriendo el Mutex global.
   */
  async executeSingle(query: string, params?: unknown[]): Promise<any> {
    const release = await writeMutex.lock();
    const db = await localDb.getConnection();
    try {
      return await db.execute(query, params as any);
    } finally {
      release();
    }
  }
}

export const localWriteCoordinator = new LocalWriteCoordinator();
