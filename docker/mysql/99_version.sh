#!/bin/sh
# Ultimo paso al crear la base: anota con que version de los 11 scripts se creo.
# actualizar.sh / actualizar.bat comparan esta huella con la de los scripts actuales
# y, si cambiaron despues de un git pull, avisan que hay que recrear la base.
cat /docker-entrypoint-initdb.d/*.sql | sha256sum | cut -d' ' -f1 > /var/lib/mysql/.mass_bd_version
