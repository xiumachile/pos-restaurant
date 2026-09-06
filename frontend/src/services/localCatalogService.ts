import { localDb } from "@/db/localDb";
import type { Category, Product } from "@/types/catalog";

interface LocalCategoryRow {
  uuid: string;
  name_translations: string;
  sort_order: number;
  is_active: number | boolean;
  last_updated: string;
}

interface LocalProductRow {
  uuid: string;
  category_id: string | null;
  sku: string | null;
  name_translations: string;
  description_translations?: string | null;
  base_price: number;
  tax_rate: number;
  is_combo: number | boolean;
  kitchen_zone_id?: string | null;
  is_active: number | boolean;
  last_updated: string;
}

/**
 * Hash determinístico de string a número positivo.
 * Usado para generar IDs numéricos estables a partir de UUIDs.
 */
function hashStringToNumber(str: string): number {
  let hash = 0;
  for (let i = 0; i < str.length; i++) {
    const char = str.charCodeAt(i);
    hash = ((hash << 5) - hash) + char;
    hash |= 0; // Convert to 32bit integer
  }
  return Math.abs(hash);
}

/**
 * Servicio local para catálogo (offline-first).
 * 
 * Lee directamente de SQLite las tablas local_categories y local_products
 * que PullEngine mantiene sincronizadas con el backend.
 */
export const localCatalogService = {
  /**
   * Lista categorías activas desde SQLite.
   */
  async listCategories(): Promise<Category[]> {
    console.log("[localCatalogService] 📋 listCategories() desde SQLite");

    const db = await localDb.getConnection();

    const rows = await db.select<LocalCategoryRow[]>(`
      SELECT uuid, name_translations, sort_order, is_active, last_updated
      FROM local_categories
      WHERE is_active = 1
      ORDER BY sort_order ASC, uuid ASC
    `);

    console.log(`[localCatalogService] ✅ ${rows.length} categorías encontradas`);

    return rows.map((row) => this.toCategory(row));
  },

  /**
   * Lista productos desde SQLite con filtros opcionales.
   * 
   * NOTA: local_products NO tiene sort_order (schema actual),
   * por lo que ordenamos por uuid. El orden se mantiene consistente.
   */
  async listProducts(filters?: {
    categoryId?: number;
    search?: string;
  }): Promise<Product[]> {
    console.log(`[localCatalogService] 📋 listProducts(filters: ${JSON.stringify(filters)})`);

    const db = await localDb.getConnection();

    // IMPORTANTE: local_products schema es:
    // uuid, category_id, sku, name_translations, description_translations,
    // base_price, tax_rate, is_combo, kitchen_zone_id, is_active, last_updated
    // NO tiene sort_order (diferencia con local_categories)
    let query = `
      SELECT uuid, category_id, sku, name_translations, description_translations,
             base_price, tax_rate, is_combo, kitchen_zone_id, is_active, last_updated
      FROM local_products
      WHERE is_active = 1
    `;
    const params: any[] = [];

    if (filters?.categoryId) {
      query += ` AND (category_id = ? OR category_id = CAST(? AS TEXT))`;
      params.push(filters.categoryId, String(filters.categoryId));
    }

    query += ` ORDER BY name_translations ASC, uuid ASC`;

    const rows = await db.select<LocalProductRow[]>(query, params);

    let products = rows.map((row) => this.toProduct(row));

    // Filtro de búsqueda en memoria
    if (filters?.search && filters.search.trim()) {
      const search = filters.search.trim().toLowerCase();
      products = products.filter((p) => {
        const name = (p.name_translations?.es || p.name_translations?.en || "").toLowerCase();
        const sku = (p.sku || "").toLowerCase();
        return name.includes(search) || sku.includes(search);
      });
    }

    console.log(`[localCatalogService] ✅ ${products.length} productos encontrados`);
    return products;
  },

  /**
   * Convierte una fila de local_categories al tipo Category.
   */
  toCategory(row: LocalCategoryRow): Category {
    let nameTranslations: Record<string, string> = {};
    try {
      nameTranslations = typeof row.name_translations === "string"
        ? JSON.parse(row.name_translations)
        : row.name_translations || {};
    } catch {
      nameTranslations = { es: row.name_translations || "Sin nombre" };
    }

    return {
      id: hashStringToNumber(row.uuid),
      uuid: row.uuid,
      company_id: 0,
      branch_id: 0,
      name_translations: nameTranslations,
      sort_order: row.sort_order || 0,
      is_active: Boolean(row.is_active),
      created_at: row.last_updated,
      updated_at: row.last_updated,
      deleted_at: null,
      tax_id: null,
    } as unknown as Category;
  },

  /**
   * Convierte una fila de local_products al tipo Product.
   * Usa hash determinístico del UUID para generar id numérico único.
   */
  toProduct(row: LocalProductRow): Product {
    let nameTranslations: Record<string, string> = {};
    let descriptionTranslations: Record<string, string> | null = null;

    try {
      nameTranslations = typeof row.name_translations === "string"
        ? JSON.parse(row.name_translations)
        : row.name_translations || {};
    } catch {
      nameTranslations = { es: row.name_translations || "Sin nombre" };
    }

    try {
      descriptionTranslations = row.description_translations
        ? (typeof row.description_translations === "string"
          ? JSON.parse(row.description_translations)
          : row.description_translations)
        : null;
    } catch {
      descriptionTranslations = null;
    }

    return {
      id: hashStringToNumber(row.uuid),
      uuid: row.uuid,
      company_id: 0,
      branch_id: 0,
      category_id: 0,
      sku: row.sku || "",
      name_translations: nameTranslations,
      description_translations: descriptionTranslations,
      base_price: String(row.base_price || 0),
      tax_rate: String(row.tax_rate || 19),
      is_combo: Boolean(row.is_combo),
      kitchen_zone_id: row.kitchen_zone_id ? parseInt(row.kitchen_zone_id, 10) : null,
      is_active: Boolean(row.is_active),
      created_at: row.last_updated,
      updated_at: row.last_updated,
      deleted_at: null,
      tax_id: null,
      menu_item_uuid: row.uuid,
    } as unknown as Product;
  },
};
