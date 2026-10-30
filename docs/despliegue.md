# Guía de instalación y despliegue

Cómo poner a funcionar Minimarket Mass en una PC del aula o en un servidor, cómo sacar respaldos y qué hacer si algo falla. Para el uso diario ver `docs/manual_usuario.md`.

## 1. Requisitos

| Programa | Versión | Para qué |
|---|---|---|
| PHP | 8.3 o más, con `pdo_mysql` y `mbstring` | La aplicación |
| Docker Desktop | Cualquiera reciente | MySQL 8.4.11 sin instalarlo (recomendado) |
| MySQL | 8.4 (si no se usa Docker; por ejemplo Laragon) | La base de datos |
| Git | Cualquiera | Bajar el proyecto |

Revisar PHP con `php -v` y `php -m` (deben salir `pdo_mysql` y `mbstring`).

## 2. Configuración (`.env`)

```powershell
copy .env.example .env      # Linux/macOS: cp .env.example .env
```

Abrir `.env` y cambiar **las dos claves** (`DB_PASSWORD` y `DB_ROOT_PASSWORD`). El `.env` nunca se sube a GitHub (está en `.gitignore`).

| Variable | Valor del aula | En un servidor |
|---|---|---|
| `DB_HOST` | `127.0.0.1` | Donde esté MySQL |
| `DB_PORT` | `3307` | `3306` si MySQL está en el mismo servidor sin Docker |
| `DB_NAME` | `minimarket_mass` | Igual |
| `DB_USER` / `DB_PASSWORD` | `mass_app` / la que pongan | Un usuario solo para la aplicación, con clave larga |
| `DB_ROOT_PASSWORD` | La que pongan | Solo la usa Docker para crear la base |

## 3. Opción A: Docker + `php -S` (aula)

```powershell
docker compose up -d          # crea la base y corre los 10 scripts en orden
docker compose ps             # esperar a que diga "healthy"
php -S localhost:8000 -t public
```

Abrir `http://localhost:8000` y entrar con un DNI de prueba (ver `docs/manual_usuario.md`).

Los scripts de `database/` se ejecutan solo la primera vez, cuando el volumen está vacío, en este orden: `schema.sql`, `datos.sql`, `boleta.sql`, `login.sql`, `seguridad.sql`, `ajustes.sql`, `auditoria.sql`, `ingresos.sql`, `operaciones.sql` y `catalogo.sql`. Para empezar de cero (**se borran los datos**): `docker compose down -v` y luego `docker compose up -d`.

## 4. Opción B: MySQL sin Docker (Laragon)

Ejecutar los mismos scripts, en el mismo orden, con el usuario root de su MySQL:

```powershell
mysql -u root -p -h 127.0.0.1 -P 3306 < database/schema.sql
mysql -u root -p -h 127.0.0.1 -P 3306 < database/datos.sql
mysql -u root -p -h 127.0.0.1 -P 3306 < database/boleta.sql
mysql -u root -p -h 127.0.0.1 -P 3306 < database/login.sql
mysql -u root -p -h 127.0.0.1 -P 3306 < database/seguridad.sql
mysql -u root -p -h 127.0.0.1 -P 3306 < database/ajustes.sql
mysql -u root -p -h 127.0.0.1 -P 3306 < database/auditoria.sql
mysql -u root -p -h 127.0.0.1 -P 3306 < database/ingresos.sql
mysql -u root -p -h 127.0.0.1 -P 3306 < database/operaciones.sql
mysql -u root -p -h 127.0.0.1 -P 3306 < database/catalogo.sql
```

Luego crear el usuario de la aplicación con solo los permisos que necesita (no root):

```sql
CREATE USER 'mass_app'@'localhost' IDENTIFIED BY 'una_clave_larga';
GRANT SELECT, INSERT, UPDATE, DELETE, EXECUTE ON minimarket_mass.* TO 'mass_app'@'localhost';
```

y poner en `.env` `DB_PORT=3306` y esa clave.

## 5. Opción C: servidor en internet con HTTPS

`php -S` es solo para pruebas. Para que las tiendas entren desde cualquier lugar se usa un servidor (por ejemplo un VPS con Ubuntu 24.04) con Apache o Nginx y HTTPS. Las configuraciones listas están en `deploy/`.

**Reglas que no cambian:**

