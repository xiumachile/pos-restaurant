#!/bin/bash
# Backup de SQLite local
# Uso: ./scripts/backup/backup-sqlite.sh [output_dir]
# Ejemplo: ./scripts/backup/backup-sqlite.sh /backup/sqlite

set -e

OUTPUT_DIR="${1:-./backups/sqlite}"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
SOURCE_DB="database/local.sqlite"
BACKUP_NAME="local_${TIMESTAMP}.sqlite"
BACKUP_PATH="${OUTPUT_DIR}/${BACKUP_NAME}"

# Verificar que la BD existe
if [ ! -f "${SOURCE_DB}" ]; then
    echo "❌ Error: Base de datos SQLite no encontrada: ${SOURCE_DB}"
    exit 1
fi

# Crear directorio si no existe
mkdir -p "${OUTPUT_DIR}"

echo "💾 Iniciando backup de SQLite..."
echo "   Origen: ${SOURCE_DB}"
echo "   Destino: ${BACKUP_PATH}"

# Copiar la BD (SQLite permite copiar el archivo directamente)
cp "${SOURCE_DB}" "${BACKUP_PATH}"

# Verificar integridad del backup
if sqlite3 "${BACKUP_PATH}" "PRAGMA integrity_check;" | grep -q "ok"; then
    SIZE=$(du -h "${BACKUP_PATH}" | cut -f1)
    echo "✅ Backup completado exitosamente"
    echo "   Tamaño: ${SIZE}"
    echo "   Integridad: OK"
    echo "   Archivo: ${BACKUP_PATH}"
else
    echo "❌ Error: Backup corrupto"
    rm -f "${BACKUP_PATH}"
    exit 1
fi

# Limpiar backups antiguos (mantener últimos 7 días para SQLite)
find "${OUTPUT_DIR}" -name "local_*.sqlite" -type f -mtime +7 -delete
echo "🗑️  Backups antiguos (>7 días) eliminados"
