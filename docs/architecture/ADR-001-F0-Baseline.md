# ADR-001: F0 — Baseline y Congelación Arquitectónica

## Status
✅ Aceptado (2026-08-26)

## Contexto
El proyecto tenía 220 commits y una arquitectura considerable, pero sin tests funcionales. 
Era imposible saber qué funcionaba y qué no sin ejecutar manualmente cada funcionalidad.

## Decisión
Implementar una suite de tests completa y documentar el estado del sistema como baseline.

### Resultados
- **511 tests pasando (87%):** Identity, Companies, Branches, Catalog, Sync, Users, Roles
- **73 tests fallando (13%):** Order State Machine, Table State Machine, Payment Ledger

### Deuda técnica identificada
Los 73 tests fallidos se clasifican en:
1. **Order State Machine** (~50 tests): Transiciones de estado no implementadas
2. **Table State Machine** (~5 tests): Integración Order ↔ Table no funciona
3. **Problemas menores** (~18 tests): Rutas faltantes, métodos no implementados

## Consecuencias

### Positivas
- ✅ Baseline estable establecido
- ✅ CI/CD funcional (tests corren automáticamente en cada push)
- ✅ Deuda técnica cuantificada y priorizada
- ✅ Equipo sabe exactamente qué funciona y qué no

### Negativas
- ⚠️ 73 tests fallan consistentemente (deuda técnica documentada)
- ⚠️ No se puede avanzar a F1 sin resolver Order/Table/Payment State Machines

## Próximos pasos
1. **F1 — Domain Core** (3 semanas): Definir agregados y contratos formales
2. **F2 — State Machines** (3 semanas): Resolver los 73 tests fallidos
   - Order State Machine
   - Table State Machine
   - Payment Ledger

## Gate de F0
✅ **Completado:** Se puede responder "¿Cuál es exactamente el core que no puede romperse?"
- Core estable: 511 tests pasan
- Core inestable: 73 tests fallan (backlog de F2)
