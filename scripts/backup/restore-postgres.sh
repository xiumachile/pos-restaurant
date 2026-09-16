#!/bin/bash
# Restauración de PostgreSQL
# Uso: ./scripts/backup/restore-postgres.sh <backup_file>
# Ejemplo: ./scripts/backup/restore-postgres.sh backups/postgres/pos_restaurant_20260916_120000.sql.gz

set -e

if [ -z "$1" ]; then
    echo "❌ Error: Debes especificar el archivo de backup"
    echo "   Uso: $0 <backup_file.sql.gz>"
    exit 1
fi

BACKUP_FILE="$1"

if [ ! -f "${BACKUP_FILE}" ]; then
    echo "❌ Error: Archivo no encontrado: ${BACKUP_FILE}"
    exit 1
fi

echo "⚠️  ADVERTENCIA: Esta operación sobrescribirá la base de datos actual"
echo "   Backup a restaurar: ${BACKUP_FILE}"
echo ""
read -p "¿Continuar? (escribe 'RESTORE' para confirmar): " CONFIRM

if [ "${CONFIRM}" != "RESTORE" ]; then
    echo "❌ Operación cancelada"
    exit 1
fi

echo "🔄 Iniciando restauración..."

# Descomprimir y restaurar
gunzip -c "${BACKUP_FILE}" | docker compose exec -T postgres psql \
    --username=postgres \
    --dbname=pos_restaurant \
    --quiet

echo "✅ Restauración completada"
echo "   Verifica la integridad de los datos"
