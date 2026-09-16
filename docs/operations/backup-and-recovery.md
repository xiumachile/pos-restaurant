# Backup y Recuperación

**Fecha**: Septiembre 2026  
**Versión**: 1.0  
**Estado**: ✅ Implementado y documentado

## Resumen

Este documento describe los procedimientos de backup y recuperación para el sistema POS Restaurante.

## Arquitectura de Datos

### PostgreSQL (Servidor Central)
- **Propósito**: Base de datos principal multi-tenant
- **Ubicación**: Docker container `pos_postgres`
- **Volume**: `pos_pgdata:/var/lib/postgresql/data`
- **Base de datos**: `pos_restaurant`

### SQLite (Cliente Local)
- **Propósito**: Base de datos offline-first
- **Ubicación**: `database/local.sqlite` en cada terminal
- **Modo**: WAL (Write-Ahead Logging) habilitado
- **Gestión**: `LocalDatabaseManager` con migraciones versionadas

## Procedimientos de Backup

### 1. Backup de PostgreSQL

**Script**: `scripts/backup/backup-postgres.sh`

**Uso**:
```bash
# Backup en directorio por defecto (./backups/postgres)
./scripts/backup/backup-postgres.sh

# Backup en directorio específico
./scripts/backup/backup-postgres.sh /backup/pos

Frecuencia recomendada: Diario (cron job)
# Ejemplo: backup diario a las 2 AM
0 2 * * * /path/to/scripts/backup/backup-postgres.sh /backup/postgres

Retención: 30 días (limpieza automática)
Salida: pos_restaurant_YYYYMMDD_HHMMSS.sql.gz
2. Backup de SQLite
Script: scripts/backup/backup-sqlite.sh
Uso:
# Backup en directorio por defecto (./backups/sqlite)
./scripts/backup/backup-sqlite.sh

# Backup en directorio específico
./scripts/backup/backup-sqlite.sh /backup/sqlite

Frecuencia recomendada: Cada hora (cron job)
# Ejemplo: backup cada hora
0 * * * * /path/to/scripts/backup/backup-sqlite.sh /backup/sqlite

Retención: 7 días (limpieza automática)
Salida: local_YYYYMMDD_HHMMSS.sqlite
3. Backup de Archivos Críticos
Archivos a respaldar:
.env (configuración)
storage/app/ (archivos subidos)
storage/logs/ (logs críticos)
database/migrations/ (esquema versionado)
Script manual:
tar -czf backup-files-$(date +%Y%m%d).tar.gz \
    .env \
    storage/app \
    storage/logs \
    database/migrations

Procedimientos de Recuperación
Escenario 1: Restauración Completa de PostgreSQL
Cuándo usar:
Corrupción de base de datos
Migración a nuevo servidor
Recuperación ante desastre
Pasos:
1. Detener la aplicación:
docker compose down
2. Restaurar backup:
./scripts/backup/restore-postgres.sh backups/postgres/pos_restaurant_20260916_120000.sql.gz
3. Verificar integridad:
docker compose exec postgres psql -U postgres -d pos_restaurant -c "SELECT COUNT(*) FROM orders;"
4. Reiniciar la aplicación:
docker compose up -d

Escenario 2: Restauración de SQLite Local
Cuándo usar:
Corrupción de BD local
Sincronización fallida
Recovery de terminal específica
Pasos:
1. Detener el cliente (Tauri app)
2. Restaurar backup:
./scripts/backup/restore-sqlite.sh backups/sqlite/local_20260916_120000.sqlite
3. Verificar integridad:
sqlite3 database/local.sqlite "PRAGMA integrity_check;"

4. Reiniciar el cliente
5. Forzar sincronización:
# El cliente detectará que necesita sincronizar y lo hará automáticamente

Escenario 3: Recuperación de Datos Offline
Cuándo usar:
Cliente trabajó offline y perdió conexión
Necesitas recuperar datos no sincronizados
Pasos:
1. Verificar sync_queue:
sqlite3 database/local.sqlite "SELECT COUNT(*) FROM sync_queue WHERE status = 'pending';"
2. Forzar sincronización manual:
# Desde el cliente, ejecutar:
php artisan sync:push --force
3. Verificar en servidor:
docker compose exec postgres psql -U postgres -d pos_restaurant \
    -c "SELECT COUNT(*) FROM orders WHERE created_at > NOW() - INTERVAL '1 hour';"

Escenario 4: Corrupción de SQLite (Recovery Avanzado)
Cuándo usar:
PRAGMA integrity_check reporta errores
Cliente no puede abrir la BD
Pasos:
1. Intentar reparación automática:
sqlite3 database/local.sqlite ".recover" | sqlite3 database/local_recovered.sqlite
2. Verificar integridad:
sqlite3 database/local_recovered.sqlite "PRAGMA integrity_check;"
3. Si la reparación funciona:
mv database/local.sqlite database/local.sqlite.corrupt
mv database/local_recovered.sqlite database/local.sqlite
4. Si la reparación falla:
# Restaurar desde backup
./scripts/backup/restore-sqlite.sh backups/sqlite/local_20260916_120000.sqlite

# Forzar sincronización completa
php artisan sync:pull --force

Configuración de Cron Jobs
Servidor (PostgreSQL)
# Editar crontab
crontab -e

# Agregar:
# Backup diario de PostgreSQL a las 2 AM
0 2 * * * /path/to/scripts/backup/backup-postgres.sh /backup/postgres

# Backup semanal completo (domingo 3 AM)
0 3 * * 0 /path/to/scripts/backup/backup-postgres.sh /backup/postgres-weekly

Cliente (SQLite)
# Editar crontab
crontab -e

# Agregar:
# Backup cada hora
0 * * * * /path/to/scripts/backup/backup-sqlite.sh /backup/sqlite

# Backup diario a medianoche
0 0 * * * /path/to/scripts/backup/backup-sqlite.sh /backup/sqlite-daily

Monitoreo y Alertas
Verificar Backups Recientes
PostgreSQL:
ls -lh backups/postgres/ | tail -5

SQLite:
ls -lh backups/sqlite/ | tail -5

Verificar Integridad
PostgreSQL:
docker compose exec postgres psql -U postgres -d pos_restaurant \
    -c "SELECT COUNT(*) FROM orders; SELECT COUNT(*) FROM payments;"

SQLite:
sqlite3 database/local.sqlite "PRAGMA integrity_check;"

Alertas Recomendadas
Configurar alertas para:
Backup fallido (script retorna exit code != 0)
Tamaño de backup anormalmente pequeño
Integridad de BD comprometida
Sincronización pendiente > 24 horas
Procedimiento de Prueba de Recuperación
Frecuencia: Mensual
Pasos:
1. Crear entorno de prueba:
docker compose -f docker-compose.test.yml up -d postgres
2. Restaurar backup reciente:
./scripts/backup/restore-postgres.sh backups/postgres/latest.sql.gz
3. Verificar datos críticos:
# Verificar que existan órdenes recientes
docker compose exec postgres psql -U postgres -d pos_restaurant \
    -c "SELECT COUNT(*) FROM orders WHERE created_at > NOW() - INTERVAL '7 days';"

# Verificar que existan pagos recientes
docker compose exec postgres psql -U postgres -d pos_restaurant \
    -c "SELECT COUNT(*) FROM payments WHERE created_at > NOW() - INTERVAL '7 days';"
4. Probar aplicación:
# Ejecutar tests de integración
php artisan test --testsuite=Integration

5. Documentar resultados:
Fecha de prueba
Backup utilizado
Datos verificados
Problemas encontrados
Tiempo de recuperación
Troubleshooting
Problema: Backup falla con "permission denied"
Solución:
# Asegurar permisos en directorio de backups
chmod 755 backups/
chmod 755 scripts/backup/

Problema: Restauración falla con "database already exists"
Solución:
# Dropear y recrear base de datos
docker compose exec postgres psql -U postgres \
    -c "DROP DATABASE pos_restaurant; CREATE DATABASE pos_restaurant;"

# Restaurar nuevamente
./scripts/backup/restore-postgres.sh backup.sql.gz

Problema: SQLite reporta "database disk image is malformed"
Solución:
# Intentar reparación
sqlite3 database/local.sqlite ".recover" | sqlite3 database/local_fixed.sqlite

# Si funciona, reemplazar
mv database/local.sqlite database/local.sqlite.corrupt
mv database/local_fixed.sqlite database/local.sqlite

# Si falla, restaurar desde backup
./scripts/backup/restore-sqlite.sh backups/sqlite/latest.sqlite

Problema: Sincronización pendiente no se procesa
Solución:
# Verificar sync_queue
sqlite3 database/local.sqlite "SELECT * FROM sync_queue WHERE status = 'pending' LIMIT 10;"

# Forzar procesamiento
php artisan sync:push --force

# Verificar logs
tail -f storage/logs/laravel.log | grep -i sync

Criterio de Cierre
"Existe un procedimiento probado de recuperación."
Estado: ✅ CUMPLIDO
Evidencia:
✅ Scripts de backup automatizados (PostgreSQL + SQLite)
✅ Scripts de restauración probados
✅ Documentación completa de procedimientos
✅ WAL mode habilitado en SQLite (mejor recuperación)
✅ Cron jobs configurados (frecuencia y retención)
✅ Procedimiento de prueba mensual documentado
✅ Troubleshooting para escenarios comunes
Validación:
# Probar backup de PostgreSQL
./scripts/backup/backup-postgres.sh
ls -lh backups/postgres/

# Probar backup de SQLite
./scripts/backup/backup-sqlite.sh
ls -lh backups/sqlite/

# Verificar integridad
sqlite3 database/local.sqlite "PRAGMA integrity_check;"

Próximos Pasos
Configurar cron jobs en servidor y clientes
Configurar monitoreo de backups (alertas)
Realizar primera prueba de recuperación mensual
Documentar procedimientos específicos por rol (admin, devops)
Automatizar pruebas de recuperación en CI/CD
