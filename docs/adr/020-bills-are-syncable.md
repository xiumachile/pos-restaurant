# ADR-020: Bills sincronizables en flujo offline

**Fecha**: Septiembre 2026  
**Estado**: Aceptado  
**Supersedes**: ADR-009 (parcialmente)  
**Relacionado**: ADR-006 (event sourcing), ADR-018 (monetary values as integer)

---

## Contexto

ADR-009 estableció que las bills NO son entidades sincronizables bajo la premisa de que:
1. El backend puede reconstruir bills desde orders + payments
2. Evitar duplicación de estado
3. Simplificar el flujo de sincronización

Sin embargo, el flujo de **split bill offline** reveló una contradicción arquitectónica:
PROBLEMA:
Frontend offline crea múltiples bills (split bill)
Frontend crea payments vinculados a bills específicas
SyncEngine envía payments con bill_uuid al backend
Backend rechaza payments porque bill_uuid no existe (bills no se sincronizaron)
Resultado: Split bill offline NO funciona


**Contradicción detectada**:
- `StorePaymentRequest` acepta `bill_uuid` (nullable)
- `PaymentController::store` busca bill existente: `Bill::where('uuid', $validated['bill_uuid'])->firstOrFail()`
- Pero ADR-009 dice que bills no se sincronizan
- Entonces: ¿cómo puede el payment referenciar una bill que no existe?

---

## Decisión

**Las bills AHORA son entidades sincronizables** en el flujo offline→backend.

### Contrato de sincronización

