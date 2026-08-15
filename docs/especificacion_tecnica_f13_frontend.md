# Especificación Técnica — F13 FRONTD (App Desktop Tauri + React)

**Módulo:** Aplicación de escritorio POS
**Stack:** Tauri v2 (Rust + WebView) + React 18 + TypeScript
**Integración:** Backend Laravel ya completo (F9 OFFLNE disponible)
**Estado:** 0% iniciado (nuevo proyecto)
**Duración estimada:** 18 días (19 Ago - 11 Sep 2026)

---

## 1. Objetivo

Construir una aplicación de escritorio POS completa que funcione en:
- **Modo online:** conectado al backend Laravel vía API REST
- **Modo offline:** usando SQLite local y sincronizando con F9 OFFLNE al recuperar conexión

La app debe soportar el flujo completo de un restaurante:
1. Login (con PIN POS para garzones)
2. Selección de mesa
3. Toma de pedidos (con combos y sustituciones)
4. Envío a cocina (KDS)
5. Gestión de pagos (split bill, propinas)
6. Cierre de caja con arqueo
7. Impresión de tickets y comandas

## 2. Stack Técnico

### 2.1 Core: Tauri v2

**Por qué Tauri (vs Electron):**
- Binarios más pequeños (~10MB vs ~150MB de Electron)
- Menor consumo de memoria (WebView del OS vs Chromium embebido)
- Backend en Rust (seguridad y performance)
- Acceso nativo a hardware (impresoras, cajón monedero)
- Soporte nativo para SQLite local
- Multiplataforma (Windows, macOS, Linux)

**Componentes de Tauri:**
- `src-tauri/` — Código Rust (backend nativo)
  - Acceso a SQLite local
  - Gestión de impresoras ESC/POS
  - Control de cajón monedero
  - File system access
  - System tray
- `src/` — Frontend React (WebView)
  - UI completa de la app
  - Lógica de negocio
  - Estado global (Zustand/Redux)

### 2.2 Frontend: React 18 + TypeScript

**Librerías principales:**
- `react` + `react-dom` — UI framework
- `typescript` — Type safety
- `vite` — Build tool (rápido, optimizado)
- `tailwindcss` — Utility-first CSS
- `zustand` — State management (simple, sin boilerplate)
- `react-router-dom` — Routing
- `axios` — HTTP client
- `@tanstack/react-query` — Server state management
- `react-hook-form` + `zod` — Formularios y validación
- `date-fns` — Manipulación de fechas
- `i18next` — Internacionalización (es-CL, zh-CN)

**Librerías de UI:**
- `shadcn/ui` — Componentes accesibles y personalizables
- `lucide-react` — Iconos
- `sonner` — Toasts/notificaciones
- `cmdk` — Command palette (atajos de teclado)

### 2.3 Base de datos local: SQLite

**Por qué SQLite:**
- Embebido (no requiere servidor)
- Transaccional (ACID)
- Portable (un archivo .db)
- Soportado nativamente por Tauri
- Compatible con el backend (F9 OFFLNE ya usa SQLite)

### 2.4 Testing

**Frontend:**
- `vitest` — Unit tests
- `@testing-library/react` — Component tests
- `playwright` — E2E tests

**Tauri (Rust):**
- `cargo test` — Unit tests de Rust
- Tests de integración con SQLite

