import { render, screen } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { CapabilityGate } from '@/components/CapabilityGate';
import { useCapabilitiesStore } from '@/store/useCapabilitiesStore';
import { useAuthStore } from '@/store/useAuthStore';
import { CapabilityKey } from '@/types/capabilities';

vi.mock('@/store/useAuthStore', () => ({
  useAuthStore: vi.fn(),
}));

describe('CapabilityGate', () => {
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

  it('debería renderizar children cuando capability está habilitada', () => {
    render(
      <CapabilityGate requires={CapabilityKey.CAN_ACCEPT_TIPS}>
        <div data-testid="content">Contenido visible</div>
      </CapabilityGate>
    );
    expect(screen.getByTestId('content')).toBeInTheDocument();
  });

  it('no debería renderizar children cuando capability está deshabilitada', () => {
    render(
      <CapabilityGate requires={CapabilityKey.CAN_SPLIT_BILLS}>
        <div data-testid="content">Contenido oculto</div>
      </CapabilityGate>
    );
    expect(screen.queryByTestId('content')).not.toBeInTheDocument();
  });

  it('debería renderizar fallback cuando capability está deshabilitada', () => {
    render(
      <CapabilityGate
        requires={CapabilityKey.CAN_SPLIT_BILLS}
        fallback={<div data-testid="fallback">No disponible</div>}
      >
        <div>Contenido</div>
      </CapabilityGate>
    );
    expect(screen.getByTestId('fallback')).toBeInTheDocument();
  });

  it('debería invertir lógica con invert=true', () => {
    render(
      <CapabilityGate requires={CapabilityKey.CAN_SPLIT_BILLS} invert>
        <div data-testid="inverted">Mostrar si deshabilitado</div>
      </CapabilityGate>
    );
    expect(screen.getByTestId('inverted')).toBeInTheDocument();
  });
});
