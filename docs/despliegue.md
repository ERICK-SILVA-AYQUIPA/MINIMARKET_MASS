# Guía de instalación y despliegue

Cómo poner a funcionar Minimarket Mass en una PC del equipo o en un servidor de producción, qué archivos del repositorio se usan en cada caso, cómo conectar la app Android, cómo sacar respaldos y qué hacer si algo falla. Para el uso diario ver `docs/manual_usuario.md`.

Todo lo de esta guía se probó el 30/10/2026 en Ubuntu 24.04 (nginx 1.24, Apache 2.4.58, PHP 8.3 con PHP-FPM, MySQL 8.4.11 en Docker y MySQL 8.0 de Ubuntu sin Docker), con HTTPS y las cinco pruebas de `backend/tests/` en verde.

## 1. Qué va a producción y qué no

En el servidor se clona el repositorio completo (así `git pull` sirve para actualizar), pero el navegador **solo** ve la carpeta `frontend/public/`. El resto se usa desde PHP, desde la consola o no se usa.

**Lo que usa el sistema en producción**

| Ruta | Para qué |
|---|---|
| `frontend/public/` | Raíz del sitio. `index.php` (página web), `api/index.php` (API de la app Android), `css/`, `js/`, `fuentes/`, `iconos.svg` e `img/productos/` (fotos que sube el administrador) |
| `frontend/views/` | Pantallas que arma `index.php`. No se abren directo desde el navegador |
| `backend/config/`, `backend/controllers/`, `backend/models/` | Conexión, sesión y toda la lógica. Los carga `index.php` desde fuera de la raíz web |
| `backend/database/` (11 scripts) | Crean la base la primera vez (sección 4) y traen los cambios de cada versión nueva (sección 8) |
| `backend/scripts/` | Respaldo, restauración, respaldo diario y `verificar_instalacion.php`. Se corren desde la consola del servidor |
| `docker-compose.yml` | MySQL 8.4.11 con la hora de Lima, escuchando solo en `127.0.0.1`. Su servicio `web` (PHP de pruebas) **no** se usa en producción: `.env` lleva `COMPOSE_PROFILES=` vacío |
| `deploy/nginx-mass.conf` o `deploy/apache-mass.conf` | Se copia a la configuración del servidor web (solo uno de los dos) |
| `.env` | **Se crea en el servidor** a partir de `.env.example`. Nunca se sube a GitHub |

**Lo que está en el repositorio pero no se usa en producción**

| Ruta | Por qué |
|---|---|
| `.env.laragon` | Entra a MySQL como `root` sin clave. Solo para las PCs con Laragon; en un servidor `verificar_instalacion.php` marca FALLA |
| `iniciar_servidor_laragon.bat` | Solo Windows: abre `php -S localhost:8000`, que es para pruebas |
| `actualizar.bat`, `actualizar.sh`, `docker/web/` | Levantan la página con `php -S` dentro de Docker en las PCs del equipo (`docs/equipo/docker.md`). En el servidor no se corren: harían `git pull` y podrían recrear la base |
| `backend/database/instalar_laragon.sql` | Empieza con `DROP DATABASE IF EXISTS minimarket_mass`. **Nunca correrlo en el servidor**: borra todas las ventas reales |
| `backend/database/consultas.sql` | Consultas de estudio del curso; ningún paso de la instalación las ejecuta |
| `backend/database/catalogo_inicial.csv` | Ejemplo para la pantalla **Importar CSV**; no se carga solo |
| `backend/tests/` | Las corre la CI. `web.php`, `integracion.php` y `api.php` registran ventas e ingresos: **nunca correrlas contra la base de producción** |
| `frontend/public/poo_*.php` | Páginas de práctica de POO. `poo_repository.php` y `poo_ventas.php` leen la base **sin pedir login**. Las configuraciones de `deploy/` las bloquean (nginx responde 404 y Apache 403) |
| `android-app/` | Código de la app. Se compila aparte y se instala el `.apk` en los celulares (sección 7) |
| `docs/`, `final.docx`, `README.md`, `CONTRIBUTING.md`, `.github/`, `.editorconfig`, `.gitattributes` | Documentación, informe, reglas del equipo y CI |

