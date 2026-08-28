# ADR-002: Aislamiento Multi-Tenant

**Status:** Accepted
**Date:** 2026-08-28
**Deciders:** Equipo de Desarrollo
**Sprint:** S1 - Consolidación de Seguridad

---

## Context

POS Restaurant es una aplicación SaaS multi-tenant donde múltiples restaurantes (companies) comparten la misma infraestructura. Cada company tiene una o más sucursales (branches). El riesgo principal es **cross-tenant data leakage**: que un usuario de Company A pueda acceder, modificar o eliminar datos de Company B.

Necesitamos decidir:
- ¿Cómo prevenir que queries filtren datos entre tenants?
- ¿Cómo validar ownership en servicios y controllers?
- ¿Cómo manejar contextos sin autenticación (jobs, listeners)?

---

## Decision

**Arquitectura de 3 capas para aislamiento multi-tenant:**

### Capa 1: TenantContextMiddleware
Establece el contexto de tenant SIEMPRE desde el usuario autenticado.

```php
public function handle(Request $request, Closure $next)
{
    $user = $request->user();
    if ($user) {
        $this->tenantContext->setCompany(
            companyId: $user->company_id,
            branchId: $user->branch_id,
            userId: $user->id,
        );
    }
    return $next($request);
}

### Capa 2: BelongsToTenant Trait
Aplicado a entidades tenant-scoped. Añade CompanyScope y BranchScope globales que filtran automáticamente por tenant.

```php
class Product extends Model
{
    use BelongsToTenant;
}

// Query filtra automáticamente por company_id del TenantContext
Product::all();  // SELECT * FROM products WHERE company_id = ?

---

## Amenazas Corregidas (S1)

| # | Amenaza | Riesgo | Fix |
|---|---------|--------|-----|
| THREAT-001 | PaymentQueryService sin tenant filter | Alta | TenantContext + validación ownership |
| THREAT-002 | ComboReplacementRuleController sin tenant filter | Alta | TenantContext + 4 validaciones |
| THREAT-003 | MenuResolutionService | NO-amenaza | Caller ya valida |
| THREAT-004 | RecipeService sin tenant filter | Alta | TenantContext + validación ownership |
| THREAT-005 | SyncService sin tenant filter | Alta | Validación defensiva |
| THREAT-006 | BranchQueryService sin tenant filter | Alta | TenantContext + validación ownership |
| THREAT-007 | CashierTablesController sin tenant filter | Alta | Validación defensiva |

**Resultado:** 7 amenazas resueltas, 590+ tests validando aislamiento.

---

## Security Requirements

### Autenticación
- Todas las rutas protegidas requieren JWT válido
- TenantContextMiddleware establece contexto desde usuario autenticado
- NO se acepta `company_id` o `branch_id` desde request body/headers

### Autorización
- Services validan ownership antes de operar
- BelongsToTenant trait filtra queries automáticamente
- Policies validan permisos por instancia

---

## Consequences

### Positive
1. **Defensa en profundidad**: 3 capas independientes previenen leaks
2. **Automático**: BelongsToTenant filtra sin código explícito
3. **Consistente**: mismo patrón en todos los módulos
4. **Testable**: CrossTenantIsolationTest valida comportamiento

### Negative
1. **Overhead de TenantContext**: cada request setea contexto
2. **Sin contexto en jobs**: deben propagar tenant explícitamente
3. **Validaciones duplicadas**: scopes + validaciones defensivas

---

## Alternatives Considered

### Alternativa 1: Solo Scopes Globales
Confiar solo en BelongsToTenant, sin validaciones en services.
**Decisión:** Rechazada. Se necesita defensa en profundidad.

### Alternativa 2: Middleware por Endpoint
Cada endpoint valida tenant manualmente.
**Decisión:** Rechazada. Propenso a errores humanos.

### Alternativa 3: Row-Level Security (PostgreSQL)
Filtrar a nivel de base de datos con RLS.
**Decisión:** Rechazada. Over-engineering para nuestro caso.

---

## Testing Requirements

### Tests Obligatorios
- CrossTenantIsolationTest: valida que usuario A no ve datos de B
- Tests por cada service crítico (PaymentQuery, RecipeService, BranchQuery)
- Tests de propagation en jobs/listeners

**Cobertura actual:** 606 tests pasando, incluyendo 6 cross-tenant tests.

---

## Implementation

### Archivos Relevantes
app/Shared/Application/TenantContext.php
app/Shared/Http/Middleware/TenantContextMiddleware.php
app/Shared/Domain/Traits/BelongsToTenant.php
app/Shared/Domain/Scopes/CompanyScope.php
app/Shared/Domain/Scopes/BranchScope.php
tests/Feature/CrossTenantIsolationTest.php

---

## Related ADRs

- **ADR-001**: Arquitectura de Autenticación JWT
- **ADR-003**: Patrón Controller → Service → Domain

---

## Changelog

| Fecha | Versión | Cambios |
|-------|---------|---------|
| 2026-08-28 | 1.0 | Versión inicial (basada en S1) |
