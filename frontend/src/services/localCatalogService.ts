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
  category_id: string | null;  // ← ES EL UUID DE LA CATEGORÍA (no número)
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
 * Debe coincidir con el hash usado en useCatalog.ts
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

export const localCatalogService = {
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
   * IMPORTANTE: category_id en local_products es un UUID (string),
   * pero el filtro viene como number (id numérico del backend).
   * Por lo tanto, primero buscamos la categoría cuyo hash(uuid) === categoryId,
   * y luego filtramos productos por el UUID real.
   */
  async listProducts(filters?: {
    categoryId?: number;
    search?: string;
  }): Promise<Product[]> {
    console.log(`[localCatalogService] 📋 listProducts(filters: ${JSON.stringify(filters)})`);
    const db = await localDb.getConnection();

    // Traer TODOS los productos activos (el filtrado se hace en memoria)
    const query = `
      SELECT uuid, category_id, sku, name_translations, description_translations,
             base_price, tax_rate, is_combo, kitchen_zone_id, is_active, last_updated
      FROM local_products
      WHERE is_active = 1
    `;

    const rows = await db.select<LocalProductRow[]>(query, []);
    console.log(`[localCatalogService] 📦 ${rows.length} productos totales en SQLite`);

    let products = rows.map((row) => this.toProduct(row));

    // FILTRO POR CATEGORÍA: mapear categoryId (number) → UUID de categoría
    if (filters?.categoryId) {
      // Obtener todas las categorías para hacer el mapeo
      const categories = await this.listCategories();
      const targetCategory = categories.find(c => c.id === filters.categoryId);
      
      if (targetCategory) {
        // Filtrar productos por UUID de la categoría
        products = products.filter(p => {
          const productRow = rows.find(r => r.uuid === p.uuid);
          return productRow?.category_id === targetCategory.uuid;
        });
        console.log(`[localCatalogService] 🎯 Filtro categoryId=${filters.categoryId} → UUID=${targetCategory.uuid} → ${products.length} productos`);
      } else {
        console.log(`[localCatalogService] ⚠️ Categoría ${filters.categoryId} no encontrada`);
        products = [];
      }
    }

    // FILTRO POR BÚSQUEDA
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
      category_id: 0,  // Se pierde aquí, pero usamos category_uuid para filtrar
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
