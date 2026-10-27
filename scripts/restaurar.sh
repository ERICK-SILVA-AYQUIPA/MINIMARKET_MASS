#!/usr/bin/env bash
# Restaura un respaldo hecho con scripts/respaldo.sh. REEMPLAZA todos los datos actuales de minimarket_mass.
#   bash scripts/restaurar.sh respaldos/minimarket_mass_2026-10-27_1530.sql
set -euo pipefail
cd "$(dirname "$0")/.."

CONTENEDOR=minimarket_mass_db
ARCHIVO="${1:-}"
if [ -z "$ARCHIVO" ] || [ ! -f "$ARCHIVO" ]; then
    echo "Uso: bash scripts/restaurar.sh respaldos/minimarket_mass_FECHA.sql" >&2
    exit 1
fi

read -r -p "Esto reemplaza TODOS los datos de minimarket_mass con $ARCHIVO. Escribe SI para seguir: " RESPUESTA
if [ "$RESPUESTA" != "SI" ]; then
    echo "Cancelado: no se cambio nada."
    exit 0
fi

# El respaldo trae DROP TABLE + CREATE TABLE de cada tabla, asi que deja la base igual que el dia del respaldo
docker exec -i "$CONTENEDOR" sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql -uroot' < "$ARCHIVO"
echo "Base restaurada desde $ARCHIVO"
