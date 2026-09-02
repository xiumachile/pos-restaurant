import { renderHook } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { useCapabilities } from '@/hooks/useCapabilities';
import { useCapabilitiesStore } from '@/store/useCapabilitiesStore';
import { useAuthStore } from '@/store/useAuthStore';
import { CapabilityKey } from '@/types/capabilities';

// Mocks
vi.mock('@/store/useAuthStore', () => ({
  useAuthStore: vi.fn(),
}));

describe('useCapabilities', () => {
  beforeEach(() => {
    useCapabilitiesStore.setState({
      capabilities: {
        [CapabilityKey.CAN_ACCEPT_TIPS]: {
          key: CapabilityKey.CAN_ACCEPT_TIPS,
          is_enabled: true,
          description: 'Aceptar propinas',
          icon: '💰',
          category: 'payments',
        },
        [CapabilityKey.CAN_SPLIT_BILLS]: {
          key: CapabilityKey.CAN_SPLIT_BILLS,
          is_enabled: false,
          description: 'Dividir cuentas',
          icon: '📋',
          category: 'payments',
        },
      } as any,
      isLoading: false,
      error: null,
      lastFetchedAt: Date.now(),
    });

    (useAuthStore as any).mockImplementation((selector: any) =>
      selector({ user: { company: { uuid: 'test-company-uuid' } } })
    );
  });

  it('debería identificar capabilities habilitadas', () => {
    const { result } = renderHook(() => useCapabilities());
    expect(result.current.isFeatureEnabled(CapabilityKey.CAN_ACCEPT_TIPS)).toBe(true);
    expect(result.current.isFeatureEnabled(CapabilityKey.CAN_SPLIT_BILLS)).toBe(false);
  });

  it('debería marcar isReady cuando hay datos cargados', () => {
    const { result } = renderHook(() => useCapabilities());
    expect(result.current.isReady).toBe(true);
  });

  it('debería retornar false para capabilities inexistentes', () => {
    const { result } = renderHook(() => useCapabilities());
    expect(result.current.isFeatureEnabled('non_existent' as any)).toBe(false);
  });
});
