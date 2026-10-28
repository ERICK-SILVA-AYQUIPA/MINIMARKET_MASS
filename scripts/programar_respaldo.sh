#!/usr/bin/env bash
# Programa el respaldo diario (23:00) en el crontab del usuario actual. Se puede correr dos veces sin duplicarlo.
#   bash scripts/programar_respaldo.sh          # programa
#   bash scripts/programar_respaldo.sh quitar   # lo quita
# El log queda en respaldos/respaldo.log (respaldos/ no se sube a GitHub).
set -euo pipefail
cd "$(dirname "$0")/.."

PROYECTO="$(pwd)"
MARCA="# minimarket_mass respaldo diario"
LINEA="0 23 * * * cd '$PROYECTO' && bash scripts/respaldo.sh >> respaldos/respaldo.log 2>&1 $MARCA"

ACTUAL="$(crontab -l 2>/dev/null | grep -vF "$MARCA" || true)"
if [ "${1:-}" = "quitar" ]; then
    printf '%s\n' "$ACTUAL" | sed '/^$/d' | crontab -
    echo "Respaldo diario quitado del crontab."
    exit 0
fi

mkdir -p respaldos
printf '%s\n%s\n' "$ACTUAL" "$LINEA" | sed '/^$/d' | crontab -
echo "Listo: el respaldo corre todos los dias a las 23:00. Revisa con: crontab -l"
