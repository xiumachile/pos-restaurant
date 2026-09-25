# Frontend SQLite Write Map

**Fecha de generación:** 2026-09-26  
**Commit de referencia:** `e81ce38038c12c7ff9179cdb0b2facb3f0ccc9c3`  
**Objetivo:** Identificar todos los puntos de escritura en SQLite para consolidar la arquitectura Offline-First y eliminar los bloqueos (`database is locked`).

---

## 1. Resumen Ejecutivo

El frontend actual realiza escrituras en SQLite a través de múltiples puntos de entrada no coordinados:
- **Repositories** (`OrderRepository`, `SyncQueueRepository`, etc.)
- **Services** (`PullEngine`, `SyncEngine`, `localTablesService`, `offlinePaymentService`)

**Problema raíz identificado:** No existe un coordinador de escritura único (Single Writer). Múltiples módulos pueden intentar ejecutar `localDb.execute` o `localDb.transaction` simultáneamente. Dado que cada llamada a `execute` en Tauri es una llamada IPC independiente a Rust, las transacciones se fragmentan o colisionan, causando `database is locked` o `no transaction is active`.

---

## 2. Mapa de Escrituras por Módulo

### 📦 A. Orders & OrderItems (`OrderRepository.ts`)
- **Operaciones:** `create`, `addItem`, `createWithItems`, `recalculateOrderTotals`, `updateStatus`, `delete`.
- **Uso de Transacciones:** Sí (`localDb.transaction` en líneas 99, 198, 421).
- **Riesgo de Concurrencia:** **ALTO**. 
  - Aunque `createWithItems` usa `transaction(async (db) => { ... })`, existen llamadas sueltas a `localDb.execute` en el mismo archivo (líneas 283, 296, 363, 370, 391) que, si se invocan durante una transacción, rompen el estado.
  - Llama a `localTablesService.markOccupied()` que realiza sus propias escrituras.

### 🔄 B. Sincronización (`SyncQueueRepository.ts`, `SyncEngine.ts`, `PullEngine.ts`)
- **Operaciones:** `enqueue`, `claim`, `markSynced`, `markFailed`, `cleanup`, `PullEngine` bulk operations.
- **Uso de Transacciones:** Parcial. `PullEngine` ejecuta múltiples `DELETE` e `INSERT` (líneas 179-467) que parecen estar fuera de una transacción agrupada.
- **Riesgo de Concurrencia:** **CRÍTICO**. 
  - El `PullEngine` y el `SyncEngine` corren en background. Si la UI está creando un pedido (`OrderRepository`) al mismo tiempo que el `PullEngine` hace un `DELETE FROM local_products`, ocurre una colisión de escritura inmediata.
  - `SyncEngine.ts` línea 263 llama a `localDb.execute` directamente.

### 🪑 C. Mesas (`localTablesService.ts`)
- **Operaciones:** `markOccupied`, `release`, `clearMutations`.
- **Uso de Transacciones:** No. Usa `localDb.execute` directo (líneas 216, 224, 252, 258, 283, 305).
- **Riesgo de Concurrencia:** **ALTO**. 
  - Es llamado desde dentro de la transacción de `OrderRepository.create`, abriendo una segunda "rama" de escritura que compite por el bloqueo.

### 💰 D. Pagos y Caja (`PaymentRepository.ts`, `CashSessionRepository.ts`, `CashMovementRepository.ts`, `offlinePaymentService.ts`, `offlineCashCloseService.ts`)
- **Operaciones:** `create`, `update`, `close`.
- **Uso de Transacciones:** Sí, en servicios (`offlinePaymentService.ts:128`, `offlineCashCloseService.ts:68`), pero también hay llamadas a `localDb.execute` sueltas dentro de los mismos archivos.
- **Riesgo de Concurrencia:** **MEDIO/ALTO**. Mismo patrón que Orders: mezcla de `transaction` y `execute` directo.

### 🖨️ E. Impresión (`LocalPrintJobRepository.ts`)
- **Operaciones:** `create`, `updateStatus`, `delete`.
- **Uso de Transacciones:** No. Usa `localDb.execute` directo.
- **Riesgo de Concurrencia:** **MEDIO**. Generalmente disparado después de un pago, pero si falla y reintenta, puede solaparse con otras escrituras.

---

## 3. Puntos de Riesgo de Concurrencia (Hotspots)

1. **Hotspot 1: Creación de Pedido + Pull Engine**  
   La UI llama a `OrderRepository.createWithItems` (que hace `BEGIN IMMEDIATE`), pero si el `PullEngine` está ejecutando un `DELETE FROM local_categories` en ese exacto milisegundo, SQLite lanza `database is locked` porque el `PullEngine` no está esperando en la cola del coordinador.

2. **Hotspot 2: `localTablesService.markOccupied` dentro de transacción**  
   Al llamar a un servicio externo que usa `localDb.execute` *dentro* de un bloque `localDb.transaction`, se generan dos contextos de conexión o se fuerza un auto-rollback.

3. **Hotspot 3: `SyncQueueRepository` actualizando estados**  
   Múltiples llamadas a `UPDATE sync_queue` (líneas 69, 88, 129, etc.) sin un coordinador global que serialice los reclamos (`claim`) de trabajos de sync.

---

## 4. Regla de Oro para la Consolidación (Fase 2 en adelante)

> **"Ningún módulo debe llamar a `localDb.execute` o `localDb.transaction` directamente. Todas las escrituras deben pasar por el `LocalWriteCoordinator`, el cual garantiza que solo una operación de escritura (ya sea una transacción multi-statement o un statement único) se ejecute a la vez, utilizando la misma instancia de conexión `db`."**
