import { useTranslation } from "react-i18next";
import { useActiveChannelStore, CHANNEL_LABELS, type ChannelType } from "@/stores/useActiveChannelStore";

/**
 * Selector visual del canal de venta activo.
 * Se usa en OrderCatalogPanel para que el garzón elija el tipo de pedido.
 * 100% bilingüe: todos los textos usan t().
 */
export function ChannelSelector() {
  const { t } = useTranslation();
  const channel = useActiveChannelStore((s) => s.channel);
  const setChannel = useActiveChannelStore((s) => s.setChannel);

  // Canales relevantes para toma de pedidos (excluye uber_eats/rappi que son externos)
  const channels: ChannelType[] = ["dine_in", "delivery", "takeout"];

  return (
    <div className="flex gap-1.5 mb-3 items-center">
      <span className="text-xs text-slate-400 flex items-center px-2">
        {t("orders.channel_label")}:
      </span>
      {channels.map((ch) => {
        const { icon } = CHANNEL_LABELS[ch];
        const isActive = channel === ch;
        return (
          <button
            key={ch}
            onClick={() => setChannel(ch)}
            className={`px-3 py-1.5 rounded-lg text-xs font-medium transition-all ${
              isActive
                ? "bg-orange-500 text-white shadow-md"
                : "bg-slate-800 text-slate-300 hover:bg-slate-700"
            }`}
          >
            <span className="mr-1">{icon}</span>
            {t(`orders.channel_${ch}`)}
          </button>
        );
      })}
    </div>
  );
}
