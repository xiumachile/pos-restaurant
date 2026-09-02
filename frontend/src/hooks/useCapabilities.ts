import { useEffect } from 'react';
import { useCapabilitiesStore } from '@/store/useCapabilitiesStore';
import { useAuthStore } from '@/store/useAuthStore';
import { CapabilityKey } from '@/types/capabilities';

/**
 * Hook para consumir capabilities en componentes.
 * 
 * Ejemplo de uso:
 *   const { isFeatureEnabled, isReady } = useCapabilities();
 *   if (!isFeatureEnabled(CapabilityKey.CAN_ACCEPT_TIPS)) return null;
 */
export function useCapabilities() {
  const user = useAuthStore((state) => state.user);
  const { capabilities, fetchCapabilities, isCapabilityEnabled, isLoading, error } =
    useCapabilitiesStore();

  // Usar company.uuid (no company_id)
  const companyUuid = user?.company?.uuid ?? '';

  // Auto-fetch al montar si hay empresa
  useEffect(() => {
    if (companyUuid && Object.keys(capabilities).length === 0) {
      fetchCapabilities(companyUuid);
    }
  }, [companyUuid, capabilities, fetchCapabilities]);

  return {
    /**
     * Verifica si una capability está habilitada.
     */
    isFeatureEnabled: (key: CapabilityKey) => isCapabilityEnabled(key),

    /**
     * Si los datos ya están cargados.
     */
    isReady: Object.keys(capabilities).length > 0 && !isLoading,

    /**
     * Todas las capabilities.
     */
    capabilities,

    isLoading,
    error,
    refetch: () => companyUuid && fetchCapabilities(companyUuid),
  };
}
