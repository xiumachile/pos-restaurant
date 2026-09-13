# ADR-012: Multi-tenancy local obligatorio

**Fecha**: 2026-01-13  
**Estado**: Aceptado  
**Decisores**: Equipo de desarrollo

## Contexto

### Problema detectado

El sistema es multi-tenant y multi-branch, pero varias tablas locales SQLite **NO tienen filtros de tenant**:

| Tabla | company_id | branch_id | Estado |
|-------|------------|-----------|--------|
| `local_orders` | ✅ | ✅ | Seguro |
| `local_bills` | ✅ | ✅ | Seguro |
| `local_payments` | ✅ | ✅ | Seguro |
| `local_cash_sessions` | ✅ | ✅ | Seguro |
| `local_tables` | ❌ | ❌ | **VULNERABLE** |
| `local_products` | ❌ | ❌ | **VULNERABLE** |
| `local_categories` | ❌ | ❌ | **VULNERABLE** |
| `local_payment_methods` | ❌ | ❌ | **VULNERABLE** |
| `printer_configs` | ❌ | ❌ | **VULNERABLE** |

### Escenario de riesgo
Empresa 1, Sucursal 1 usa Terminal A
PullEngine sincroniza mesas, productos, categorías
Datos de Empresa 1 quedan en SQLite
Empresa 1 cierra sesión
Empresa 2, Sucursal 3 usa el mismo Terminal A
PullEngine sincroniza: DELETE FROM local_tables (sin filtro)
Borra datos de Empresa 1
Inserta datos de Empresa 2
Empresa 2 cierra sesión
Empresa 1 vuelve a usar Terminal A
PullEngine sincroniza: DELETE FROM local_tables (sin filtro)
Borra datos de Empresa 2
Inserta datos de Empresa 1

**Resultado**: Datos de diferentes empresas se sobrescriben constantemente, pero no hay aislamiento real.

### Problemas adicionales

1. **PullEngine hace DELETE masivos**:
   ```sql
   DELETE FROM local_categories;  -- Borra TODO
   DELETE FROM local_products;    -- Borra TODO
   DELETE FROM local_payment_methods;  -- Borra TODO

Servicios no filtran por tenant:
localTablesService.getAllTables() retorna TODAS las mesas
localCatalogService.listProducts() retorna TODOS los productos
Sin filtros de company_id/branch_id
Consultas sin contexto:
Muchas queries usan SELECT * FROM local_tables WHERE uuid = ?
No validan que la mesa pertenezca al tenant actual
Decisión
Política de multi-tenancy local
Todas las tablas locales que almacenan datos de negocio DEBEN tener:
Columnas obligatorias:
company_id TEXT NOT NULL
branch_id TEXT NOT NULL
Índices compuestos:

   CREATE INDEX idx_tabla_tenant ON tabla(company_id, branch_id);

Filtros obligatorios en servicios:
Todas las consultas DEBEN incluir WHERE company_id = ? AND branch_id = ?
Los servicios DEBEN recibir contexto de usuario autenticado
Si no hay contexto, el servicio DEBE rechazar la operación
PullEngine debe filtrar por tenant:
DELETE debe incluir WHERE company_id = ? AND branch_id = ?
INSERT debe incluir company_id y branch_id en todas las filas
PullEngine recibe contexto de usuario autenticado
Tablas que requieren migración
local_tables
local_products
local_categories
local_payment_methods
printer_configs
Consecuencias
Positivas
✅ Aislamiento real de datos: Empresa 1 nunca ve datos de Empresa 2
✅ Seguridad: Previene fugas de datos entre tenants
✅ Consistencia: Todos los datos locales pertenecen al tenant actual
✅ Auditoría: Trazabilidad clara de qué datos pertenecen a qué empresa
Negativas
⚠️ Migración compleja: Requiere agregar columnas y migrar datos existentes
⚠️ Refactor de servicios: Todos los servicios que usan estas tablas deben actualizarse
⚠️ Breaking change: APIs de servicios cambian (requieren contexto)
⚠️ Performance: Filtros adicionales en queries (mitigado con índices)
Implementación
Fase 1: Migración de schema (este commit)
Agregar columnas company_id y branch_id como NULLABLE
Migrar datos existentes con company_id y branch_id del contexto actual
Crear índices compuestos
Fase 2: Refactor de servicios (commits siguientes)
Actualizar localTablesService para requerir contexto
Actualizar localCatalogService para requerir contexto
Actualizar PullEngine para filtrar por tenant
Actualizar printer_configs para requerir contexto
Fase 3: Hacer columnas NOT NULL (commit final)
Verificar que todas las filas tienen company_id y branch_id
ALTER TABLE para hacer columnas NOT NULL
Validar integridad
Fase 4: Tests de aislamiento
Crear datos de Empresa 1
Verificar que Empresa 2 no puede accederlos
Validar que PullEngine respeta filtros de tenant
Alternativas consideradas
Alternativa 1: No hacer nada
❌ Descartada: Riesgo de seguridad inaceptable
Alternativa 2: Limpiar datos en logout
❌ Descartada: No previene acceso cruzado durante la sesión
Alternativa 3: Usar bases de datos separadas por tenant
❌ Descartada: Complejidad excesiva para Tauri/SQLite
Alternativa 4: Multi-tenancy obligatorio en todas las tablas (ACEPTADA)
Alternativa 4: Multi-tenancy obligatorio en todas las tablas (ACEPTADA)
✅ Seguridad real
✅ Implementación incremental
✅ Bajo impacto en performance
Referencias
Migración 012: frontend/src/db/migrations/012_add_tenant_to_local_tables.sql
Refactor de servicios: commits siguientes
Tests de aislamiento: frontend/src/tests/services/multiTenantIsolation.test.ts