- **La raíz del sitio es la carpeta `public/`**, nunca la carpeta del proyecto. Así nadie puede descargar `.env`, `config/`, `database/` ni `respaldos/`.
- **HTTPS obligatorio**: sin él, la clave y la cookie de sesión viajan sin cifrar. Con HTTPS la cookie se marca `Secure` sola (`config/Sesion.php`) y la aplicación envía `Strict-Transport-Security`, así el navegador ya no vuelve a abrir el sitio sin cifrar.
- **MySQL no queda abierto a internet**: `docker-compose.yml` publica el puerto solo en `127.0.0.1`. El firewall abre solo 22, 80 y 443.
- En el `php.ini` del servidor web: `display_errors = Off`, `log_errors = On`, `upload_max_filesize = 2M` y `post_max_size = 8M` (para importar el catálogo).
- La hora es la de Lima: PHP la fija en `public/index.php` y MySQL en `docker-compose.yml` (`TZ: America/Lima`). Si MySQL no está en Docker, ponerle la misma zona.

**Paso a paso (Ubuntu 24.04, con Nginx):**

```bash
# 1. Programas (Docker según docs.docker.com/engine/install/ubuntu)
sudo apt update && sudo apt install -y nginx php8.3-fpm php8.3-mysql php8.3-mbstring php8.3-gd git certbot python3-certbot-nginx
sudo ufw allow OpenSSH && sudo ufw allow 'Nginx Full' && sudo ufw enable

# 2. Proyecto y base
sudo git clone https://github.com/ERICK-SILVA-AYQUIPA/MINIMARKET_MASS.git /var/www/minimarket_mass
cd /var/www/minimarket_mass
sudo cp .env.example .env && sudo nano .env        # claves largas y distintas
sudo chown root:www-data .env && sudo chmod 640 .env
sudo docker compose up -d                           # esperar "healthy" con: sudo docker compose ps
sudo chown www-data public/img/productos           # PHP guarda aquí las fotos de producto

# 3. Sitio con HTTPS (antes, apuntar el dominio a la IP del servidor)
sudo cp deploy/nginx-mass.conf /etc/nginx/sites-available/mass    # cambiar mass.ejemplo.pe por el dominio
sudo ln -s /etc/nginx/sites-available/mass /etc/nginx/sites-enabled/
sudo certbot --nginx -d mass.ejemplo.pe             # crea el certificado y lo renueva solo
sudo nginx -t && sudo systemctl reload nginx

# 4. Revisar y programar el respaldo
php scripts/verificar_instalacion.php
sudo bash scripts/programar_respaldo.sh
```

Con Apache es igual, pero en el paso 3 se usa `deploy/apache-mass.conf` (`sudo a2enmod ssl headers rewrite`, `sudo a2ensite mass` y `sudo certbot --apache -d mass.ejemplo.pe`).

## 6. Antes de usarlo con datos reales

1. Cambiar las claves de `.env` por claves largas (12 caracteres o más).
2. Cada empleado cambia `Mass2026!` en **Mi clave**, o el administrador le pone una nueva en **Personal**.
3. Reemplazar los datos de prueba: direcciones de `datos.sql`, y precios y códigos de barras del catálogo. Lo más rápido es **Catálogo → Importar CSV** con el archivo de la tienda.
4. Programar el respaldo diario (sección 7).
5. Correr la revisión de la instalación. No cambia nada; dice qué falta corregir:

```bash
php scripts/verificar_instalacion.php
```

Revisa la versión de PHP y sus extensiones, que las claves de `.env` no sean las de ejemplo, que `public/` no tenga archivos que no deben descargarse, que la aplicación no entre a MySQL como root, las 36 tablas, `sp_boleta`, la hora de Lima, que ningún empleado activo siga con la clave de prueba y que haya un respaldo de las últimas 48 horas. Termina en `0 fallas` cuando está listo. En el aula, con los datos de prueba, es normal que marque la clave de prueba.

## 7. Respaldo y restauración

| | Windows (PowerShell) | Linux / macOS |
|---|---|---|
| Sacar respaldo | `powershell -ExecutionPolicy Bypass -File scripts\respaldo.ps1` | `bash scripts/respaldo.sh` |
| Programar el diario (23:00) | `powershell -ExecutionPolicy Bypass -File scripts\programar_respaldo.ps1` | `bash scripts/programar_respaldo.sh` |
| Quitar el diario | `... programar_respaldo.ps1 -Quitar` | `bash scripts/programar_respaldo.sh quitar` |
| Restaurar | `powershell -ExecutionPolicy Bypass -File scripts\restaurar.ps1 respaldos\ARCHIVO.sql` | `bash scripts/restaurar.sh respaldos/ARCHIVO.sql` |

