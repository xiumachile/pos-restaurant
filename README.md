# 🍽️ POS Restaurant — Sistema de Punto de Venta Offline-First

Sistema de punto de venta multi-tenant para restaurantes, diseñado para funcionar
**offline-first** con sincronización automática al recuperar conexión.

**Stack**: Laravel 11 · React 18 · Tauri 2 · SQLite local · PostgreSQL cloud

---

## 📊 Estado del Proyecto

| Métrica | Valor |
|---------|-------|
| **Tests totales** | 1074 (875 backend + 199 frontend) |
| **Fases completadas** | 11 de 12 |
| **Estado** | 🟢 Producción ready |
| **Última actualización** | Septiembre 2026 |

---

## 🏗️ Arquitectura
┌─────────────────────────────────────────────────────┐
│ TAURI SHELL │
│ (desktop app) │
│ ┌───────────────────────────────────────────────┐ │
│ │ REACT FRONTEND │ │
│ │ ┌─────────┐ ┌──────────┐ ┌─────────────┐ │ │
│ │ │ Zustand │ │ React │ │ Axios + │ │ │
│ │ │ Stores │ │ Query │ │ Interceptors│ │ │
│ │ └────┬─────┘ └────┬─────┘ └──────┬──────┘ │ │
│ │ │ │ │ │ │
│ │ ┌────▼──────────────▼───────────────▼──────┐ │ │
│ │ │ SERVICE LAYER │ │ │
│ │ │ authContext · offlinePayment · sync │ │ │
│ │ └────────────────┬──────────────────────────┘ │ │
│ │ │ │ │
│ │ ┌────────────────▼──────────────────────────┐ │ │
│ │ │ SQLite LOCAL (WAL mode) │ │ │
│ │ │ orders · bills · payments · cash_sessions │ │ │
│ │ │ sync_queue · offline_events │ │ │
│ │ └────────────────┬──────────────────────────┘ │ │
│ └───────────────────┼──────────────────────────────┘ │
│ │ │
│ ┌───────────────────▼──────────────────────────────┐ │
│ │ SYNC ENGINE │ │
│ │ Push (sync_queue → API) · Pull (API → SQLite) │ │
│ │ Backoff exponencial · Idempotencia · Multi-tenant│ │
│ └───────────────────┬──────────────────────────────┘ │
└──────────────────────┼──────────────────────────────────┘
│ HTTPS (cuando hay conexión)
▼
┌──────────────────────────────┐
│ LARAVEL BACKEND (API) │
│ PostgreSQL · Payment Ledger │
│ Multi-tenant · JWT Auth │
└──────────────────────────────┘

### Principios de diseño

1. **Offline-first**: Toda operación se guarda primero en SQLite local
2. **Event Sourcing híbrido**: `offline_events` append-only para auditoría
3. **Idempotencia**: Cada operación tiene `idempotency_key` UUID
4. **Multi-tenant estricto**: `company_id` + `branch_id` validados en cada capa
5. **Fail-safe**: Si algo falla, no se pierde data (cola de sync con retry)

---

## 🚀 Quick Start

### Prerrequisitos

