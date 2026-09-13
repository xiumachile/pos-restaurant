import { localDb } from "../localDb";
import { getCashierContextSafe } from "../../services/authContext";
import { v4 as uuidv4 } from "uuid";

/**
 * Tipos de impresora soportados.
 * - receipt: Impresora de tickets/boletas (caja)
 * - kitchen: Impresora de comandas (cocina)
 * - bar: Impresora de bebidas (bar)
 */
export type PrinterType = "receipt" | "kitchen" | "bar";

/**
 * Configuración de impresora persistida en SQLite.
 */
export interface PrinterConfig {
  local_uuid: string;
  printer_type: PrinterType;
  name: string;
  ip: string;
  port: number;
  is_default: boolean;
  is_active: boolean;
  notes: string | null;
  company_id: string;
  branch_id: string;
  created_at: string;
  updated_at: string;
}

export interface CreatePrinterConfigPayload {
  printer_type: PrinterType;
  name: string;
  ip: string;
  port?: number;
  is_default?: boolean;
  notes?: string | null;
}

export interface UpdatePrinterConfigPayload {
  name?: string;
  ip?: string;
  port?: number;
  is_default?: boolean;
  is_active?: boolean;
  notes?: string | null;
}

/**
 * PrinterConfigRepository: CRUD para configuración de impresoras.
 *
 * ADR-012: TODAS las operaciones filtran por company_id + branch_id
 * para garantizar aislamiento multi-tenant local.
 *
 * USO:
 *   const configs = await PrinterConfigRepository.findByType("receipt");
 *   const defaultPrinter = await PrinterConfigRepository.getDefault("kitchen");
 *   await PrinterConfigRepository.create({ printer_type: "receipt", name: "Caja 1", ip: "192.168.1.100" });
 */
export class PrinterConfigRepository {
  /**
   * Obtiene el contexto actual del cajero.
   * Lanza error si no hay contexto (usuario no autenticado).
   */
  private static getContext() {
    const ctx = getCashierContextSafe();
    if (!ctx) {
      throw new Error("[PrinterConfigRepository] Sin contexto de usuario autenticado");
    }
    return ctx;
  }

  /**
   * Obtiene todas las impresoras activas de un tipo específico del tenant actual.
   */
  static async findByType(printerType: PrinterType): Promise<PrinterConfig[]> {
    const { company_id, branch_id } = this.getContext();
    const rows = await localDb.select<any>(
      `SELECT * FROM printer_configs 
       WHERE printer_type = ? AND is_active = 1 AND company_id = ? AND branch_id = ?
       ORDER BY is_default DESC, created_at ASC`,
      [printerType, company_id, branch_id]
    );
    return rows.map(this.rowToConfig);
  }

  /**
   * Obtiene la impresora default de un tipo específico del tenant actual.
   * Retorna null si no hay default configurada.
   */
  static async getDefault(printerType: PrinterType): Promise<PrinterConfig | null> {
    const { company_id, branch_id } = this.getContext();
    const row = await localDb.selectOne<any>(
      `SELECT * FROM printer_configs 
       WHERE printer_type = ? AND is_default = 1 AND is_active = 1 AND company_id = ? AND branch_id = ?
       LIMIT 1`,
      [printerType, company_id, branch_id]
    );
    return row ? this.rowToConfig(row) : null;
  }

  /**
   * Obtiene una impresora por UUID local del tenant actual.
   */
  static async findByLocalUuid(localUuid: string): Promise<PrinterConfig | null> {
    const { company_id, branch_id } = this.getContext();
    const row = await localDb.selectOne<any>(
      "SELECT * FROM printer_configs WHERE local_uuid = ? AND company_id = ? AND branch_id = ?",
      [localUuid, company_id, branch_id]
    );
    return row ? this.rowToConfig(row) : null;
  }

  /**
   * Obtiene TODAS las impresoras (activas e inactivas) del tenant actual.
   */
  static async findAll(): Promise<PrinterConfig[]> {
    const { company_id, branch_id } = this.getContext();
    const rows = await localDb.select<any>(
      "SELECT * FROM printer_configs WHERE company_id = ? AND branch_id = ? ORDER BY printer_type, is_default DESC, name ASC",
      [company_id, branch_id]
    );
    return rows.map(this.rowToConfig);
  }

