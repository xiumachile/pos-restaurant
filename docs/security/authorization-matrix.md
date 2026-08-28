# Matriz de Autorización Multi-Tenant

**Versión:** 1.0
**Fecha:** 2026-08-28
**Estado:** Activo
**Sprint:** S1 - Consolidación de Seguridad

---

## Resumen Ejecutivo

Esta matriz define formalmente los permisos de acceso por rol en el sistema POS Restaurant. Todos los módulos implementan aislamiento multi-tenant mediante:

1. **Autenticación JWT** (guard `api`)
2. **TenantContextMiddleware** (establece company_id/branch_id desde usuario autenticado)
3. **Validación de ownership** en servicios (THREAT-001 a THREAT-007 corregidos)
4. **Policies** para autorización granular por instancia

---

## Roles del Sistema

| Rol | Descripción | Alcance |
|-----|-------------|---------|
| `admin` | Administrador de empresa | Acceso completo a toda la empresa (todas las branches) |
| `manager` | Gerente de sucursal | Acceso completo a su branch, lectura de otras branches de su empresa |
| `cashier` | Cajero | Operaciones de caja, pagos, arqueos en su branch |
| `waiter` | Mesero | Gestión de pedidos propios en su branch |
| `kitchen` | Cocina | Gestión de pedidos en cola de cocina en su branch |
| `supervisor` | Supervisor | Supervisión de arqueos y operaciones sensibles |

---

## Matriz de Permisos por Módulo

### Orders (Pedidos)

| Acción | admin | manager | cashier | waiter | kitchen | Notas |
|--------|-------|---------|---------|--------|---------|-------|
| GET /v1/orders | ✅ Todas | ✅ Su branch | ✅ Su branch | ✅ Solo propios | ✅ En cocina | |
| GET /v1/orders/{id} | ✅ | ✅ | ✅ | ✅ Si es propio | ✅ Si está en cocina | OrderPolicy |
| POST /v1/orders | ✅ | ✅ | ❌ | ✅ | ❌ | Solo crear |
| PUT /v1/orders/{id} | ✅ | ✅ | ❌ | ✅ Si propio+draft | ❌ | OrderPolicy |
| DELETE /v1/orders/{id} | ✅ | ✅ | ❌ | ✅ Si propio+draft | ❌ | OrderPolicy |
| POST /v1/orders/{id}/confirm | ✅ | ✅ | ❌ | ✅ Si propio | ❌ | StateMachine |
| POST /v1/orders/{id}/pay | ✅ | ✅ | ✅ | ❌ | ❌ | Cashier only |
| POST /v1/orders/{id}/close | ✅ | ✅ | ✅ | ❌ | ❌ | Cashier only |

**Aislamiento:**
- Usuario solo ve pedidos de su `branch_id` (TenantContext)
- Waiter solo ve pedidos donde `waiter_id = user.id` (OrderPolicy)
- Kitchen solo ve pedidos en estados `confirmed`, `preparing`

---

### Tables (Mesas)

| Acción | admin | manager | cashier | waiter | kitchen |
|--------|-------|---------|---------|--------|---------|
| GET /v1/tables | ✅ | ✅ | ✅ | ✅ | ✅ |
| POST /v1/tables | ✅ | ✅ | ❌ | ❌ | ❌ |
| PUT /v1/tables/{id} | ✅ | ✅ | ❌ | ❌ | ❌ |
| DELETE /v1/tables/{id} | ✅ | ✅ | ❌ | ❌ | ❌ |

**Aislamiento:** Usuario solo ve mesas de su branch. Validación defensiva en CashierTablesController (THREAT-007).

---

### Payments (Pagos)

| Acción | admin | manager | cashier | waiter | kitchen |
|--------|-------|---------|---------|--------|---------|
| GET /v1/payments | ✅ | ✅ | ✅ | ❌ | ❌ |
| POST /v1/billing/payments | ✅ | ✅ | ✅ | ❌ | ❌ |
| GET /v1/cash-sessions | ✅ | ✅ | ✅ | ❌ | ❌ |
| POST /v1/cash-sessions/open | ✅ | ✅ | ✅ | ❌ | ❌ |

