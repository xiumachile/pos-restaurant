import { X, Clock, Users, Package } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import type { Order } from '@/types/orders';
import { formatPrice } from '@/types/catalog';

interface OrderDetailsModalProps {
  order: Order | null;
  isOpen: boolean;
  onClose: () => void;
}

const CHANNEL_ICONS: Record<string, string> = {
  dine_in: '🍽️',
  delivery: '🚗',
  takeout: '🥡',
  uber_eats: '🛵',
  rappi: '📱',
};

export function OrderDetailsModal({ order, isOpen, onClose }: OrderDetailsModalProps) {
  const { t } = useTranslation();

  if (!isOpen || !order) return null;

  const channelIcon = CHANNEL_ICONS[order.fulfillment_channel] || '📦';

  const getElapsedTime = () => {
    const minutes = Math.floor((Date.now() - new Date(order.created_at).getTime()) / 60000);
    if (minutes < 1) return '< 1 min';
    if (minutes < 60) return `${minutes} min`;
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;
    return `${hours}h ${mins}m`;
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
      <div className="bg-white dark:bg-slate-800 rounded-xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-hidden flex flex-col">
        {/* Header */}
        <div className="flex items-center justify-between p-5 border-b border-gray-200 dark:border-slate-700">
          <div className="flex items-center gap-3">
            <span className="text-3xl">{channelIcon}</span>
            <div>
              <h2 className="text-xl font-bold text-gray-900 dark:text-white">
                {order.order_number}
              </h2>
              <p className="text-sm text-gray-500 dark:text-slate-400">
                {order.table ? `Mesa ${order.table.table_number}` : order.customer_name || t('orders.no_customer', 'Sin cliente')}
              </p>
            </div>
          </div>
          <button
            onClick={onClose}
            className="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700 rounded-lg transition-colors"
            title={t('common.close', 'Cerrar')}
          >
            <X size={20} />
          </button>
        </div>

        {/* Content */}
        <div className="flex-1 overflow-y-auto p-5 space-y-4">
          {/* Meta info */}
          <div className="flex items-center gap-4 text-sm text-gray-600 dark:text-slate-400">
            <span className="flex items-center gap-1">
              <Clock size={14} />
              {getElapsedTime()}
            </span>
            {order.waiter && (
              <span className="flex items-center gap-1">
                <Users size={14} />
                {order.waiter.name}
              </span>
            )}
            <span className="flex items-center gap-1">
              <Package size={14} />
              {order.items.length} {t('orders.items', 'items')}
            </span>
          </div>

          {/* Items */}
          <div className="space-y-2">
            <h3 className="font-semibold text-gray-900 dark:text-white">
              {t('orders.items_detail', 'Detalle de Items')}
            </h3>
            <div className="space-y-2">
              {order.items.map((item) => (
                <div
                  key={item.uuid}
                  className="flex items-start justify-between p-3 bg-gray-50 dark:bg-slate-700/50 rounded-lg"
                >
                  <div className="flex-1">
                    <p className="font-medium text-gray-900 dark:text-white">
                      {item.quantity}x {item.name}
                    </p>
                    {item.notes && (
                      <p className="text-xs text-gray-500 dark:text-slate-400 mt-1 italic">
                        {item.notes}
                      </p>
                    )}
                  </div>
                  <span className="font-semibold text-gray-900 dark:text-white ml-4">
                    {formatPrice(item.subtotal)}
                  </span>
                </div>
              ))}
            </div>
          </div>

          {/* Notes */}
          {order.notes && (
            <div className="p-3 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg">
              <p className="text-sm font-medium text-yellow-900 dark:text-yellow-200 mb-1">
                {t('orders.notes', 'Notas')}:
              </p>
              <p className="text-sm text-yellow-800 dark:text-yellow-300">
                {order.notes}
              </p>
            </div>
          )}

          {/* Totals */}
          <div className="border-t border-gray-200 dark:border-slate-700 pt-4 space-y-2">
            <div className="flex justify-between text-sm text-gray-600 dark:text-slate-400">
              <span>{t('orders.subtotal', 'Subtotal')}:</span>
              <span>{formatPrice(order.subtotal)}</span>
            </div>
            {order.tax_amount > 0 && (
              <div className="flex justify-between text-sm text-gray-600 dark:text-slate-400">
                <span>{t('orders.tax', 'Impuestos')}:</span>
                <span>{formatPrice(order.tax_amount)}</span>
              </div>
            )}
            {order.discount_amount > 0 && (
              <div className="flex justify-between text-sm text-red-600 dark:text-red-400">
                <span>{t('orders.discount', 'Descuento')}:</span>
                <span>-{formatPrice(order.discount_amount)}</span>
              </div>
            )}
            <div className="flex justify-between text-lg font-bold text-gray-900 dark:text-white pt-2 border-t border-gray-200 dark:border-slate-700">
              <span>{t('orders.total', 'Total')}:</span>
              <span className="text-orange-500">{formatPrice(order.total)}</span>
            </div>
          </div>
        </div>

        {/* Footer */}
        <div className="p-5 border-t border-gray-200 dark:border-slate-700">
          <button
            onClick={onClose}
            className="w-full px-4 py-2 bg-gray-200 dark:bg-slate-700 hover:bg-gray-300 dark:hover:bg-slate-600 text-gray-900 dark:text-white rounded-lg font-medium transition-colors"
          >
            {t('common.close', 'Cerrar')}
          </button>
        </div>
      </div>
    </div>
  );
}