**Lo que se crea en el servidor y nunca se sube a GitHub** (ya está en `.gitignore`): `.env`, `respaldos/` y las fotos de `frontend/public/img/productos/`.

## 2. Requisitos

| Programa | Versión | Para qué |
|---|---|---|
| PHP | 8.3 o más, con `pdo_mysql`, `mbstring` y `gd` | La aplicación (`gd` solo para subir fotos de producto) |
| MySQL | 8.4.11 en Docker (recomendado) u 8.0.16 o más sin Docker | La base de datos |
| Docker con `docker compose` | Cualquiera reciente | Levanta MySQL sin instalarlo |
| Git | Cualquiera | Bajar y actualizar el proyecto |
| Servidor web (solo producción) | nginx o Apache 2.4, con PHP-FPM | Sirve `frontend/public/` con HTTPS |

Revisar PHP con `php -v` y `php -m` (deben salir `pdo_mysql`, `mbstring` y `gd`).

## 3. Configuración (`.env`)

```bash
cp .env.example .env        # Windows: copy .env.example .env
```

Abrir `.env` y cambiar **las dos claves** (`DB_PASSWORD` y `DB_ROOT_PASSWORD`) por claves de 12 caracteres o más. `backend/config/Database.php` lee el `.env` de la carpeta del proyecto (no el de `frontend/public/`); `docker-compose.yml` también lo lee para crear la base.

| Variable | PC del equipo con Docker | Producción con Docker | Producción con MySQL sin Docker | Laragon |
|---|---|---|---|---|
| `DB_HOST` | `127.0.0.1` | `127.0.0.1` | `localhost` | `127.0.0.1` |
| `DB_PORT` | `3307` | `3307` | `3306` | `3306` |
| `DB_NAME` | `minimarket_mass` | `minimarket_mass` | `minimarket_mass` | `minimarket_mass` |
| `DB_USER` | `mass_app` | `mass_app` | `mass_app` | `root` |
| `DB_PASSWORD` | La que pongan | Clave larga | Clave larga | (vacía) |
| `DB_ROOT_PASSWORD` | La que pongan | Clave larga, distinta | No se usa | No se usa |
| `COMPOSE_PROFILES` | `web` | **vacío** | No se usa | No se usa |
| `WEB_PORT` | `8000` | No se usa | No se usa | No se usa |

- `COMPOSE_PROFILES=web` (lo trae `.env.example`) hace que Docker levante también la página con `php -S` en el puerto 8000, abierto a la red. En producción **va vacío**: ahí atiende nginx o Apache, y Docker abre sus puertos aunque el firewall `ufw` los cierre.
- Con Docker, el usuario `mass_app` lo crea MySQL al levantar el contenedor, con permisos solo sobre la base `minimarket_mass`. `DB_ROOT_PASSWORD` solo la usan Docker y los scripts de respaldo.
- Una variable que ya existe en el sistema (por ejemplo, puesta en PHP-FPM) gana sobre la del `.env`.

## 4. La base de datos

Los scripts de `backend/database/` se ejecutan **una sola vez y en este orden** (`docker-compose.yml` los monta como `01_` a `11_`):

| # | Script | Qué agrega |
|---|---|---|
| 1 | `schema.sql` | La base y sus 26 tablas. **Empieza con `DROP DATABASE IF EXISTS`** |
| 2 | `datos.sql` | 3 tiendas de Arequipa, 9 empleados, productos, stock y ventas de ejemplo |
| 3 | `boleta.sql` | Vista `vw_comprobante` y procedimiento `sp_boleta` |
| 4 | `login.sql` | Columna `clave_hash` con la clave de prueba `Mass2026!` |
| 5 | `seguridad.sql` | Tabla `intento_login` (bloqueo por claves erradas) |
| 6 | `ajustes.sql` | Tabla `ajuste_inventario` |
| 7 | `auditoria.sql` | Tabla `auditoria` |
| 8 | `ingresos.sql` | Tablas `ingreso_mercaderia` y `detalle_ingreso` |
| 9 | `operaciones.sql` | Devoluciones, movimientos de caja y vencimientos |
| 10 | `catalogo.sql` | 82 productos más con su primer ingreso en las 3 tiendas |
| 11 | `api_movil.sql` | Tablas `api_token` y `api_operacion` de la app Android |