  /**
   * Crea una nueva impresora para el tenant actual.
   * Si is_default=true, primero desmarca cualquier default previa del mismo tipo (del mismo tenant).
   */
  static async create(payload: CreatePrinterConfigPayload): Promise<PrinterConfig> {
    const { company_id, branch_id } = this.getContext();
    const local_uuid = uuidv4();
    const port = payload.port ?? 9100;
    const is_default = payload.is_default ? 1 : 0;

    // Si es default, desmarcar otros defaults del mismo tipo (del mismo tenant)
    if (is_default) {
      await localDb.execute(
        "UPDATE printer_configs SET is_default = 0 WHERE printer_type = ? AND company_id = ? AND branch_id = ?",
        [payload.printer_type, company_id, branch_id]
      );
    }

    await localDb.execute(
      `INSERT INTO printer_configs (
        local_uuid, printer_type, name, ip, port, is_default, is_active, notes, 
        company_id, branch_id, created_at, updated_at
      ) VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)`,
      [
        local_uuid,
        payload.printer_type,
        payload.name,
        payload.ip,
        port,
        is_default,
        payload.notes || null,
        company_id,
        branch_id,
      ]
    );

    console.log(`[PrinterConfigRepository] 🖨️ Impresora creada: ${local_uuid} (${payload.printer_type}) para ${company_id}/${branch_id}`);

    return (await this.findByLocalUuid(local_uuid))!;
  }

  /**
   * Actualiza una impresora existente del tenant actual.
   * Si se marca como default, desmarca cualquier default previa del mismo tipo (del mismo tenant).
   */
  static async update(
    localUuid: string,
    payload: UpdatePrinterConfigPayload
  ): Promise<PrinterConfig> {
    const { company_id, branch_id } = this.getContext();
    const existing = await this.findByLocalUuid(localUuid);
    if (!existing) {
      throw new Error(`Impresora ${localUuid} no encontrada o no pertenece al tenant actual`);
    }

    // Si se marca como default, desmarcar otros del mismo tipo (del mismo tenant)
    if (payload.is_default) {
      await localDb.execute(
        "UPDATE printer_configs SET is_default = 0 WHERE printer_type = ? AND local_uuid != ? AND company_id = ? AND branch_id = ?",
        [existing.printer_type, localUuid, company_id, branch_id]
      );
    }

    const updates: string[] = [];
    const params: any[] = [];

    if (payload.name !== undefined) {
      updates.push("name = ?");
      params.push(payload.name);
    }
    if (payload.ip !== undefined) {
      updates.push("ip = ?");
      params.push(payload.ip);
    }
    if (payload.port !== undefined) {
      updates.push("port = ?");
      params.push(payload.port);
    }
    if (payload.is_default !== undefined) {
      updates.push("is_default = ?");
      params.push(payload.is_default ? 1 : 0);
    }
    if (payload.is_active !== undefined) {
      updates.push("is_active = ?");
      params.push(payload.is_active ? 1 : 0);
    }
    if (payload.notes !== undefined) {
      updates.push("notes = ?");
      params.push(payload.notes);
    }

    if (updates.length === 0) {
      return existing;
    }

    updates.push("updated_at = CURRENT_TIMESTAMP");
    params.push(localUuid);

    await localDb.execute(
      `UPDATE printer_configs SET ${updates.join(", ")} WHERE local_uuid = ? AND company_id = ? AND branch_id = ?`,
      [...params, company_id, branch_id]
    );

    console.log(`[PrinterConfigRepository] ✏️ Impresora actualizada: ${localUuid}`);

    return (await this.findByLocalUuid(localUuid))!;
  }

  /**
   * Elimina una impresora del tenant actual (hard delete).
   */
  static async delete(localUuid: string): Promise<void> {
    const { company_id, branch_id } = this.getContext();
    await localDb.execute(
      "DELETE FROM printer_configs WHERE local_uuid = ? AND company_id = ? AND branch_id = ?",
      [localUuid, company_id, branch_id]
    );
    console.log(`[PrinterConfigRepository] 🗑️ Impresora eliminada: ${localUuid}`);
  }

  /**
   * Convierte fila de DB a PrinterConfig (convierte INTEGER a boolean).
   */
  private static rowToConfig(row: any): PrinterConfig {
    return {
      local_uuid: row.local_uuid,
      printer_type: row.printer_type,
      name: row.name,
      ip: row.ip,
      port: row.port,
      is_default: row.is_default === 1,
      is_active: row.is_active === 1,
      notes: row.notes,
      company_id: row.company_id,
      branch_id: row.branch_id,
      created_at: row.created_at,
      updated_at: row.updated_at,
    };
  }
}
