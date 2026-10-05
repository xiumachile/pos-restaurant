import { create } from "zustand";

/**
 * Canales de venta soportados.
 * Debe coincidir con MenuActivation::CHANNEL_* del backend.
 */
export type ChannelType = "dine_in" | "delivery" | "takeout" | "uber_eats" | "rappi";

export const CHANNEL_LABELS: Record<ChannelType, { icon: string }> = {
  dine_in: { icon: "🍽️" },
  delivery: { icon: "🚗" },
  takeout: { icon: "🥡" },
  uber_eats: { icon: "🛵" },
  rappi: { icon: "📱" },
};

interface ActiveChannelState {
  channel: ChannelType;
  setChannel: (channel: ChannelType) => void;
}

/**
 * DEPRECADO (Fase 2): Este store ya no es la fuente de verdad del canal.
 * 
 * Desde Fase 2, el canal vive como atributo del pedido en useCartStore
 * (campo `channel` de TableCart), garantizando que cada pedido tenga su
 * propio canal sin contaminación cruzada.
 * 
 * Este store se mantiene únicamente para:
 *   - Exportar el tipo ChannelType y CHANNEL_LABELS (usados por varios componentes)
 *   - Exportar la función channelToOrderType (mapper para el backend)
 *   - Ser fallback en componentes legacy que aún no han sido migrados
 * 
 * setChannel() ya no persiste: es un setter en memoria que se resetea
 * al refrescar la página. Para iniciar un pedido con canal específico,
 * usar useCartStore.initOrder({ tableUuid, channel }).
 */
export const useActiveChannelStore = create<ActiveChannelState>((set) => ({
  channel: "dine_in",
  setChannel: (channel) => {
    set({ channel });
  },
}));

/**
 * Convierte un ChannelType (canal de venta/menú) al OrderType que espera
 * el backend al crear una orden.
 * 
 * NOTA: Backend espera "takeout" (sin guion) en CreateOrderRequest.
 * Este mapper asegura que el frontend envíe el valor correcto.
 */
export function channelToOrderType(channel: ChannelType): "dine_in" | "takeout" | "delivery" {
  switch (channel) {
    case "dine_in":
      return "dine_in";
    case "delivery":
      return "delivery";
    case "takeout":
      return "takeout";
    case "uber_eats":
      return "delivery"; // Uber Eats es un servicio de delivery
    case "rappi":
      return "delivery"; // Rappi es un servicio de delivery
    default:
      return "delivery";
  }
}
