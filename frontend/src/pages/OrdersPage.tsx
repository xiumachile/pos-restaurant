import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router-dom';
import { useActiveOrders, type ChannelFilter } from '@/hooks/useActiveOrders';
import { ChannelTabs } from '@/components/orders/ChannelTabs';
import { OrderCard } from '@/components/orders/OrderCard';
import { Loader2, Package } from 'lucide-react';
import type { Order } from '@/types/orders';

export function OrdersPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const [activeChannel, setActiveChannel] = useState<ChannelFilter>('all');
  const { data: orders = [], isLoading } = useActiveOrders(activeChannel);

  const handleAddItems = (order: Order) => {
    if (order.table) {
      navigate(`/tables/${order.table.uuid}`);
    } else {
      navigate(`/orders/takeaway/${order.uuid}`);
    }
  };

  const handleViewDetails = (order: Order) => {
    // TODO: Implementar modal de detalles del pedido
    console.log('View order details:', order.uuid);
  };

  return (
    <div className="flex flex-col h-full gap-4 p-4 md:p-6">
      {/* Header */}
      <div>
        <h1 className="text-2xl font-bold text-white mb-1">{t('orders.active_orders', 'Pedidos Activos')}</h1>
        <p className="text-sm text-slate-400">{t('orders.active_orders_desc', 'Gestiona pedidos en curso por canal')}</p>
      </div>

      {/* Channel Tabs */}
      <ChannelTabs
        activeChannel={activeChannel}
        onChannelChange={setActiveChannel}
      />

      {/* Orders Grid */}
      <div className="flex-1 overflow-y-auto">
        {isLoading ? (
          <div className="flex items-center justify-center py-12">
            <Loader2 className="animate-spin text-orange-500" size={32} />
          </div>
        ) : orders.length === 0 ? (
          <div className="flex items-center justify-center h-full text-slate-400 bg-slate-800/30 rounded-xl border border-slate-700/50">
            <div className="text-center py-12">
              <Package size={48} className="mx-auto mb-3 opacity-30" />
              <p>{t('orders.no_active_orders', 'No hay pedidos activos')}</p>
            </div>
          </div>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            {orders.map((order) => (
              <OrderCard
                key={order.uuid}
                order={order}
                onAddItems={handleAddItems}
                onViewDetails={handleViewDetails}
              />
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
