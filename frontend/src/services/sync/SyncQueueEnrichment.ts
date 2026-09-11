/**
 * Módulo de enriquecimiento para SyncQueue.
 * 
 * Extrae campos del payload JSON para diagnóstico:
 * - payment_uuid (cuando entity_type = 'payment')
 * - idempotency_key (si existe en payload)
 * - terminal_id (si existe en payload)
 * - cash_session (cuando entity_type = 'cash_session' o 'cash_movement')
 */

export interface EnrichedSyncItem {
  payment_uuid?: string;
  idempotency_key?: string;
  terminal_id?: string;
  cash_session_uuid?: string;
}

/**
 * Extrae campos de diagnóstico del payload.
 * @param payload JSON string del payload
 * @param entity_type Tipo de entidad
 */
export function enrichSyncQueueItem(
  payload: string,
  entity_type: string
): EnrichedSyncItem {
  const enriched: EnrichedSyncItem = {};

  try {
    const data = JSON.parse(payload);

    // Payment UUID (cuando entity_type = 'payment')
    if (entity_type === 'payment') {
      enriched.payment_uuid = data.local_uuid || data.uuid || data.payment_uuid;
    }

    // Idempotency Key (si existe)
    if (data.idempotency_key) {
      enriched.idempotency_key = data.idempotency_key;
    }

    // Terminal ID (si existe)
    if (data.terminal_id) {
      enriched.terminal_id = data.terminal_id;
    }

    // Cash Session (cuando entity_type = 'cash_session' o 'cash_movement')
    if (entity_type === 'cash_session' || entity_type === 'cash_movement') {
      enriched.cash_session_uuid = 
        data.local_uuid || 
        data.cash_session_local_uuid || 
        data.cash_session_uuid;
    }

    return enriched;
  } catch (error) {
    console.warn('[SyncQueueEnrichment] Error parsing payload:', error);
    return {};
  }
}

/**
 * Formatea payment_uuid para display (muestra solo primeros 8 chars).
 */
export function formatPaymentUuid(uuid?: string): string {
  if (!uuid) return '-';
  return `${uuid.substring(0, 8)}...`;
}

/**
 * Formatea cash_session para display.
 */
export function formatCashSession(uuid?: string): string {
  if (!uuid) return '-';
  return `${uuid.substring(0, 8)}...`;
}
