#!/usr/bin/env bash
# Prueba de restauracion: demuestra que un respaldo de verdad se puede devolver.
#   1. anota una huella (CHECKSUM TABLE) de cada tabla y deja una foto de prueba
#   2. saca un respaldo cifrado con backend/scripts/respaldo.sh
#   3. BORRA la base y las fotos (docker compose down -v) y levanta una base nueva
#   4. restaura con backend/scripts/restaurar.sh y compara: mismas tablas, mismos datos, misma foto
#   5. corre backend/scripts/verificar_instalacion.php
# Borra TODOS los datos del contenedor: solo corre en la CI o pidiendolo con --borrar-todo.
#   bash backend/tests/restauracion.sh --borrar-todo
set -euo pipefail
cd "$(dirname "$0")/../.."

if [ "${CI:-}" != "true" ] && [ "${1:-}" != "--borrar-todo" ]; then
    echo "Esta prueba borra la base del contenedor. Si es lo que quieres: bash backend/tests/restauracion.sh --borrar-todo" >&2
    exit 1
fi

CONTENEDOR=minimarket_mass_db
FOTOS=frontend/public/img/productos
FOTO_PRUEBA="$FOTOS/prueba_restauracion.jpg"
export RESPALDO_CLAVE="prueba-restauracion-$$-$RANDOM$RANDOM"
export RESPALDO_REMOTO=""  # la prueba no copia nada afuera
fallas=0

# comprobar "texto" comando...: OK si el comando sale bien, FALLA si no (sin cortar la prueba)
comprobar() {
    local texto="$1"
    shift
    if "$@"; then echo "  OK    $texto"; else echo "  FALLA $texto"; fallas=$((fallas + 1)); fi
}
no() { ! "$@"; }
mysql_root() {
    docker exec -i "$CONTENEDOR" sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql -uroot -N -B minimarket_mass' <<< "$1"
}
# Huella de la base: cada tabla con su CHECKSUM, mas la vista y el procedimiento
huella() {
    local tablas
    tablas="$(mysql_root "SELECT GROUP_CONCAT(table_name ORDER BY table_name) FROM information_schema.tables WHERE table_schema = 'minimarket_mass' AND table_type = 'BASE TABLE'")"
    mysql_root "CHECKSUM TABLE ${tablas//,/, }"
    mysql_root "SELECT table_name FROM information_schema.views WHERE table_schema = 'minimarket_mass' ORDER BY 1"
    mysql_root "SELECT routine_name FROM information_schema.routines WHERE routine_schema = 'minimarket_mass' ORDER BY 1"
}
esperar_base() {
    docker compose up -d --wait > /dev/null 2>&1
    # El healthcheck responde antes de que terminen los scripts de inicio: se espera a la ultima tabla
    for _ in $(seq 1 60); do
        if [ "$(mysql_root "SELECT COUNT(*) FROM information_schema.routines WHERE routine_schema = 'minimarket_mass' AND routine_name = 'sp_boleta'" 2> /dev/null)" = "1" ]; then
            sleep 3
            return 0
        fi
        sleep 2
    done
    echo "La base nueva no termino de crearse." >&2
    return 1
}

echo "Antes del respaldo"
mkdir -p "$FOTOS"
head -c 4096 /dev/urandom > "$FOTO_PRUEBA"
# Un dato que la base nueva no tiene: asi la prueba no depende de lo que hayan hecho las pruebas anteriores
mysql_root "CREATE TABLE prueba_restauracion (dato VARCHAR(40) NOT NULL); INSERT INTO prueba_restauracion VALUES ('$RESPALDO_CLAVE')"
FOTO_ANTES="$(sha256sum < "$FOTO_PRUEBA")"
HUELLA_ANTES="$(huella)"
echo "  $(grep -c . <<< "$HUELLA_ANTES") tablas, vistas y procedimientos anotados"

echo "Respaldo"
SALIDA="$(bash backend/scripts/respaldo.sh)"
echo "$SALIDA" | sed 's/^/  /'
ARCHIVO="$(sed -n 's/^Respaldo listo: \([^ ]*\) .*/\1/p' <<< "$SALIDA")"
es_cifrado() { [[ "$1" == *.tar.gz.gpg ]] && [ -s "$1" ]; }
comprobar "El respaldo es un .tar.gz cifrado" es_cifrado "$ARCHIVO"
comprobar "Sin la clave no se puede abrir" no tar -tzf "$ARCHIVO" 2> /dev/null

echo "Se pierde todo (docker compose down -v y fotos borradas)"
docker compose down -v > /dev/null 2>&1
rm -f "$FOTO_PRUEBA"
esperar_base
comprobar "La base nueva no es la del respaldo (la prueba tiene sentido)" [ "$(huella)" != "$HUELLA_ANTES" ]

echo "Restauracion"
con_otra_clave() { RESPALDO_CLAVE=clave-equivocada bash backend/scripts/restaurar.sh "$ARCHIVO" --si > /dev/null 2>&1; }
comprobar "Con otra clave no restaura nada" no con_otra_clave
bash backend/scripts/restaurar.sh "$ARCHIVO" --si | sed 's/^/  /'
HUELLA_DESPUES="$(huella)"
comprobar "Todas las tablas, la vista y el procedimiento quedan iguales" [ "$HUELLA_DESPUES" = "$HUELLA_ANTES" ]
if [ "$HUELLA_DESPUES" != "$HUELLA_ANTES" ]; then
    diff <(echo "$HUELLA_ANTES") <(echo "$HUELLA_DESPUES") | head -n 20 || true
fi
comprobar "La foto de producto vuelve igual" [ "$(sha256sum < "$FOTO_PRUEBA" 2> /dev/null)" = "$FOTO_ANTES" ]
rm -f "$FOTO_PRUEBA"
mysql_root "DROP TABLE prueba_restauracion"

echo "verificar_instalacion.php"
VERIFICAR="$(php backend/scripts/verificar_instalacion.php || true)"
echo "$VERIFICAR" | sed 's/^/  /'
comprobar "verificar_instalacion: todas las tablas" grep -qE "OK +Las [0-9]+ tablas" <<< "$VERIFICAR"
comprobar "verificar_instalacion: sp_boleta" grep -q "OK    Procedimiento sp_boleta" <<< "$VERIFICAR"
comprobar "verificar_instalacion: ve el respaldo nuevo" grep -q "OK    Hay un respaldo de las últimas 48 horas" <<< "$VERIFICAR"
# En la CI las claves son las de .env.example y los empleados tienen la clave de prueba: esas fallas se esperan
OTRAS="$(grep "FALLA" <<< "$VERIFICAR" | grep -v "es la de ejemplo o es corta" | grep -v "clave de prueba" || true)"
comprobar "verificar_instalacion: ninguna otra FALLA" [ -z "$OTRAS" ]

echo
echo "$fallas fallas"
[ "$fallas" -eq 0 ]