Al terminar hay **36 tablas**. Si falta `api_movil.sql`, la página web funciona pero la app Android no puede entrar.

- **Con Docker**: `docker compose up -d` corre los 11 solo cuando el volumen `mysql_datos` está vacío (la primera vez). Para volver a empezar en una PC de pruebas (**borra los datos**): `docker compose down -v` y `docker compose up -d`.
- **Sin Docker**: se corren a mano, en el mismo orden (sección 5.2 en Laragon y sección 6.3 en un servidor).

## 5. PCs del equipo (pruebas, no producción)

`php -S` es el servidor de pruebas de PHP: atiende de a una petición y sin HTTPS. Sirve para el aula, no para atender a las tiendas.

### 5.1 Con Docker (recomendado)

MySQL y PHP corren en Docker; la PC solo necesita Docker Desktop y Git. Paso a paso para el equipo en `docs/equipo/docker.md`.

```bash
./actualizar.bat      # Windows (o doble clic). Linux o Mac: bash actualizar.sh
```

La primera vez crea `.env` desde `.env.example` y la base con los 11 scripts. Las siguientes veces hace `git pull` (si se está en `main`), reconstruye PHP si cambió `docker/web/Dockerfile` y, si cambió algún script de `backend/database/`, ofrece recrear la base (borra los datos de prueba). La huella de los scripts con que se creó cada base la anota `docker/mysql/99_version.sh` y la compara `docker/mysql/revisar_version.sh`.

Abrir `http://localhost:8000` y entrar con un DNI de prueba de `docs/manual_usuario.md` (por ejemplo `70000001`, clave `Mass2026!`). La API de la app queda en `http://localhost:8000/api/v1/` (prueba: `http://localhost:8000/api/v1/salud`); desde un celular en el mismo Wi-Fi, `http://IP-de-la-PC:8000/`. Las pruebas corren dentro del contenedor: `docker compose exec web php backend/tests/unitarios.php`.

Sin el script es lo mismo que `docker compose up -d --build --wait`. Con PHP instalado en la PC también se puede seguir usando `php -S localhost:8000 -t frontend/public` contra el MySQL de Docker (puerto `3307`), pero entonces no se debe tener el servicio `web` prendido, porque usa el mismo puerto.

### 5.2 Con Laragon (Windows)

Paso a paso en `docs/guia_laragon.md`. En resumen: iniciar MySQL en Laragon y hacer doble clic en `iniciar_servidor_laragon.bat`. Ese archivo copia `.env.laragon` a `.env` si no existe, instala `backend/database/instalar_laragon.sql` si la base no tiene la tabla `producto` (es la unión de los 11 scripts) y abre `http://localhost:8000`.

## 6. Producción: servidor en internet con HTTPS

Para que las tiendas y la app Android entren desde cualquier lugar se usa un servidor (por ejemplo un VPS con Ubuntu 24.04) con nginx o Apache, PHP-FPM y HTTPS. Las configuraciones listas están en `deploy/`.

**Reglas que no cambian:**

- **La raíz del sitio es `frontend/public/`**, nunca la carpeta del proyecto. Así nadie puede descargar `.env`, `backend/` ni `respaldos/`.
- **HTTPS obligatorio**: sin él, la clave y la cookie de sesión viajan sin cifrar, y el APK de lanzamiento de la app no se conecta. Con HTTPS la cookie `MASSID` sale con `secure` (`backend/config/Sesion.php`) y la web y la API envían `Strict-Transport-Security`.
- **MySQL no queda abierto a internet**: `docker-compose.yml` publica el puerto solo en `127.0.0.1`. El firewall abre solo 22, 80 y 443.
- **La aplicación no entra como `root`** de MySQL: usa `mass_app`.
- **La hora es la de Lima**: PHP la fija en `frontend/public/index.php` y `frontend/public/api/index.php`; MySQL en `docker-compose.yml` (`TZ: America/Lima`) o, sin Docker, con `default-time-zone` (sección 6.3).
- `php.ini` de PHP-FPM (`/etc/php/8.3/fpm/php.ini`): `display_errors = Off`, `log_errors = On`, `upload_max_filesize = 2M` y `post_max_size = 8M`. Son los valores que trae Ubuntu 24.04; solo hay que revisar que nadie los cambió.