- El respaldo guarda todo: las 36 tablas, los datos, la vista `vw_comprobante` y el procedimiento `sp_boleta`. Queda en `respaldos/minimarket_mass_AAAA-MM-DD_HHMM.sql`.
- Las **fotos de producto** no van en la base: están en `public/img/productos/`. Copiar esa carpeta junto con el respaldo (por ejemplo, comprimida en el mismo disco o nube) y devolverla al restaurar. Si se pierde, los productos se ven con el ícono de su categoría.
- Se hace con `--single-transaction`: se puede sacar con la tienda vendiendo, sin detenerla.
- El script revisa que el archivo termine bien; si no, lo borra y avisa.
- **Se guardan los últimos 30 respaldos** (un mes si es diario); cada respaldo nuevo borra los más antiguos.
- El respaldo diario se puede programar dos veces sin que se duplique. En Windows queda en el Programador de tareas como "Minimarket Mass - respaldo diario" y, si la PC estaba apagada a las 23:00, corre apenas se prenda. En Linux queda en el `crontab` y escribe en `respaldos/respaldo.log`.
- **Restaurar reemplaza todos los datos actuales**. Pide escribir `SI` para seguir.
- Los respaldos tienen DNIs y hashes de claves: `respaldos/` está en `.gitignore`. Guardar una copia fuera de la PC o del servidor (USB o nube privada), al menos una vez por semana.
- Funciona con el contenedor de Docker (`minimarket_mass_db`). Sin Docker, usar `mysqldump --single-transaction --routines --triggers --databases minimarket_mass > respaldo.sql`.

## 8. Actualizar a una versión nueva

```powershell
bash scripts/respaldo.sh        # o respaldo.ps1: siempre respaldar antes
git pull origin main
```

Si la versión nueva trae un script nuevo en `database/` (por ejemplo `ingresos.sql`), hay dos caminos:

- **Aula, sin datos que cuidar**: `docker compose down -v` y `docker compose up -d` (se vuelve a crear todo).
- **Con datos reales**: ejecutar solo el script nuevo sobre la base que ya existe:
  `docker exec -i minimarket_mass_db sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot minimarket_mass' < database/ingresos.sql`

## 9. Comprobar que todo funciona

```powershell
php tests/unitarios.php     # 87, sin base de datos
php tests/seguridad.php     # 19, revisa CSRF, escapado, SQL y JavaScript en todo el código
php tests/web.php           # 160, por HTTP con los 3 roles; solo deja en la auditoría la foto de prueba que sube y quita
php tests/integracion.php   # 171, contra MySQL; solo con la base recién creada (cambia datos)
```

En GitHub corren solas en cada push (`.github/workflows/ci.yml`).

## 10. Problemas comunes

| Síntoma | Causa y solución |
|---|---|
| "La base de datos no está disponible" | MySQL apagado: `docker compose up -d` y esperar "healthy". Revisar `DB_PORT` en `.env` |
| `docker compose up` dice "Falta DB_PASSWORD en .env" | No existe `.env` o falta la clave: copiar `.env.example` |
| El puerto 3307 está ocupado | Laragon u otro MySQL lo usa: detenerlo o cambiar `DB_PORT` a `3308` |
| "could not find driver" | PHP sin `pdo_mysql`: activar `extension=pdo_mysql` en `php.ini` |
| Al subir una foto: "Call to undefined function imagecreatefromstring" | PHP sin `gd`: activar `extension=gd` en `php.ini` (o `apt install php8.3-gd`) y reiniciar el servidor web |
| "No se pudo guardar la foto" | PHP no puede escribir en `public/img/productos/`: darle permiso al usuario del servidor web |
| Falta una tabla (por ejemplo `ingreso_mercaderia`) | El volumen se creó con una versión anterior: ver sección 8 |
| "Demasiados intentos fallidos" al entrar | 5 claves erradas seguidas con ese DNI (o 20 desde la misma PC). Esperar, o en el aula `docker compose down -v` |
| La hora de las ventas está corrida 5 horas | MySQL sin `TZ: America/Lima` (ver sección 5) |
| El CSV se ve con letras raras en Excel | Abrir con doble clic (lleva BOM UTF-8); no copiar y pegar el texto |
| Otra PC de la red ya no llega a MySQL por el puerto 3307 | Ahora MySQL solo escucha en `127.0.0.1` (más seguro). La aplicación debe correr en la misma PC que Docker |
| Al importar el catálogo sale "413 Request Entity Too Large" | Nginx o Apache limitan el tamaño: usar las configuraciones de `deploy/` (2 MB) |
| El navegador dice "conexión no segura" | Falta el certificado: `sudo certbot --nginx -d DOMINIO` (o `--apache`) |