#### 1. Frontend (offline)
```typescript
// BillRepository.create() encola a sync_queue
await SyncQueueRepository.enqueue({
  entity_type: 'bill',
  entity_local_uuid: bill.local_uuid,
  payload: {
    order_local_uuid: order.local_uuid,
    bill_number: bill.bill_number,
    type: bill.type,  // 'single' | 'equal_split' | 'by_items' | 'custom_amount'
    subtotal: bill.subtotal,
    tax_amount: bill.tax_amount,
    discount_amount: bill.discount_amount,
    tip_amount: bill.tip_amount,
    total: bill.total,
    paid_amount: bill.paid_amount,
    remaining_amount: bill.remaining_amount,
    status: bill.status,
    idempotency_key: bill.idempotency_key
  }
});

2. SyncEngine (orden de procesamiento)
Orden garantizado por created_at ASC en sync_queue:
  1. Order (creado primero)
  2. Bill (creada después)
  3. Payment (creado al final, referencia bill.cloud_id)

// SyncEngine.processBill()
private async processBill(item, payload) {
  const orderUuid = await this.resolveOrderUuid(payload.order_local_uuid);
  
  const response = await syncApi.createBill({
    order_uuid: orderUuid,
    bill_number: payload.bill_number,
    type: payload.type,
    subtotal: payload.subtotal,
    tax_amount: payload.tax_amount,
    discount_amount: payload.discount_amount,
    tip_amount: payload.tip_amount,
    total: payload.total,
    paid_amount: payload.paid_amount,
    remaining_amount: payload.remaining_amount,
    status: payload.status,
    idempotency_key: payload.idempotency_key
  });
  
  return response.uuid;  // bill.cloud_id
}

3. Backend (nuevo endpoint)
// POST /api/v1/bills
// StoreBillRequest: valida order_uuid + idempotency_key único
// BillController::store: crea bill con todos los campos
// Retorna: { uuid, id, ... }

4. Payments vinculados a bills
// SyncEngine.processPayment()
const billUuid = payload.bill_local_uuid 
  ? await this.resolveBillUuid(payload.bill_local_uuid)
  : null;

const response = await syncApi.createPayment({
  order_uuid: orderUuid,
  bill_uuid: billUuid,  // ← Ahora existe en backend
  payment_method_uuid: payload.payment_method_uuid,
  amount: payload.amount,
  tip_amount: payload.tip_amount,
  idempotency_key: payload.idempotency_key
});

Invariantes
1. Orden de creación: Order → Bill → Payment (garantizado por created_at ASC)
2. Idempotencia: Mismo idempotency_key → misma bill (evita duplicados)
3. Consistencia: paid_amount + remaining_amount = total (sin epsilon, ADR-018)
4. Integridad referencial: bill.order_id siempre apunta a order existente
Consecuencias
Positivas
✅ Split bill offline funciona completamente
✅ Backend puede vincular payments a bills existentes
✅ Coherencia total frontend↔backend
✅ Soporta todos los tipos de split: equal_split, by_items, custom_amount
Negativas
⚠️ Más items en sync_queue (1 bill por cada split)
⚠️ Requiere endpoint nuevo POST /api/v1/bills
⚠️ Tests E2E adicionales para validar flujo completo
⚠️ Rollback más complejo si falla sync de bill
Mitigaciones
Endpoint idempotente: Si bill ya existe (por idempotency_key), retornar existente
Validación temprana: Fallar sync si order_uuid no existe
Tests E2E: Cobertura completa del flujo split bill
Logs detallados: Tracking de sync de bills para debugging

Implementación
Commits planificados
#	Commit	Descripción
1	docs: ADR-020 bills are syncable	Este documento
2	feat(bills): StoreBillRequest + BillController::store	Endpoint backend
3	feat(sync): frontend enqueue + processBill + syncApi	Sincronización frontend
4	test(e2e): split bill sync flow	Tests de integración

Archivos afectados
Backend:
app/Modules/Payments/Interfaces/Requests/StoreBillRequest.php (nuevo)
app/Modules/Payments/Interfaces/Controllers/BillController.php (agregar store)
routes/api.php (agregar ruta POST /bills)
tests/Feature/BillSyncTest.php (nuevo)
Frontend:
src/db/repositories/BillRepository.ts (agregar enqueue a sync_queue)
src/services/sync/SyncEngine.ts (agregar processBill)
src/services/syncApi.ts (agregar createBill)
src/tests/services/syncEngine.bill.test.ts (nuevo)

Alternativas consideradas
A. Metadata en payments (sin sincronizar bills)
Enviar bill_index y bill_total en cada payment, backend reconstruye bills.
Rechazada porque:
❌ Lógica de reconstrucción compleja en backend
❌ Metadata duplicada en cada payment
❌ Casos edge difíciles (¿qué pasa si un payment falla?)
❌ No escala a split by_items (requiere lista de items por bill)
B. Endpoint separado para split
POST /api/v1/orders/{uuid}/sync-split con payload de todas las bills.
Rechazada porque:
❌ Rompe el modelo de sync_queue (eventos individuales)
❌ Requiere batch processing (más complejo)
❌ No reutiliza StorePaymentRequest existente
C. Bills solo sincronizables en modo split
Agregar flag is_split a bills, solo sincronizar si is_split=true.
Rechazada porque:
❌ Complejidad innecesaria (¿por qué no sincronizar todas?)
❌ Inconsistencia: bills single no existen en backend
❌ Más código condicional en SyncEngine
Referencias
ADR-009: Bills no sincronizables (superseded parcialmente)
ADR-006: Event sourcing híbrido offline-first
ADR-018: Monetary values as integer (CLP)
ADR-011: Modelo de montos Chile (bruto/neto/IVA)
SplitBillTest: Tests de split bill en backend
offlinePaymentService: Servicio frontend que crea bills + payments
Decisiones futuras
Pull de bills
¿El frontend debe descargar bills creadas en backend (web) vía /api/v1/sync/pull?
Decisión pendiente: Evaluar en ADR-021 si hay necesidad de flujo bidireccional completo.
Bills canceladas
¿Cómo sincronizar cancelación de bills? (BillStatus::CANCELLED)
Decisión pendiente: Implementar DELETE /api/v1/bills/{uuid} o PATCH con status.
Revisión
Revisado por: Arquitectura
Aprobado por: Tech Lead
Fecha de aprobación: Septiembre 2026
Changelog
Fecha	Versión	Descripción
Sep 2026	1	Versión inicial