### 6.1 Paso a paso con nginx (Ubuntu 24.04)

Antes de empezar, apuntar el dominio a la IP del servidor (registro A). En los comandos, cambiar `mass.ejemplo.pe` por el dominio real.

```bash
# 1. Programas. Docker se instala según docs.docker.com/engine/install/ubuntu
sudo apt update
sudo apt install -y nginx php8.3-fpm php8.3-mysql php8.3-mbstring php8.3-gd git certbot python3-certbot-nginx
sudo ufw allow OpenSSH && sudo ufw allow 'Nginx Full' && sudo ufw enable

# 2. Proyecto, .env y base
sudo git clone https://github.com/rick-back/minimarket_mass.git /var/www/minimarket_mass
cd /var/www/minimarket_mass
sudo cp .env.example .env && sudo nano .env          # claves largas y distintas, y COMPOSE_PROFILES= vacío
sudo chown root:www-data .env && sudo chmod 640 .env  # PHP lo lee; los demás usuarios no
sudo docker compose up -d --wait                      # crea la base con los 11 scripts
sudo chown www-data frontend/public/img/productos     # PHP guarda aquí las fotos de producto

# 3. Certificado de Let's Encrypt (se renueva solo). Va ANTES de activar el sitio
sudo certbot certonly --nginx -d TU-DOMINIO

# 4. Sitio
sudo cp deploy/nginx-mass.conf /etc/nginx/sites-available/mass
sudo sed -i 's/mass\.ejemplo\.pe/TU-DOMINIO/g' /etc/nginx/sites-available/mass
sudo ln -s /etc/nginx/sites-available/mass /etc/nginx/sites-enabled/
sudo rm /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
```

El certificado se saca primero y con `certonly` porque `deploy/nginx-mass.conf` ya trae las líneas `ssl_certificate` con las rutas de Let's Encrypt (`/etc/letsencrypt/live/TU-DOMINIO/`): si el sitio se activa antes de que exista el certificado, `nginx -t` falla. Si la carpeta del proyecto no es `/var/www/minimarket_mass`, cambiar también esa ruta en la configuración.

### 6.2 Con Apache en vez de nginx

En el paso 1 se instala `apache2 php8.3-fpm php8.3-mysql php8.3-mbstring php8.3-gd git certbot python3-certbot-apache` y se abre `'Apache Full'` en `ufw`. Los pasos 3 y 4 cambian a (también el certificado primero):

```bash
sudo a2enmod ssl headers rewrite proxy_fcgi setenvif
sudo a2enconf php8.3-fpm
sudo certbot certonly --apache -d TU-DOMINIO
sudo cp deploy/apache-mass.conf /etc/apache2/sites-available/mass.conf
sudo sed -i 's/mass\.ejemplo\.pe/TU-DOMINIO/g' /etc/apache2/sites-available/mass.conf
sudo a2dissite 000-default && sudo a2ensite mass
sudo apache2ctl configtest && sudo systemctl reload apache2
```

`deploy/apache-mass.conf` ya pasa la cabecera `Authorization` a PHP (`CGIPassAuth On`), que la app Android necesita para su token.

### 6.3 Variante: MySQL sin Docker en el mismo servidor

Si no se quiere Docker, se usa el MySQL de Ubuntu (`sudo apt install mysql-server`, versión 8.0):

```bash
# Hora de Lima para MySQL (Perú no cambia de hora en el año)
printf '[mysqld]\ndefault-time-zone = "-05:00"\n' | sudo tee /etc/mysql/mysql.conf.d/zz-mass.cnf
sudo systemctl restart mysql

# Los 11 scripts, en orden y una sola vez
cd /var/www/minimarket_mass
for f in schema datos boleta login seguridad ajustes auditoria ingresos operaciones catalogo api_movil; do
  sudo mysql < backend/database/$f.sql || break
done
```

