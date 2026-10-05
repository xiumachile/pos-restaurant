import { useTranslation } from 'react-i18next';
import { Clock, Users, Plus, Eye } from 'lucide-react';
import type { Order } from '@/types/orders';
import { formatPrice } from '@/types/catalog';

interface OrderCardProps {
  order: Order;
  onViewDetails?: (order: Order) => void;
  onAddItems?: (order: Order) => void;
}

const CHANNEL_ICONS: Record<string, string> = {
  dine_in: '🍽️',
  delivery: '🚗',
  takeout: '🥡',
  uber_eats: '🛵',
  rappi: '📱',
};

const STATUS_LABELS: Record<string, { labelKey: string; color: string }> = {
  draft: { labelKey: 'orders.status.draft', color: 'bg-slate-700 text-slate-300' },
  confirmed: { labelKey: 'orders.status.confirmed', color: 'bg-blue-900/40 text-blue-300' },
  preparing: { labelKey: 'orders.status.preparing', color: 'bg-yellow-900/40 text-yellow-300' },
  ready: { labelKey: 'orders.status.ready', color: 'bg-green-900/40 text-green-300' },
  ready_for_pickup: { labelKey: 'orders.status.ready_for_pickup', color: 'bg-green-900/40 text-green-300' },
  picked_up: { labelKey: 'orders.status.picked_up', color: 'bg-purple-900/40 text-purple-300' },
  dispatched: { labelKey: 'orders.status.dispatched', color: 'bg-purple-900/40 text-purple-300' },
  served: { labelKey: 'orders.status.served', color: 'bg-orange-900/40 text-orange-300' },
};

export function OrderCard({ order, onViewDetails, onAddItems }: OrderCardProps) {
  const { t } = useTranslation();

  const channelIcon = CHANNEL_ICONS[order.fulfillment_channel] || '📦';
  const statusInfo = STATUS_LABELS[order.status] || STATUS_LABELS.draft;

  const getElapsedTime = () => {
    const minutes = Math.floor((Date.now() - new Date(order.created_at).getTime()) / 60000);
    if (minutes < 1) return '< 1 min';
    if (minutes < 60) return `${minutes} min`;
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;
    return `${hours}h ${mins}m`;
  };

  return (
    <div className="bg-slate-800 rounded-lg p-4 border-2 border-slate-700 hover:border-orange-500/60 transition-all">
      {/* Header */}
      <div className="flex items-start justify-between mb-3">
        <div className="flex items-center gap-2">
          <span className="text-2xl">{channelIcon}</span>
          <div>
            <h3 className="font-bold text-white text-lg">{order.order_number}</h3>
            <p className="text-xs text-slate-400">
              {order.table ? `Mesa ${order.table.table_number}` : order.customer_name || t('orders.no_customer', 'Sin cliente')}
            </p>
          </div>
        </div>
        <span className={`px-2 py-1 rounded-full text-xs font-bold ${statusInfo.color}`}>
          {t(statusInfo.labelKey, order.status)}
        </span>
      </div>

      {/* Meta info */}
      <div className="flex items-center gap-3 text-xs text-slate-400 mb-3">
        <span className="flex items-center gap-1">
          <Clock size={12} />
          {getElapsedTime()}
        </span>
        {order.waiter && (
          <span className="flex items-center gap-1">
            <Users size={12} />
            {order.waiter.name}
          </span>
        )}
        <span>{order.items.length} items</span>
      </div>

      {/* Total */}
      <div className="flex items-center justify-between pt-3 border-t border-slate-700">
        <span className="text-sm text-slate-400">{t('orders.total', 'Total')}:</span>
        <span className="text-xl font-bold text-orange-400">{formatPrice(order.total)}</span>
      </div>

      {/* Actions */}
      {(onViewDetails || onAddItems) && (
        <div className="flex gap-2 mt-3">
          {onAddItems && (
            <button
              onClick={() => onAddItems(order)}
              className="flex-1 flex items-center justify-center gap-2 px-3 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg font-medium transition-colors"
            >
              <Plus size={16} />
              {t('orders.add_items', 'Agregar')}
            </button>
          )}
          {onViewDetails && (
            <button
              onClick={() => onViewDetails(order)}
              className="flex-1 flex items-center justify-center gap-2 px-3 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg font-medium transition-colors"
            >
              <Eye size={16} />
              {t('orders.view_details', 'Ver')}
            </button>
          )}
        </div>
      )}
    </div>
  );
}
