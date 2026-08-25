import apiClient from "./apiClient";
import type { PriceList } from "./priceListService";

/* ─── Tipos ─── */

export interface Menu {
  id: number;
  uuid: string;
  company_id: number;
  branch_id: number;
  name: string;
  description: string | null;
  price_list_id: number;
  is_default: boolean;
  is_active: boolean;
  created_at: string;
  updated_at: string;
  deleted_at: string | null;
  price_list?: PriceList;
}

export interface MenuActivation {
  id: number;
  uuid: string;
  menu_id: number;
  channel_type: string;
  days_of_week: number[] | null;
  time_from: string | null;
  time_to: string | null;
  priority: number;
  is_active: boolean;
}

export interface MenuCreatePayload {
  name: string;
  description?: string | null;
  price_list_id: string;
  is_default?: boolean;
  is_active?: boolean;
}

export interface MenuUpdatePayload extends Partial<MenuCreatePayload> {}

export interface MenuActivationPayload {
  channel_type: string;
  days_of_week?: number[] | null;
  time_from?: string | null;
  time_to?: string | null;
  priority?: number;
  is_active?: boolean;
}

export interface MenuProductAssignPayload {
  products: Array<{
    product_uuid: string;
    position?: number;
    is_available?: boolean;
  }>;
}

/* ─── Respuestas del backend ─── */

interface SuccessResponse<T> {
  success: boolean;
  data: T;
}

interface ListResponse<T> {
  success: boolean;
  data: T[];
}

/* ─── Servicio de administración de menús ─── */

export const menuAdminService = {
  /**
   * Lista todos los menús.
   */
  async listAll(): Promise<Menu[]> {
    const response = await apiClient.get<ListResponse<Menu>>(
      "/catalog/menus"
    );
    const data = response.data as any;
    return Array.isArray(data?.data) ? data.data : [];
  },

  /**
   * Obtiene detalle de un menú con productos.
   */
  async show(uuid: string): Promise<{ menu: Menu; items: any[] }> {
    const response = await apiClient.get<SuccessResponse<{ menu: Menu; items: any[] }>>(
      `/catalog/menus/${uuid}`
    );
    return response.data.data;
  },

  /**
   * Crea un nuevo menú.
   */
  async create(payload: MenuCreatePayload): Promise<Menu> {
    const response = await apiClient.post<SuccessResponse<Menu>>(
      "/catalog/menus",
      payload
    );
    return response.data.data;
  },

  /**
   * Actualiza un menú.
   */
  async update(uuid: string, payload: MenuUpdatePayload): Promise<Menu> {
    const response = await apiClient.put<SuccessResponse<Menu>>(
      `/catalog/menus/${uuid}`,
      payload
    );
    return response.data.data;
  },

  /**
   * Elimina un menú.
   */
  async delete(uuid: string): Promise<void> {
    await apiClient.delete(`/catalog/menus/${uuid}`);
  },

  /**
   * Actualiza las reglas de activación de un menú.
   */
  async updateActivations(
    menuUuid: string,
    activations: MenuActivationPayload[]
  ): Promise<MenuActivation[]> {
    const response = await apiClient.put<SuccessResponse<MenuActivation[]>>(
      `/catalog/menus/${menuUuid}/activations`,
      { activations }
    );
    return response.data.data;
  },

  /**
   * Asigna productos a un menú.
   */
  async assignProducts(
    menuUuid: string,
    payload: MenuProductAssignPayload
  ): Promise<any[]> {
    const response = await apiClient.put<SuccessResponse<any[]>>(
      `/catalog/menus/${menuUuid}/products`,
      payload
    );
    return response.data.data;
  },
};
