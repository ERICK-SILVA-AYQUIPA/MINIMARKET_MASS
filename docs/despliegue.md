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
docker compose up -d          # crea la base y corre los 7 scripts en orden
docker compose ps             # esperar a que diga "healthy"
php -S localhost:8000 -t public
```

Abrir `http://localhost:8000` y entrar con un DNI de prueba (ver `docs/manual_usuario.md`).

Los scripts de `database/` se ejecutan solo la primera vez, cuando el volumen está vacío, en este orden: `schema.sql`, `datos.sql`, `boleta (1).sql`, `login.sql`, `seguridad.sql`, `ajustes.sql` y `auditoria.sql`. Para empezar de cero (**se borran los datos**): `docker compose down -v` y luego `docker compose up -d`.

## 4. Opción B: MySQL sin Docker (Laragon)

Ejecutar los mismos scripts, en el mismo orden, con el usuario root de su MySQL:

```powershell
mysql -u root -p -h 127.0.0.1 -P 3306 < database/schema.sql
mysql -u root -p -h 127.0.0.1 -P 3306 < database/datos.sql
mysql -u root -p -h 127.0.0.1 -P 3306 < "database/boleta (1).sql"
mysql -u root -p -h 127.0.0.1 -P 3306 < database/login.sql
mysql -u root -p -h 127.0.0.1 -P 3306 < database/seguridad.sql
mysql -u root -p -h 127.0.0.1 -P 3306 < database/ajustes.sql
mysql -u root -p -h 127.0.0.1 -P 3306 < database/auditoria.sql
```

Luego crear el usuario de la aplicación con solo los permisos que necesita (no root):

```sql
CREATE USER 'mass_app'@'localhost' IDENTIFIED BY 'una_clave_larga';
GRANT SELECT, INSERT, UPDATE, DELETE, EXECUTE ON minimarket_mass.* TO 'mass_app'@'localhost';
```

y poner en `.env` `DB_PORT=3306` y esa clave.

## 5. Opción C: servidor con Apache o Nginx

`php -S` es solo para pruebas. En un servidor real:

