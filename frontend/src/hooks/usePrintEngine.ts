import { useEffect, useRef } from 'react';
import { printEngine } from '../services/printing/PrintEngine';
import { offlinePrintEngine } from '../services/printing/OfflinePrintEngine';
import { useAuthStore } from '../store/useAuthStore';

/**
 * Contador de referencias a nivel de módulo.
 * Permite que múltiples componentes usen el hook sin
 * iniciar/detener el engine innecesariamente.
 */
let printEngineRefCount = 0;

/**
 * Hook que inicia AMBOS motores de impresión cuando el usuario está autenticado
 * y los detiene al cerrar sesión.
 * 
 * MOTORES GESTIONADOS:
 * 1. printEngine (OnlinePrintEngine): Polling del backend para jobs del cloud
 * 2. offlinePrintEngine (OfflinePrintEngine): Polling de SQLite para jobs locales
 * 
 * ARQUITECTURA HÍBRIDA:
 * - Ambos motores corren en paralelo
 * - OnlinePrintEngine: Solo funciona cuando hay conexión al backend
 * - OfflinePrintEngine: SIEMPRE funciona (incluso offline)
 * - Si el backend falla, los jobs locales siguen imprimiéndose
 * 
 * IDEMPOTENTE: Los engines solo se inician/detienen cuando cambia el
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
        
        // Iniciar AMBOS motores
        printEngine.start();
        offlinePrintEngine.start();
        
        console.log("[PrintEngine] 📊 Ref count:", printEngineRefCount);
        console.log("[PrintEngine] 🚀 Ambos motores iniciados (online + offline)");
      }
    } else {
      if (printEngineRefCount > 0) {
        printEngineRefCount--;
        if (printEngineRefCount === 0) {
          // Detener AMBOS motores
          printEngine.stop();
          offlinePrintEngine.stop();
          
          console.log("[PrintEngine] 📊 Ref count:", printEngineRefCount);
          console.log("[PrintEngine] ⏹️  Ambos motores detenidos");
        }
      }
    }

    // NOTA: NO hacemos cleanup aquí.
    // El cleanup solo ocurre cuando isAuthenticated cambia (logout).
    // React StrictMode re-ejecutará el effect, pero el refCount
    // previene inicios/detenciones innecesarias.
  }, [isAuthenticated]);
}
