# Arquitectura de Seguridad Multi-Tenant (ADR-012)

## Estado: ✅ VALIDADO (Fase D - Septiembre 2026)

## Resumen

El sistema implementa aislamiento multi-tenant mediante **Global Scopes
automáticos** con diseño **fail-closed**. Todos los datos están aislados
por `company_id` y `branch_id` sin requerir intervención del desarrollador.

## Mecanismo

### Trait BelongsToTenant

```php
trait BelongsToTenant {
    protected static function bootBelongsToTenant(): void {
        static::addGlobalScope(new CompanyScope());   // Siempre
        if (static::hasBranchColumn()) {
            static::addGlobalScope(new BranchScope()); // Si aplica
        }
        // Auto-asignación en creating...
    }
}
Efecto en queries
Toda query Eloquent sobre modelos con el trait se filtra automáticamente:
-- Código PHP:
Category::where('uuid', $uuid)->first();

-- SQL ejecutado:
SELECT * FROM categories 
WHERE uuid = ? 
  AND company_id = ?    -- Inyectado por CompanyScope
  AND branch_id = ?;    -- Inyectado por BranchScope

Cobertura
38 modelos con BelongsToTenant
60 tests de aislamiento pasando
Fail-closed: sin tenant context → excepción
Modelos protegidos
Orders, Bills, Payments, CashSessions, Products, Categories, Menus,
Tables, InventoryItems, JournalEntries, LedgerEntries, DteDocuments,
AuditLogs, Branches, Recipes, y más.
Excepciones controladas
1. Autenticación JWT
JwtUserProvider usa withoutGlobalScopes() para buscar usuarios
antes de conocer su tenant. Esto es correcto y necesario.
2. CapabilityKey (enum)
Las capabilities son definiciones globales del sistema, no datos
de tenant. No requieren aislamiento.
3. Queries DB::table() directas
⚠️ DB::table() bypass-ea GlobalScope. Usar solo cuando:
Se filtra explícitamente por company_id Y branch_id
O se usa en contexto de sistema (migraciones, comandos)
Tests de validación
CrossTenantIsolationTest
MultiTenantIsolationTest
TenantContextRestrictionTest
TenantFailClosedTest
TenantFailClosedEdgeCasesTest
CrossTenantIdempotencyTest
SecurityCrossTenantAuditTest
Recomendaciones
✅ Preferir Eloquent sobre DB::table() para datos de tenant
✅ Usar BelongsToTenant en todos los modelos con company_id
✅ No usar withoutGlobalScopes() fuera de autenticación/sistema
✅ Validar en tests el aislamiento al agregar nuevos modelos
