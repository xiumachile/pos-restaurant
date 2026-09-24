🍽️ POS Restaurant — Sistema de Punto de Venta Offline-First
Sistema de punto de venta multi-tenant para restaurantes, diseñado para funcionar offline-first con sincronización automática al recuperar conexión.
Stack: Laravel 12 · React 19 · Tauri 2 · SQLite local · PostgreSQL cloud
📊 Estado del Proyecto
Fuente de verdad: Ver docs/PROJECT_STATUS.md para información detallada y actualizada.

Métrica	Valor
Estado	🟢 Producción Ready
Tests totales: Verificar vía CI o ejecutar `php artisan test` && `npm run test:ci`
Gates completados	4/4
Checklist validado	176/176 puntos
Deuda técnica P0/P1	0
Última actualización	Septiembre 2026

🏗️ Arquitectura

┌─────────────────────────────────────────────────────┐
│                   TAURI SHELL                       │
│                 (desktop app)                       │
│  ┌───────────────────────────────────────────────┐  │
│  │           REACT FRONTEND                      │  │
│  │  ┌─────────┐  ┌──────────┐  ┌─────────────┐  │  │
│  │  │ Zustand │  │  React   │  │  Axios +    │  │  │
│  │  │ Stores  │  │  Query   │  │ Interceptors│  │  │
│  │  └────┬─────┘  └────┬─────┘  └──────┬──────┘  │  │
│  │       │              │               │         │  │
│  │  ┌────▼──────────────▼───────────────▼──────┐ │  │
│  │  │         SERVICE LAYER                    │ │  │
│  │  │  authContext · offlinePayment · sync     │ │  │
│  │  └────────────────┬──────────────────────────┘ │  │
│  │                   │                            │  │
│  │  ┌────────────────▼──────────────────────────┐ │  │
│  │  │        SQLite LOCAL (WAL mode)            │ │  │
│  │  │  orders · bills · payments · cash_sessions│ │  │
│  │  │  sync_queue · offline_events              │ │  │
│  │  └────────────────┬──────────────────────────┘ │  │
│  └───────────────────┼──────────────────────────────┘  │
│                      │                                 │
│  ┌───────────────────▼──────────────────────────────┐  │
│  │              SYNC ENGINE                         │  │
│  │  Push (sync_queue → API) · Pull (API → SQLite)  │  │
│  │  Backoff exponencial · Idempotencia · Multi-tenant│ │
│  └───────────────────┬──────────────────────────────┘  │
└──────────────────────┼─────────────────────────────────┘
                       │
              HTTPS (cuando hay conexión)
                       ↓
         ┌─────────────────────────────┐
         │      LARAVEL BACKEND        │
         │  ┌───────────────────────┐  │
         │  │   PostgreSQL Cloud    │  │
         │  │   (Multi-tenant)      │  │
         │  └───────────────────────┘  │
         └─────────────────────────────┘

Principios de Diseño
1. Offline-First: Funciona sin conexión, sincroniza después
2. Multi-tenant: Aislamiento completo por empresa/sucursal
3. Idempotencia: Operaciones seguras ante reintentos
4. Event Sourcing Híbrido: Auditoría completa de cambios
5. Thin Client: Lógica de negocio en backend

🚀 Quick Start
Prerrequisitos
PHP 8.4+
Node.js 20+
PostgreSQL 16+
Redis 7+
Composer 2.x
Rust 1.70+ (para Tauri)
Backend (Laravel)

# Clonar y configurar
git clone https://github.com/xiumachile/pos-restaurant.git
cd pos-restaurant
cp .env.example .env
composer install

# Base de datos
php artisan key:generate
php artisan migrate
php artisan db:seed

# Servidor de desarrollo
php artisan serve

Desarrollo web (sin Tauri)
cd frontend
npm install
npm run dev

Accede a http://localhost:5173

Desarrollo con Tauri (desktop)
cd frontend
npm install
npm run tauri dev

Tests
# Suite completa de tests (verificar conteo en CI)
php artisan test

# Módulo específico
php artisan test --filter=Payment

