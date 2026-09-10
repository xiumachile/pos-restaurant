# ADR-006: Event Sourcing Híbrido para Offline-First

**Fecha**: Septiembre 2026  
**Estado**: Aceptado  
**Contexto**: Frontend offline-first con Tauri + SQLite

---

## Contexto

El sistema POS opera en restaurantes donde la conexión a internet es inestable.
Necesitamos:

1. **Auditoría completa**: Saber quién hizo qué y cuándo, incluso offline
2. **Reconstrucción de estado**: Poder reconstruir el estado de una entidad desde eventos
3. **Correcciones sin borrar**: Ajustes como `ADJUST_PAYMENT` en vez de `UPDATE`
4. **Sincronización confiable**: No perder datos cuando falla la conexión

## Decisión

Implementamos **Event Sourcing híbrido**:

- **`offline_events`** (tabla append-only): Registra todos los eventos de negocio
- **`sync_queue`** (tabla FIFO): Cola de sincronización hacia el backend
- **Repositorios** (estado mutable): Mantienen el estado actual para consultas rápidas

### ¿Por qué "híbrido"?

A diferencia del Event Sourcing puro (donde el estado se reconstruye siempre desde eventos),
nosotros mantenemos **estado mutable** en tablas `local_orders`, `local_payments`, etc.
Los eventos sirven para **auditoría y corrección**, no como fuente primaria de verdad.

                ┌──────────────┐
Operación ──────▶│ Repository │──────▶ Estado mutable (local_orders, etc.)
│ │
│ + EventStore│──────▶ Evento append-only (offline_events)
│ │
│ + SyncQueue │──────▶ Cola de sincronización (sync_queue)
└──────────────┘


## Esquema de `offline_events`

```sql
CREATE TABLE IF NOT EXISTS offline_events (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  company_id TEXT NOT NULL,
  branch_id TEXT NOT NULL,
  terminal_id TEXT NOT NULL,
  user_id TEXT NOT NULL,
  entity_type TEXT NOT NULL,      -- 'order', 'payment', 'cash_session'
  entity_uuid TEXT NOT NULL,      -- UUID de la entidad afectada
  event_type TEXT NOT NULL,       -- 'CREATE_PAYMENT', 'ADJUST_PAYMENT', etc.
  payload TEXT NOT NULL,          -- JSON con datos del evento
  created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

Tipos de evento implementados
Entidad
Evento
Descripción
payment
CREATE_PAYMENT
Pago registrado
payment
ADJUST_PAYMENT
Corrección de pago (no UPDATE)
cash_session
OPEN_SESSION
Caja abierta
cash_session
CLOSE_SESSION
Caja cerrada
cash_movement
CREATE_MOVEMENT
Retiro/depósito/ajuste
Correcciones explícitas (no UPDATE)

// ❌ INCORRECTO: borrar historia
await db.execute("UPDATE local_payments SET amount = 50 WHERE id = ?", [id]);

// ✅ CORRECTO: corrección append-only
await EventStore.record({
  entity_type: "payment",
  entity_uuid: paymentUuid,
  event_type: "ADJUST_PAYMENT",
  payload: { amount: 50, reason: "Corrección de monto" },
});

Reconstrucción de estado
const events = await EventStore.getByEntity("payment", paymentUuid);
const state = EventStore.replay(events); // Reconstruye estado desde eventos

Consecuencias
Positivas
✅ Auditoría completa (quién, qué, cuándo, desde qué terminal)
✅ Correcciones sin perder historia (ADJUST en vez de UPDATE)
✅ Estado reconstruible desde eventos
✅ Consultas rápidas (estado mutable, no replay costoso)
✅ Compatible con sincronización offline (cola separada)
Negativas
⚠️ Duplicación parcial de datos (estado + eventos)
⚠️ Más escrituras por operación (2 tablas en vez de 1)
⚠️ offline_events crece indefinidamente (necesita poda periódica)
Riesgos mitigados
Pérdida de datos offline: sync_queue con retry automático
Auditoría corrupta: Eventos son append-only, nunca se borran
Inconsistencia entre estado y eventos: Ambos se escriben en la misma transacción
Referencias
Martin Fowler: Event Sourcing
ADR-004: docs/architecture/decisions/004-ledger-idempotency.md
FASE 8 del roadmap: Event Sourcing híbrido offline
