import apiClient from "./apiClient";

/* ─── Tipos ─── */

export interface PriceList {
  id: number;
  uuid: string;
  company_id: number;
  branch_id: number;
  name: string;
  display_name: string;
  channel_type: string | null;
  currency: string;
  is_default: boolean;
  is_active: boolean;
  created_at: string;
  updated_at: string;
  deleted_at: string | null;
}

export interface ProductPrice {
  id: number;
  uuid: string;
  product_id: number;
  price_list_id: number;
  price: string;
  currency: string;
  created_at: string;
  updated_at: string;
  price_list?: PriceList;
}

export interface PriceListCreatePayload {
  name: string;
  display_name?: string;
  channel_type?: string | null;
  currency?: string;
  is_default?: boolean;
  is_active?: boolean;
}

export interface PriceListUpdatePayload extends Partial<PriceListCreatePayload> {}

export interface ProductPriceUpsertPayload {
  prices: Array<{
    price_list_id: string;
    price: number;
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

/* ─── Servicio de listas de precios ─── */

export const priceListService = {
  /**
   * Lista todas las listas de precios.
   */
  async listAll(): Promise<PriceList[]> {
    const response = await apiClient.get<ListResponse<PriceList>>(
      "/catalog/price-lists"
    );
    const data = response.data as any;
    return Array.isArray(data?.data) ? data.data : [];
  },

  /**
   * Crea una nueva lista de precios.
   */
  async create(payload: PriceListCreatePayload): Promise<PriceList> {
    const response = await apiClient.post<SuccessResponse<PriceList>>(
      "/catalog/price-lists",
      payload
    );
    return response.data.data;
  },

  /**
   * Actualiza una lista de precios.
   */
  async update(uuid: string, payload: PriceListUpdatePayload): Promise<PriceList> {
    const response = await apiClient.put<SuccessResponse<PriceList>>(
      `/catalog/price-lists/${uuid}`,
      payload
    );
    return response.data.data;
  },

  /**
   * Elimina una lista de precios.
   */
  async delete(uuid: string): Promise<void> {
    await apiClient.delete(`/catalog/price-lists/${uuid}`);
  },

  /**
   * Obtiene los precios de un producto.
   */
  async getProductPrices(productUuid: string): Promise<ProductPrice[]> {
    const response = await apiClient.get<ListResponse<ProductPrice>>(
      `/catalog/products/${productUuid}/prices`
    );
    const data = response.data as any;
    return Array.isArray(data?.data) ? data.data : [];
  },

  /**
   * Actualiza masivamente los precios de un producto.
   */
  async upsertProductPrices(
    productUuid: string,
    payload: ProductPriceUpsertPayload
  ): Promise<ProductPrice[]> {
    const response = await apiClient.put<SuccessResponse<ProductPrice[]>>(
      `/catalog/products/${productUuid}/prices`,
      payload
    );
    return response.data.data;
  },

  /**
   * Elimina un precio específico de un producto.
   */
  async deleteProductPrice(
    productUuid: string,
    priceListUuid: string
  ): Promise<void> {
    await apiClient.delete(
      `/catalog/products/${productUuid}/prices/${priceListUuid}`
    );
  },
};