- PHP 8.2+ · Composer
- Node.js 20+ · npm
- Rust (para Tauri) — [instalar](https://www.rust-lang.org/tools/install)
- PostgreSQL 15+ (backend)
- Docker (opcional, para desarrollo)

### Backend (Laravel)

```bash
# Clonar y configurar
git clone git@github.com:xiumachile/pos-restaurant.git
cd pos-restaurant
cp .env.example .env
composer install

# Base de datos
php artisan migrate
php artisan db:seed

# Servidor de desarrollo
php artisan serve
Frontend (React + Tauri)

cd frontend
npm install

# Desarrollo web (sin Tauri)
npm run dev

# Desarrollo con Tauri (desktop)
npm run tauri dev

# Tests
npm test           # Suite completa (199 tests)
npm test -- --run  # Sin watch mode
Tests Backend
# Suite completa (875 tests)
php artisan test

# Módulo específico
php artisan test --filter=PaymentTest
php artisan test --filter=LedgerTest

📁 Estructura del Proyecto
pos-restaurant/
├── app/                    # Backend Laravel
│   ├── Modules/            # Módulos de dominio (DDD)
│   │   ├── Identity/       # Auth, Users, Roles
│   │   ├── Companies/      # Empresas, Sucursales
│   │   ├── Catalog/        # Productos, Categorías, Menús
│   │   ├── Orders/         # Pedidos, Items, State Machine
│   │   ├── Payments/       # Pagos, Ledger, Refunds
│   │   ├── Cashier/        # Caja, Sesiones, Cobros
│   │   ├── Kitchen/        # Pantalla cocina
│   │   ├── Tables/         # Mesas, Estado
│   │   └── ...             # Más módulos
│   └── Http/               # Controllers, Middleware
├── frontend/               # Frontend React + Tauri
│   ├── src/
│   │   ├── components/     # Componentes React
│   │   ├── db/             # SQLite local
│   │   │   ├── repositories/  # OrderRepo, PaymentRepo, etc.
│   │   │   └── migrations/    # Esquema local
│   │   ├── services/       # authContext, sync, payments
│   │   │   └── sync/       # SyncEngine, PullEngine
│   │   ├── store/          # Zustand stores
│   │   ├── tests/          # 199 tests (Vitest)
│   │   └── types/          # TypeScript types
│   └── src-tauri/          # Configuración Tauri
├── docs/                   # Documentación
│   ├── adr/                # Decisiones arquitectónicas
│   ├── architecture/       # Diagramas y decisiones
│   ├── roadmap/            # Estado y roadmap
│   └── DEPLOY.md           # Guía de deploy
└── tests/                  # Tests backend (875 tests)

🔐 Seguridad
Multi-tenant
Cada operación valida company_id + branch_id + user_id:
Backend: Middleware BelongsToTenant + scopes automáticos en Eloquent
Frontend: authContext.ts inyecta contexto en cada operación
SyncEngine: validateContext() rechaza items de otro tenant
Auditoría: Event Sourcing con user_id real (UUID, no numérico)
Almacenamiento seguro
Entorno
Token JWT
Mecanismo
Producción (Tauri)
Tauri Store encriptado
@tauri-apps/plugin-store
Desarrollo (Web)
localStorage
Fallback automático
Tests
Mock en memoria
enableTauriEnv() / disableTauriEnv()
CSP (Content Security Policy)
default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline';
connect-src 'self' https://api.* http://localhost:*;
frame-src 'none'; object-src 'none';

🔄 Sincronización Offline
Flujo de escritura (Push)

Usuario crea pedido → SQLite local → sync_queue → SyncEngine → Backend API
                                                      ↑
                                                 Retry con backoff
                                                 exponencial (30s/60s/120s)

Flujo de lectura (Pull)
Backend API → PullEngine → SQLite local → React Query → UI

Garantías
✅ Idempotencia: idempotency_key en cada operación
✅ Orden: FIFO por created_at en sync_queue
✅ Retry: Backoff exponencial (30s, 60s, 120s) hasta 5 intentos
✅ Multi-tenant: Validación estricta antes de enviar al backend
✅ Auditoría: offline_events append-only (Event Sourcing híbrido)
📋 Fases Completadas

Fase
Descripción
Tests
Estado
0-3
Core backend: Auth, Multi-tenant, Catalog, Orders
830
✅
4
Payment Ledger (double-entry) + Refunds
850
✅
5
Frontend: React + Tauri + SQLite local
120
✅
6
Repositorios offline (Order, Payment, Bill, Cash)
145
✅
7
SyncEngine bidireccional (Push + Pull)
155
✅
8
Event Sourcing híbrido (offline_events)
165
✅
9
Resiliencia (double-click, crash, retry, offline)
175
✅
10
Seguridad (CSP, Tauri Store, JWT encriptado)
184
✅
11
Multi-tenant + auditoría + refactors
199
✅
📖 Documentación adicional
Documento
Descripción
docs/adr/
Decisiones arquitectónicas (001-006)
docs/architecture/
Diagramas y matrices de módulos
docs/roadmap/
Estado actual y roadmap
docs/DEPLOY.md
Guía de deploy a producción
🧑‍💻 Desarrollo
Convenciones
Commits: Conventional Commits
feat(module): nueva funcionalidad
fix(module): corrección de bug
test(module): tests nuevos
security(module): mejoras de seguridad
refactor(module): refactorización sin cambio funcional
Tests: Todo código nuevo requiere tests (definición de "done")
Multi-tenant: Toda operación debe incluir company_id + branch_id
Scripts útiles

# Backend
php artisan test                    # 875 tests
php artisan test --filter=Payment   # Tests de un módulo

# Frontend
cd frontend
npm test                            # 199 tests (watch mode)
npm test -- --run                   # Sin watch
npm test -- --run authContext       # Tests específicos
npm run dev                        # Dev server (web)
npm run tauri dev                  # Dev server (desktop)
npm run build                      # Build de producción

📜 Licencia
Proyecto privado. Todos los derechos reservados.
