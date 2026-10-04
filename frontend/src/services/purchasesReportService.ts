import apiClient from "./apiClient";
import { type DateFilter, resolveDateFilter } from "./reportsService";

export interface PurchaseKPIs {
  total_amount: number;
  purchases_count: number;
  average_ticket: number;
  with_document_count: number;
}

export interface DocumentTypeDistribution {
  document_type: string;
  count: number;
  total_amount: number;
}

export interface TopSupplier {
  supplier_name: string;
  supplier_rut: string | null;
  purchases_count: number;
  total_amount: number;
}

export interface TopIngredientPurchase {
  ingredient_name: string;
  ingredient_sku: string;
  base_unit: string;
  purchases_count: number;
  total_quantity: number;
  total_amount: number;
}

export const purchasesReportService = {
  async getKPIs(filter: DateFilter): Promise<PurchaseKPIs> {
    const { from_date, to_date } = resolveDateFilter(filter);
    const response = await apiClient.get<{ data: PurchaseKPIs }>(
      `/reports/purchases/kpis?from_date=${from_date}&to_date=${to_date}`
    );
    return response.data.data;
  },

  async getByDocumentType(filter: DateFilter): Promise<DocumentTypeDistribution[]> {
    const { from_date, to_date } = resolveDateFilter(filter);
    const response = await apiClient.get<{ data: DocumentTypeDistribution[] }>(
      `/reports/purchases/by-document-type?from_date=${from_date}&to_date=${to_date}`
    );
    return response.data.data;
  },

  async getTopSuppliers(filter: DateFilter, limit = 10): Promise<TopSupplier[]> {
    const { from_date, to_date } = resolveDateFilter(filter);
    const response = await apiClient.get<{ data: TopSupplier[] }>(
      `/reports/purchases/top-suppliers?from_date=${from_date}&to_date=${to_date}&limit=${limit}`
    );
    return response.data.data;
  },

  async getTopIngredients(filter: DateFilter, limit = 10): Promise<TopIngredientPurchase[]> {
    const { from_date, to_date } = resolveDateFilter(filter);
    const response = await apiClient.get<{ data: TopIngredientPurchase[] }>(
      `/reports/purchases/top-ingredients?from_date=${from_date}&to_date=${to_date}&limit=${limit}`
    );
    return response.data.data;
  },
};
