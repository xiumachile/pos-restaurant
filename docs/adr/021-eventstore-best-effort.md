# ADR-021: EventStore es best-effort (no atómico con la entidad)

## Estado: Aceptada

## Contexto

El sistema usa Event Sourcing híbrido (ADR-006) con tres componentes:
- **Repositorios**: Estado mutable en tablas `local_orders`, `local_payments`, etc.
- **EventStore**: Tabla append-only `offline_events` para auditoría
- **SyncQueue**: Cola FIFO para sincronización con backend

Existe la pregunta arquitectónica: ¿Qué pasa si EventStore falla al registrar un evento?

### Dos modelos posibles

**Modelo A (Atómico)**: Payment + Event son una unidad transaccional

Payment OK + Event FAIL → ROLLBACK del pago

**Modelo B (Best-effort)**: EventStore es auxiliar, no bloquea la operación

Payment OK + Event FAIL → Payment sigue válido, evento se recupera después

## Decisión

**Implementamos Modelo B (best-effort)** por las siguientes razones:

### 1. Prioridad #1 del POS: Nunca perder una venta

En un restaurante, si el cliente ya entregó $11.000 en efectivo y EventStore falla (disco lleno, corrupción SQLite, error de I/O), **no podemos perder la venta**. El pago debe persistir aunque el evento de auditoría falle.

### 2. EventStore es local y recuperable

- `offline_events` es una tabla SQLite local
- Si falla por error temporal (disco lleno), se puede recuperar después
- No afecta la sincronización con backend (eso usa `sync_queue`)
- No afecta la integridad financiera (eso está en el backend `ledger`)

### 3. Backend tiene su propia auditoría

El backend registra cada payment en `PaymentLedgerService`, que es la fuente de verdad financiera. EventStore local es para debugging y replay local, no para contabilidad.

### 4. ADR-006 ya lo define así

> "Los eventos sirven para **auditoría y corrección**, no como fuente primaria de verdad."

## Implementación

### Patrón en repositorios

```typescript
// En PaymentRepository.create()
try {
  // 1. Crear payment (CRÍTICO - debe persistir)
  const payment = await localDb.execute(INSERT_INTO_local_payments...);
  
  // 2. Registrar evento (BEST-EFFORT - no bloquea)
  try {
    await EventStore.record({
      event_type: 'CREATE_PAYMENT',
      entity_uuid: local_uuid,
      payload: payment
    });
  } catch (eventErr: any) {
    // ADR-021: No crítico - payment ya persistió
    console.warn("[PaymentRepository] ⚠️ No se pudo registrar evento:", eventErr?.message);
  }
  
  return payment;
} catch (dbErr) {
  // Este SÍ es crítico - fallo al crear payment
  throw new Error(`Error creating payment: ${dbErr.message}`);
}

Lo mismo aplica a:
	CashMovementRepository.create() - movimiento persiste aunque evento falle
	OrderRepository - order persiste aunque evento falle
	BillRepository - bill persiste aunque evento falle
Casos de uso de EventStore
EventStore se usa para:
1. Debugging local: Ver qué operaciones hizo el terminal
2. Replay de estado: Reconstruir estado de una entidad desde eventos
3. Correcciones: Registrar ADJUST_PAYMENT en vez de UPDATE
4. Auditoría offline: Saber quién hizo qué cuando no había conexión

NO se usa para:
	Integridad financiera (eso está en backend)
	Sincronización (eso usa sync_queue)
	Validación de negocios (eso está en validaciones del repositorio)
Mecanismo de recuperación
Si EventStore falla repetidamente:
1. Logs: El warning se registra en logs del sistema
2. Monitoreo: Se puede alertar si hay muchos warnings de EventStore
3. Recuperación manual: Admin puede revisar logs y re-generar eventos si es necesario
4. No hay pérdida de datos: El payment/movement ya está en la tabla principal
Consecuencias
Positivas
	Resiliencia: El POS sigue funcionando aunque EventStore falle
	Performance: No bloquea operaciones críticas por auditoría
	Simplicidad: No requiere transacciones distribuidas complejas

Negativas
	Auditoría incompleta: Si EventStore falla, puede haber operaciones sin evento
	Replay parcial: No se puede reconstruir estado completo si faltan eventos
	Requiere monitoreo: Hay que detectar cuando EventStore está fallando
Testing
Los tests validan este comportamiento:
// offlinePaymentService.printing.test.ts
it("funciona aunque no se pueda generar el receipt (pago sigue siendo válido)", async () => {
  // Simular fallo de EventStore
  vi.mocked(EventStore.record).mockRejectedValue(new Error("DB error"));
  
  // Payment debe crearse exitosamente
  const result = await offlinePaymentService.createPaymentOffline({...});
  expect(result.payment).toBeDefined();
  expect(result.payment.status).toBe("pending");
});

Referencias
ADR-006: Event Sourcing Híbrido (define el modelo general)
ADR-019: Bill link en payments (usa EventStore para auditoría)
Backend: PaymentLedgerService (fuente de verdad financiera)
Frontend: EventStore.ts (implementación local)
Historial
2026-09-18: Creado para clarificar inconsistencia entre código (Modelo B) y documentación (lenguaje ambiguo)
