# ADR-015: Idempotencia Scoping a Tenant

**Estado**: Aceptado  
**Fecha**: 2026-09-15  
**Decisores**: Arquitecto del sistema  
**Prioridad**: P0 (crítico para producción)

---

## Contexto

El sistema maneja múltiples tenants (empresas) en la misma base de datos. Las operaciones de pago y mutaciones críticas usan idempotencia para prevenir duplicados en caso de reintentos de red o timeouts.

### Problema identificado

Las migraciones originales definían constraints UNIQUE globales:
- `payments.idempotency_key` (global)
- `idempotency_keys.key` (global)
- `journal_entries.journal_entry_number` (global)

Esto causaba **colisiones cross-tenant**:
- Tenant A crea payment con `idempotency_key = "uuid-123"`
- Tenant B intenta crear payment con el mismo `idempotency_key = "uuid-123"`
- Base de datos rechaza el segundo payment (UNIQUE violation)
- **Violación de ADR-002 (multi-tenant isolation)**

### Riesgo de seguridad

1. **Cross-tenant data leakage**: Respuestas cacheadas de un tenant podrían retornarse a otro
2. **Denial of service**: Un tenant podría bloquear operaciones de otro usando las mismas keys
3. **Inconsistencia de datos**: Journal entries con números duplicados entre tenants

---

## Decisión

### Scope de idempotencia por tenant

**Alcance elegido**: `(company_id, branch_id, idempotency_key)` para payments, `(company_id, key)` para idempotency_keys.

**Justificación**:
- Dos terminales en la misma branch pueden reintentar con la misma key (legítimo)
- Dos branches del mismo tenant pueden usar la misma key (legítimo)
- Dos tenants NUNCA deben colisionar (protección P0)

### Implementación

#### 1. Migración: scope payments.idempotency_key

