import { localDb } from "../localDb";
import { v4 as uuidv4 } from "uuid";

/**
 * Tipos de eventos críticos del sistema.
 * Cada evento representa una acción inmutable que ocurrió en el sistema.
 */
export type EventType =
  // Pagos
  | "CREATE_PAYMENT"      // Registro de pago
  | "ADJUST_PAYMENT"      // Corrección de pago (monto, método, etc.)
  | "REFUND_PAYMENT"      // Reembolso de pago
  
  // Movimientos de caja
  | "CREATE_MOVEMENT"     // Movimiento de caja (withdrawal/deposit/adjustment)
  
  // Sesiones de caja
  | "OPEN_SESSION"        // Apertura de sesión
  | "CLOSE_SESSION"       // Cierre de sesión
  
  // Pedidos (futuro)
  | "CREATE_ORDER"        // Creación de pedido
  | "CONFIRM_ORDER"       // Confirmación de pedido
  | "CANCEL_ORDER";       // Cancelación de pedido

/**
 * Entidades que pueden tener eventos asociados.
 */
export type EntityType = "payment" | "movement" | "session" | "order";

/**
 * Estructura de un evento offline.
 */
export interface OfflineEvent {
  event_uuid: string;
  idempotency_key: string;
  company_id: string;
  branch_id: string;
  terminal_id: string;
  user_id: string;
  entity_type: EntityType;
  entity_uuid: string;
  event_type: EventType;
  payload: string;  // JSON string
  created_at: string;
  notes?: string | null;
  sync_status: "pending" | "syncing" | "synced" | "failed";
  cloud_event_id?: string | null;
  sync_error?: string | null;
}

/**
 * Payload para crear un evento.
 */
export interface CreateEventPayload {
  company_id: string;
  branch_id: string;
  terminal_id: string;
  user_id: string;
  entity_type: EntityType;
  entity_uuid: string;
  event_type: EventType;
  payload: Record<string, any>;
  notes?: string;
}

/**
 * EventStore: Gestiona eventos inmutables (append-only).
 * 
 * REGLAS FUNDAMENTALES:
 * 1. Los eventos son INMUTABLES: una vez creados, nunca se modifican
 * 2. Para correcciones, se crea un nuevo evento (ej: ADJUST_PAYMENT)
 * 3. Solo se permite UPDATE para sync_status y cloud_event_id
 * 4. Nunca se permite DELETE de eventos
 */
export class EventStore {
  /**
   * Registra un nuevo evento (append-only).
   * 
   * @throws Error si idempotency_key ya existe (evento duplicado)
   */
  static async record(payload: CreateEventPayload): Promise<OfflineEvent> {
    const event_uuid = uuidv4();
    const idempotency_key = uuidv4();

    await localDb.execute(
      `INSERT INTO offline_events (
        event_uuid, idempotency_key, company_id, branch_id, terminal_id, user_id,
        entity_type, entity_uuid, event_type, payload, created_at, notes, sync_status
      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')`,
      [
        event_uuid,
        idempotency_key,
        payload.company_id,
        payload.branch_id,
        payload.terminal_id,
        payload.user_id,
        payload.entity_type,
        payload.entity_uuid,
        payload.event_type,
        JSON.stringify(payload.payload),
        new Date().toISOString(),
        payload.notes || null,
      ]
    );

    console.log(`[EventStore] 📝 Evento registrado: ${payload.event_type} para ${payload.entity_type}:${payload.entity_uuid}`);

    const event = await this.findByUuid(event_uuid);
    if (!event) {
      throw new Error(`Error creando evento ${event_uuid}`);
    }

    return event;
  }

  /**
   * Busca evento por UUID.
   */
  static async findByUuid(eventUuid: string): Promise<OfflineEvent | null> {
    const results = await localDb.select<OfflineEvent>(
      "SELECT * FROM offline_events WHERE event_uuid = ?",
      [eventUuid]
    );
    return results[0] || null;
  }

  /**
   * Lista todos los eventos de una entidad (trail de auditoría).
   */
  static async findByEntity(
    entityType: EntityType,
    entityUuid: string
  ): Promise<OfflineEvent[]> {
    return await localDb.select<OfflineEvent>(
      `SELECT * FROM offline_events 
       WHERE entity_type = ? AND entity_uuid = ? 
       ORDER BY created_at ASC`,
      [entityType, entityUuid]
    );
  }

  /**
   * Lista eventos pendientes de sincronización.
   * 
   * NOTA: Filtra en JavaScript porque el mock no soporta IN correctamente.
   */
  static async getPendingSync(): Promise<OfflineEvent[]> {
    const allEvents = await localDb.select<OfflineEvent>(
      `SELECT * FROM offline_events ORDER BY created_at ASC`
    );
    
    return allEvents.filter(e => e.sync_status === 'pending' || e.sync_status === 'failed');
  }

  /**
   * Marca evento como sincronizado.
   * 
   * IMPORTANTE: Este es el ÚNICO UPDATE permitido en la tabla.
   */
  static async markAsSynced(eventUuid: string, cloudEventId: string): Promise<void> {
    await localDb.execute(
      `UPDATE offline_events 
       SET sync_status = 'synced', cloud_event_id = ?, sync_error = NULL 
       WHERE event_uuid = ?`,
      [cloudEventId, eventUuid]
    );
  }

  /**
   * Marca evento como fallido.
   * 
   * IMPORTANTE: Este es el ÚNICO UPDATE permitido en la tabla.
   */
  static async markAsFailed(eventUuid: string, error: string): Promise<void> {
    await localDb.execute(
      `UPDATE offline_events 
       SET sync_status = 'failed', sync_error = ? 
       WHERE event_uuid = ?`,
      [error, eventUuid]
    );
  }

  /**
   * Reconstruye el estado actual de una entidad desde sus eventos.
   * 
   * Ejemplo: Para un payment, replay de CREATE_PAYMENT + ADJUST_PAYMENT.
   */
  static async getEntityState(
    entityType: EntityType,
    entityUuid: string
  ): Promise<Record<string, any>> {
    const events = await this.findByEntity(entityType, entityUuid);
    
    let state: Record<string, any> = {};
    
    for (const event of events) {
      const payload = JSON.parse(event.payload);
      
      switch (event.event_type) {
        case "CREATE_PAYMENT":
        case "CREATE_MOVEMENT":
        case "OPEN_SESSION":
        case "CREATE_ORDER":
          // Evento de creación: inicializa estado
          state = { ...payload };
          break;
          
        case "ADJUST_PAYMENT":
          // Ajuste: merge con estado actual
          state = { ...state, ...payload };
          break;
          
        case "CLOSE_SESSION":
        case "CANCEL_ORDER":
          // Evento terminal: marca como cerrado/cancelado
          state = { ...state, status: "closed", ...payload };
          break;
      }
    }
    
    return state;
  }
}
