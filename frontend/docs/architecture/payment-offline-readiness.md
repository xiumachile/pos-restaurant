# Payment Offline Readiness - Criterios GO/NO-GO

**Estado**: FASE 0 - BLOQUEO DE DESARROLLO
**Rama**: `hardening/payment-offline-foundation`
**Fecha**: 2026-09-07
**Decisión**: Congelar pagos offline hasta que backend financiero sea estable

---

## 🎯 Objetivo

Garantizar que el backend financiero es sólido y confiable **antes** de implementar pagos offline.

**Riesgo de saltar esta fase**:
- Doble cobro por reintentos
- Estados inconsistentes entre tablas
- Pérdida de pagos durante sincronización
- Violaciones de integridad financiera

---

## 📊 Criterios GO/NO-GO

### FASE 1 — INTEGRIDAD FINANCIERA (P0)

#### 1.1 Doble actualización de Bill
**Problema identificado**: PaymentService y CashierTableService ambos actualizan `paid_amount`

**Criterio GO**:
- [ ] Solo PaymentService actualiza Bill.paid_amount
- [ ] CashierTableService NO modifica campos financieros
- [ ] Test que verifica: pagar 2 veces con misma idempotency_key NO incrementa paid_amount
- [ ] Test que verifica: pagar desde Terminal A y Terminal B simultáneamente produce resultado consistente

