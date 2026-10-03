import apiClient from "./apiClient";

/* ─── Tipos ─── */

export type MovementType =
  | "in_purchase"
  | "in_production"
  | "out_consumption"
  | "out_waste"
  | "adjustment";

export interface RawIngredientMovement {
  uuid: string;
  type: MovementType;
  type_label: string;
  quantity_base: number;
  balance_after: number;
  reference_type: string | null;
  reference_id: number | null;
  user_id: number | null;
  reason: string | null;
  created_at: string;
}

export interface MovementFilters {
  type?: MovementType | "all";
  limit?: number;
}

export interface ProductionBatchResponse {
  uuid: string;
  product_uuid: string;
  product_name: string;
  quantity: number;
  movements_count: number;
  batch_notes: string | null;
  created_at: string;
}

export interface CreateProductionBatchPayload {
  product_uuid: string;
  quantity: number;
  batch_notes?: string;
}



/* ─── Labels ─── */

export const MOVEMENT_TYPE_CONFIG: Record<
  MovementType,
  { labelEs: string; color: string; icon: string }
> = {
  in_purchase: {
    labelEs: "Compra",
    color: "bg-emerald-500/20 text-emerald-300 border-emerald-500/30",
    icon: "📥",
  },
  in_production: {
    labelEs: "Producción",
    color: "bg-green-500/20 text-green-300 border-green-500/30",
    icon: "🏭",
  },
  out_consumption: {
    labelEs: "Consumo",
    color: "bg-red-500/20 text-red-300 border-red-500/30",
    icon: "📤",
  },
  out_waste: {
    labelEs: "Merma",
    color: "bg-orange-500/20 text-orange-300 border-orange-500/30",
    icon: "🗑️",
  },
  adjustment: {
    labelEs: "Ajuste",
    color: "bg-yellow-500/20 text-yellow-300 border-yellow-500/30",
    icon: "⚙️",
  },
};

/* ─── Servicio ─── */

export const inventoryService = {
  /**
   * Lista movimientos de un insumo con filtros opcionales.
   */
  async listMovements(
    ingredientUuid: string,
    filters: MovementFilters = {}
  ): Promise<RawIngredientMovement[]> {
    const params = new URLSearchParams();
    if (filters.type && filters.type !== "all") {
      params.set("type", filters.type);
    }
    if (filters.limit) {
      params.set("limit", String(filters.limit));
    }

    const queryString = params.toString();
    const url = `/recipes/ingredients/${ingredientUuid}/movements${
      queryString ? `?${queryString}` : ""
    }`;

    const response = await apiClient.get<{ data: RawIngredientMovement[] }>(url);
    const data = response.data as any;
    return Array.isArray(data?.data) ? data.data : Array.isArray(data) ? data : [];
  },

  /**
   * Registra un lote de producción (descuenta ingredientes de la receta).
   */
  async createProductionBatch(
    payload: CreateProductionBatchPayload
  ): Promise<ProductionBatchResponse> {
    const response = await apiClient.post<{ data: ProductionBatchResponse }>(
      "/recipes/production-batches",
      payload
    );
    const data = response.data as any;
    return data?.data ?? data;
  },

};
