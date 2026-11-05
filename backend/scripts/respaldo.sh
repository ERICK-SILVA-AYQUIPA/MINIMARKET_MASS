#!/usr/bin/env bash
# Respaldo completo de Minimarket Mass: la base minimarket_mass (tablas, datos, vista y procedimiento)
# y las fotos de producto (frontend/public/img/productos/), juntas en un solo archivo:
#   respaldos/minimarket_mass_AAAA-MM-DD_HHMM.tar.gz        (sin cifrar)
#   respaldos/minimarket_mass_AAAA-MM-DD_HHMM.tar.gz.gpg    (cifrado, si .env tiene RESPALDO_CLAVE)
#
#   bash backend/scripts/respaldo.sh
#
# Opcional, en .env (ver docs/despliegue.md, seccion 10):
#   RESPALDO_CLAVE=...            cifra el respaldo con gpg (AES256). Sin esa clave NO se puede restaurar:
#                                 guardala tambien fuera del servidor (gestor de claves o papel en sobre cerrado).
#   RESPALDO_REMOTO=drive:mass    copia el respaldo fuera del servidor con rclone (Google Drive, otro equipo por SFTP...).
#   RESPALDO_DIAS_REMOTO=90       dias que se guardan en el remoto (por defecto 90).
#   RESPALDO_GUARDAR=30           respaldos que se guardan en este disco (por defecto 30).
#
# Donde esta MySQL (RESPALDO_MODO): "docker" usa el contenedor minimarket_mass_db y la clave de root no se escribe
# aqui (la lee MySQL dentro del contenedor). "local" usa el mysqldump de esta PC: MySQL sin Docker (seccion 6.3)
# o Laragon. Vacio = docker si el contenedor existe; si no, local. Ver backend/scripts/mysql_local.sh.
# Los respaldos llevan DNIs y hashes de claves: la carpeta respaldos/ esta en .gitignore y NO se sube a GitHub.
set -euo pipefail
cd "$(dirname "$0")/../.."
umask 077  # los respaldos solo los puede leer el usuario que los crea

CONTENEDOR=minimarket_mass_db
FOTOS_PADRE=frontend/public/img

# Lee una variable del entorno o, si no esta, del .env (sin ejecutar el .env)
leer_env() {
    local valor="${!1:-}"
    if [ -z "$valor" ] && [ -f .env ]; then
        valor="$(grep -E "^$1=" .env | tail -n 1 | cut -d= -f2- | tr -d '\r' | sed -e 's/^"\(.*\)"$/\1/' -e "s/^'\(.*\)'$/\1/")" || valor=""
    fi
    printf '%s' "$valor"
}
CLAVE="$(leer_env RESPALDO_CLAVE)"
REMOTO="$(leer_env RESPALDO_REMOTO)"
DIAS_REMOTO="$(leer_env RESPALDO_DIAS_REMOTO)"
DIAS_REMOTO="${DIAS_REMOTO:-90}"
# Cuantos respaldos se guardan en este disco (30 = un mes si es diario)
GUARDAR="$(leer_env RESPALDO_GUARDAR)"
GUARDAR="${GUARDAR:-30}"
if ! [[ "$GUARDAR" =~ ^[1-9][0-9]{0,3}$ ]]; then
    echo "RESPALDO_GUARDAR debe ser un numero de 1 a 9999 (es: $GUARDAR)." >&2
    exit 1
fi

# Si se pidio cifrar o copiar afuera, se revisa antes de empezar que esten las herramientas
if [ -n "$CLAVE" ] && ! command -v gpg > /dev/null; then
    echo "RESPALDO_CLAVE esta puesta pero falta gpg: instalalo con  sudo apt install gnupg" >&2
    exit 1
fi
if [ -n "$REMOTO" ] && ! command -v rclone > /dev/null; then
    echo "RESPALDO_REMOTO esta puesto pero falta rclone: instalalo con  sudo apt install rclone  y configuralo con  rclone config" >&2
    exit 1
fi

mkdir -p respaldos
NOMBRE="minimarket_mass_$(date +%Y-%m-%d_%H%M)"
TEMPORAL="$(mktemp -d)"
trap 'rm -rf "$TEMPORAL"' EXIT

