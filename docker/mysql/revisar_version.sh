#!/bin/sh
# Compara la base que ya existe con los scripts de backend/database/ actuales. Imprime una palabra:
#   NUEVA     la base todavia no existe (se crea sola al levantar)
#   IGUAL     la base se creo con estos mismos scripts
#   DISTINTO  los scripts cambiaron (git pull) o la base es de antes de esta revision: hay que recrearla
# La corre actualizar.sh / actualizar.bat en un contenedor aparte, sin tocar la base.
if [ ! -d /var/lib/mysql/mysql ]; then
    echo NUEVA
    exit 0
fi
ACTUAL=$(cat /docker-entrypoint-initdb.d/*.sql | sha256sum | cut -d' ' -f1)
if [ "$(cat /var/lib/mysql/.mass_bd_version 2>/dev/null)" = "$ACTUAL" ]; then
    echo IGUAL
else
    echo DISTINTO
fi
