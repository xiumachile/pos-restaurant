export interface Category {
  id: number;
  uuid: string;
  company_id: number;
  branch_id: number;
  name_translations: Record<string, string>;
  sort_order: number;
  is_active: boolean;
  created_at: string;
  updated_at: string;
  deleted_at: string | null;
  tax_id: number | null;
}

export interface Product {
  id: number;
  uuid: string;
  company_id: number;
  branch_id: number;
  category_id: number;
  sku: string;
  name_translations: Record<string, string>;
  description_translations: Record<string, string> | null;
  base_price: string;
  tax_rate: string;
  is_combo: boolean;
  kitchen_zone_id: number | null;
  is_active: boolean;
  created_at: string;
  updated_at: string;
  deleted_at: string | null;
  tax_id: number | null;
  category?: Category;
  /** UUID del MenuItem asociado (requerido por el backend para crear OrderItem) */
  menu_item_uuid?: string | null;
}

export function getTranslatedName(
  translations: Record<string, string> | null | undefined,
  locale: string = "es"
): string {
  if (!translations || typeof translations !== "object") return "Sin nombre";
  return (
    translations[locale] ||
    translations["es-CL"] ||
    translations["es"] ||
    translations["zh"] ||
    translations["zh-CN"] ||
    Object.values(translations)[0] ||
    "Sin nombre"
  );
}

export function formatPrice(amount: string | number): string {
  const num = typeof amount === "string" ? parseFloat(amount) : amount;
  if (isNaN(num)) return "$0";
  return new Intl.NumberFormat("es-CL", {
    style: "currency",
    currency: "CLP",
    minimumFractionDigits: 0,
  }).format(num);
}

export function parsePrice(price: string): number {
  return parseFloat(price) || 0;
}
