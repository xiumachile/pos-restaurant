/**
 * Categoría tal como la retorna el backend.
 */
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

/**
 * Producto tal como la retorna el backend.
 * Nota: base_price y tax_rate vienen como strings ("6500.00") por el cast decimal:2 de Laravel.
 */
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
}

/**
 * Obtiene el nombre traducido priorizando es-CL → es → zh → primer valor disponible.
 */
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

/**
 * Formatea precio en CLP. Acepta string ("6500.00") o number.
 */
export function formatPrice(amount: string | number): string {
  const num = typeof amount === "string" ? parseFloat(amount) : amount;
  if (isNaN(num)) return "$0";
  return new Intl.NumberFormat("es-CL", {
    style: "currency",
    currency: "CLP",
    minimumFractionDigits: 0,
  }).format(num);
}

/**
 * Convierte el precio string a número.
 */
export function parsePrice(price: string): number {
  return parseFloat(price) || 0;
}
