#!/bin/bash
# Backup de PostgreSQL
# Uso: ./scripts/backup/backup-postgres.sh [output_dir]
# Ejemplo: ./scripts/backup/backup-postgres.sh /backup/pos

set -e

OUTPUT_DIR="${1:-./backups/postgres}"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_NAME="pos_restaurant_${TIMESTAMP}.sql.gz"
BACKUP_PATH="${OUTPUT_DIR}/${BACKUP_NAME}"

# Crear directorio si no existe
mkdir -p "${OUTPUT_DIR}"

echo "🗄️  Iniciando backup de PostgreSQL..."
echo "   Base de datos: pos_restaurant"
echo "   Destino: ${BACKUP_PATH}"

# Ejecutar pg_dump dentro del container
docker compose exec -T postgres pg_dump \
    --username=postgres \
    --dbname=pos_restaurant \
    --format=plain \
    --verbose \
    --no-owner \
    --no-privileges \
    | gzip > "${BACKUP_PATH}"

# Verificar que el backup se creó
if [ -f "${BACKUP_PATH}" ]; then
    SIZE=$(du -h "${BACKUP_PATH}" | cut -f1)
    echo "✅ Backup completado exitosamente"
    echo "   Tamaño: ${SIZE}"
    echo "   Archivo: ${BACKUP_PATH}"
else
    echo "❌ Error: No se pudo crear el backup"
    exit 1
fi

# Limpiar backups antiguos (mantener últimos 30 días)
find "${OUTPUT_DIR}" -name "pos_restaurant_*.sql.gz" -type f -mtime +30 -delete
echo "🗑️  Backups antiguos (>30 días) eliminados"
