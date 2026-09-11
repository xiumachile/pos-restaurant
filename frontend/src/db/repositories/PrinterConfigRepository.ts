import { localDb } from "../localDb";
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
 * USO:
 *   const configs = await PrinterConfigRepository.findByType("receipt");
 *   const defaultPrinter = await PrinterConfigRepository.getDefault("kitchen");
 *   await PrinterConfigRepository.create({ printer_type: "receipt", name: "Caja 1", ip: "192.168.1.100" });
 */
export class PrinterConfigRepository {
  /**
   * Obtiene todas las impresoras activas de un tipo específico.
   */
  static async findByType(printerType: PrinterType): Promise<PrinterConfig[]> {
    const rows = await localDb.select<any>(
      `SELECT * FROM printer_configs 
       WHERE printer_type = ? AND is_active = 1 
       ORDER BY is_default DESC, created_at ASC`,
      [printerType]
    );
    return rows.map(this.rowToConfig);
  }

  /**
   * Obtiene la impresora default de un tipo específico.
   * Retorna null si no hay default configurada.
   */
  static async getDefault(printerType: PrinterType): Promise<PrinterConfig | null> {
    const row = await localDb.selectOne<any>(
      `SELECT * FROM printer_configs 
       WHERE printer_type = ? AND is_default = 1 AND is_active = 1 
       LIMIT 1`,
      [printerType]
    );
    return row ? this.rowToConfig(row) : null;
  }

  /**
   * Obtiene una impresora por UUID local.
   */
  static async findByLocalUuid(localUuid: string): Promise<PrinterConfig | null> {
    const row = await localDb.selectOne<any>(
      "SELECT * FROM printer_configs WHERE local_uuid = ?",
      [localUuid]
    );
    return row ? this.rowToConfig(row) : null;
  }

  /**
   * Obtiene TODAS las impresoras (activas e inactivas) de todos los tipos.
   */
  static async findAll(): Promise<PrinterConfig[]> {
    const rows = await localDb.select<any>(
      "SELECT * FROM printer_configs ORDER BY printer_type, is_default DESC, name ASC"
    );
    return rows.map(this.rowToConfig);
  }

  /**
   * Crea una nueva impresora.
   * Si is_default=true, primero desmarca cualquier default previa del mismo tipo.
   */
  static async create(payload: CreatePrinterConfigPayload): Promise<PrinterConfig> {
    const local_uuid = uuidv4();
    const port = payload.port ?? 9100;
    const is_default = payload.is_default ? 1 : 0;

    // Si es default, desmarcar otros defaults del mismo tipo
    if (is_default) {
      await localDb.execute(
        "UPDATE printer_configs SET is_default = 0 WHERE printer_type = ?",
        [payload.printer_type]
      );
    }

    await localDb.execute(
      `INSERT INTO printer_configs (
        local_uuid, printer_type, name, ip, port, is_default, is_active, notes, created_at, updated_at
      ) VALUES (?, ?, ?, ?, ?, ?, 1, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)`,
      [
        local_uuid,
        payload.printer_type,
        payload.name,
        payload.ip,
        port,
        is_default,
        payload.notes || null,
      ]
    );

    console.log(`[PrinterConfigRepository] 🖨️ Impresora creada: ${local_uuid} (${payload.printer_type})`);

    return (await this.findByLocalUuid(local_uuid))!;
  }

  /**
   * Actualiza una impresora existente.
   * Si se marca como default, desmarca cualquier default previa del mismo tipo.
   */
  static async update(
    localUuid: string,
    payload: UpdatePrinterConfigPayload
  ): Promise<PrinterConfig> {
    const existing = await this.findByLocalUuid(localUuid);
    if (!existing) {
      throw new Error(`Impresora ${localUuid} no encontrada`);
    }

    // Si se marca como default, desmarcar otros del mismo tipo
    if (payload.is_default) {
      await localDb.execute(
        "UPDATE printer_configs SET is_default = 0 WHERE printer_type = ? AND local_uuid != ?",
        [existing.printer_type, localUuid]
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
      `UPDATE printer_configs SET ${updates.join(", ")} WHERE local_uuid = ?`,
      params
    );

    console.log(`[PrinterConfigRepository] ✏️ Impresora actualizada: ${localUuid}`);

    return (await this.findByLocalUuid(localUuid))!;
  }

  /**
   * Elimina una impresora (hard delete).
   */
  static async delete(localUuid: string): Promise<void> {
    await localDb.execute(
      "DELETE FROM printer_configs WHERE local_uuid = ?",
      [localUuid]
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
      created_at: row.created_at,
      updated_at: row.updated_at,
    };
  }
}
