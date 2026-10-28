#!/usr/bin/env bash
# Respaldo de la base minimarket_mass (tablas, datos, vista y procedimiento) en respaldos/AAAA-MM-DD_HHMM.sql
#   bash scripts/respaldo.sh
# La clave de root no se escribe aqui: la lee MySQL dentro del contenedor (variable MYSQL_ROOT_PASSWORD).
# Los respaldos llevan DNIs y hashes de claves: la carpeta respaldos/ esta en .gitignore y NO se sube a GitHub.
set -euo pipefail
cd "$(dirname "$0")/.."

CONTENEDOR=minimarket_mass_db
mkdir -p respaldos
ARCHIVO="respaldos/minimarket_mass_$(date +%Y-%m-%d_%H%M).sql"

docker exec "$CONTENEDOR" sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysqldump -uroot --single-transaction --routines --triggers --databases minimarket_mass' > "$ARCHIVO"

# Un respaldo vacio o cortado no sirve: se revisa que termine bien
if ! tail -n 1 "$ARCHIVO" | grep -q "Dump completed"; then
    echo "El respaldo no se completo. Revisa que el contenedor este corriendo (docker compose ps)." >&2
    rm -f "$ARCHIVO"
    exit 1
fi
echo "Respaldo listo: $ARCHIVO ($(du -h "$ARCHIVO" | cut -f1))"

# Se guardan los ultimos 30 respaldos (un mes si es diario); los mas antiguos se borran
GUARDAR=30
ls -1t respaldos/minimarket_mass_*.sql | tail -n +$((GUARDAR + 1)) | while read -r VIEJO; do
    rm -f -- "$VIEJO"
    echo "Borrado por antiguo: $VIEJO"
done
