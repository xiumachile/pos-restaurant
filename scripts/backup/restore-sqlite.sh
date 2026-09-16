#!/bin/bash
# Restauración de SQLite local
# Uso: ./scripts/backup/restore-sqlite.sh <backup_file>
# Ejemplo: ./scripts/backup/restore-sqlite.sh backups/sqlite/local_20260916_120000.sqlite

set -e

if [ -z "$1" ]; then
    echo "❌ Error: Debes especificar el archivo de backup"
    echo "   Uso: $0 <backup_file.sqlite>"
    exit 1
fi

BACKUP_FILE="$1"
TARGET_DB="database/local.sqlite"

if [ ! -f "${BACKUP_FILE}" ]; then
    echo "❌ Error: Archivo no encontrado: ${BACKUP_FILE}"
    exit 1
fi

echo "⚠️  ADVERTENCIA: Esta operación sobrescribirá la base de datos local"
echo "   Backup a restaurar: ${BACKUP_FILE}"
echo ""
read -p "¿Continuar? (escribe 'RESTORE' para confirmar): " CONFIRM

if [ "${CONFIRM}" != "RESTORE" ]; then
    echo "❌ Operación cancelada"
    exit 1
fi

echo "🔄 Iniciando restauración..."

# Crear backup de seguridad
if [ -f "${TARGET_DB}" ]; then
    BACKUP_SECURITY="${TARGET_DB}.backup.$(date +%Y%m%d_%H%M%S)"
    cp "${TARGET_DB}" "${BACKUP_SECURITY}"
    echo "💾 Backup de seguridad creado: ${BACKUP_SECURITY}"
fi

# Restaurar
cp "${BACKUP_FILE}" "${TARGET_DB}"

# Verificar integridad
if sqlite3 "${TARGET_DB}" "PRAGMA integrity_check;" | grep -q "ok"; then
    echo "✅ Restauración completada"
    echo "   Integridad: OK"
else
    echo "❌ Error: Base de datos restaurada está corrupta"
    exit 1
fi