`boleta.sql` imprime tres comprobantes de ejemplo al terminar; es normal. Luego se crea el usuario de la aplicación con solo los permisos que usa:

```sql
CREATE USER 'mass_app'@'localhost' IDENTIFIED BY 'una_clave_larga';
GRANT SELECT, INSERT, UPDATE, DELETE, EXECUTE ON minimarket_mass.* TO 'mass_app'@'localhost';
```

y en `.env` se pone `DB_HOST=localhost` (PHP entra por el socket de MySQL), `DB_PORT=3306`, `DB_USER=mass_app` y esa clave. Con este MySQL, los scripts de respaldo de `backend/scripts/` no sirven (buscan el contenedor `minimarket_mass_db`): ver la última fila de la sección 10.

### 6.4 Revisar que quedó bien

```bash
curl https://TU-DOMINIO/api/v1/salud                 # {"ok":true,"datos":{"servicio":"Minimarket Mass API",...}}
curl -I http://TU-DOMINIO/                            # 301 hacia https://
curl -I https://TU-DOMINIO/poo_ventas.php             # 404 en nginx, 403 en Apache
sudo -u www-data php backend/scripts/verificar_instalacion.php
```

`verificar_instalacion.php` se corre como `www-data` porque el `.env` solo lo pueden leer root y el servidor web; con otro usuario dice "No hay .env". Lo que revisa está en la sección 9.

## 7. La app Android en producción

La app no se instala en el servidor: se compila en una PC con Android Studio y se instala el `.apk` en los celulares. Solo necesita la dirección del servidor; nunca recibe datos de MySQL.

- **Dirección**: la raíz del sitio con `https://` y `/` al final, por ejemplo `https://TU-DOMINIO/`. La app le agrega `api/v1/...` sola. No poner `/api/` ni `/api/v1/` en la dirección.
- **Compilar el APK de lanzamiento** (desde `android-app/`, con la llave de firma de `android-app/README.md`):

  ```bash
  ./gradlew assembleRelease -PmassApiUrl=https://TU-DOMINIO/
  ```

  Sin `-PmassApiUrl` toma `https://mass.ejemplo.pe/` de `android-app/gradle.properties`, que no existe. Si la dirección no empieza con `https://`, la compilación se detiene. El APK queda en `android-app/app/build/outputs/apk/release/app-release.apk`.
- **Solo HTTPS**: el APK de lanzamiento rechaza `http://` (`android-app/app/src/main/res/xml/network_security_config.xml`). El APK de prueba (`assembleDebug`) sí acepta `http://` para usarlo con `php -S 0.0.0.0:8000 -t frontend/public` en el Wi-Fi de la tienda; no se reparte fuera de ella.
- **Cambiar de servidor sin recompilar**: en la pantalla de ingreso, botón **Cambiar** junto a la dirección (prueba la conexión antes de guardar).
- La llave de firma (`*.jks`) y `android-app/keystore.properties` nunca se suben (están en `android-app/.gitignore`). Si se pierde la llave, las actualizaciones ya no se instalan encima.

## 8. Actualizar a una versión nueva

```bash
cd /var/www/minimarket_mass
sudo bash backend/scripts/respaldo.sh            # siempre respaldar antes
sudo git pull origin main
sudo systemctl reload php8.3-fpm
```

Si la versión nueva trae un script nuevo en `backend/database/` (por ejemplo, cuando llegó `api_movil.sql`):

- **Con datos reales**: ejecutar **solo el script nuevo** sobre la base que ya existe. Nunca `schema.sql` ni `instalar_laragon.sql`, que borran la base.
  - Con Docker: `sudo docker exec -i minimarket_mass_db sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot minimarket_mass' < backend/database/api_movil.sql`
  - Sin Docker: `sudo mysql minimarket_mass < backend/database/api_movil.sql`
