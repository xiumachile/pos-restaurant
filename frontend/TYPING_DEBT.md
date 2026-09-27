# Deuda de Tipado Estricto

## Estado Actual
- `tsc --noEmit` pasa con 0 errores
- Pero existen ~228 usos de `any` fuera de tests
- Esto da falsa sensación de seguridad de tipos

## Archivos Críticos Prioritarios
1. **Repositorios** (25 `any`): OrderRepository, LocalWriteCoordinator, nativeDb
2. **Servicios de dinero** (~50 `any`): paymentsService, tipService, localPaymentsService, cashierService
3. **Motores de sync** (23 `any`): SyncEngine, PullEngine

## Plan de Acción Futuro
- Iteración dedicada para refactor de tipos en repositorios críticos
- Establecer regla ESLint `@typescript-eslint/no-explicit-any: warn` para evitar nueva deuda
- Priorizar archivos que tocan transacciones financieras

## Nota
Los intentos de arreglar `any` en `nativeDb.ts` y `LocalWriteCoordinator.ts` revelaron que se necesita un refactor más profundo (interfaces completas de better-sqlite3, manejo de errores con discriminated unions, etc.) que no se puede hacer de forma segura en una sesión de parches rápidos.
