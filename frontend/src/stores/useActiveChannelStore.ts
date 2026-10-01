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
 * Store global del canal de venta activo en el POS.
 * Persiste en sessionStorage para sobrevivir recargas de página durante la sesión.
 */
export const useActiveChannelStore = create<ActiveChannelState>((set) => ({
  channel: (sessionStorage.getItem("pos_active_channel") as ChannelType) || "dine_in",
  setChannel: (channel) => {
    sessionStorage.setItem("pos_active_channel", channel);
    set({ channel });
  },
}));

/**
 * Convierte un ChannelType (canal de venta/menú) al OrderType que espera
 * el backend al crear una orden.
 * 
 * NOTA: Existe inconsistencia histórica en el código:
 * - ChannelType usa "takeout" (convención MenuActivation del backend)
 * - CreateOrderPayload.order_type usa "take_out" (convención Orders del backend)
 * Este mapper aísla esa inconsistencia en un solo lugar.
 */
export function channelToOrderType(channel: ChannelType): "dine_in" | "take_out" | "delivery" {
  switch (channel) {
    case "dine_in":
      return "dine_in";
    case "delivery":
      return "delivery";
    case "takeout":
      return "take_out";
    default:
      return "delivery";
  }
}