- **PC de pruebas, sin datos que cuidar**: `actualizar.bat` (o `bash actualizar.sh`) lo detecta y ofrece recrear la base. A mano: `docker compose down -v` y `docker compose up -d --wait`.

Después correr otra vez `verificar_instalacion.php`: si dice que faltan tablas, falta algún script.

## 9. Antes de usarlo con datos reales

La base se crea con datos de ejemplo (las 3 tiendas de `datos.sql`, 9 empleados con la clave `Mass2026!` y ventas de ejemplo). Antes de abrir:

1. Claves de `.env` largas (12 caracteres o más) y distintas.
2. Cada empleado cambia `Mass2026!` en **Mi clave**, o el administrador le pone una nueva en **Personal**. Desactivar en **Personal** a los empleados de ejemplo que no existen.
3. Reemplazar los datos de ejemplo: direcciones de las tiendas y precios y códigos de barras del catálogo. Lo más rápido es **Catálogo → Importar CSV** con el archivo de la tienda.
4. Programar el respaldo diario (sección 10).
5. Correr la revisión. No cambia nada; dice qué falta corregir:

```bash
sudo -u www-data php backend/scripts/verificar_instalacion.php
```

Revisa la versión de PHP y sus extensiones, que PHP pueda guardar fotos, `display_errors` y el tamaño de subida (los del `php.ini` de consola, `/etc/php/8.3/cli/php.ini`), que las claves de `.env` no sean las de ejemplo, que `frontend/public/` no tenga `.env`, `.sql`, `.bak` ni `.log`, que la aplicación no entre como root, las 36 tablas, `sp_boleta`, la hora de Lima, que ningún empleado activo siga con la clave de prueba y que haya un respaldo de las últimas 48 horas. Termina con `0 fallas` cuando está listo. En el aula, con los datos de prueba, es normal que marque las claves de ejemplo y la clave de prueba.

## 10. Respaldo y restauración

| | Windows (PowerShell) | Linux |
|---|---|---|
| Sacar respaldo | `powershell -ExecutionPolicy Bypass -File backend\scripts\respaldo.ps1` | `sudo bash backend/scripts/respaldo.sh` |
| Programar el diario (23:00) | `powershell -ExecutionPolicy Bypass -File backend\scripts\programar_respaldo.ps1` | `sudo bash backend/scripts/programar_respaldo.sh` |
| Quitar el diario | `... programar_respaldo.ps1 -Quitar` | `sudo bash backend/scripts/programar_respaldo.sh quitar` |
| Restaurar | `powershell -ExecutionPolicy Bypass -File backend\scripts\restaurar.ps1 respaldos\ARCHIVO.sql` | `sudo bash backend/scripts/restaurar.sh respaldos/ARCHIVO.sql` |

- En Linux van con `sudo` porque usan `docker exec`; el respaldo diario queda en el `crontab` de root y escribe en `respaldos/respaldo.log`. En Windows queda en el Programador de tareas como "Minimarket Mass - respaldo diario" y, si la PC estaba apagada a las 23:00, corre apenas se prenda.
- El respaldo guarda todo: las 36 tablas, los datos, la vista `vw_comprobante` y el procedimiento `sp_boleta`. Queda en `respaldos/minimarket_mass_AAAA-MM-DD_HHMM.sql`, con la hora del servidor.
- Las **fotos de producto** no van en la base: están en `frontend/public/img/productos/`. Copiar esa carpeta junto con el respaldo y devolverla al restaurar. Si se pierde, los productos se ven con el ícono de su categoría.
- Se hace con `--single-transaction`: se puede sacar con la tienda vendiendo.
- El script revisa que el archivo termine bien; si no, lo borra y avisa.
- **Se guardan los últimos 30 respaldos**; cada respaldo nuevo borra los más antiguos.
- **Restaurar reemplaza todos los datos actuales**. Pide escribir `SI` para seguir.
- Los respaldos tienen DNIs y hashes de claves: `respaldos/` está en `.gitignore` y fuera de la raíz web. Guardar una copia fuera del servidor (USB o nube privada) al menos una vez por semana.
- **Sin Docker** (sección 6.3) los scripts no sirven. Respaldo: `sudo mysqldump --single-transaction --routines --triggers --databases minimarket_mass > respaldos/minimarket_mass_$(date +%F_%H%M).sql`. Restaurar: `sudo mysql < respaldos/ARCHIVO.sql`.

