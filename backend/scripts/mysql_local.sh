# Lo usan respaldo.sh y restaurar.sh (se carga con ".", no se corre solo). Necesita leer_env().
#
# Elige donde esta MySQL y, sin Docker, prepara el mysqldump/mysql de esta PC.
#   RESPALDO_MODO=docker   el contenedor minimarket_mass_db (PCs del equipo y servidor con Docker)
#   RESPALDO_MODO=local    MySQL instalado en la PC o el servidor (seccion 6.3 de docs/despliegue.md)
#   vacio                  docker si el contenedor existe; si no, local
#
# Usuario de MySQL en modo local. Necesita poder leer toda la base (y, al restaurar, borrarla y crearla):
#   RESPALDO_DB_USER / RESPALDO_DB_PASSWORD   por ejemplo root. Si no se ponen, se usan DB_USER y DB_PASSWORD.
#   RESPALDO_DB_HOST / RESPALDO_DB_PORT       si no se ponen, DB_HOST y DB_PORT. Con "localhost" entra por el
#                                             socket: sirve para el root de Ubuntu sin clave (con sudo).
# La clave va en un archivo temporal que solo lee este usuario, nunca en la linea de comandos (ps la mostraria).

modo_mysql() {
    local modo
    modo="$(leer_env RESPALDO_MODO)"
    case "$modo" in
        docker|local) printf '%s' "$modo" ;;
        "")
            if command -v docker > /dev/null 2>&1 && docker inspect "$CONTENEDOR" > /dev/null 2>&1; then
                printf docker
            else
                printf local
            fi
            ;;
        *)
            echo "RESPALDO_MODO debe ser docker, local o quedar vacio (es: $modo)." >&2
            return 1
            ;;
    esac
}

# Deja $1/mysql.cnf y las variables MYSQLDUMP, MYSQL_CLI y USUARIO_LOCAL
preparar_mysql_local() {
    local carpeta="$1" host port clave
    MYSQLDUMP="$(command -v mysqldump || command -v mariadb-dump || true)"
    MYSQL_CLI="$(command -v mysql || command -v mariadb || true)"
    if [ -z "$MYSQLDUMP" ] || [ -z "$MYSQL_CLI" ]; then
        echo "No hay contenedor de Docker ni mysqldump en esta PC. Instala el cliente: sudo apt install mysql-client" >&2
        exit 1
    fi
    USUARIO_LOCAL="$(leer_env RESPALDO_DB_USER)"
    if [ -n "$USUARIO_LOCAL" ]; then
        clave="$(leer_env RESPALDO_DB_PASSWORD)"
    else
        USUARIO_LOCAL="$(leer_env DB_USER)"
        clave="$(leer_env DB_PASSWORD)"
    fi
    USUARIO_LOCAL="${USUARIO_LOCAL:-root}"
    host="$(leer_env RESPALDO_DB_HOST)"
    host="${host:-$(leer_env DB_HOST)}"
    port="$(leer_env RESPALDO_DB_PORT)"
    port="${port:-$(leer_env DB_PORT)}"
    # En un archivo de opciones de MySQL, \ y " van escapados dentro de las comillas
    esc() { local v="${1//\\/\\\\}"; printf '"%s"' "${v//\"/\\\"}"; }
    {
        echo "[client]"
        echo "user=$(esc "$USUARIO_LOCAL")"
        [ -n "$clave" ] && echo "password=$(esc "$clave")"
        [ -n "$host" ] && echo "host=$host"
        [ -n "$host" ] && [ "$host" != localhost ] && [ -n "$port" ] && echo "port=$port"
        echo "default-character-set=utf8mb4"
    } > "$carpeta/mysql.cnf"
    chmod 600 "$carpeta/mysql.cnf"
}