## 3. Arquitectura General

    +--------------------------------------------------------------+
    |                    TAURI APP (Desktop)                       |
    |  +--------------------------------------------------------+  |
    |  |  FRONTEND (React + TypeScript)                         |  |
    |  |  - Pages (Login, Menu, Orders, Kitchen, Payment)       |  |
    |  |  - Components (reutilizables)                          |  |
    |  |  - Store (Zustand)                                     |  |
    |  |  - API Client (Axios + React Query)                    |  |
    |  +--------------------------------------------------------+  |
    |                          |                                   |
    |  +--------------------------------------------------------+  |
    |  |  TAURI BRIDGE (IPC)                                    |  |
    |  |  - invoke() — Llama funciones de Rust                  |  |
    |  |  - events — Escucha eventos de Rust                    |  |
    |  +--------------------------------------------------------+  |
    |                          |                                   |
    |  +--------------------------------------------------------+  |
    |  |  BACKEND NATIVO (Rust)                                 |  |
    |  |  - SQLite Local (offline-first)                        |  |
    |  |  - Impresoras ESC/POS                                  |  |
    |  |  - Cajón monedero                                      |  |
    |  |  - Sync Engine (push/pull con backend)                 |  |
    |  +--------------------------------------------------------+  |
    +--------------------------------------------------------------+
                              |
                        Internet (opcional)
                              |
    +--------------------------------------------------------------+
    |              BACKEND LARAVEL (Servidor)                      |
    |  - API REST (ya completa)                                    |
    |  - PostgreSQL (BD central)                                   |
    |  - F9 OFFLNE (Sync Engine + ConflictResolver)                |
    |  - WebSocket (Reverb para real-time)                         |
    +--------------------------------------------------------------+

## 4. Flujo de Sincronización Offline-First

### 4.1 Modo Online

    Frontend → API REST (Laravel) → PostgreSQL
                    ↓
             Respuesta inmediata

### 4.2 Modo Offline

    Frontend → Tauri Bridge → SQLite Local
                    ↓
             Respuesta inmediata
                    ↓
             Guardar en sync_queue

### 4.3 Recuperación de Conexión

    Tauri detecta conexión → Sync Engine
                    ↓
             push: SQLite → Laravel (POST /api/v1/sync/push)
                    ↓
             pull: Laravel → SQLite (POST /api/v1/sync/pull)
                    ↓
             ConflictResolver (4 estrategias)
                    ↓
             Actualizar SQLite local

## 5. Módulos a Implementar

### 5.1 Setup Inicial (2 días)

**F13.1: Setup Tauri + React + TypeScript**
- Inicializar proyecto Tauri v2
- Configurar React + TypeScript + Vite
- Configurar Tailwind CSS + shadcn/ui
- Configurar Zustand para estado global
- Configurar React Router
- Configurar i18next (es-CL, zh-CN)

### 5.2 Autenticación (2 días)

**F13.2: Auth (Login JWT + PIN POS)**
- Pantalla de login (email + password)
- Login con PIN POS (4-6 dígitos)
- Almacenamiento seguro de JWT
- Refresh token automático
- Logout
- Multi-sucursal (selector de sucursal)
- Gestión de sesión (timeout automático)

### 5.3 Catálogo y Mesas (2 días)

**F13.3: Catálogo + Mesas**
- Vista de mesas (grid con estados: disponible, ocupada, reservada)
- Selección de mesa
- Vista de catálogo (categorías + productos)
- Búsqueda de productos
- Vista de combos con componentes
- Sustituciones (any_product, allowed_category, no_substitution)
- Carrito de pedido (items, cantidades, notas)

### 5.4 Pedidos (3 días)

**F13.4: Pedidos (ORDERS)**
- Crear pedido nuevo
- Agregar productos al pedido
- Modificar cantidades
- Agregar notas a items
- Aplicar descuentos (con autorización si requiere)
- Calcular totales (subtotal, IVA, descuento, total)
- Confirmar pedido (envía a cocina)
- Cancelar pedido (con razón)
- Modo offline: guardar en SQLite local

### 5.5 Cocina / KDS (2 días)

**F13.5: Cocina / KDS**
- Vista de cocina (pedidos pendientes)
- Estados: pendiente, en preparación, listo, servido
- Asignar cocinero a pedido
- Marcar como listo
- Marcar como servido
- Filtros por estado
- Ordenamiento por prioridad
- Modo offline: sync al recuperar conexión

### 5.6 Pagos y Caja (2 días)

