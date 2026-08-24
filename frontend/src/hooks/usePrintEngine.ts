import { useEffect } from 'react';
import { printEngine } from '../services/printing/PrintEngine';
import { useAuthStore } from '../store/useAuthStore';

/**
 * Hook que inicia el PrintEngine cuando el usuario está autenticado
 * y lo detiene al desmontar o al cerrar sesión.
 */
export function usePrintEngine() {
  const isAuthenticated = useAuthStore((s) => s.isAuthenticated);

  useEffect(() => {
    if (!isAuthenticated) {
      printEngine.stop();
      return;
    }

    printEngine.start();

    return () => {
      printEngine.stop();
    };
  }, [isAuthenticated]);
}
