import { X } from "lucide-react";
import { useTranslation } from "react-i18next";
import type { ChannelType } from "@/stores/useActiveChannelStore";

/**
 * Modal de selección de canal para pedidos SIN MESA.
 *
 * DOMINIO:
 *   - CON MESA (dine_in): Se inicia desde el plano de mesas (click en mesa)
 *   - SIN MESA: Se selecciona canal aquí
 *
 * Canales disponibles (sin dine_in):
 *   🚗 Delivery
 *   🥡 Takeout (Para Llevar)
 *   🛵 Uber Eats
 *   📱 Rappi
 *
 * NO incluye dine_in porque ese canal requiere mesa física
 * y se inicia desde el plano de mesas.
 */

interface ChannelOption {
  channel: ChannelType;
  icon: string;
  labelKey: string;
  defaultLabel: string;
  description: string;
}

// Canales SIN MESA (dine_in se excluye intencionalmente)
const CHANNEL_OPTIONS: ChannelOption[] = [
  {
    channel: "delivery",
    icon: "🚗",
    labelKey: "orders.delivery",
    defaultLabel: "Delivery",
    description: "Entrega a domicilio",
  },
  {
    channel: "takeout",
    icon: "🥡",
    labelKey: "orders.takeout",
    defaultLabel: "Para Llevar",
    description: "Retiro en tienda",
  },
  {
    channel: "uber_eats",
    icon: "🛵",
    labelKey: "orders.uber_eats",
    defaultLabel: "Uber Eats",
    description: "Pedido vía Uber Eats",
  },
  {
    channel: "rappi",
    icon: "📱",
    labelKey: "orders.rappi",
    defaultLabel: "Rappi",
    description: "Pedido vía Rappi",
  },
];

interface ChannelSelectionModalProps {
  isOpen: boolean;
  onSelect: (channel: ChannelType) => void;
  onClose: () => void;
}

export function ChannelSelectionModal({ isOpen, onSelect, onClose }: ChannelSelectionModalProps) {
  const { t } = useTranslation();

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm">
      <div className="bg-white dark:bg-slate-800 rounded-xl shadow-2xl max-w-lg w-full mx-4">
        {/* Header */}
        <div className="flex items-center justify-between p-5 border-b border-gray-200 dark:border-slate-700">
          <div>
            <h2 className="text-xl font-bold text-gray-900 dark:text-white">
              {t("orders.select_channel", "Seleccionar Canal")}
            </h2>
            <p className="text-sm text-gray-500 dark:text-slate-400 mt-1">
              {t("orders.new_order_without_table", "Nuevo pedido sin mesa")}
            </p>
          </div>
          <button
            onClick={onClose}
            className="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700 rounded-lg transition-colors"
            title={t("common.close", "Cerrar")}
          >
            <X size={20} />
          </button>
        </div>

        {/* Grid de canales */}
        <div className="p-5 grid grid-cols-2 gap-3">
          {CHANNEL_OPTIONS.map((option) => (
            <button
              key={option.channel}
              onClick={() => onSelect(option.channel)}
              className="flex flex-col items-center gap-2 p-6 rounded-xl border-2 border-gray-200 dark:border-slate-600 hover:border-orange-500 dark:hover:border-orange-500 hover:bg-orange-50 dark:hover:bg-orange-900/20 transition-all duration-200 group"
            >
              <span className="text-4xl group-hover:scale-110 transition-transform">
                {option.icon}
              </span>
              <span className="font-semibold text-gray-900 dark:text-white">
                {t(option.labelKey, option.defaultLabel)}
              </span>
              <span className="text-xs text-gray-500 dark:text-slate-400 text-center">
                {option.description}
              </span>
            </button>
          ))}
        </div>

        {/* Footer */}
        <div className="p-4 border-t border-gray-200 dark:border-slate-700">
          <p className="text-xs text-gray-500 dark:text-slate-400 text-center">
            💡 {t("orders.dine_in_hint", "Para pedidos en mesa, selecciona una mesa desde el plano")}
          </p>
        </div>
      </div>
    </div>
  );
}
