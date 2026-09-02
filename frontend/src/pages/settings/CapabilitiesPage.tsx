import { useCapabilities } from '@/hooks/useCapabilities';
import { useAuthStore } from '@/store/useAuthStore';
import { useCapabilitiesStore } from '@/store/useCapabilitiesStore';
import { CapabilityKey, type CapabilityInfo } from '@/types/capabilities';
import { ArrowLeft, Loader2 } from 'lucide-react';
import { NavLink, useNavigate } from 'react-router-dom';

const CATEGORY_LABELS = {
  operations: '🏪 Operaciones',
  payments: '💳 Pagos',
  marketing: '📣 Marketing',
} as const;

/**
 * Página de administración de capabilities de la empresa.
 * Permite habilitar/deshabilitar features de forma granular.
 */
export function CapabilitiesPage() {
  const user = useAuthStore((state) => state.user);
  const { capabilities, isLoading, error, refetch } = useCapabilities();
  const toggleCapability = useCapabilitiesStore((state) => state.toggleCapability);
  const navigate = useNavigate();

  const companyUuid = user?.company?.uuid ?? '';

  // Agrupar por categoría
  const grouped = Object.values(capabilities).reduce(
    (acc, cap) => {
      if (!acc[cap.category]) acc[cap.category] = [];
      acc[cap.category].push(cap);
      return acc;
    },
    {} as Record<string, CapabilityInfo[]>
  );

  const handleToggle = (key: CapabilityKey) => {
    toggleCapability(companyUuid, key);
  };

  return (
    <div>
      {/* Header */}
      <div className="flex items-center gap-4 mb-8">
        <button
          onClick={() => navigate('/settings')}
          className="p-2 hover:bg-slate-800 rounded-lg transition-colors"
          aria-label="Volver a configuración"
        >
          <ArrowLeft size={20} />
        </button>
        <div>
          <h1 className="text-3xl font-bold">Capacidades de la Empresa</h1>
          <p className="text-slate-400 mt-1">
            Habilita o deshabilita funcionalidades específicas del POS
          </p>
        </div>
      </div>

      {/* Estados */}
      {isLoading && (
        <div className="flex items-center justify-center py-12">
          <Loader2 className="animate-spin text-orange-500" size={32} />
        </div>
      )}

      {error && (
        <div className="bg-red-900/20 border border-red-700 rounded-lg p-4 mb-6">
          <p className="text-red-400 mb-2">Error al cargar capacidades</p>
          <p className="text-sm text-slate-400 mb-3">{error}</p>
          <button
            onClick={refetch}
            className="px-4 py-2 bg-red-700 hover:bg-red-600 rounded text-sm"
          >
            Reintentar
          </button>
        </div>
      )}

      {/* Lista agrupada por categoría */}
      {!isLoading && !error && (
        <div className="space-y-8">
          {Object.entries(grouped).map(([category, caps]) => (
            <div key={category}>
              <h2 className="text-lg font-semibold mb-3 text-slate-200">
                {CATEGORY_LABELS[category as keyof typeof CATEGORY_LABELS]}
              </h2>
              <div className="space-y-2">
                {caps.map((cap) => (
                  <CapabilityRow
                    key={cap.key}
                    capability={cap}
                    onToggle={() => handleToggle(cap.key)}
                  />
                ))}
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

function CapabilityRow({
  capability,
  onToggle,
}: {
  capability: CapabilityInfo;
  onToggle: () => void;
}) {
  return (
    <div
      className={`flex items-center justify-between p-4 rounded-lg border transition-colors ${
        capability.is_enabled
          ? 'bg-slate-800 border-orange-500/30'
          : 'bg-slate-800/50 border-slate-700'
      }`}
    >
      <div className="flex items-center gap-3">
        <span className="text-2xl">{capability.icon}</span>
        <div>
          <div className="font-medium">{capability.description}</div>
          <div className="text-xs text-slate-500 font-mono">
            {capability.key}
          </div>
        </div>
      </div>

      <button
        onClick={onToggle}
        className={`relative w-12 h-6 rounded-full transition-colors ${
          capability.is_enabled ? 'bg-orange-500' : 'bg-slate-600'
        }`}
        aria-label={`${capability.is_enabled ? 'Deshabilitar' : 'Habilitar'} ${capability.description}`}
      >
        <span
          className={`absolute top-1 left-1 w-4 h-4 bg-white rounded-full transition-transform ${
            capability.is_enabled ? 'translate-x-6' : ''
          }`}
        />
      </button>
    </div>
  );
}
