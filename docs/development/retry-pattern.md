# Patrón: Retry on Local Failure

**Contexto**: Sistema offline-first con sync queue  
**Estado**: Aceptado  
**Fecha**: Septiembre 2026

## Problema

En operaciones que involucran **backend + SQLite local**, hay un orden crítico:
1. Operación en backend (éxito) ✅
2. Actualización en SQLite local (puede fallar) ❌
3. Retornar éxito al usuario


Si el paso 2 falla después del paso 1 exitoso, queda una **inconsistencia silenciosa**:
- Backend: operación completada ✅
- SQLite: operación no aplicada ❌
- Usuario: ve que todo salió bien ✅

## Casos donde se aplica

| Operación | Riesgo si no se aplica |
|-----------|------------------------|
| Apertura de caja | Sesión abierta en backend pero no en SQLite |
| Cierre de caja | Sesión cerrada en backend pero SQLite cree que está abierta |
| Creación de órdenes | Orden existe en backend pero no en carrito local |
| Registro de pagos | Pago registrado en backend pero balance local desactualizado |

## Patrón recomendado

```typescript
try {
  // 1. Intentar operación local
  await LocalRepository.create(payload);
  console.log(`[Service] ✅ Operación local exitosa`);
} catch (localErr) {
  // 2. Si falla tras éxito en backend: encolar retry
  console.error("[Service] ❌ Error crítico: operación en backend pero no en SQLite");
  
  try {
    await SyncQueueRepository.enqueue({
      company_id: ctx.company_id,
      branch_id: ctx.branch_id,
      entity_type: "cash_session",  // o la entidad correspondiente
      entity_local_uuid: backendId,  // usar cloud_id del backend
      action: "create",  // o "update"
      payload: { /* datos para reconstruir */ },
    });
    console.log("[Service] ✅ Retry encolado en sync_queue");
  } catch (enqueueErr) {
    // 3. Si TODO falla: lanzar error (no silenciar)
    console.error("[Service] ❌ FALLA CRÍTICA: no se pudo encolar retry");
    throw new Error("INCONSISTENCIA: operación en backend pero no se pudo actualizar localmente ni encolar retry");
  }
}

Principios clave
1. Fail-safe, no fail-silent
❌ Mal:

catch (err) {
  console.warn("Error:", err);
  // Silenciosamente continua
}

✅ Bien:
catch (err) {
  console.error("Error crítico:", err);
  // Intentar recovery o lanzar error claro
  throw new Error("Mensaje descriptivo del problema");
}

2. Usar cloud_id como entity_local_uuid en retry
Cuando el update local falla pero el backend ya procesó la operación, usa el cloud_id del backend como entity_local_uuid en el sync_queue.
SyncEngine debe buscar primero por local_uuid y si no encuentra, por cloud_id.
3. Parámetros opcionales para contexto
Los repositorios deben aceptar parámetros opcionales para manejar tanto el caso "offline primero" como "online con retry":

static async close(
  uuidOrCloudId: string,  // acepta ambos
  closingAmount: number,
  syncStatus: 'pending' | 'synced' = 'pending'  // default offline
): Promise<void> {
  // ...
}

Implementaciones actuales
Archivo
Método
Estado
paymentsService.ts
openSession
✅ Implementado
paymentsService.ts
closeSession
✅ Implementado
paymentsService.ts
getDashboard (sync)
✅ Implementado
Casos que NO requieren retry
Lectura pura: si falla leer desde SQLite, el UI simplemente muestra estado vacío
Operaciones solo-locales: sin backend involucrado, no hay inconsistencia
Fallbacks defensivos: el fallback es parte del diseño (ej: backend→SQLite en catalog)
Testing
Todo patrón retry debe tener al menos estos tests:
✅ Operación normal (sin fallo)
✅ Fallback a cloud_id cuando local_uuid no existe
✅ Encolado en sync_queue tras fallo
✅ SyncEngine procesa el retry correctamente
Ejemplo: src/tests/services/paymentsService.retry.test.ts
Referencias
Commit d30298c: implementación inicial del patrón
ADR-010: Estrategia Money (integridad monetaria)
SyncEngine.ts: handlers que soportan búsqueda por cloud_id