**F13.6: Pagos + Caja**
- Pantalla de pago
- Métodos de pago (efectivo, tarjeta, transferencia)
- Split bill (dividir cuenta)
- Propinas
- Apertura de cajón (con monto inicial)
- Cierre de caja con arqueo
- Movimientos de caja (retiros, depósitos)
- Impresión de ticket
- Modo offline: guardar pagos en SQLite

### 5.7 Sync Local (3 días)

**F13.7: Sync Local (SQLite)**
- Integración con F9 OFFLNE del backend
- Detección automática de conexión/desconexión
- Push de cambios locales al servidor
- Pull de cambios del servidor
- ConflictResolver (4 estrategias)
- Indicador visual de estado de conexión
- Reintentos automáticos con backoff
- Logs de sincronización

### 5.8 Pulido y Testing (2 días)

**F13.8: Pulido UX + Tests E2E**
- Atajos de teclado (command palette)
- Notificaciones (toasts)
- Loading states
- Error handling
- Tests unitarios (Vitest)
- Tests de componentes (Testing Library)
- Tests E2E (Playwright)
- Optimización de performance
- Build de producción

## 6. Integración con Backend

### 6.1 API Endpoints (ya implementados)

**Autenticación:**
- `POST /api/v1/auth/login` — Login con email/password
- `POST /api/v1/auth/login/pos` — Login con PIN POS
- `POST /api/v1/auth/refresh` — Refresh token
- `POST /api/v1/auth/logout` — Logout

**Catálogo:**
- `GET /api/v1/catalog/categories` — Categorías
- `GET /api/v1/catalog/products` — Productos
- `GET /api/v1/catalog/combos/{uuid}` — Detalle de combo

**Mesas:**
- `GET /api/v1/tables` — Lista de mesas
- `PUT /api/v1/tables/{uuid}/status` — Cambiar estado

**Pedidos:**
- `POST /api/v1/orders` — Crear pedido
- `GET /api/v1/orders` — Lista de pedidos
- `PUT /api/v1/orders/{uuid}` — Actualizar pedido
- `DELETE /api/v1/orders/{uuid}` — Cancelar pedido

**Cocina:**
- `GET /api/v1/kitchen/orders` — Pedidos para cocina
- `PUT /api/v1/kitchen/orders/{uuid}/status` — Cambiar estado

**Pagos:**
- `POST /api/v1/billing/payments` — Registrar pago
- `GET /api/v1/billing/payments` — Lista de pagos

**Caja:**
- `POST /api/v1/cash-sessions/open` — Apertura de caja
- `POST /api/v1/cash-sessions/close` — Cierre de caja
- `GET /api/v1/cash-sessions/current` — Sesión actual

**Sync:**
- `GET /api/v1/sync/health` — Estado del sync
- `GET /api/v1/sync/status` — Estadísticas
- `POST /api/v1/sync/push` — Push cambios locales
- `POST /api/v1/sync/pull` — Pull cambios del servidor

### 6.2 WebSocket (Reverb)

**Canales:**
- `kitchen.{branch_id}` — Eventos de cocina
- `waiters.{branch_id}` — Eventos de garzones
- `dashboard.{company_id}` — Eventos de dashboard

**Eventos:**
- `OrderCreated` — Nuevo pedido
- `OrderStatusChanged` — Cambio de estado
- `PaymentReceived` — Pago recibido
- `TableStatusChanged` — Cambio de estado de mesa

## 7. Estrategia de Testing

### 7.1 Frontend (React)

**Unit Tests (Vitest):**
- Funciones utilitarias
- Custom hooks
- Lógica de negocio (sin UI)
- Cobertura objetivo: 80%

**Component Tests (Testing Library):**
- Renderizado de componentes
- Interacciones de usuario
- Accesibilidad
- Cobertura objetivo: 70%

**E2E Tests (Playwright):**
- Flujo completo de login
- Flujo completo de pedido
- Flujo completo de pago
- Modo offline y sync
- Cobertura objetivo: 60%

### 7.2 Tauri (Rust)

