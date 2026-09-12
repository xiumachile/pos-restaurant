# ADR-009: Bills no son entidades sincronizables independientes

**Fecha**: Septiembre 2026  
**Estado**: Aceptado  
**Contexto**: Arquitectura offline-first con sync queue

---

## Contexto

El sistema maneja `bills` (cuentas) en dos modelos diferentes:

### Modelo Online (Backend)
- Bills se crean vía `POST /orders/{uuid}/split`
- Representan **sub-cuentas** de un order (split entre mesas/comensales)
- Campos clave: `type` (equal_split, by_items, custom_amount), `guest_count`, `item_ids`
- **NO existe endpoint `POST /bills` directo**

### Modelo Offline (SQLite - LocalBill)
- Bills se crean vía `BillRepository.create()` automáticamente
- Representan **la cuenta completa** de un order (1 bill = 1 order)
- Campos clave: `discount_total`, `tip_amount`, `grand_total`, `order_local_uuid`
- `offlinePaymentService` las crea cuando el order no tiene bill

## Problema identificado

Al intentar implementar `processBill()` para sincronizar bills, detectamos:

1. **El backend NO tiene endpoint `POST /bills`**
2. Los modelos son conceptualmente incompatibles:
   - Online: bills = artefacto de split (sub-entidad)
   - Offline: bills = cuenta completa del order (entidad derivada)
3. Forzar sincronización crearía:
   - Inconsistencias entre modelos
   - Bills "fantasma" en SQLite sin equivalente en backend
   - Posibles errores 404 al intentar POST a endpoint inexistente

## Decisión

**Las bills NO se sincronizan como entidades independientes.**

### Implementación:

1. Eliminar `SyncQueueRepository.enqueue` de `BillRepository.create()` y `BillRepository.update()`
2. Mantener `case "bill"` en SyncEngine como throw defensivo (protección contra errores)
3. Las bills existen SOLO en SQLite como artefacto de tracking local

### Justificación:

- **Order ya se sincroniza** → contiene todos los totales
- **Payments ya se sincronizan** → contienen referencias al order
- **Backend puede reconstruir bills** desde order + payments
- **Principio DRY**: no duplicar estado que puede derivarse

## Consecuencias

### Positivas
✅ Menos items en sync_queue (menos superficie de error)
✅ Coherencia arquitectónica con el modelo del backend
✅ Menos complejidad en SyncEngine
✅ Sin riesgo de bills "fantasma" en backend

### Negativas
⚠️ Si el backend necesita conocer bills específicas para reportes, debe reconstruirlas
⚠️ Tests existentes que verificaban encolado de bill requieren ajuste
⚠️ Migración de datos: bills ya encoladas en sync_queue quedarán como "failed" (esperado)

## Alternativas consideradas

### A. Crear endpoint `POST /bills` en backend
**Rechazada porque:**
- ❌ Rompería el modelo del backend (bills como sub-entidad)
- ❌ Requeriría lógica de reconciliación compleja
- ❌ No soluciona la diferencia conceptual entre online/offline

### B. Sincronizar bills como `order.split` automáticamente
**Rechazada porque:**
- ❌ El flujo offline NO usa splits (usa bill única)
- ❌ El backend esperaría payload de split, no datos de bill
- ❌ Forzaría al frontend a hacer split online de una bill única (contradictorio)

### C. Marcar bills como "local only" (documentar y no tocar)
**Rechazada porque:**
- ❌ Deja el bug actual (intentan sincronizarse y fallan)
- ❌ sync_queue se llena de items fallidos innecesariamente
- ✅ Esta fue la decisión elegida (eliminar encolado)

## Flujo final

### Creación de bill offline:

offlinePaymentService.createPaymentOffline()
↓
BillRepository.create()
↓
INSERT INTO local_bills (SQLite)
↓
❌ NO se encola en sync_queue
↓
Bill existe solo localmente para tracking de pagos


### Sincronización real:
Order → sync_queue → POST /orders (backend)
Payment → sync_queue → POST /billing/payments (backend)
↓
Backend reconstruye bills automáticamente desde order + payments


## Verificación

- ✅ `BillRepository.create()` ya no encola en sync_queue
- ✅ `BillRepository.update()` ya no encola en sync_queue
- ✅ `case "bill"` en SyncEngine lanza error defensivo
- ✅ Tests de offlinePaymentService ajustados (no verifican encolado)
- ✅ Suite completa verde

## Métricas de implementación

- **Commits**: 2 (ADR + implementación)
- **Archivos modificados**: 4 (BillRepository, tests, SyncEngine tests)
- **Líneas removidas**: ~20 (enqueue de bills)
- **Tests ajustados**: 2-3 (offlinePaymentService)

## Referencias

- `src/db/repositories/BillRepository.ts` - Repositorio local
- `src/services/billsService.ts` - Flujo online (split)
- `src/services/offlinePaymentService.ts` - Flujo offline (bill única)
- `src/types/bills.ts` - Modelos Bill vs LocalBill
- ADR-007: Arquitectura híbrida de impresión y cobro offline-first