- **La raíz del sitio debe ser la carpeta `public/`**, nunca la carpeta del proyecto. Así nadie puede descargar `.env`, `config/` ni `database/`.
- Todo entra por `public/index.php` con `?ruta=...`, así que no hace falta reescribir URLs.
- **HTTPS obligatorio** (por ejemplo con Let's Encrypt). Con HTTPS la cookie de sesión se marca `Secure` sola (`config/Sesion.php`).
- En `php.ini`: `display_errors = Off` y `log_errors = On`. La aplicación ya no muestra detalles, y los errores quedan en el log de PHP.
- La hora del sistema es la de Lima: PHP la fija en `public/index.php` y MySQL en `docker-compose.yml` (`TZ: America/Lima`). Si MySQL no está en Docker, ponerle la misma zona.

Apache (`/etc/apache2/sites-available/mass.conf`):

```apache
<VirtualHost *:80>
    ServerName mass.ejemplo.pe
    DocumentRoot /var/www/minimarket_mass/public
    <Directory /var/www/minimarket_mass/public>
        AllowOverride None
        Require all granted
    </Directory>
</VirtualHost>
```

Nginx con PHP-FPM:

```nginx
server {
    server_name mass.ejemplo.pe;
    root /var/www/minimarket_mass/public;
    index index.php;
    location / { try_files $uri /index.php?$query_string; }
    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }
}
```

## 6. Antes de usarlo con datos reales

1. Cambiar las claves de `.env` por claves largas.
2. Cada empleado cambia `Mass2026!` en **Mi clave**, o el administrador le pone una nueva en **Personal**.
3. Reemplazar los datos de prueba de `datos.sql` (direcciones, productos y precios inventados).
4. Programar el respaldo diario (sección 7).
5. Pasar las pruebas (sección 9).

## 7. Respaldo y restauración

| | Windows (PowerShell) | Linux / macOS |
|---|---|---|
| Sacar respaldo | `powershell -ExecutionPolicy Bypass -File scripts\respaldo.ps1` | `bash scripts/respaldo.sh` |
| Restaurar | `powershell -ExecutionPolicy Bypass -File scripts\restaurar.ps1 respaldos\ARCHIVO.sql` | `bash scripts/restaurar.sh respaldos/ARCHIVO.sql` |

- El respaldo guarda todo: las 29 tablas, los datos, la vista `vw_comprobante` y el procedimiento `sp_boleta`. Queda en `respaldos/minimarket_mass_AAAA-MM-DD_HHMM.sql`.
- Se hace con `--single-transaction`: se puede sacar con la tienda vendiendo, sin detenerla.
- El script revisa que el archivo termine bien; si no, lo borra y avisa.
- **Restaurar reemplaza todos los datos actuales**. Pide escribir `SI` para seguir.
- Los respaldos tienen DNIs y hashes de claves: `respaldos/` está en `.gitignore`. Guardar una copia fuera de la PC (USB o nube privada).
- Funciona con el contenedor de Docker (`minimarket_mass_db`). Sin Docker, usar `mysqldump --single-transaction --routines --triggers --databases minimarket_mass > respaldo.sql`.

Respaldo diario automático:

- **Windows**: Programador de tareas → Crear tarea básica → Diariamente, 23:00 → Iniciar un programa `powershell.exe` con argumentos `-ExecutionPolicy Bypass -File C:\ruta\minimarket_mass\scripts\respaldo.ps1`.
- **Linux**: `crontab -e` y agregar `0 23 * * * cd /var/www/minimarket_mass && bash scripts/respaldo.sh >> respaldos/respaldo.log 2>&1`.

## 8. Actualizar a una versión nueva

```powershell
bash scripts/respaldo.sh        # o respaldo.ps1: siempre respaldar antes
git pull origin main
```

Si la versión nueva trae un script nuevo en `database/` (por ejemplo `auditoria.sql`), hay dos caminos:

- **Aula, sin datos que cuidar**: `docker compose down -v` y `docker compose up -d` (se vuelve a crear todo).
- **Con datos reales**: ejecutar solo el script nuevo sobre la base que ya existe:
  `docker exec -i minimarket_mass_db sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot minimarket_mass' < database/auditoria.sql`

## 9. Comprobar que todo funciona

```powershell
php tests/unitarios.php     # 51, sin base de datos
php tests/seguridad.php     # 16, revisa CSRF, escapado y SQL en todo el código
php tests/web.php           # 103, por HTTP con los 3 roles; no cambia datos
php tests/integracion.php   # 103, contra MySQL; solo con la base recién creada (cambia datos)
```

En GitHub corren solas en cada push (`.github/workflows/ci.yml`).

## 10. Problemas comunes

| Síntoma | Causa y solución |
|---|---|
| "La base de datos no está disponible" | MySQL apagado: `docker compose up -d` y esperar "healthy". Revisar `DB_PORT` en `.env` |
| `docker compose up` dice "Falta DB_PASSWORD en .env" | No existe `.env` o falta la clave: copiar `.env.example` |
| El puerto 3307 está ocupado | Laragon u otro MySQL lo usa: detenerlo o cambiar `DB_PORT` a `3308` |
| "could not find driver" | PHP sin `pdo_mysql`: activar `extension=pdo_mysql` en `php.ini` |
| Falta una tabla (por ejemplo `auditoria`) | El volumen se creó con una versión anterior: ver sección 8 |
| "Demasiados intentos fallidos" al entrar | 5 claves erradas seguidas con ese DNI (o 20 desde la misma PC). Esperar, o en el aula `docker compose down -v` |
| La hora de las ventas está corrida 5 horas | MySQL sin `TZ: America/Lima` (ver sección 5) |
| El CSV se ve con letras raras en Excel | Abrir con doble clic (lleva BOM UTF-8); no copiar y pegar el texto |