```php
// Antes: UNIQUE global
$table->unique(['idempotency_key']);

// Después: UNIQUE scoped
$table->unique(['company_id', 'branch_id', 'idempotency_key']);

2. Migración: scope idempotency_keys.key
// Antes: UNIQUE global
$table->unique(['key']);

// Después: UNIQUE scoped
$table->unique(['company_id', 'key']);

3. Migración: scope journal_entries.journal_entry_number
// Antes: UNIQUE global
$table->unique(['journal_entry_number']);

// Después: UNIQUE scoped
$table->unique(['company_id', 'journal_entry_number']);

4. Middleware: cache key scoped
// Cache key incluye company_id para prevenir cross-tenant leakage
$cacheKey = $hasTenantContext
    ? "idempotency:{$companyId}:{$idempotencyKey}"
    : "idempotency:global:{$idempotencyKey}";

5. PaymentService: búsqueda con scope
// Buscar idempotency_key con scope de tenant
$existing = Payment::where('company_id', $order->company_id)
    ->where('branch_id', $order->branch_id)
    ->where('idempotency_key', $idempotencyKey)
    ->first();

6. IdempotencyKey: sin global scopes para registros sin tenant
// Sin tenant context (tests legacy): deshabilitar global scopes
$query = IdempotencyKey::withoutGlobalScopes()
    ->whereNull('company_id');

Consecuencias
Positivas
✅ Protección P0 garantizada: Dos tenants no pueden colisionar
✅ Idempotencia funciona: Mismo tenant con misma key → respuesta cacheada
✅ Conflict detection: Mismo tenant con misma key + diferente body → 409
✅ Journal entries independientes: Cada tenant tiene su propia secuencia
✅ Compatibilidad con tests legacy: Registros sin tenant funcionan
Negativas
⚠️ Migración destructiva: Requiere eliminar constraints globales y crear scoped
⚠️ Complejidad adicional: Middleware debe manejar registros con y sin tenant
⚠️ Breaking change: Cualquier código que dependa de uniqueness global fallará
Riesgos mitigados
Backfill de datos: Migración incluye UPDATE ... FROM users para backfill
Tests legacy: Registros sin tenant usan withoutGlobalScopes()
Rollback: Migración down() restaura constraints globales

Alternativas consideradas
Alternativa 1: Usar Redis como única fuente de verdad
Idea: Eliminar tabla idempotency_keys y usar solo Redis con TTL.
Rechazada porque:
❌ Redis puede perder datos (crash, restart)
❌ No hay audit trail permanente
❌ Dificulta debugging de problemas de idempotencia
❌ No cumple con ADR-006 (event sourcing híbrido)
Alternativa 2: Usar UUIDs aleatorios sin scope
Idea: Generar UUIDs completamente aleatorios y confiar en que no colisionen.

Rechazada porque:
❌ Probabilidad de colisión no es cero (aunque muy baja)
❌ No previene ataques maliciosos (tenant A usa UUID de tenant B)
❌ No hay protección a nivel de DB (última línea de defensa)
Alternativa 3: Scope solo por company_id (sin branch_id)
Idea: UNIQUE (company_id, idempotency_key) para payments.
Rechazada porque:
❌ Dos terminales en la misma branch no podrían reintentar con la misma key
❌ Dos branches del mismo tenant colisionarían (innecesario)
✅ Opción elegida: (company_id, branch_id, idempotency_key) es más permisiva

Validación
Tests implementados
Archivo: tests/Feature/CrossTenantIdempotencyTest.php
Caso 1: Dos tenants pueden usar la misma key sin colisionar

// Tenant A crea payment con key "uuid-123"
$responseA = $this->actingAs($this->userA, 'api')
    ->postJson("/api/v1/billing/payments", [...], ['Idempotency-Key' => 'uuid-123']);
$responseA->assertStatus(201);

// Tenant B crea payment con la MISMA key
$responseB = $this->actingAs($this->userB, 'api')
    ->postJson("/api/v1/billing/payments", [...], ['Idempotency-Key' => 'uuid-123']);
$responseB->assertStatus(201);

// CRÍTICO: Ambos payments deben existir (no colisionan)
expect($responseB->json('data.uuid'))->not->toBe($responseA->json('data.uuid'));

Caso 2: Mismo tenant con misma key + mismo body → idempotencia funciona

// Primer request
$response1 = $this->actingAs($this->userA, 'api')
    ->postJson("/api/v1/billing/payments", [...], ['Idempotency-Key' => 'uuid-456']);

// Segundo request (reintento)
$response2 = $this->actingAs($this->userA, 'api')
    ->postJson("/api/v1/billing/payments", [...], ['Idempotency-Key' => 'uuid-456']);

// CRÍTICO: Debe retornar el MISMO payment (respuesta cacheada)
expect($response2->json('data.uuid'))->toBe($response1->json('data.uuid'));

Caso 3: Mismo tenant con misma key + diferente body → 409 conflict

// Primer request
$response1 = $this->actingAs($this->userA, 'api')
    ->postJson("/api/v1/billing/payments", ['amount' => 1000], ['Idempotency-Key' => 'uuid-789']);

// Segundo request con MISMA key pero DIFERENTE amount
$response2 = $this->actingAs($this->userA, 'api')
    ->postJson("/api/v1/billing/payments", ['amount' => 2000], ['Idempotency-Key' => 'uuid-789']);

// CRÍTICO: Debe retornar 409 conflict
$response2->assertStatus(409);

Resultado de tests
✓ CrossTenantIdempotencyTest: 3 passed
✓ Suite completa: 878 passed (2517 assertions)
✓ 0 tests fallando

Archivos modificados
Migraciones (3 archivos)
database/migrations/2026_09_15_000001_scope_idempotency_keys_to_tenant.php
Agrega company_id, branch_id a tabla idempotency_keys
Reemplaza UNIQUE (key) por UNIQUE (company_id, key)
Backfill desde users table
database/migrations/2026_09_15_000002_scope_payments_idempotency_key_to_tenant.php
Reemplaza UNIQUE (idempotency_key) por UNIQUE (company_id, branch_id, idempotency_key)
database/migrations/2026_09_15_000003_scope_journal_entry_number_to_tenant.php
Reemplaza UNIQUE (journal_entry_number) por UNIQUE (company_id, journal_entry_number)
Middleware y Services (2 archivos)

Middleware y Services (2 archivos)
app/Shared/Http/Middleware/IdempotencyKeyMiddleware.php
Cache key incluye company_id para scope de tenant
Búsqueda en DB con scope de tenant
Manejo de registros sin tenant (tests legacy)
app/Modules/Payments/Domain/Services/PaymentService.php
Búsqueda de idempotency_key con scope de tenant
Entity (1 archivo)
app/Shared/Domain/Entities/IdempotencyKey.php
Agrega company_id, branch_id al $fillable
Usa trait BelongsToTenant para global scopes

Tests (1 archivo nuevo)
tests/Feature/CrossTenantIdempotencyTest.php
3 tests validando protección P0

Criterio de cierre
✅ Cumplido:
Todas las tablas críticas tienen constraints UNIQUE scoped por tenant
Middleware usa cache keys scoped por tenant
PaymentService busca idempotency_keys con scope de tenant
Tests validan los 3 escenarios críticos
Suite completa: 878 tests pasando
✅ Protección P0 garantizada:
Dos tenants pueden usar la misma idempotency_key sin colisionar
Mismo tenant con misma key + mismo body → idempotencia funciona
Mismo tenant con misma key + diferente body → 409 conflict
✅ Integridad financiera:
Journal entries tienen secuencia independiente por tenant
No hay riesgo de colisiones cross-tenant en el ledger

Referencias
ADR-002: Multi-tenant isolation (base de esta decisión)
ADR-006: Event sourcing híbrido offline-first (contexto de idempotencia)
ADR-012: Local multi-tenancy (principios aplicados)
ADR-013: Tenant immutability (consistencia con protección de tenant)
ADR-014: Fail-secure auth (principio de seguridad defensiva)

Changelog
Fecha	Versión	Cambios
2026/9/15	1	Versión inicial