**Aislamiento:** PaymentQueryService valida ownership de cash_session_id (THREAT-001).

---

### Cashier (Caja)

| Acción | admin | manager | cashier | waiter | kitchen |
|--------|-------|---------|---------|--------|---------|
| GET /v1/cashier/dashboard | ✅ | ✅ | ✅ | ❌ | ❌ |
| GET /v1/cashier/registers | ✅ | ✅ | ✅ | ❌ | ❌ |
| POST /v1/cashier/counts | ✅ | ✅ | ✅ | ❌ | ❌ |
| POST /v1/cashier/counts/{id}/supervise | ✅ | ✅ | ❌ | ❌ | ❌ |
| GET /v1/cashier/reports/z-report | ✅ | ✅ | ❌ | ❌ | ❌ |

---

### Catalog (Catálogo)

| Acción | admin | manager | cashier | waiter | kitchen |
|--------|-------|---------|---------|--------|---------|
| GET /v1/catalog/products | ✅ | ✅ | ✅ | ✅ | ✅ |
| POST /v1/catalog/products | ✅ | ✅ | ❌ | ❌ | ❌ |
| PUT /v1/catalog/products/{id} | ✅ | ✅ | ❌ | ❌ | ❌ |
| DELETE /v1/catalog/products/{id} | ✅ | ✅ | ❌ | ❌ | ❌ |
| PUT combos/substitution-policy | ✅ | ✅ | ❌ | ❌ | ❌ |

**Aislamiento:** ComboReplacementRuleController valida ownership de menuItem, product, category, branch (THREAT-002).

---

### Kitchen (Cocina)

| Acción | admin | manager | cashier | waiter | kitchen |
|--------|-------|---------|---------|--------|---------|
| GET /v1/kitchen/queue | ✅ | ✅ | ❌ | ❌ | ✅ |
| POST /v1/kitchen/orders/{id}/priority | ✅ | ✅ | ❌ | ❌ | ✅ |
| POST /v1/kitchen/orders/{id}/assign-cook | ✅ | ✅ | ❌ | ❌ | ✅ |

---

### Inventory (Inventario)

| Acción | admin | manager | cashier | waiter | kitchen |
|--------|-------|---------|---------|--------|---------|
| GET /v1/inventory | ✅ | ✅ | ❌ | ❌ | ❌ |
| POST /v1/inventory | ✅ | ✅ | ❌ | ❌ | ❌ |
| PUT /v1/inventory/{id} | ✅ | ✅ | ❌ | ❌ | ❌ |
| POST /v1/inventory/{id}/movement | ✅ | ✅ | ❌ | ❌ | ❌ |

---

### Recipes (Recetas)

| Acción | admin | manager | cashier | waiter | kitchen |
|--------|-------|---------|---------|--------|---------|
| GET /v1/recipes | ✅ | ✅ | ❌ | ❌ | ✅ |
| POST /v1/recipes | ✅ | ✅ | ❌ | ❌ | ❌ |
| PUT /v1/recipes/{id} | ✅ | ✅ | ❌ | ❌ | ❌ |
| GET /v1/recipes/ingredients | ✅ | ✅ | ❌ | ❌ | ✅ |

**Aislamiento:** RecipeService valida ownership de ingredientes (THREAT-004).

---

### Audit (Auditoría)

| Acción | admin | manager | cashier | waiter | kitchen |
|--------|-------|---------|---------|--------|---------|
| GET /v1/audit-logs | ✅ | ✅ | ❌ | ❌ | ❌ |
| GET /v1/audit-logs/actions | ✅ | ✅ | ❌ | ❌ | ❌ |

**Restricción:** Middleware `role:admin,manager`

---

### Fiscal (Documentos Tributarios)

| Acción | admin | manager | cashier | waiter | kitchen |
|--------|-------|---------|---------|--------|---------|
| GET /v1/fiscal/dtes | ✅ | ✅ | ✅ | ❌ | ❌ |
| POST /v1/fiscal/dtes | ✅ | ✅ | ✅ | ❌ | ❌ |
| POST /v1/fiscal/dtes/{id}/cancel | ✅ | ✅ | ❌ | ❌ | ❌ |
| POST /v1/fiscal/certificates | ✅ | ✅ | ❌ | ❌ | ❌ |

