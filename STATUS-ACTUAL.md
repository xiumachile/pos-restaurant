# Estado Actual del Sistema

**Fecha:** 11 de septiembre de 2026  
**Commits totales:** ~235  
**Estado del repo:** Limpio

---

## 1. Matriz de módulos

### Clasificación de estado

- **READY:** Producción, bien testeado, sin deuda conocida
- **STABLE:** Funcional, tests básicos, deuda menor
- **PARTIAL:** Funcionalidad parcial, falta integración completa
- **EXPERIMENTAL:** Prototipo o en desarrollo activo
- **DEPRECATED:** Obsoleto, plan de eliminación

### Estado por módulo

| Módulo | Status | Tests | Deuda técnica | Prioridad |
|--------|--------|-------|---------------|-----------|
| Identity | STABLE | ✅ | — | — |
| Companies | STABLE | ✅ | — | — |
| Branches | STABLE | ✅ | — | — |
| Catalog | STABLE | ✅ | — | — |
| Orders | STABLE | ✅ | — | — |
| Tables | STABLE | ✅ | — | — |
| Kitchen | STABLE | ✅ | — | F7 |
| **Payments** | **READY** | ✅ | — | — |
| Cashier | STABLE | ✅ | — | F7 |
| Inventory | PARTIAL | ⚠️ | Sin ledger unificado | F6 |
| Recipes | PARTIAL | ⚠️ | — | F6 |
| Billing | STABLE | ✅ | — | F0.4 |
| Fiscal | EXPERIMENTAL | ❌ | Sin DTE completo | F12 |
| **Sync** | **READY** | ✅ | — | — |
| **Printers** | **READY** | ✅ | Arquitectura híbrida online+offline | — |
| Reports | EXPERIMENTAL | ❌ | Sin desarrollo | F11 |
| Delivery | EXPERIMENTAL | ❌ | Sin desarrollo | F10 |

---

## 2. Infraestructura offline-first (completado septiembre 2026)

### Componentes disponibles

#### Repositorios locales (SQLite)
- ✅ `local_orders` — Pedidos offline
- ✅ `local_order_items` — Items del pedido
- ✅ `local_tables` — Estado de mesas
- ✅ `local_bills` — Cuentas
- ✅ `local_payments` — Pagos
- ✅ `local_cash_sessions` — Sesiones de caja
- ✅ `local_cash_movements` — Movimientos de caja
- ✅ `offline_events` — Event Sourcing híbrido (append-only)
- ✅ `local_print_jobs` — Cola de impresión offline

#### Motores de impresión
- ✅ `OnlinePrintEngine` — Polling del backend (jobs del cloud)
- ✅ `OfflinePrintEngine` — Polling de SQLite (jobs locales)
- ✅ Ambos se inician al autenticarse (usePrintEngine)

#### Hooks de UI
- ✅ `useConnectionMode` — Detecta modo offline
- ✅ `useOfflinePayment` — Drop-in replacement de usePayBill
- ✅ `useOfflinePrintJob` — Encola impresiones offline

### Flujo híbrido de cobro

BillPaymentModalV2
↓
useConnectionMode() detecta modo
↓
┌────┴────┐
Online Offline
│ │
↓ ↓
usePayBill useOfflinePayment
│ │
↓ ↓
Backend offlinePaymentService
│ ├─ LocalBill
↓ ├─ LocalPayment
PostgreSQL ├─ CashMovement
├─ LocalPrintJob
└─ SyncQueue


---

## 3. Métricas de calidad

### Tests

| Tipo | Cantidad | Estado |
|------|----------|--------|
| Backend (PHP/PHPUnit) | ~875 | ✅ Pasando |
| Frontend (Vitest) | ~250 | ✅ Pasando |
| **Total** | **~1125** | ✅ |

### Cobertura por capa (frontend)

| Capa | Tests | Estado |
|------|-------|--------|
| Repositorios (DB) | 80+ | ✅ Completo |
| Servicios | 60+ | ✅ Críticos cubiertos |
| Hooks | 50+ | ✅ Core cubierto |
| Componentes | 40+ | ✅ UI principal |
| Stores | 20+ | ✅ Estado global |

---

## 4. Documentación técnica

### ADRs (Architecture Decision Records)

