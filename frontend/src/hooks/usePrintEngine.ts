import { useEffect, useRef } from 'react';
import { printEngine } from '../services/printing/PrintEngine';
import { useAuthStore } from '../store/useAuthStore';

/**
 * Contador de referencias a nivel de módulo.
 * Permite que múltiples componentes usen el hook sin
 * iniciar/detener el engine innecesariamente.
 */
let printEngineRefCount = 0;

/**
 * Hook que inicia el PrintEngine cuando el usuario está autenticado
 * y lo detiene al cerrar sesión.
 *
 * IDEMPOTENTE: El engine solo se inicia/detiene cuando cambia el
 * contador de referencias, no en cada mount/unmount de StrictMode.
 */
export function usePrintEngine() {
  const isAuthenticated = useAuthStore((s) => s.isAuthenticated);
  const lastAuthStateRef = useRef<boolean | null>(null);

  useEffect(() => {
    // Solo actuar si el estado de autenticación CAMBIÓ realmente
    if (lastAuthStateRef.current === isAuthenticated) {
      return;
    }
    lastAuthStateRef.current = isAuthenticated;

    if (isAuthenticated) {
      if (printEngineRefCount === 0) {
        printEngineRefCount++;
        printEngine.start();
        console.log("[PrintEngine] 📊 Ref count:", printEngineRefCount);
      }
    } else {
      if (printEngineRefCount > 0) {
        printEngineRefCount--;
        if (printEngineRefCount === 0) {
          printEngine.stop();
          console.log("[PrintEngine] 📊 Ref count:", printEngineRefCount);
        }
      }
    }

    // NOTA: NO hacemos cleanup aquí.
    // El cleanup solo ocurre cuando isAuthenticated cambia (logout).
    // React StrictMode re-ejecutará el effect, pero el refCount
    // previene inicios/detenciones innecesarias.
  }, [isAuthenticated]);
}
