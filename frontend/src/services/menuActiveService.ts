import apiClient from "./apiClient";
import type { ChannelType } from "@/stores/useActiveChannelStore";

export interface ActiveMenuItem {
  product_uuid: string;
  name: string;
  category_id: number;
  position: number;
  price: number;
}

export interface ActiveMenu {
  uuid: string;
  name: string;
  description?: string | null;
  price_list?: {
    uuid: string;
    name: string;
    display_name: string;
  };
}

export interface ActiveMenuResponse {
  menu: ActiveMenu;
  items: ActiveMenuItem[];
}

export const menuActiveService = {
  /**
   * Obtiene la carta activa para el canal actual del usuario autenticado.
   * Retorna null si no hay carta (el endpoint retorna 404).
   */
  async getActive(channel: ChannelType): Promise<ActiveMenuResponse | null> {
    try {
      const res = await apiClient.get<{
        success: boolean;
        data: ActiveMenuResponse;
      }>("/catalog/menus/active", {
        params: { channel_type: channel },
      });
      return res.data.data;
    } catch (err: any) {
      // 404 = no hay carta activa para este contexto (esperado)
      if (err?.response?.status === 404) {
        return null;
      }
      throw err;
    }
  },
};