| ADR | Título | Fecha |
|-----|--------|-------|
| 001 | Authentication | Ago 2026 |
| 002 | Multi-tenant isolation | Ago 2026 |
| 003 | Controller-Service pattern | Ago 2026 |
| 004 | Defensa en profundidad multi-tenant | Ago 2026 |
| 005 | Sistema de capabilities multi-restaurante | Ago 2026 |
| **006** | **Event Sourcing híbrido offline** | **Sep 2026** |
| **007** | **Flujo de impresión híbrido** | **Sep 2026** |

### Guías operativas

- ✅ `README.md` — Arquitectura + Quick start + Seguridad
- ✅ `docs/DEPLOY.md` — Guía completa de deploy a producción
- ✅ `STATUS-ACTUAL.md` — Este documento
- ✅ `docs/roadmap/ROADMAP-F0-F12.md` — Roadmap completo

---

## 5. Deuda técnica priorizada

### 🔴 Crítica

Ninguna al momento. Todas las deudas críticas fueron resueltas en la mega-sesión
de agosto-septiembre 2026.

### 🟡 Alta

1. **Sin CI/CD formal** (F0.1)
   - Tests pasan localmente pero no en push automático
   - Configurar GitHub Actions con PHPUnit + Vitest

2. **Sin stress test de sincronización** (F3)
   - Probar sync con 1000+ items en cola
   - Validar recuperación tras 1h offline

### 🟢 Media

3. **Dos sistemas de inventario paralelos** (F6)
   - Unificar RawIngredient e InventoryItem

4. **Reportes sin desarrollo** (F11)
   - UI de reportes básicos pendiente

5. **Delivery sin desarrollo** (F10)
   - Integración con apps de delivery pendiente

---

## 6. Trabajo completado en septiembre 2026

### Sesión del 10-11 de septiembre

**Commits**: 8 commits atómicos  
**Tests agregados**: +40  
**Archivos nuevos**: 12+  
**Estado final**: Flujo completo de cobro offline funcional

#### Resumen por commit:

1. **`25f1040`** — Integrar SyncStatusIndicator en Header + mejorar UX
2. **`c03bee3`** — SyncBadge + SyncErrorBox reutilizables
3. **`1aee1bc`** — Panel de diagnóstico de Sync Queue
4. **`ee70739`** — Fix de TypeScript errors
5. **`6dcc4a6`** — Migración 008 + LocalPrintJobRepository
6. **`5959813`** — OfflinePrintEngine + useOfflinePrintJob + markAsPermanentlyFailed
7. **`90f0610`** — Integración dual de PrintEngines en usePrintEngine
8. **`1349112`** — Integración offline en BillPaymentModalV2 (Estrategia A)

---

## 7. Próximos pasos sugeridos

### Corto plazo (1-2 sesiones)

1. **UI de caja offline** — Hook `useOfflineCashSession` + modales de open/close
2. **Tests E2E** del flujo completo offline
3. **CI/CD con GitHub Actions** — Tests automáticos en push

### Mediano plazo (1-2 meses)

4. **Fase 6 — Inventory Ledger unificado**
5. **Fase 10 — Delivery integration**
6. **Fase 11 — Reportes básicos**

### Largo plazo

7. **Fase 12 — Fiscal DTE completo** (facturación electrónica Chile)
8. **Stress tests distribuidos**
9. **Mobile POS** (versión iOS/Android con el mismo core)

---

## 8. Convenciones arquitectónicas vigentes

### Estructura de módulo (backend)

app/Modules/{Module}/
├── Domain/
│ ├── Entities/
│ ├── Repositories/
│ └── Events/
├── Application/
│ ├── UseCases/
│ └── DTOs/
└── Infrastructure/
├── Controllers/
├── Eloquent/
└── Listeners/


### Reglas de comunicación entre módulos
- ❌ Nunca `DB::table(otro_modulo)` desde un controller
- ❌ Nunca `withoutGlobalScopes()` sin justificación
- ✅ Emitir evento de dominio + listener en módulo consumidor
- ✅ O llamar un caso de uso del módulo destino

### Convenciones frontend (React + Tauri)
- ✅ `use{Entity}` para queries (React Query)
- ✅ `use{Action}` para mutations
- ✅ Repositorios en `src/db/repositories/`
- ✅ Hooks en `src/hooks/`
- ✅ Tests co-localizados: `src/tests/{type}/{name}.test.ts`
- ✅ Mocks compartidos en `src/tests/mocks/`

### Tenant isolation
- Todo request deriva `company_id` y `branch_id` del token, no del payload
- `BelongsToTenant` trait en todas las entidades
- Helper `getCashierContextSafe()` en frontend para obtener contexto

