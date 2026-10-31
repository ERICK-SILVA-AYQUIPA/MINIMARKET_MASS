#!/usr/bin/env bash
# Deja Minimarket Mass igual que en GitHub en esta PC (Linux o Mac), con Docker:
#   bash actualizar.sh
# Sirve la primera vez y despues de cada cambio del equipo. Hace, en orden:
#   1) crea .env desde .env.example si no existe
#   2) baja lo ultimo de main (git pull), solo si estas en la rama main
#   3) si cambiaron los scripts de backend/database/, recrea la base (pregunta antes: borra los datos de prueba)
#   4) levanta MySQL y la pagina web + API (docker compose) y espera a que esten listas
# En Windows es actualizar.bat (doble clic). Guia: docs/equipo/docker.md
set -uo pipefail
cd "$(dirname "$0")"
export COMPOSE_PROFILES=web

echo "== Minimarket Mass: actualizar con Docker =="

if ! docker info >/dev/null 2>&1; then
    echo "Docker no esta corriendo. Abre Docker Desktop, espera a que diga 'running' y vuelve a correr esto." >&2
    exit 1
fi

if [ ! -f .env ]; then
    cp .env.example .env
    echo "[OK] Se creo .env desde .env.example (claves de ejemplo: bien para tu PC, nunca para un servidor)."
fi

if ! docker compose config -q >/dev/null 2>&1; then
    echo "[ERROR] Tu .env no sirve para Docker (por ejemplo, es el de Laragon y no tiene clave)." >&2
    echo "        Renombralo a .env.viejo y vuelve a correr esto: se crea uno nuevo desde .env.example." >&2
    exit 1
fi

RAMA=$(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo "")
if [ "$RAMA" = "main" ]; then
    echo "[..] Bajando lo ultimo de GitHub (main)..."
    if ! git pull --ff-only origin main; then
        echo "[AVISO] No se pudo hacer git pull (cambios tuyos sin guardar o sin internet). Se sigue con lo que tienes." >&2
    fi
else
    echo "[AVISO] Estas en la rama '$RAMA', no en main: no se baja nada. Para lo ultimo del equipo: git checkout main"
fi

echo "[..] Revisando si la base esta al dia con backend/database/..."
ESTADO=$(docker compose run --rm --no-deps -T --entrypoint sh mysql /usr/local/bin/revisar_version.sh 2>/dev/null | tr -d '\r' | tail -n 1)
if [ "$ESTADO" = "DISTINTO" ]; then
    echo
    echo "Los scripts de la base cambiaron desde que se creo tu base (o tu base es antigua)."
    echo "Hay que recrearla: se BORRAN los datos de prueba de esta PC y se cargan otra vez los de GitHub."
    read -r -p "Recrear la base ahora? [S/n] " RESP
    if [ -z "$RESP" ] || [[ "$RESP" =~ ^[SsYy] ]]; then
        docker compose down -v
    else
        echo "[AVISO] La base se queda como estaba; algunas pantallas o la app pueden fallar."
    fi
fi

echo "[..] Levantando MySQL y la pagina web (la primera vez tarda unos minutos)..."
if ! docker compose up -d --build --wait; then
    echo "[ERROR] Algo no levanto. Mira el detalle con: docker compose logs" >&2
    exit 1
fi

IP=$( (hostname -I 2>/dev/null || ipconfig getifaddr en0 2>/dev/null) | awk '{print $1}')
PUERTO=$(grep -E '^WEB_PORT=' .env | cut -d= -f2 | tr -d '\r')
PUERTO=${PUERTO:-8000}
echo
echo "== Listo =="
echo "  Pagina web:            http://localhost:$PUERTO   (DNI 70000001, clave Mass2026!)"
echo "  App Android (emulador): http://10.0.2.2:$PUERTO/"
[ -n "$IP" ] && echo "  App Android (celular en el mismo Wi-Fi): http://$IP:$PUERTO/"
echo "  Apagar todo: docker compose stop"
