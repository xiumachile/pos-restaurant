## Arquitectura de producción
              Internet
                 │
       ┌─────────▼─────────┐
       │   Load Balancer    │
       │   (nginx/caddy)    │
       └─────────┬─────────┘
                 │
       ┌─────────▼─────────┐
       │   Laravel API      │
       │   (PHP-FPM 8.2)    │
       │   + PostgreSQL 15  │
       │   + Redis (cache)  │
       └─────────┬─────────┘
                 │
    ┌────────────▼────────────┐
    │    Tauri Desktop App     │
    │  (distribución local)    │
    │  React + SQLite local    │
    │  Sincronización auto     │
    └──────────────────────────┘


---

## 1. Backend (Laravel)

### 1.1 Requisitos del servidor

| Componente | Mínimo | Recomendado |
|------------|--------|-------------|
| CPU | 2 cores | 4 cores |
| RAM | 2 GB | 4 GB |
| Disco | 20 GB SSD | 50 GB SSD |
| PHP | 8.2 | 8.3 |
| PostgreSQL | 15 | 16 |
| Redis | 7 | 7+ |

### 1.2 Variables de entorno críticas

```bash
# .env de producción
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.tu-dominio.com

# Base de datos
DB_CONNECTION=pgsql
DB_HOST=localhost
DB_DATABASE=pos_restaurant
DB_USERNAME=pos_user
DB_PASSWORD=<contraseña-fuerte-generada>

# JWT
JWT_SECRET=<generado-con-php-artisan-jwt:secret>

# Cache
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# Seguridad
CORS_ALLOWED_ORIGINS=https://tu-dominio.com
SANCTUM_STATEFUL_DOMAINS=tu-dominio.com

1.3 Comandos de deploy

# 1. Clonar y configurar
git clone git@github.com:xiumachile/pos-restaurant.git
cd pos-restaurant
cp .env.example .env
# Editar .env con valores de producción

# 2. Instalar dependencias
composer install --no-dev --optimize-autoloader

# 3. Generar key y JWT
php artisan key:generate
php artisan jwt:secret

# 4. Migraciones
php artisan migrate --force

# 5. Caché de producción
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# 6. Permisos
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# 7. Verificar
php artisan test --testsuite=Feature

1.4 nginx (ejemplo)
server {
    listen 443 ssl http2;
    server_name api.tu-dominio.com;

    root /var/www/pos-restaurant/public;
    index index.php;

    ssl_certificate /etc/letsencrypt/live/api.tu-dominio.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/api.tu-dominio.com/privkey.pem;

    # Headers de seguridad
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}

2. Frontend (Tauri Desktop)
2.1 Build de producción

cd frontend

# 1. Instalar dependencias
npm ci

# 2. Build web (React)
npm run build

# 3. Build Tauri (genera instalador nativo)
npm run tauri build

2.2 Artefactos generados
OS
Archivo
Ubicación
Linux
.deb, .AppImage
src-tauri/target/release/bundle/
Windows
.msi, .exe
src-tauri/target/release/bundle/
macOS
.dmg, .app
src-tauri/target/release/bundle/

2.3 Configuración de producción
En frontend/src-tauri/tauri.conf.json:

{
  "build": {
    "beforeBuildCommand": "npm run build",
    "frontendDist": "../dist"
  },
  "app": {
    "windows": [{
      "title": "POS Restaurant",
      "width": 1280,
      "height": 800,
      "fullscreen": false,
      "resizable": true
    }],
    "security": {
      "csp": "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; connect-src 'self' https://api.tu-dominio.com; img-src 'self' data:; font-src 'self' data:; frame-src 'none'; object-src 'none';"
    }
  }
}

2.4 Configuración de API en producción
En frontend/.env.production:

VITE_API_BASE_URL=https://api.tu-dominio.com/api

3. Base de datos
3.1 PostgreSQL (Backend)

# Crear usuario y base de datos
sudo -u postgres psql
CREATE USER pos_user WITH PASSWORD '<contraseña-fuerte>';
CREATE DATABASE pos_restaurant OWNER pos_user;
GRANT ALL PRIVILEGES ON DATABASE pos_restaurant TO pos_user;

# Configurar para producción
# postgresql.conf
max_connections = 100
shared_buffers = 1GB
effective_cache_size = 3GB
work_mem = 4MB
maintenance_work_mem = 256MB

3.2 SQLite (Frontend local)
La base de datos SQLite local se crea automáticamente al iniciar la app.
Ubicación: $APP_DATA/pos-restaurant/local.db
OS
Ruta
Linux
~/.local/share/pos-restaurant/local.db
Windows
%APPDATA%\pos-restaurant\local.db
macOS
~/Library/Application Support/pos-restaurant/local.db
Backup automático: El archivo SQLite se puede copiar directamente como backup.

4. Checklist de producción
Pre-deploy
Variables de entorno configuradas (.env)
JWT secret generado (php artisan jwt:secret)
PostgreSQL configurado con usuario dedicado
SSL/TLS configurado (Let's Encrypt o similar)
CORS configurado con dominio exacto
Redis configurado para cache/session/queue
Backups de PostgreSQL programados (diario)
Post-deploy
php artisan test pasa (875 tests)
API accesible desde navegador (/api/health)
Login funciona (JWT token retornado)
App Tauri conecta al backend
Crear empresa y sucursal de prueba
Crear usuario cajero de prueba
Flujo completo: login → abrir caja → crear pedido → cobrar → cerrar caja
Test offline: desconectar internet → operar → reconectar → verificar sync
Monitoreo

# Logs del backend
tail -f storage/logs/laravel.log

# Cola de sync pendientes (desde la app)
# Menú → Diagnóstico → Cola de sincronización

# Base de datos
pg_stat_activity  # Conexiones activas
pg_stat_user_tables  # Actividad por tabla

5. Troubleshooting
"La app no sincroniza"
Verificar conexión: curl https://api.tu-dominio.com/api/health
Verificar token: ¿El usuario tiene sesión activa?
Verificar sync_queue: ¿Hay items en estado failed?
Verificar logs: [SyncEngine] en consola de la app
"Items marcados como failed"
Verificar last_error en sync_queue
Si dice [MultiTenant]: el item pertenece a otra company/branch
Si dice 422: el backend rechazó por validación (ver response data)
Si dice 401: token expirado, re-login necesario
"Base de datos local corrupta"
Cerrar la app
Borrar local.db (se recreará automáticamente)
Abrir la app → los datos se re-sincronizarán desde el backend
⚠️ Datos no sincronizados se perderán
6. Seguridad en producción
Hardening obligatorio
Firewall: Solo puertos 443 (HTTPS) y 22 (SSH)
Fail2ban: Protección contra brute force
Updates: unattended-upgrades para parches de seguridad
Backups: PostgreSQL dump diario + rotación de 30 días
Logs: Centralizar en servicio externo (ej: Papertrail)
Secretos: Nunca en git, solo en .env del servidor
Rotación de secretos

# Cada 90 días:
php artisan jwt:secret   # Regenerar JWT secret
# Actualizar APP_KEY si es necesario
# Cambiar contraseña de PostgreSQL
# Los usuarios deberán re-loguearse después de rotar JWT