**Test de aceptación**:
```bash
# Terminal A: POST /cashier/bills/{uuid}/pay con idempotency_key=ABC
# Terminal B: POST /cashier/bills/{uuid}/pay con idempotency_key=ABC (mismo key)
# Resultado esperado: Solo 1 pago registrado, paid_amount incrementado 1 vez
1.2 Idempotencia realmente segura
Problema identificado: Reintentos pueden causar doble cobro
Criterio GO:
IdempotencyMiddleware verifica existencia de Payment ANTES de procesar
PaymentService usa transacción atómica: Payment + Ledger + Bill + Order + Table
Si transacción falla, NO hay cambios parciales
Test de timeout durante pago: verificar rollback completo
Test de crash durante pago: verificar rollback completo
Test de refresh de página durante pago: verificar idempotencia
Test de aceptación:
# 1. Iniciar pago
# 2. Simular timeout a mitad de transacción
# 3. Reintentar con misma idempotency_key
# Resultado esperado: Pago completado exactamente 1 vez
1.3 Endpoints de pago unificados
Problema identificado: Dos endpoints con reglas diferentes
/cashier/bills/{uuid}/pay
/billing/payments
Criterio GO:
Un solo endpoint oficial documentado
Endpoint deprecado marcado con @deprecated
Migración de frontend al endpoint oficial
Test que verifica: ambos endpoints producen mismo resultado
Decisión recomendada:
Oficial: /cashier/bills/{uuid}/pay (más específico, mejor REST)
Deprecado: /billing/payments (mantener por compatibilidad, loggear warning)
1.4 Validación de relaciones de dominio
Problema identificado: No se valida que todo pertenezca al mismo contexto
Criterio GO:
PaymentController valida: Company → Branch → Table → Order → Bill → Payment
Test: intentar pagar Bill de otra Branch → 403 Forbidden
Test: intentar pagar Bill de otra Company → 403 Forbidden
Test: intentar pagar Bill sin Order asociado → 422 Unprocessable
Test de aceptación:
// Test: Cross-branch payment attempt
$bill = Bill::factory()->for($branchA)->create();
$response = $this->postJson("/api/branches/{$branchB->id}/cashier/bills/{$bill->uuid}/pay", [...]);
$response->assertStatus(403);
1.5 Concurrencia
Problema identificado: Dos terminales pagando simultáneamente
Criterio GO:
PaymentService usa lockForUpdate() en Bill
Transacción con isolation level SERIALIZABLE
Unique constraint en idempotency_key
Test: 10 pagos concurrentes con misma idempotency_key → solo 1 éxito
Test: 10 pagos concurrentes con diferentes keys → todos éxito, sin corrupción
Test de aceptación:
// Test: Concurrent payments
$promises = [];
for ($i = 0; $i < 10; $i++) {
    $promises[] = Http::post("/api/cashier/bills/{$bill->uuid}/pay", [
        'idempotency_key' => 'same-key-for-all'
    ]);
}
$responses = Promise\settle($promises)->wait();
$successCount = count(array_filter($responses, fn($r) => $r['state'] === 'fulfilled'));
$this->assertEquals(1, $successCount);
🔍 Diagnóstico del Backend
Comandos de diagnóstico
cd ~/pos-restaurant/backend

# 1. Ejecutar todos los tests de pagos
./vendor/bin/pest --filter=Payment

# 2. Verificar cobertura de tests
./vendor/bin/pest --coverage --filter=Payment

# 3. Buscar doble actualización de paid_amount
grep -rn "paid_amount.*+=" app/Services/PaymentService.php
grep -rn "paid_amount.*+=" app/Services/CashierTableService.php

# 4. Verificar idempotencia
grep -rn "idempotency_key" app/Http/Middleware/
grep -rn "idempotency_key" app/Services/PaymentService.php

# 5. Listar endpoints de pago
grep -rn "pay" routes/api.php | grep -i payment
Preguntas críticas
¿Qué pasa si PaymentService falla a mitad de transacción?
¿Hay rollback de Bill.paid_amount?
¿Hay rollback de Ledger?
¿Hay rollback de Table.status?
¿Qué pasa si dos terminales pagan simultáneamente?
¿Hay lock en la fila de Bill?
¿Hay transacción atómica?
¿Qué isolation level usa MySQL?
¿Qué pasa si el frontend reenvía el mismo pago?
¿IdempotencyMiddleware verifica antes de procesar?
¿Retorna el Payment existente o crea uno nuevo?
¿Loggea el reintento?
📋 Checklist de Ejecución
FASE 0 - Setup
Crear rama hardening/payment-offline-foundation
Crear documento payment-offline-readiness.md
Ejecutar diagnóstico del backend
Documentar hallazgos
FASE 1.1 - Doble actualización
Identificar todas las actualizaciones de paid_amount
Eliminar actualizaciones duplicadas
Escribir test de idempotencia
Verificar que solo PaymentService actualiza Bill
FASE 1.2 - Idempotencia segura
Revisar IdempotencyMiddleware
Verificar transacción atómica en PaymentService
Test de timeout durante pago
Test de crash durante pago
Test de refresh durante pago
FASE 1.3 - Endpoints unificados
Identificar endpoints duplicados
Marcar endpoint deprecado con @deprecated
Migrar frontend al endpoint oficial
Test de paridad entre endpoints
FASE 1.4 - Validación de dominio
Agregar validación de Company/Branch en PaymentController
Test de cross-branch payment (debe fallar)
Test de cross-company payment (debe fallar)
FASE 1.5 - Concurrencia
Agregar lockForUpdate() en PaymentService
Configurar isolation level SERIALIZABLE
Agregar unique constraint en idempotency_key
Test de 10 pagos concurrentes
🚦 Decisión GO/NO-GO
Criterios para continuar con Pagos Offline
GO si:
Todos los tests de FASE 1 pasan
Cobertura de tests > 90% en PaymentService
No hay actualizaciones duplicadas de paid_amount
Idempotencia verificada en 5 escenarios de fallo
Concurrencia probada con 10 pagos simultáneos
Code review aprobado por 2 desarrolladores
NO-GO si:
Algún test de FASE 1 falla
Cobertura < 90%
Doble actualización aún existe
Idempotencia no está verificada
Concurrencia no está probada
📅 Timeline Estimado
Fase
Duración
Dependencias
FASE 0 - Diagnóstico
2-4 horas
Ninguna
FASE 1.1 - Doble actualización
4-6 horas
Diagnóstico
FASE 1.2 - Idempotencia
6-8 horas
FASE 1.1
FASE 1.3 - Endpoints
2-3 horas
FASE 1.1
FASE 1.4 - Validación
3-4 horas
FASE 1.1
FASE 1.5 - Concurrencia
4-6 horas
FASE 1.2
Total FASE 1
21-31 horas
-
🎯 Siguiente Acción Inmediata
Diagnóstico del backend (próximos 30 minutos):
bash

1234567891011121314
Resultado esperado: Lista de problemas específicos con ubicación exacta en el código.
📝 Notas
No escribir código de pagos offline hasta que FASE 1 esté completa
Documentar cada hallazgo en este documento
Crear tests primero (TDD) antes de refactorizar
Commits pequeños y atómicos (uno por problema resuelto)
Autor: Arquitectura WokMesa
Revisado por: [Pendiente]
Aprobado por: [Pendiente]
