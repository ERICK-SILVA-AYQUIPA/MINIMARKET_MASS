#!/usr/bin/env bash
# Restaura un respaldo hecho con backend/scripts/respaldo.sh. REEMPLAZA todos los datos actuales de minimarket_mass
# y las fotos de producto.
#   bash backend/scripts/restaurar.sh respaldos/minimarket_mass_2026-11-03_2300.tar.gz.gpg
#   bash backend/scripts/restaurar.sh respaldos/minimarket_mass_2026-11-03_2300.tar.gz
#   bash backend/scripts/restaurar.sh respaldos/minimarket_mass_2026-10-27_1530.sql      (respaldos antiguos: solo la base)
# Agregar --si al final para no preguntar (lo usa la prueba de restauracion de la CI).
# Un respaldo .gpg pide la misma RESPALDO_CLAVE con la que se hizo (en .env o como variable).
# Para traer uno de la copia de afuera:  rclone copy "$RESPALDO_REMOTO/ARCHIVO" respaldos/
set -euo pipefail
cd "$(dirname "$0")/../.."
umask 077

CONTENEDOR=minimarket_mass_db
FOTOS=frontend/public/img/productos
ARCHIVO="${1:-}"
CONFIRMADO="${2:-}"
if [ -z "$ARCHIVO" ] || [ ! -f "$ARCHIVO" ]; then
    echo "Uso: bash backend/scripts/restaurar.sh respaldos/minimarket_mass_FECHA.tar.gz[.gpg] [--si]" >&2
    exit 1
fi

leer_env() {
    local valor="${!1:-}"
    if [ -z "$valor" ] && [ -f .env ]; then
        valor="$(grep -E "^$1=" .env | tail -n 1 | cut -d= -f2- | tr -d '\r' | sed -e 's/^"\(.*\)"$/\1/' -e "s/^'\(.*\)'$/\1/")" || valor=""
    fi
    printf '%s' "$valor"
}

TEMPORAL="$(mktemp -d)"
trap 'rm -rf "$TEMPORAL"' EXIT

# 1. Se abre el respaldo en una carpeta temporal y se revisa ANTES de tocar nada
TAR=""
case "$ARCHIVO" in
    *.tar.gz.gpg)
        CLAVE="$(leer_env RESPALDO_CLAVE)"
        if [ -z "$CLAVE" ]; then
            echo "El respaldo esta cifrado: pon en .env la RESPALDO_CLAVE con la que se hizo." >&2
            exit 1
        fi
        if ! gpg --batch --quiet --pinentry-mode loopback --passphrase-fd 3 -o "$TEMPORAL/respaldo.tar.gz" -d "$ARCHIVO" 3<<< "$CLAVE"; then
            echo "No se pudo descifrar $ARCHIVO: la clave no es la correcta o el archivo esta danado." >&2
            exit 1
        fi
        TAR="$TEMPORAL/respaldo.tar.gz"
        ;;
    *.tar.gz) TAR="$ARCHIVO" ;;
    *.sql) cp "$ARCHIVO" "$TEMPORAL/base.sql" ;;
    *)
        echo "No reconozco el archivo $ARCHIVO: debe ser .tar.gz.gpg, .tar.gz o .sql" >&2
        exit 1
        ;;
esac
if [ -n "$TAR" ]; then
    mkdir "$TEMPORAL/abierto"
    tar -xzf "$TAR" -C "$TEMPORAL/abierto" --no-same-owner
    mv "$TEMPORAL/abierto/base.sql" "$TEMPORAL/base.sql" 2> /dev/null || {
        echo "$ARCHIVO no trae base.sql: no es un respaldo de respaldo.sh." >&2
        exit 1
    }
fi
if ! tail -n 1 "$TEMPORAL/base.sql" | grep -q "Dump completed"; then
    echo "La base del respaldo esta cortada (no termina en 'Dump completed'): no se restaura nada." >&2
    exit 1
fi
TRAE_FOTOS=no
[ -d "$TEMPORAL/abierto/productos" ] && TRAE_FOTOS=si

if [ "$CONFIRMADO" != "--si" ]; then
    read -r -p "Esto reemplaza TODOS los datos de minimarket_mass$([ "$TRAE_FOTOS" = si ] && echo ' y las fotos de producto') con $ARCHIVO. Escribe SI para seguir: " RESPUESTA
    if [ "$RESPUESTA" != "SI" ]; then
        echo "Cancelado: no se cambio nada."
        exit 0
    fi
fi

# 2. La base: el respaldo trae DROP TABLE + CREATE TABLE de cada tabla, asi que la deja igual que el dia del respaldo
docker exec -i "$CONTENEDOR" sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql -uroot' < "$TEMPORAL/base.sql"
echo "Base restaurada desde $ARCHIVO"

# 3. Las fotos: se cambian las de ahora por las del respaldo (se deja el .gitkeep)
if [ "$TRAE_FOTOS" = si ]; then
    mkdir -p "$FOTOS"
    find "$FOTOS" -mindepth 1 ! -name .gitkeep -delete
    cp -R "$TEMPORAL/abierto/productos/." "$FOTOS/"
    chmod -R u+rwX,go+rX "$FOTOS"  # el servidor web debe poder leerlas
    # Con sudo quedarian a nombre de root: se dejan del mismo dueno que la carpeta (el usuario del servidor web)
    if [ "$(id -u)" = 0 ]; then
        chown -R --reference="$FOTOS" "$FOTOS" || true
    fi
    echo "Fotos restauradas: $(find "$FOTOS" -type f ! -name .gitkeep | wc -l)"
else
    echo "Este respaldo no trae fotos: se dejan las de $FOTOS como estan."
fi
