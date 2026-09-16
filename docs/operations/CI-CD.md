# CI/CD Pipeline

**Fecha**: Septiembre 2026  
**Estado**: ✅ Implementado y documentado  
**Criterio de cierre**: No permitir merge si fallan tests, typecheck, build, migraciones o validaciones críticas.

## Workflows

### `.github/workflows/ci.yml`

Pipeline principal ejecutado en cada push a `main`/`develop` y cada PR.

#### Jobs

| # | Job | Descripción | Timeout |
|---|-----|-------------|---------|
| 1 | `backend` | Pest tests con PostgreSQL + migraciones | 20 min |
| 2 | `frontend` | TypeScript + Vitest + Vite build | 15 min |
| 3 | `tauri-build` | Validación de build de app desktop | 30 min |
| 4 | `secret-scan` | Detección de secrets con gitleaks | 5 min |
| 5 | `ci-gate` | Gate final (todos deben pasar) | 1 min |

#### Dependencias
backend ──────┐
frontend ─────┤
tauri-build ──┼──► ci-gate
secret-scan ──┘


### `.github/workflows/secret-scan.yml`

Scan independiente de secrets (legacy, ahora incluido en ci.yml).

## Scripts Locales

### Backend

```bash
# Ejecutar todos los tests
php artisan test

# Con migraciones
php artisan migrate --force
php artisan test

Frontend
# Type check
npm run typecheck

# Tests en CI mode (sin watch)
npm run test:ci

# Build
npm run build

# Tauri build
npm run tauri build

Branch Protection Rules
Configurar en GitHub (Settings → Branches → Add rule):
Branch: main

Branch name pattern: main
Protection rules:
  ✓ Require a pull request before merging
    ✓ Require approvals: 1
    ✓ Dismiss stale pull request approvals when new commits are pushed
  ✓ Require status checks to pass before merging
    ✓ Require branches to be up to date before merging
    Status checks required:
      - CI Gate (all checks passed)
      - Backend (Pest)
      - Frontend (TS + Build + Vitest)
      - Tauri Build Validation
      - Secret Scan
  ✓ Require conversation resolution before merging
  ✓ Do not allow bypassing the above settings
  ✓ Restrict who can push to matching branches

Branch: develop
Branch name pattern: develop
Protection rules:
  ✓ Require status checks to pass before merging
    Status checks required:
      - CI Gate (all checks passed)
  ✓ Require conversation resolution before merging

Gate Checks Obligatorios
1. Backend (Pest)
Valida: 997+ tests pasando
Incluye: Migraciones aplicadas en PostgreSQL limpio
Falla si: Algún test falla o migración rompe esquema
2. Frontend (TS + Build + Vitest)
Valida:
npm run typecheck sin errores
npm run test:ci sin tests fallando
npm run build genera dist/ exitosamente
Falla si: Error de TypeScript, test falla, o build rompe
3. Tauri Build
Valida: Aplicación desktop compila sin errores
Incluye: Build en modo debug (sin bundle)
Falla si: Error de compilación Rust o frontend
Plataformas: Ubuntu Linux (otros platforms via releases)
4. Secret Scan
Valida: Sin secrets expuestos en código o historial
Herramienta: gitleaks
Falla si: Detecta API keys, passwords, tokens, etc.
5. CI Gate
Valida: Todos los jobs anteriores pasaron
Falla si: Cualquier job previo falló
Ejecución Local
Reproducir CI completo
# Backend
php artisan migrate:fresh --force
php artisan test

# Frontend
cd frontend
npm ci
npm run typecheck
npm run test:ci
npm run build
npm run tauri build --debug --no-bundle

Solo checks críticos
# Backend (sin migraciones)
php artisan test

# Frontend
cd frontend
npm run typecheck && npm run build

Troubleshooting
Backend falla por migraciones
# Resetear BD de test
php artisan migrate:fresh --env=testing
php artisan test

Frontend falla por typecheck
cd frontend
npm run typecheck 2>&1 | grep "error TS"

Tauri build falla en Linux
# Instalar dependencias
sudo apt-get install -y \
  libwebkit2gtk-4.1-dev \
  build-essential \
  libssl-dev \
  libayatana-appindicator3-dev \
  librsvg2-dev

Secret scan detecta falso positivo
Agregar a .gitleaksignore:
[allowlist]
  description = "Global allowlist"
  paths = [
    '''\.env\.example$''',
    '''tests/.*'''
  ]

Métricas
Métrica	Objetivo	Actual
Tiempo de CI	< 15 min	~12 min
Backend tests	< 3 min	~2 min
Frontend build	< 5 min	~4 min
Tauri build	< 20 min	~15 min
Secret scan	< 1 min	~30s

Reglas Post-Freeze
🔒 Ningún merge a main sin CI pasando
🔒 Todos los PRs requieren aprobación
🔒 Cualquier cambio a CI requiere revisión de 2 personas
🔒 Cambios a branch protection requieren aprobación de admin
Monitoreo
GitHub Actions
URL: https://github.com/xiumachile/pos-restaurant/actions
Dashboard: Ver workflow runs por branch
Notificaciones
Configurar notificaciones de fallos en:
Slack webhook (opcional)
Email de equipo
GitHub notifications
Próximos Pasos
Inmediato
✅ CI/CD implementado
📋 Configurar branch protection rules en GitHub UI
📢 Comunicar al equipo las reglas de merge
Futuro (opcional)
Deploy automático a staging (post-merge a develop)
Deploy a producción (post-merge a main, manual approval)
Multi-platform Tauri builds (Windows, macOS)
Test coverage reporting
Bundle size tracking
