import { localDb } from "@/db/localDb";
import { v4 as uuidv4 } from "uuid";
import { useAuthStore } from "@/store/useAuthStore";

export interface LocalCustomer {
  uuid: string;
  company_id: string;
  branch_id: string;
  phone: string;
  name: string;
  delivery_address: string | null;
  delivery_reference: string | null;
  notes: string | null;
  is_active: number;
  created_at: string;
  updated_at: string;
}

const clean = (v: string | undefined | null): string => (v ?? "").replace(/\s+/g, "").trim();
const trimOrNull = (v: string | undefined | null): string | null => {
  const t = (v ?? "").trim();
  return t.length > 0 ? t : null;
};

export const customerService = {
  /**
   * Busca un cliente por teléfono (coincidencia exacta normalizada).
   */
  async findByPhone(phone: string): Promise<LocalCustomer | null> {
    const target = clean(phone);
    if (!target) return null;

    try {
      const user = useAuthStore.getState().user;
      if (!user) return null;

      const rows = await localDb.select<LocalCustomer>(
        `SELECT * FROM local_customers
         WHERE REPLACE(phone, ' ', '') = ?
           AND branch_id = ?
           AND is_active = 1
         LIMIT 1`,
        [target, String(user.branch_id)]
      );

      return rows.length > 0 ? rows[0] : null;
    } catch (error) {
      console.error("[customerService] Error buscando cliente por teléfono:", error);
      return null;
    }
  },

  /**
   * Búsqueda parcial por nombre.
   */
  async searchByName(name: string): Promise<LocalCustomer[]> {
    if (!name || name.trim().length < 2) return [];

    try {
      const user = useAuthStore.getState().user;
      if (!user) return [];

      return await localDb.select<LocalCustomer>(
        `SELECT * FROM local_customers
         WHERE name LIKE ?
           AND branch_id = ?
           AND is_active = 1
         ORDER BY name
         LIMIT 20`,
        [`%${name.trim()}%`, String(user.branch_id)]
      );
    } catch (error) {
      console.error("[customerService] Error buscando cliente por nombre:", error);
      return [];
    }
  },

  /**
   * Crea un nuevo cliente local.
   */
  async create(data: {
    phone: string;
    name: string;
    delivery_address?: string;
    delivery_reference?: string;
    notes?: string;
  }): Promise<LocalCustomer> {
    const user = useAuthStore.getState().user;
    if (!user) throw new Error("No hay usuario autenticado");

    const companyId = String(user.company_id);
    const branchId = String(user.branch_id);

    if (!companyId || !branchId) {
      throw new Error("Datos de tenant incompletos en la sesión");
    }

    const uuid = uuidv4();
    const now = new Date().toISOString();
    const phone = clean(data.phone);
    const name = (data.name ?? "").trim();

    if (!phone) throw new Error("El teléfono es obligatorio");
    if (!name) throw new Error("El nombre es obligatorio");

    console.log("[customerService.create] Insertando cliente:", {
      uuid,
      company_id: companyId,
      branch_id: branchId,
      phone,
      name,
    });

    try {
      await localDb.execute(
        `INSERT INTO local_customers
         (uuid, company_id, branch_id, phone, name, delivery_address, delivery_reference, notes, is_active, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)`,
        [
          uuid,
          companyId,
          branchId,
          phone,
          name,
          trimOrNull(data.delivery_address),
          trimOrNull(data.delivery_reference),
          trimOrNull(data.notes),
          now,
          now,
        ]
      );
    } catch (dbError: any) {
      console.error("[customerService.create] Error de base de datos:", dbError);
      throw new Error(dbError?.message || "Error al guardar el cliente");
    }

    return {
      uuid,
      company_id: companyId,
      branch_id: branchId,
      phone,
      name,
      delivery_address: trimOrNull(data.delivery_address),
      delivery_reference: trimOrNull(data.delivery_reference),
      notes: trimOrNull(data.notes),
      is_active: 1,
      created_at: now,
      updated_at: now,
    };
  },

  /**
   * Actualiza un cliente existente.
   */
  async update(
    uuid: string,
    data: Partial<Pick<LocalCustomer, "phone" | "name" | "delivery_address" | "delivery_reference" | "notes">>
  ): Promise<void> {
    const fields: string[] = [];
    const values: unknown[] = [];

    if (data.phone !== undefined) {
      fields.push("phone = ?");
      values.push(clean(data.phone));
    }
    if (data.name !== undefined) {
      fields.push("name = ?");
      values.push((data.name ?? "").trim());
    }
    if (data.delivery_address !== undefined) {
      fields.push("delivery_address = ?");
      values.push(trimOrNull(data.delivery_address));
    }
    if (data.delivery_reference !== undefined) {
      fields.push("delivery_reference = ?");
      values.push(trimOrNull(data.delivery_reference));
    }
    if (data.notes !== undefined) {
      fields.push("notes = ?");
      values.push(trimOrNull(data.notes));
    }

    if (fields.length === 0) return;

    fields.push("updated_at = ?");
    values.push(new Date().toISOString());
    values.push(uuid);

    await localDb.execute(
      `UPDATE local_customers SET ${fields.join(", ")} WHERE uuid = ?`,
      values as unknown[]
    );
  },
};
