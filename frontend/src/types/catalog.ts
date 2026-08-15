export interface Category {
  id: number;
  uuid: string;
  name_translations: Record<string, string>;
  description_translations?: Record<string, string>;
  is_active: boolean;
  sort_order?: number;
}

export interface Product {
  id: number;
  uuid: string;
  sku: string;
  name_translations: Record<string, string>;
  description_translations?: Record<string, string>;
  base_price: number;
  category_id?: number;
  category?: Category;
  is_combo: boolean;
  is_active: boolean;
  image_url?: string;
}

/**
 * Helper para obtener el nombre traducido.
 * Prioriza es-CL, luego zh-CN, luego el primer idioma disponible.
 */
export function getTranslatedName(
  translations: Record<string, string> | undefined,
  locale: string = "es-CL"
): string {
  if (!translations) return "Sin nombre";
  return (
    translations[locale] ||
    translations["es-CL"] ||
    translations["zh-CN"] ||
    Object.values(translations)[0] ||
    "Sin nombre"
  );
}

/**
 * Formatea precio en CLP (pesos chilenos)
 */
export function formatPrice(amount: number): string {
  return new Intl.NumberFormat("es-CL", {
    style: "currency",
    currency: "CLP",
    minimumFractionDigits: 0,
  }).format(amount);
}
