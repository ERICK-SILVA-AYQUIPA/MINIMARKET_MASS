# Minimarket Mass en tu PC con Docker (guía del equipo)

Con Docker todos tenemos **lo mismo**: la misma base de datos (MySQL 8.4.11), el mismo PHP (8.3, igual que el servidor) y los mismos datos de prueba. Nadie instala PHP ni MySQL a mano, y para ponerse al día con el equipo basta **un doble clic**.

| Qué | Dónde queda |
|---|---|
| Página web | http://localhost:8000 |
| API de la app Android | http://localhost:8000/api/v1/ (prueba: `/api/v1/salud`) |
| Base de datos | Dentro de Docker. Desde tu PC (HeidiSQL, Workbench): `127.0.0.1`, puerto `3307`, usuario y clave de tu `.env` |

Usuario de prueba: DNI `70000001`, clave `Mass2026!` (los demás en `docs/manual_usuario.md`).

## 1. Instalar (una sola vez)

1. **Docker Desktop**: https://www.docker.com/products/docker-desktop/ . En Windows, si lo pide, acepta instalar WSL 2 y reinicia. Ábrelo y espera a que diga **Engine running**.
2. **Git** (https://git-scm.com/) o GitHub Desktop.

## 2. Primera vez

```powershell
git clone https://github.com/rick-back/minimarket_mass.git
cd minimarket_mass
```

Luego:

- **Windows**: doble clic en **`actualizar.bat`**.
- **Linux o Mac**: `bash actualizar.sh`

La primera vez tarda unos minutos (descarga MySQL y PHP y crea la base con los 11 scripts). Al final abre http://localhost:8000 solo.

> Si antes usabas **Laragon**, tu `.env` es el de Laragon y no sirve para Docker: el script te lo dirá. Renómbralo a `.env.viejo` y vuelve a hacer doble clic. Y no tengas abierto `iniciar_servidor_laragon.bat` al mismo tiempo (los dos usan el puerto 8000).

## 3. Cada vez que el equipo sube cambios

Otra vez **doble clic en `actualizar.bat`** (o `bash actualizar.sh`). Hace todo solo:

1. Si estás en la rama `main`, baja lo último de GitHub (`git pull`).
2. Revisa si cambió algún script de la base (`backend/database/*.sql`). Si cambió, **pregunta si recrea la base**: responde **S** (Enter). Se borran los datos de prueba de tu PC y se cargan otra vez los de GitHub, así todos quedamos con la misma base.
3. Reconstruye PHP si cambió su configuración (`docker/web/Dockerfile`) y levanta todo.

Si estás trabajando en tu propia rama, el script no baja nada (te avisa). Para traer lo del equipo a tu rama: `git checkout main`, doble clic en `actualizar.bat`, y luego `git checkout tu-rama` y `git merge main`.

Los cambios en archivos `.php`, `.css` o `.js` se ven al recargar el navegador; no hace falta correr nada.

## 4. App Android

La app de depuración necesita la dirección de **tu** PC, terminada en `:8000/`:

| Dónde corre la app | Dirección |
|---|---|
| Emulador de Android Studio | `http://10.0.2.2:8000/` (ya viene así) |
| Celular de verdad, en el **mismo Wi-Fi** que tu PC | `http://IP-de-tu-PC:8000/`, por ejemplo `http://192.168.1.50:8000/` |

`actualizar.bat` muestra tu IP al terminar (la del Wi-Fi casi siempre empieza con `192.168.`). En la app, pantalla de ingreso, botón **Cambiar** junto a la dirección: escribe la tuya y la app prueba la conexión antes de guardarla. Cada compañero pone la IP de **su** PC.

Si el celular no conecta: abre `http://IP-de-tu-PC:8000/api/v1/salud` en el navegador del celular. Si no carga, Windows está bloqueando el puerto: cuando Windows pregunte, permite Docker Desktop en **Redes privadas**, y revisa que el Wi-Fi esté como red **privada**.

## 5. Comandos útiles

Desde la carpeta del proyecto:

| Para | Comando |
|---|---|
| Apagar todo (los datos se quedan) | `docker compose stop` |
| Prender otra vez | `actualizar.bat` o `docker compose --profile web up -d` |
| Ver si está prendido | `docker compose ps` (deben salir `minimarket_mass_db` y `minimarket_mass_web`) |
| Ver errores | `docker compose logs web` o `docker compose logs mysql` |
| Empezar la base de cero (borra tus datos de prueba) | `docker compose down -v` y luego `actualizar.bat` |
| Correr las pruebas sin tener PHP instalado | `docker compose exec web php backend/tests/unitarios.php` (igual con `seguridad`, `web`, `integracion` y `api`) |
| Consola de MySQL | `docker exec -it minimarket_mass_db mysql -u mass_app -p minimarket_mass` |

## 6. Reglas para que todos sigamos iguales

- **Un cambio en la base se hace en `backend/database/`** (en el script que corresponde) y se sube con Pull Request. Si lo haces a mano en HeidiSQL, solo lo tendrás tú. Al subirlo, a los demás `actualizar.bat` les recrea la base sola.
- **No cambies versiones** de `docker-compose.yml` ni de `docker/web/Dockerfile` sin avisar al equipo y sin Pull Request: son lo que hace que todos tengamos lo mismo.
- **El `.env` nunca se sube** a GitHub (ya está en `.gitignore`). Cada uno tiene el suyo, creado desde `.env.example`. Si `.env.example` trae una línea nueva, `actualizar.bat` no toca tu `.env`: copia esa línea a tu `.env` a mano.
- Antes de hacer Pull Request, corre las pruebas (sección 5) con la base recién creada.

## 7. Problemas comunes

| Qué pasa | Qué hacer |
|---|---|
| "Docker no está corriendo" | Abrir Docker Desktop y esperar a que diga **Engine running** |
| "Tu .env no sirve para Docker" | Renombrar `.env` a `.env.viejo` y volver a correr `actualizar.bat` |
| "port is already allocated" con 8000 | Otro programa usa el 8000 (por ejemplo `php -S` o Laragon). Cerrarlo, o poner `WEB_PORT=8080` en tu `.env` |
| "port is already allocated" con 3307 | Otro MySQL usa el 3307: detenerlo o poner `DB_PORT=3308` en tu `.env` |
| La página dice "La base de datos no está disponible" | `docker compose ps`; si algo no dice `healthy`, correr `actualizar.bat` otra vez |
| Falta una tabla o la app dice "Ocurrió un error en el servidor" | Tu base es antigua: `docker compose down -v` y `actualizar.bat` |
| "Demasiados intentos fallidos" al entrar | Esperar 15 minutos, o `docker compose down -v` y `actualizar.bat` |

Más detalle (servidor de producción, respaldos, HTTPS): `docs/despliegue.md`.