## 11. Comprobar que el código funciona

En una PC de pruebas o en la CI, **nunca contra la base de producción** (las tres últimas registran ventas e ingresos):

```bash
php backend/tests/unitarios.php     # 87, sin base de datos
php backend/tests/seguridad.php     # 19, revisa CSRF, escapado, SQL y JavaScript en todo el código
php backend/tests/web.php           # 160, por HTTP con los 3 roles
php backend/tests/integracion.php   # 171, contra MySQL; solo con la base recién creada
php backend/tests/api.php           # 81, la API de la app Android; va después de integracion.php
```

En GitHub corren solas en cada push a `main` y en cada Pull Request (`.github/workflows/ci.yml`).

## 12. Problemas comunes

| Síntoma | Causa y solución |
|---|---|
| "La base de datos no está disponible" | MySQL apagado: `docker compose up -d --wait`. Revisar `DB_HOST` y `DB_PORT` en `.env` |
| `docker compose up` dice "Falta DB_PASSWORD en .env" | No existe `.env` o falta la clave: copiar `.env.example` |
| `nginx -t` dice `unknown directive "http2"` | Configuración vieja con `http2 on;`, que el nginx 1.24 de Ubuntu 24.04 no conoce. Usar la de `deploy/` (`listen 443 ssl http2;`) |
| `nginx -t` dice que no encuentra `fullchain.pem` | Falta el certificado: `sudo certbot certonly --nginx -d TU-DOMINIO` antes de activar el sitio |
| El navegador dice "conexión no segura" | Falta el certificado o el dominio no coincide con `server_name` / `ServerName` |
| La app Android dice que no se puede conectar | La dirección no es la raíz con `https://` y `/` al final, o el servidor no tiene certificado válido. Probar `https://TU-DOMINIO/api/v1/salud` en el navegador del celular |
| La app no puede iniciar sesión: "Ocurrió un error en el servidor" (y `/api/v1/salud` sí responde) | Falta `api_movil.sql` (tabla `api_token`): ver sección 8 |
| El puerto 3307 está ocupado | Laragon u otro MySQL lo usa: detenerlo o cambiar `DB_PORT` a `3308` |
| "could not find driver" | PHP sin `pdo_mysql`: `sudo apt install php8.3-mysql` (en Windows, `extension=pdo_mysql` en `php.ini`) |
| Al subir una foto: "Call to undefined function imagecreatefromstring" | PHP sin `gd`: `sudo apt install php8.3-gd` y `sudo systemctl reload php8.3-fpm` |
| "No se pudo guardar la foto" | PHP no puede escribir en `frontend/public/img/productos/`: `sudo chown www-data frontend/public/img/productos` |
| `verificar_instalacion.php` dice "No hay .env" | Se corrió con un usuario que no puede leerlo: usar `sudo -u www-data php ...` |
| Falta una tabla (por ejemplo `api_token`) | El volumen se creó con una versión anterior: ver sección 8 |
| "Demasiados intentos fallidos" al entrar | 5 claves erradas seguidas con ese DNI (o 20 desde la misma PC). Esperar 15 minutos; en una PC de pruebas, `docker compose down -v` |
| La hora de las ventas está corrida 5 horas | MySQL sin la hora de Lima: `TZ: America/Lima` en Docker o `default-time-zone` sin Docker (sección 6.3) |
| El CSV se ve con letras raras en Excel | Abrir con doble clic (lleva BOM UTF-8); no copiar y pegar el texto |
| Otra PC de la red no llega a MySQL por el puerto 3307 | MySQL solo escucha en `127.0.0.1` (más seguro). La aplicación debe correr en la misma máquina que Docker |
| Al importar el catálogo sale "413 Request Entity Too Large" | nginx o Apache limitan el tamaño: usar las configuraciones de `deploy/` (2 MB) |