# 1. La base
. backend/scripts/mysql_local.sh
MODO="$(modo_mysql)"
if [ "$MODO" = docker ]; then
    docker exec "$CONTENEDOR" sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysqldump -uroot --single-transaction --routines --triggers --databases minimarket_mass' > "$TEMPORAL/base.sql" || true
    PISTA="Revisa que el contenedor este corriendo (docker compose ps)."
else
    preparar_mysql_local "$TEMPORAL"
    "$MYSQLDUMP" --defaults-extra-file="$TEMPORAL/mysql.cnf" --single-transaction --routines --triggers \
        --databases minimarket_mass > "$TEMPORAL/base.sql" || true
    PISTA="Revisa que MySQL este iniciado y el usuario $USUARIO_LOCAL (RESPALDO_DB_USER en .env)."
fi

# Un respaldo vacio o cortado no sirve: se revisa que termine bien
if ! tail -n 1 "$TEMPORAL/base.sql" | grep -q "Dump completed"; then
    echo "El respaldo de la base no se completo. $PISTA" >&2
    exit 1
fi

# 2. Base + fotos en un .tar.gz
mkdir -p "$FOTOS_PADRE/productos"
FOTOS="$(find "$FOTOS_PADRE/productos" -type f ! -name .gitkeep | wc -l)"
ARCHIVO="respaldos/$NOMBRE.tar.gz"
tar -czf "$TEMPORAL/$NOMBRE.tar.gz" -C "$TEMPORAL" base.sql -C "$PWD/$FOTOS_PADRE" productos
CONTENIDO="$(tar -tzf "$TEMPORAL/$NOMBRE.tar.gz")" || CONTENIDO=""
if ! grep -qx "base.sql" <<< "$CONTENIDO"; then
    echo "El archivo $ARCHIVO quedo danado." >&2
    exit 1
fi

# 3. Cifrado (opcional)
if [ -n "$CLAVE" ]; then
    ARCHIVO="$ARCHIVO.gpg"
    gpg --batch --yes --quiet --pinentry-mode loopback --passphrase-fd 3 \
        --symmetric --cipher-algo AES256 -o "$TEMPORAL/$NOMBRE.tar.gz.gpg" "$TEMPORAL/$NOMBRE.tar.gz" 3<<< "$CLAVE"
    mv "$TEMPORAL/$NOMBRE.tar.gz.gpg" "$ARCHIVO"
else
    mv "$TEMPORAL/$NOMBRE.tar.gz" "$ARCHIVO"
fi
echo "Respaldo listo: $ARCHIVO ($(du -h "$ARCHIVO" | cut -f1), base + $FOTOS fotos$([ -n "$CLAVE" ] && echo ', cifrado'))"

# 4. Se guardan los ultimos RESPALDO_GUARDAR respaldos (30 si no se pone); los mas antiguos se borran
find respaldos -maxdepth 1 -type f -name 'minimarket_mass_*' \( -name '*.sql' -o -name '*.tar.gz' -o -name '*.tar.gz.gpg' \) \
    -printf '%T@ %p\n' | sort -rn | cut -d' ' -f2- | tail -n +$((GUARDAR + 1)) | while read -r VIEJO; do
    rm -f -- "$VIEJO"
    echo "Borrado por antiguo: $VIEJO"
done

# 5. Copia fuera del servidor (opcional). Si falla, el respaldo local ya quedo hecho, pero se avisa con error.
if [ -z "$REMOTO" ]; then
    echo "AVISO: el respaldo solo esta en este disco. Configura RESPALDO_REMOTO en .env (docs/despliegue.md, seccion 10)."
    exit 0
fi
[ -z "$CLAVE" ] && echo "AVISO: se copia afuera SIN cifrar. Pon RESPALDO_CLAVE en .env para cifrarlo."
if ! rclone copy "$ARCHIVO" "$REMOTO"; then
    echo "No se pudo copiar $ARCHIVO a $REMOTO (revisa internet y  rclone config). El respaldo local si quedo." >&2
    exit 1
fi
echo "Copiado fuera del servidor: $REMOTO/$(basename "$ARCHIVO")"
rclone delete "$REMOTO" --min-age "${DIAS_REMOTO}d" --include 'minimarket_mass_*' \
    || echo "AVISO: no se pudieron borrar los respaldos de mas de $DIAS_REMOTO dias en $REMOTO." >&2