---

### Sync (Sincronización)

| Acción | admin | manager | cashier | waiter | kitchen |
|--------|-------|---------|---------|--------|---------|
| GET /v1/sync/health | ✅ | ✅ | ✅ | ✅ | ✅ |
| POST /v1/sync/push | ✅ | ✅ | ✅ | ✅ | ✅ |
| POST /v1/sync/pull | ✅ | ✅ | ✅ | ✅ | ✅ |

**Aislamiento:** SyncService valida ownership de entidades (THREAT-005).

---

### Tax (Impuestos)

| Acción | admin | manager | cashier | waiter | kitchen |
|--------|-------|---------|---------|--------|---------|
| GET /v1/taxes | ✅ | ✅ | ✅ | ✅ | ✅ |
| POST /v1/taxes | ✅ | ✅ | ❌ | ❌ | ❌ |
| PUT /v1/taxes/{id} | ✅ | ✅ | ❌ | ❌ | ❌ |

**Test:** TaxAuthorizationTest valida que cashier recibe 403 al crear.

---

## Broadcasting Channels

| Canal | admin | manager | cashier | waiter | kitchen | Validación |
|-------|-------|---------|---------|--------|---------|------------|
| `kitchen.{branchId}` | ✅ | ✅ | ❌ | ❌ | ✅ | role + branch_id |
| `waiters.{branchId}` | ✅ | ✅ | ❌ | ✅ | ❌ | role + branch_id |
| `dashboard.{companyId}` | ✅ | ✅ | ❌ | ❌ | ❌ | role + company_id |

---

## Amenazas Corregidas (S1)

| # | Amenaza | Estado | Validación |
|---|---------|--------|------------|
| 001 | PaymentQueryService sin tenant filter | ✅ CORREGIDA | 53 tests |
| 002 | ComboReplacementRuleController | ✅ CORREGIDA | 17 tests |
| 003 | MenuResolutionService | ✅ NO ES AMENAZA | Caller ya valida |
| 004 | RecipeService sin tenant filter | ✅ CORREGIDA | 23 tests |
| 005 | SyncService sin tenant filter | ✅ CORREGIDA | 61 tests |
| 006 | BranchQueryService sin tenant filter | ✅ CORREGIDA | 3 cross-tenant |
| 007 | CashierTablesController | ✅ CORREGIDA | 3 cross-tenant |

**Total:** 590 tests pasando, 1670 assertions

---

## Reglas de Seguridad

### Autenticación
- ✅ Todas las rutas protegidas requieren JWT (guard `api`)
- ✅ TenantContextMiddleware establece contexto desde usuario autenticado
- ❌ NO se acepta `company_id` o `branch_id` desde request

### Autorización
- ✅ Policies validan permisos por instancia (OrderPolicy)
- ✅ Servicios validan ownership (defensa en profundidad)
- ✅ Middleware de roles restringe acceso por endpoint (CheckRole)

### Aislamiento Multi-Tenant
- ✅ Usuario solo ve datos de su `company_id`
- ✅ Usuario solo ve datos de su `branch_id`
- ✅ Queries filtran automáticamente (BelongsToTenant trait)
- ✅ Validación defensiva en servicios críticos

---

## Testing Requirements

### Tests Obligatorios
- ✅ CrossTenantIsolationTest: 6 tests de aislamiento
- ✅ OrderPolicyTest: 15+ tests de permisos por rol
- ✅ TaxAuthorizationTest: cashier no puede crear impuestos
- ✅ MultiTenantIsolationTest: scopes de tenant

### Cobertura Mínima
- Cada endpoint: test de autorización (rol correcto)
- Cada endpoint: test de aislamiento (no ve datos de otro tenant)
- Cada servicio crítico: test de validación de ownership

---

## Referencias

- ADR-001: `docs/adr/001-authentication.md`
- Threat Model: `docs/security/threat-model.md`
- Scanner Report: `docs/security/audit/scan-20260827-212834.md`
