import { useTranslation } from 'react-i18next';
import type { ChannelFilter } from '@/hooks/useActiveOrders';

interface ChannelTabsProps {
  activeChannel: ChannelFilter;
  onChannelChange: (channel: ChannelFilter) => void;
  counts?: Partial<Record<ChannelFilter, number>>;
}

const CHANNELS: { value: ChannelFilter; icon: string; labelKey: string }[] = [
  { value: 'all', icon: '📋', labelKey: 'orders.channels.all' },
  { value: 'dine_in', icon: '🍽️', labelKey: 'orders.channels.dine_in' },
  { value: 'delivery', icon: '🚗', labelKey: 'orders.channels.delivery' },
  { value: 'takeout', icon: '🥡', labelKey: 'orders.channels.takeout' },
  { value: 'uber_eats', icon: '🛵', labelKey: 'orders.channels.uber_eats' },
  { value: 'rappi', icon: '📱', labelKey: 'orders.channels.rappi' },
];

export function ChannelTabs({ activeChannel, onChannelChange, counts }: ChannelTabsProps) {
  const { t } = useTranslation();

  return (
    <div className="flex gap-2 overflow-x-auto pb-2">
      {CHANNELS.map((channel) => {
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
