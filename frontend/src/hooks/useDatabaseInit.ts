import { useState, useEffect } from "react";
import { runMigrations, verifyDatabase } from "../db/schema";

interface DatabaseState {
  isReady: boolean;
  isInitializing: boolean;
  error: string | null;
}

/**
 * Hook que inicializa la base de datos local al cargar la app.
 * Debe usarse en el componente raíz (App.tsx o Layout).
 */
export function useDatabaseInit(): DatabaseState {
  const [state, setState] = useState<DatabaseState>({
    isReady: false,
    isInitializing: true,
    error: null,
  });

  useEffect(() => {
    let cancelled = false;

    async function init() {
      try {
        console.log("[useDatabaseInit] Iniciando base de datos local...");

        // Verificar conexión
        const isOk = await verifyDatabase();
        if (!isOk) {
          throw new Error("No se pudo conectar a SQLite");
        }

        // Ejecutar migraciones
        await runMigrations();

        if (!cancelled) {
          setState({
            isReady: true,
            isInitializing: false,
            error: null,
          });
          console.log("[useDatabaseInit] Base de datos lista");
        }
      } catch (error: any) {
        console.error("[useDatabaseInit] Error:", error);
        if (!cancelled) {
          setState({
            isReady: false,
            isInitializing: false,
            error: error?.message || "Error desconocido",
          });
        }
      }
    }

    init();

    return () => {
      cancelled = true;
    };
  }, []);

  return state;
}
