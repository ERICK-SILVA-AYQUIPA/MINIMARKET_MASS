# PHP de las PCs del equipo: el mismo en todas e igual al del servidor de produccion
# (Ubuntu 24.04 con PHP 8.3 y las extensiones de docs/despliegue.md).
# Corre el servidor de pruebas de PHP (php -S) dentro de Docker; en produccion atiende nginx o Apache.
FROM ubuntu:24.04

# pdo_mysql: conexion a MySQL. gd: fotos de producto. mbstring: textos con tildes. curl: las pruebas de backend/tests/
RUN apt-get update \
 && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
      php8.3-cli php8.3-mysql php8.3-gd php8.3-mbstring php8.3-curl tzdata \
 && rm -rf /var/lib/apt/lists/*

# Los mismos limites que produccion y la hora de Lima
RUN { \
      echo 'upload_max_filesize = 2M'; \
      echo 'post_max_size = 8M'; \
      echo 'date.timezone = America/Lima'; \
      echo 'display_errors = On'; \
    } > /etc/php/8.3/cli/conf.d/99-mass.ini

WORKDIR /app
# Varios procesos a la vez: la pagina web y la app Android no se esperan entre si
ENV PHP_CLI_SERVER_WORKERS=4
EXPOSE 8000
CMD ["php", "-S", "0.0.0.0:8000", "-t", "frontend/public"]