# Frontend
cd frontend
npm run test

📖 Documentación
Guías Principales

Documento	Descripción
Estado del Proyecto	Métricas, gates, checklist completo
Despliegue	Guía completa de deployment
Backup y Recovery	Procedimientos de backup
CI/CD	Pipeline de integración continua

Arquitectura
Documento	Descripción
Contrato de API	Endpoints, formatos, errores
Protocolo de Sync	Sincronización offline-online
Dinero e IVA	Modelo financiero chileno
Seguridad	Auditoría de seguridad

Decisiones de Arquitectura
Ver docs/adr/ para las 21 ADRs documentadas:
ADR-001 a ADR-016: Autenticación, multi-tenancy, idempotencia, etc.
🧪 Testing
Backend (ejecutar `php artisan test`)

# Todos los tests
php artisan test

# Con coverage
php artisan test --coverage

# Tests específicos
php artisan test --filter=FinancialIntegrityTest
php artisan test --filter=PaymentIdempotencyTest
php artisan test --filter=BillIntegrityTest

Frontend (ejecutar `npm run test:ci`)
cd frontend

# Todos los tests
npm run test

# Con coverage
npm run test:coverage

# Modo CI
npm run test:ci

📦 Estructura del Proyecto
pos-restaurant/
├── app/                      # Backend Laravel
│   └── Modules/
│       ├── Orders/          # Gestión de órdenes
│       ├── Payments/        # Pagos y facturación
│       ├── Cashier/         # Caja y sesiones
│       ├── Sync/            # Motor de sincronización
│       └── ...
├── frontend/                 # Frontend React + Tauri
│   ├── src/
│   │   ├── components/      # Componentes React
│   │   ├── hooks/           # Custom hooks
│   │   ├── services/        # Lógica de negocio
│   │   └── stores/          # Estado global (Zustand)
│   └── src-tauri/           # Código Rust (Tauri)
├── docs/                     # Documentación
│   ├── adr/                 # Decisiones de arquitectura
│   ├── architecture/        # Documentos de arquitectura
│   └── operations/          # Guías operativas
├── tests/                    # Tests backend
│   ├── Feature/             # Tests de integración
│   └── Unit/                # Tests unitarios
└── docker-compose.yml        # Servicios Docker

🔧 Comandos Útiles
Backend

# Migraciones
php artisan migrate:fresh --seed

# Cache
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Queues
php artisan horizon
php artisan queue:work

# Sync manual
php artisan sync:push
php artisan sync:pull

Frontend
cd frontend

# Desarrollo
npm run dev

# Build producción
npm run build

# Type check
npm run typecheck

# Lint
npm run lint

# Tauri build
npm run tauri build

🐳 Docker
Desarrollo
# Levantar todos los servicios
docker-compose up -d

# Ver logs
docker-compose logs -f

# Parar servicios
docker-compose down

Servicios incluidos
PostgreSQL 16: Base de datos principal
Redis 7: Cache y queues
Mailhog: Servidor de email (desarrollo)
📊 Métricas de Calidad
Métrica	Valor	Objetivo
Cobertura de tests	85%+	>80%
Tiempo de CI	<15 min	<20 min
Tests pasando	1,429+	100%
Deuda técnica P0	0	0
Vulnerabilidades	0	0

🤝 Contribución
Fork el repositorio
Crea una rama feature (git checkout -b feature/AmazingFeature)
Commit tus cambios (git commit -m 'Add AmazingFeature')
Push a la rama (git push origin feature/AmazingFeature)
Abre un Pull Request
Convenciones
Commits: Conventional Commits
Branches: feature/, fix/, docs/, refactor/
PRs: Requieren 1 aprobación + CI passing
📄 Licencia
Este proyecto es propiedad de Xiuma Chile.
📞 Soporte
Documentación: /docs/
Issues: GitHub Issues
Email: soporte@xiuma.cl
Última actualización: Septiembre 2026
Versión: 1.0.0
Maintainer: Equipo POS Restaurant
Estado: 🟢 PRODUCCIÓN-READY
