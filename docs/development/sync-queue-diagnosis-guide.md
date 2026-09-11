# Guía de Diagnóstico: Cola de Sincronización

**Para**: Developers y soporte técnico  
**Última actualización**: Septiembre 2026

---

## 🚀 Acceso rápido

1. Abrir el POS desktop (Tauri)
2. Sidebar → **"Sincronización"** (icono de database)
3. URL directa: `/sync-queue`

---

## 📊 Interpretación de las 6 cards

| Card | Significado | Acción recomendada |
|------|-------------|-------------------|
| **Pendientes** | Items esperando conexión para sincronizar | Normal si hay poca conexión. Si > 50, verificar red |
| **Sincronizando** | Items en proceso de sync activo | Si > 10 durante > 1 min, puede haber un cuello de botella |
| **Sincronizados** | Items ya sincronizados con backend | No requiere acción |
| **Errores** | Items que fallaron tras max_attempts | Requiere intervención manual (ver abajo) |
| **Última Sync** | Timestamp relativo de la última sincronización | Si > 1 hora sin actividad, verificar conexión |
| **Total Histórico** | Suma de todos los items en cola | Útil para dimensionar el volumen |

---

## 🔍 Diagnóstico paso a paso

### Caso 1: Payment fallido

**Síntoma**: Usuario reporta que un cobro no aparece en el backend

**Pasos**:
1. Ir a `/sync-queue`
2. Filtrar por estado **"Fallidos"**
3. Buscar por `payment_uuid` o `terminal_id`
4. Click en 👁️ para ver el detalle
5. Revisar la sección **"Último Error"**:
   - `Timeout`: Verificar conexión a internet
   - `Backend error 500`: Revisar logs del backend
   - `Duplicate idempotency_key`: El payment ya fue sincronizado (verificar en backend)
6. Acciones:
   - **Reintentar** (🔄): Si el error fue transitorio
   - **Eliminar** (🗑️): Si el item está corrupto o duplicado

### Caso 2: Terminal sin sincronizar

**Síntoma**: Una terminal específica no sincroniza nada

**Pasos**:
1. Ir a `/sync-queue`
2. En el buscador, escribir el `terminal_id` (ej: `term-001`)
3. Verificar si hay items con ese terminal
4. Si hay muchos items **"Pendientes"** → verificar conexión de esa terminal
5. Si hay items **"Fallidos"** → revisar el error específico

### Caso 3: Cash session sin cerrar

**Síntoma**: Un cierre de caja no se refleja en el backend

**Pasos**:
1. Ir a `/sync-queue`
2. Filtrar por estado **"Pendientes"** o **"Fallidos"**
3. Buscar por `cash_session` (se muestra en la columna "Payment / Sesión")
4. Verificar el item con `entity_type = 'cash_session'` o `'cash_movement'`
5. Revisar el detalle para ver si hay error

### Caso 4: Cola vacía pero backend sin datos

**Síntoma**: `/sync-queue` muestra "Cola vacía" pero el backend no tiene los datos

**Posibles causas**:
1. Los items ya fueron sincronizados y luego eliminados (`cleanupOldSynced()`)
2. Los items nunca se encolaron (verificar `SyncQueueRepository.enqueue()`)
3. Bug en el backend al procesar el batch

**Pasos**:
1. Verificar el card "Sincronizados" — si es alto, los items sí se sincronizaron
2. Verificar logs del backend
3. Revisar `SyncEngine.processBatch()` para ver si hay errores

---

## 🛠️ Herramientas de diagnóstico

### Ver logs en consola del navegador
Consultar SQLite directamente (solo debugging)
```bash
# Abrir devtools (F12)
# Filtrar por "[SyncQueue]" o "[SyncEngine]"

# Abrir la base de datos local
sqlite3 ~/Library/Application\ Support/com.wokmesa.pos/local.db

# Ver items pendientes
SELECT * FROM sync_queue WHERE sync_status = 'pending' ORDER BY created_at DESC;

# Ver items fallidos con error
SELECT id, entity_type, entity_local_uuid, attempts, last_error 
FROM sync_queue 
WHERE sync_status = 'failed' 
ORDER BY updated_at DESC;

Forzar limpieza de items antiguos
// Desde consola del navegador (solo devs)
import { SyncQueueRepository } from '@/db/repositories/SyncQueueRepository';
await SyncQueueRepository.cleanupOldSynced();

⚠️ Errores comunes
Error
Causa probable
Solución
Timeout connecting to backend
Sin internet o backend caído
Verificar conexión, esperar y reintentar
Duplicate idempotency_key
Item ya fue sincronizado
Verificar en backend, eliminar si es duplicado
Payload invalid
Datos corruptos en el payload
Eliminar el item, verificar logs del servicio que lo creó
Backend error 400
Payload no pasa validación del backend
Revisar el payload en el modal, verificar formato
Backend error 500
Error interno del backend
Revisar logs del backend, reintentar más tarde
📚 Referencias
ADR-008: docs/adr/008-observabilidad-sincronizacion.md
SyncQueueRepository: src/db/repositories/SyncQueueRepository.ts
SyncQueueEnrichment: src/services/sync/SyncQueueEnrichment.ts
SyncEngine: src/services/sync/SyncEngine.ts
