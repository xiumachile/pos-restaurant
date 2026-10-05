import { useTranslation } from "react-i18next";
import { CHANNEL_LABELS, type ChannelType } from "@/stores/useActiveChannelStore";
import { X } from "lucide-react";

interface ChannelSelectionModalProps {
  open: boolean;
  onSelect: (channel: ChannelType) => void;
  onClose: () => void;
}

/**
 * Modal de selección de canal para iniciar un pedido nuevo fuera de mesa.
 * 
 * Se muestra como primer paso del flujo "Pedido nuevo" (delivery/takeout).
 * El canal elegido queda fijado al pedido desde su creación.
 * 
 * NO incluye uber_eats/rappi (son canales externos, no de toma manual).
 */
export function ChannelSelectionModal({ open, onSelect, onClose }: ChannelSelectionModalProps) {
  const { t } = useTranslation();

  if (!open) return null;

  // Solo canales relevantes para toma manual (excluye uber_eats/rappi)

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4">
      <div className="bg-slate-900 border border-slate-700 rounded-xl shadow-2xl max-w-md w-full p-6">
        <div className="flex items-center justify-between mb-6">
          <div>
            <h2 className="text-xl font-bold text-white">
              {t("orders.new_order_title")}
            </h2>
            <p className="text-sm text-slate-400 mt-1">
              {t("orders.select_channel")}
            </p>
          </div>
          <button
            onClick={onClose}
            className="p-2 hover:bg-slate-800 rounded-lg transition-colors"
            aria-label={t("orders.cancel")}
          >
            <X size={20} className="text-slate-400" />
          </button>
        </div>

        <div className="space-y-2">
          {channels.map((ch) => {
            const { icon } = CHANNEL_LABELS[ch];
            return (
              <button
                key={ch}
                onClick={() => onSelect(ch)}
                className="w-full flex items-center gap-4 p-4 bg-slate-800 hover:bg-slate-700 border border-slate-700 hover:border-orange-500/50 rounded-lg transition-all text-left group"
              >
                <span className="text-3xl">{icon}</span>
                <span className="text-lg font-medium text-white group-hover:text-orange-400 transition-colors">
                  {t(`orders.channel_${ch}`)}
                </span>
              </button>
            );
          })}
        </div>

        <button
          onClick={onClose}
          className="w-full mt-4 px-4 py-2 bg-slate-800 hover:bg-slate-700 border border-slate-700 rounded-lg text-slate-300 transition-colors"
        >
          {t("orders.cancel")}
        </button>
      </div>
    </div>
  );
}
