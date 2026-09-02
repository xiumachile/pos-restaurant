import { useCapabilities } from '@/hooks/useCapabilities';
import { CapabilityKey } from '@/types/capabilities';
import type { ReactNode } from 'react';

interface CapabilityGateProps {
  /**
   * Capability requerida para renderizar los children.
   */
  requires: CapabilityKey;

  /**
   * Contenido a renderizar si la capability está habilitada.
   */
  children: ReactNode;

  /**
   * Fallback opcional si la capability está deshabilitada.
   * Por defecto no renderiza nada.
   */
  fallback?: ReactNode;

  /**
   * Invertir la lógica: mostrar children si está DESHABILITADA.
   * Útil para mensajes tipo "Esta empresa no tiene X habilitado".
   */
  invert?: boolean;
}

/**
 * Componente wrapper que condicionalmente renderiza children
 * basado en si una capability de empresa está habilitada.
 * 
 * Ejemplo:
 *   <CapabilityGate requires={CapabilityKey.CAN_ACCEPT_TIPS}>
 *     <TipsSection />
 *   </CapabilityGate>
 * 
 *   <CapabilityGate requires={CapabilityKey.CAN_SPLIT_BILLS} invert>
 *     <p>Esta empresa no permite dividir cuentas</p>
 *   </CapabilityGate>
 */
export function CapabilityGate({
  requires,
  children,
  fallback = null,
  invert = false,
}: CapabilityGateProps) {
  const { isFeatureEnabled, isReady } = useCapabilities();

  // Mientras carga, no renderizar nada (evita flash)
  if (!isReady) {
    return null;
  }

  const enabled = isFeatureEnabled(requires);
  const shouldShow = invert ? !enabled : enabled;

  return shouldShow ? <>{children}</> : <>{fallback}</>;
}
