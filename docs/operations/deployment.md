# Guía de Despliegue

**Fecha**: Septiembre 2026  
**Versión**: 1.0.0  
**Estado**: 🟢 Producción Ready

## Requisitos del Sistema

### Backend (Laravel)
- **PHP**: 8.3+
- **PostgreSQL**: 16+
- **Redis**: 7+
- **Composer**: 2.x
- **Extensiones PHP**: pdo_pgsql, bcmath, intl, gd, redis

### Frontend (Tauri)
- **Node.js**: 20+
- **Rust**: 1.70+
- **Sistema Operativo**: Windows 10+, macOS 12+, Ubuntu 22.04+

### Infraestructura
- **Servidor**: 2 CPU, 4GB RAM mínimo
- **Almacenamiento**: 20GB SSD
- **Red**: Conexión estable (para sincronización)

## Despliegue Backend

### 1. Preparar Servidor

```bash
# Actualizar sistema
sudo apt update && sudo apt upgrade -y

# Instalar dependencias
sudo apt install -y \
  nginx \
  postgresql-16 \
  redis-server \
  php8.3-fpm \
  php8.3-pgsql \
  php8.3-bcmath \
  php8.3-intl \
  php8.3-gd \
  php8.3-redis \
  composer

# Iniciar servicios
sudo systemctl enable --now postgresql redis php8.3-fpm nginx

2. Configurar PostgreSQL
# Crear usuario y base de datos
sudo -u postgres psql

CREATE USER pos_user WITH PASSWORD 'secure_password_here';
CREATE DATABASE pos_restaurant OWNER pos_user;
GRANT ALL PRIVILEGES ON DATABASE pos_restaurant TO pos_user;
\q

# Configurar autenticación
sudo nano /etc/postgresql/16/main/pg_hba.conf
# Agregar: host pos_restaurant pos_user 127.0.0.1/32 md5

sudo systemctl restart postgresql

3. Desplegar Aplicación
# Clonar repositorio
cd /var/www
sudo git clone https://github.com/xiumachile/pos-restaurant.git
sudo chown -R $USER:www-data pos-restaurant
cd pos-restaurant

# Instalar dependencias
composer install --no-dev --optimize-autoloader

# Configurar entorno
cp .env.example .env
nano .env  # Configurar variables

# Generar key y migrar
php artisan key:generate
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

4. Configurar Nginx
sudo nano /etc/nginx/sites-available/pos-restaurant

server {
    listen 80;
    server_name api.tudominio.com;
    root /var/www/pos-restaurant/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}

sudo ln -s /etc/nginx/sites-available/pos-restaurant /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx

5. Configurar Queue Workers (Horizon)

# Instalar Supervisor
sudo apt install -y supervisor

# Configurar Horizon
sudo nano /etc/supervisor/conf.d/horizon.conf

[program:horizon]
process_name=%(program_name)s
command=php /var/www/pos-restaurant/artisan horizon
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/pos-restaurant/storage/logs/horizon.log

sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start horizon

6. Configurar SSL (Certbot)

sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d api.tudominio.com

Despliegue Frontend (Tauri)
Build para Producción

cd frontend

# Instalar dependencias
npm ci

# Build frontend
npm run build

# Build Tauri
npm run tauri build

Distribución
Los instaladores se generan en frontend/src-tauri/target/release/bundle/:
Windows: .msi y .exe
macOS: .dmg y .app
Linux: .deb y .AppImage
Actualizaciones
Para actualizaciones automáticas, configurar Tauri Updater:

# Generar claves de firma
npx tauri signer generate -w ~/.tauri/pos-restaurant.key

# Configurar en tauri.conf.json
# "updater": {
#   "active": true,
#   "endpoints": ["https://releases.tudominio.com/{{target}}/{{current_version}}"],
#   "pubkey": "TU_PUBLIC_KEY"
# }

Variables de Entorno Críticas
Backend (.env)

APP_NAME="POS Restaurant"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.tudominio.com

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=pos_restaurant
DB_USERNAME=pos_user
DB_PASSWORD=secure_password_here

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

QUEUE_CONNECTION=redis

CACHE_DRIVER=redis
SESSION_DRIVER=redis

Frontend (.env)

VITE_API_URL=https://api.tudominio.com
VITE_WS_URL=wss://api.tudominio.com

Monitoreo y Logs
Logs de Aplicación

# Backend
tail -f /var/www/pos-restaurant/storage/logs/laravel.log

# Horizon
tail -f /var/www/pos-restaurant/storage/logs/horizon.log

# Nginx
tail -f /var/log/nginx/access.log
tail -f /var/log/nginx/error.log

Métricas
Configurar monitoreo con:
Laravel Telescope (desarrollo)
Sentry (errores en producción)
Prometheus + Grafana (métricas)
Backup y Recovery
Ver documentación completa en: backup-and-recovery.md
Backup Automático

# Configurar cron
crontab -e

# Backup diario a las 2 AM
0 2 * * * /var/www/pos-restaurant/scripts/backup.sh >> /var/log/backup.log 2>&1

Rollback
Backend

# Rollback de código
cd /var/www/pos-restaurant
sudo git reset --hard HEAD~1

# Rollback de migraciones
php artisan migrate:rollback --step=1

# Limpiar caches
php artisan config:clear
php artisan route:clear
php artisan view:clear

Frontend
Distribuir versión anterior del instalador Tauri.
Checklist de Despliegue
Servidor configurado (PHP, PostgreSQL, Redis, Nginx)
Base de datos creada y migrada
Variables de entorno configuradas
SSL configurado
Queue workers corriendo (Horizon)
Backups automáticos configurados
Monitoreo configurado
Tests de smoke ejecutados
Documentación actualizada
Soporte
Documentación: /docs/
Issues: GitHub Issues
Email: soporte@tudominio.com
Última actualización: Septiembre 2026
Maintainer: Equipo POS Restaurant