**Unit Tests (cargo test):**
- Funciones de SQLite
- Lógica de sync
- Gestión de impresoras
- Cobertura objetivo: 90%

## 8. Riesgos Identificados

### 8.1 Riesgos Técnicos

| Riesgo | Probabilidad | Impacto | Mitigación |
|--------|--------------|---------|------------|
| Tauri v2 tiene bugs no documentados | Media | Alto | Mantener versión estable, reportar bugs, tener workaround |
| SQLite local se corrompe | Baja | Alto | Backups automáticos, validación de integridad |
| Conflicto de sync no resuelto | Media | Medio | ConflictResolver con 4 estrategias, logs detallados |
| Impresoras ESC/POS incompatibles | Media | Medio | Testing con múltiples modelos, fallback a PDF |
| Performance en hardware antiguo | Baja | Medio | Optimización de React, lazy loading, virtualización |

### 8.2 Riesgos de Proyecto

| Riesgo | Probabilidad | Impacto | Mitigación |
|--------|--------------|---------|------------|
| Stack Tauri/Rust desconocido | Alta | Alto | Tiempo de aprendizaje, documentación, ejemplos |
| Scope creep (agregar features) | Media | Medio | Stick to spec, priorizar MVP |
| Integración con backend compleja | Media | Medio | API ya documentada, tests de integración |
| Testing insuficiente | Media | Alto | CI/CD obligatorio, code review |

## 9. Definición de Done (DoD)

### 9.1 Para cada feature

- Código implementado según especificación
- Tests unitarios pasando (cobertura >80%)
- Tests de integración pasando
- Documentación actualizada
- Code review aprobado
- Sin errores de TypeScript
- Sin warnings de ESLint
- Accesibilidad (WCAG 2.1 AA)

### 9.2 Para el proyecto completo

- Todos los módulos implementados
- Tests E2E pasando (flujo completo)
- Modo offline funcionando
- Sync con backend funcionando
- Impresión de tickets funcionando
- Build de producción optimizado
- Documentación de usuario
- Documentación técnica
- Deploy guide

## 10. Cronograma Detallado

| Semana | Días | Sub-fase | Entregable |
|--------|------|----------|------------|
| **Semana 1** | 19-23 Ago | F13.1 + F13.2 | Setup + Auth |
| **Semana 2** | 26-30 Ago | F13.3 + F13.4 | Catálogo + Pedidos |
| **Semana 3** | 02-06 Sep | F13.5 + F13.6 | Cocina + Pagos |
| **Semana 4** | 09-11 Sep | F13.7 + F13.8 | Sync + Pulido |

**Hito M5:** 11 Sep 2026 — DEMO POS DESKTOP OPERATIVO

## 11. Métricas de Éxito

### 11.1 Funcionales

- Login en <2 segundos
- Crear pedido en <30 segundos
- Sync offline→online en <10 segundos
- Impresión de ticket en <3 segundos
- Cero pérdida de datos en modo offline

### 11.2 No Funcionales

- Tiempo de inicio de app <5 segundos
- Consumo de memoria <300MB
- Tamaño de binario <50MB
- 99% uptime en modo offline
- 95% de tests pasando

## 12. Próximos Pasos

1. **Merge COMBO-FLEX a main** (ya completado)
2. Crear repositorio para frontend (nuevo repo o subdirectorio)
3. Inicializar proyecto Tauri v2
4. Configurar CI/CD (GitHub Actions)
5. Comenzar F13.1: Setup Tauri + React

## 13. Referencias

- [Tauri v2 Documentation](https://v2.tauri.app/)
- [React 18 Documentation](https://react.dev/)
- [Tailwind CSS](https://tailwindcss.com/)
- [Zustand](https://github.com/pmndrs/zustand)
- [shadcn/ui](https://ui.shadcn.com/)

---

**Documento creado:** 18 Agosto 2026
**Autor:** AI Assistant
**Versión:** 1.0
**Estado:** Pendiente de aprobación
