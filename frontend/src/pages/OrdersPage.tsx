import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router-dom';
import { useActiveOrders, type ChannelFilter } from '@/hooks/useActiveOrders';
import { ChannelTabs } from '@/components/orders/ChannelTabs';
import { OrderCard } from '@/components/orders/OrderCard';
import { OrderDetailsModal } from '@/components/orders/OrderDetailsModal';
import { Loader2, Package } from 'lucide-react';
import type { Order } from '@/types/orders';
import { useCartStore } from '@/stores/useCartStore';
import { useProducts } from '@/hooks/useCatalog';

export function OrdersPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const [activeChannel, setActiveChannel] = useState<ChannelFilter>('all');
  const [selectedOrder, setSelectedOrder] = useState<Order | null>(null);
  const { data: orders = [], isLoading } = useActiveOrders(activeChannel);
  const { data: catalog = [], isLoading: isLoadingCatalog } = useProducts({});

  const handleAddItems = async (order: Order) => {
    console.log("[OrdersPage] 🔍 handleAddItems llamado");
    console.log("[OrdersPage] 📋 Order:", order.order_number, order.uuid);
    console.log("[OrdersPage] 🏷️ Canal:", order.fulfillment_channel);
    console.log("[OrdersPage] 📦 Items del pedido:", order.items?.length || 0);
    
    const cartStore = useCartStore.getState();
    
    if (order.table) {
      // Pedido con mesa: navegar a vista de mesa
      navigate(`/tables/${order.table.uuid}`);
    } else {
      // Pedido sin mesa: crear NUEVO cart del mismo canal
      // IMPORTANTE: usar order.type (OrderType: takeout/delivery) NO order.fulfillment_channel
      // order.fulfillment_channel es pickup/onsite/delivery (FulfillmentChannel del backend)
      // order.type es takeout/delivery (OrderType, lo que necesita el cart)
      const orderChannel = order.type as 'delivery' | 'takeout';
      console.log("[OrdersPage] 🏷️ Tipo del pedido:", order.type);
      console.log("[OrdersPage] 📦 Canal del cart:", orderChannel);
      
      const cartKey = cartStore.initOrder({
        tableUuid: null,
        channel: orderChannel,
        editingOrderId: order.uuid,
      });
      
      // PRECARGAR items del pedido existente en el nuevo cart
      if (order.items && order.items.length > 0 && catalog.length > 0) {
        console.log(`[OrdersPage] 📥 Precargando ${order.items.length} items del pedido ${order.order_number}`);
        
        for (const item of order.items) {
          console.log("[OrdersPage] 🔎 Item completo:", JSON.stringify(item, null, 2));
          
          // Buscar el producto en el catálogo con cascada de fallbacks:
          // 1. product_uuid (más confiable, viene del Product)
          // 2. menu_item_uuid (fallback, si hay MenuItem asociado)
          // 3. nombre del producto (último recurso, búsqueda por texto)
          let product = null;
          let matchMethod = '';
          
          if (item.product_uuid) {
            product = catalog.find((p: any) => p.uuid === item.product_uuid);
            if (product) matchMethod = 'product_uuid';
          }
          
          if (!product && item.menu_item_uuid) {
            product = catalog.find((p: any) => p.uuid === item.menu_item_uuid);
            if (product) matchMethod = 'menu_item_uuid';
          }
          
          if (!product) {
            // Fallback por nombre (coincidencia exacta)
            product = catalog.find((p: any) => {
              const translatedName = (p.name_translations?.es || p.name_translations?.en || '').toLowerCase();
              return translatedName === item.name.toLowerCase();
            });
            if (product) matchMethod = 'nombre';
          }
          
          if (product) {
            // Agregar al cart con la cantidad original
            cartStore.addItem(cartKey, product, item.quantity);
            console.log(`[OrdersPage] ✅ Item precargado (${matchMethod}): ${item.name} x${item.quantity}`);
          } else {
            console.warn(`[OrdersPage] ⚠️ Producto no encontrado en catálogo:`, {
              product_uuid: item.product_uuid,
              menu_item_uuid: item.menu_item_uuid,
              name: item.name
            });
          }
        }
      }
      
      navigate(`/orders/takeaway/${cartKey}`);
    }
  };

  const handleViewDetails = (order: Order) => {
    setSelectedOrder(order);
  };

  return (
    <div className="flex flex-col h-full gap-4 p-4 md:p-6">
      <div>
        <h1 className="text-2xl font-bold text-white mb-1">{t('orders.active_orders', 'Pedidos Activos')}</h1>
        <p className="text-sm text-slate-400">{t('orders.active_orders_desc', 'Gestiona pedidos en curso por canal')}</p>
      </div>

      <ChannelTabs
        activeChannel={activeChannel}
        onChannelChange={setActiveChannel}
      />

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

      <OrderDetailsModal
        order={selectedOrder}
        isOpen={!!selectedOrder}
        onClose={() => setSelectedOrder(null)}
      />
    </div>
  );
}
