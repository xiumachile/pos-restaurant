import { useSyncStore } from "@/store/useSyncStore";

/**
 * Hook que indica si el sistema está en modo offline.
 * 
 * Retorna true si:
 * - El navegador está offline (navigator.onLine === false)
 * - El usuario forzó modo offline simulado (Ctrl+Shift+O)
 * 
 * Uso:
 *   const isOffline = useConnectionMode();
 *   const paymentMutation = isOffline ? useOfflinePayment() : usePayBill();
 */
export function useConnectionMode(): boolean {
  const status = useSyncStore((s) => s.status);
  const simulatedOffline = useSyncStore((s) => s.simulatedOffline);

  // Offline si:
  // 1. El store reporta status "offline" (navigator.onLine === false)
  // 2. O el usuario forzó modo offline simulado
  return status === "offline" || simulatedOffline;
}
