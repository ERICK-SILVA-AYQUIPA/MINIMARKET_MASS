#!/bin/sh
# Al crear la base por primera vez, despues de los 12 scripts: aplica las migraciones de
# backend/database/migraciones/ (montada en /mass_bd) y anota cada una en migracion_aplicada,
# con la misma huella que calcula backend/config/Migraciones.php (SHA-256 sin los \r).
# Con una base que ya existe no corre: ahi las aplica backend/scripts/migrar.php (actualizar.sh / actualizar.bat).
docker_process_sql --database="$MYSQL_DATABASE" < /mass_bd/migracion_aplicada.sql
for archivo in /mass_bd/migraciones/[0-9][0-9][0-9]_*.sql; do
    [ -f "$archivo" ] || continue
    nombre=$(basename "$archivo")
    echo "$0: migracion $nombre"
    docker_process_sql --database="$MYSQL_DATABASE" < "$archivo"
    huella=$(tr -d '\r' < "$archivo" | sha256sum | cut -d' ' -f1)
    echo "INSERT INTO migracion_aplicada (nombre, fecha, checksum) VALUES ('$nombre', NOW(), '$huella');" \
        | docker_process_sql --database="$MYSQL_DATABASE"
done
