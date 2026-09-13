import { localDb } from "@/db/localDb";
import { getCashierContextSafe } from "./authContext";
import type { Category, Product } from "@/types/catalog";

interface LocalCategoryRow {
  uuid: string;
  backend_id: number | null;
  name_translations: string;
  sort_order: number;
  is_active: number | boolean;
  last_updated: string;
  company_id: string | null;
  branch_id: string | null;
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
  company_id: string | null;
  branch_id: string | null;
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
    hash |= 0;
  }
  return Math.abs(hash);
}

/**
 * Servicio de catálogo local (productos y categorías).
 * 
 * ADR-012: TODAS las operaciones filtran por company_id + branch_id
 * para garantizar aislamiento multi-tenant local.
 */
export const localCatalogService = {
  /**
   * Obtiene el contexto actual o null si no hay usuario autenticado.
   */
  _getContext() {
    return getCashierContextSafe();
  },

  async listCategories(): Promise<Category[]> {
    const ctx = this._getContext();
    if (!ctx) {
      console.warn("[localCatalogService] ⚠️ Sin contexto, retornando categorías vacías");
      return [];
    }

    console.log(`[localCatalogService] 📋 listCategories() tenant: ${ctx.company_id}/${ctx.branch_id}`);
    const db = await localDb.getConnection();

    const rows = await db.select<LocalCategoryRow[]>(`
      SELECT uuid, backend_id, name_translations, sort_order, is_active, last_updated, company_id, branch_id
      FROM local_categories
      WHERE is_active = 1 AND company_id = ? AND branch_id = ?
      ORDER BY sort_order ASC, uuid ASC
    `, [ctx.company_id, ctx.branch_id]);

    console.log(`[localCatalogService] ✅ ${rows.length} categorías encontradas`);
    return rows.map((row) => this.toCategory(row));
  },

  /**
   * Lista productos desde SQLite con filtros opcionales.
   * 
   * ADR-012: Filtrado por tenant obligatorio.
   */
  async listProducts(filters?: {
    categoryId?: number;
    search?: string;
  }): Promise<Product[]> {
    const ctx = this._getContext();
    if (!ctx) {
      console.warn("[localCatalogService] ⚠️ Sin contexto, retornando productos vacíos");
      return [];
    }

    console.log(`[localCatalogService] 📋 listProducts(filters: ${JSON.stringify(filters)}) tenant: ${ctx.company_id}/${ctx.branch_id}`);
    const db = await localDb.getConnection();

    let query = `
      SELECT uuid, category_id, sku, name_translations, description_translations,
             base_price, tax_rate, is_combo, kitchen_zone_id, is_active, last_updated,
             company_id, branch_id
      FROM local_products
      WHERE is_active = 1 AND company_id = ? AND branch_id = ?
    `;
    const params: any[] = [ctx.company_id, ctx.branch_id];

    // Filtro por categoría: comparar category_id (string) con backend_id (number)
    if (filters?.categoryId) {
      query += ` AND CAST(category_id AS INTEGER) = ?`;
      params.push(filters.categoryId);
    }

    query += ` ORDER BY base_price ASC, uuid ASC`;

    const rows = await db.select<LocalProductRow[]>(query, params);
    console.log(`[localCatalogService] 📦 ${rows.length} productos en resultado`);

    let products = rows.map((row) => this.toProduct(row));

    // Filtro por búsqueda en memoria
    if (filters?.search && filters.search.trim()) {
      const search = filters.search.trim().toLowerCase();
      products = products.filter((p) => {
        const name = (p.name_translations?.es || p.name_translations?.en || "").toLowerCase();
        const sku = (p.sku || "").toLowerCase();
        return name.includes(search) || sku.includes(search);
      });
      console.log(`[localCatalogService] 🔍 Búsqueda "${filters.search}" → ${products.length} productos`);
    }

    console.log(`[localCatalogService] ✅ Retornando ${products.length} productos`);
    return products;
  },

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
      id: row.backend_id || 0,
      uuid: row.uuid,
      company_id: row.company_id || "unknown",
      branch_id: row.branch_id || "unknown",
      name_translations: nameTranslations,
      sort_order: row.sort_order || 0,
      is_active: Boolean(row.is_active),
      created_at: row.last_updated,
      updated_at: row.last_updated,
      deleted_at: null,
      tax_id: null,
    } as unknown as Category;
  },

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
      company_id: row.company_id || "unknown",
      branch_id: row.branch_id || "unknown",
      category_id: row.category_id ? parseInt(row.category_id, 10) : 0,
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
