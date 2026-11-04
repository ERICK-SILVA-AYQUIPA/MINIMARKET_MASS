#!/usr/bin/env bash
# Corre las pruebas E2E dentro de la imagen oficial de Playwright (Ubuntu 24.04 + Chromium de la version fija).
# Asi las capturas salen con los mismos pixeles en cualquier PC y en la CI (que usa este mismo script).
#
#   bash tests/e2e/correr_en_docker.sh                     # compara contra las capturas base
#   bash tests/e2e/correr_en_docker.sh --update-snapshots  # genera o actualiza las capturas base
#   bash tests/e2e/correr_en_docker.sh 03-venta            # solo un archivo
#
# Antes: la base de Docker recien creada (docker compose down -v && docker compose up -d --wait mysql)
# y el archivo .env (copia de .env.example). Necesita Docker; en Windows, desde Git Bash o WSL.
set -euo pipefail
cd "$(dirname "$0")/../.."

IMAGEN="mcr.microsoft.com/playwright:v1.63.0-noble"   # misma version que @playwright/test en package.json

# Si esta PC sale a internet por un proxy, el contenedor tambien lo usa
extras=()
for v in HTTPS_PROXY https_proxy HTTP_PROXY http_proxy NO_PROXY no_proxy; do
    if [ -n "${!v:-}" ]; then extras+=(-e "$v"); fi
done
if [ -n "${SSL_CERT_FILE:-}" ] && [ -f "$SSL_CERT_FILE" ]; then
    extras+=(-v "$SSL_CERT_FILE:/usr/local/share/ca-certificates/proxy.crt:ro" -e NODE_EXTRA_CA_CERTS=/usr/local/share/ca-certificates/proxy.crt)
fi

# --network host: el contenedor llega a MySQL en 127.0.0.1:3307 como la PC. -u: los archivos nuevos quedan a nombre de quien corre.
docker run --rm --network host --ipc=host "${extras[@]}" \
    -e CI="${CI:-}" -e HOME=/tmp \
    -v "$PWD":/work -w /work "$IMAGEN" bash -c '
        set -e
        if [ -f /usr/local/share/ca-certificates/proxy.crt ]; then update-ca-certificates > /dev/null; fi
        # PHP 8.3 de Ubuntu (el mismo de la CI). Los paquetes se bajan por https (algunos proxies no dejan pasar http)
        if [ -n "${https_proxy:-${HTTPS_PROXY:-}}" ]; then echo "Acquire::https::Proxy \"${https_proxy:-$HTTPS_PROXY}\";" > /etc/apt/apt.conf.d/90proxy; fi
        sed -i -E "s#http://[a-z.]*archive.ubuntu.com#https://archive.ubuntu.com#g; s#http://security.ubuntu.com#https://security.ubuntu.com#g" /etc/apt/sources.list.d/ubuntu.sources 2>/dev/null || true
        apt-get update -qq > /dev/null
        DEBIAN_FRONTEND=noninteractive apt-get install -y -qq --no-install-recommends php8.3-cli php8.3-mysql php8.3-mbstring php8.3-gd > /dev/null
        npm ci --no-audit --no-fund --loglevel=error
        npx playwright test "$@"
        chown -R '"$(id -u):$(id -g)"' tests/e2e test-results playwright-report 2>/dev/null || true
    ' _ "$@"
