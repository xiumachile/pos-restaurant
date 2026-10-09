import { useTranslation } from 'react-i18next';
import type { ChannelFilter } from '@/hooks/useActiveOrders';

interface ChannelTabsProps {
  activeChannel: ChannelFilter;
  onChannelChange: (channel: ChannelFilter) => void;
  counts?: Partial<Record<ChannelFilter, number>>;
  excludeChannels?: ChannelFilter[]; // Canales a ocultar (ej: ['tables'] en Pedidos Activos)
}

// Canales disponibles:
// - all → Todos los pedidos
// - tables → Mesas con cuenta (dine_in) - solo en Caja
// - delivery → Delivery (incluye uber_eats y rappi mapeados)
// - takeout → Takeout/pickup
const CHANNELS: { value: ChannelFilter; icon: string; labelKey: string }[] = [
  { value: 'all', icon: '📋', labelKey: 'orders.channels.all' },
  { value: 'tables', icon: '🪑', labelKey: 'orders.channels.tables' },
  { value: 'delivery', icon: '🚗', labelKey: 'orders.channels.delivery' },
  { value: 'takeout', icon: '🥡', labelKey: 'orders.channels.takeout' },
];

export function ChannelTabs({ 
  activeChannel, 
  onChannelChange, 
  counts,
  excludeChannels = []
}: ChannelTabsProps) {
  const { t } = useTranslation();

  // Filtrar canales excluidos
  const visibleChannels = CHANNELS.filter(
    (channel) => !excludeChannels.includes(channel.value)
  );

  return (
    <div className="flex gap-2 overflow-x-auto pb-3 mb-4">
      {visibleChannels.map((channel) => {
        const isActive = activeChannel === channel.value;
        const count = counts?.[channel.value];

        return (
          <button
            key={channel.value}
            onClick={() => onChannelChange(channel.value)}
            className={`
              flex items-center gap-2 px-4 py-2 rounded-lg font-medium transition-all whitespace-nowrap
              ${isActive
                ? 'bg-orange-500 text-white shadow-lg'
                : 'bg-slate-800 text-slate-300 hover:bg-slate-700'
              }
            `}
          >
            <span className="text-lg">{channel.icon}</span>
            <span>{t(channel.labelKey, channel.value)}</span>
            {count !== undefined && count > 0 && (
              <span className={`
                px-2 py-0.5 rounded-full text-xs font-bold
                ${isActive ? 'bg-white/20' : 'bg-slate-700'}
              `}>
                {count}
              </span>
            )}
          </button>
        );
      })}
    </div>
  );
}
