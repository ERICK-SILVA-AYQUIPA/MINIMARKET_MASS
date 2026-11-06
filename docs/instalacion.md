# Guía de instalación (corta)

Cómo dejar Minimarket Mass funcionando, en una página. Cada paso enlaza a la guía completa (`docs/despliegue.md`) por si algo falla. Versión que se instala: la del archivo `VERSION` (sale en el pie de cada página).

## ¿Qué instalación necesito?

| Caso | Opción | Tiempo |
|---|---|---|
| Probar en una PC con Windows (aula, presentación) | **A. Laragon** | 10 min |
| Probar en cualquier PC con Docker (Windows, Linux o Mac) | **B. Docker** | 10 min |
| Las tiendas venden de verdad, desde varias PCs y la app Android | **C. Servidor con HTTPS** | 1 a 2 horas |
| Una sola tienda que quiere seguir vendiendo aunque se caiga el internet | **C** en una PC de la tienda (ver `docs/plan_sin_internet.md`, sección 6) | 1 a 2 horas |

## A. Laragon (Windows)

1. Instalar [Laragon](https://laragon.org) con PHP 8.3 o más y pulsar **Iniciar todo**.
2. Bajar el proyecto (`git clone https://github.com/rick-back/minimarket_mass.git` o descargar el ZIP de GitHub) en cualquier carpeta, por ejemplo `C:\laragon\www\minimarket_mass`.
3. Doble clic en **`iniciar_servidor_laragon.bat`**. Crea el `.env`, instala la base si no existe, aplica las migraciones y abre `http://localhost:8000`.
4. Entrar con DNI `70000001` y clave `Mass2026!`. El sistema pide cambiar la clave de prueba al primer ingreso.

Detalle y problemas: `docs/guia_laragon.md`.

## B. Docker

1. Instalar Docker Desktop (o Docker Engine en Linux) y Git.
2. `git clone https://github.com/rick-back/minimarket_mass.git` y entrar a la carpeta.
3. Windows: doble clic en **`actualizar.bat`**. Linux o Mac: `bash actualizar.sh`. La primera vez crea el `.env` desde `.env.example` y la base con todos los scripts.
4. Abrir `http://localhost:8000` y entrar como en A.

Detalle: `docs/despliegue.md`, sección 5.1, y `docs/equipo/docker.md`.

## C. Servidor con HTTPS (producción)

Resumen de `docs/despliegue.md`, sección 6 (con los comandos exactos para Ubuntu 24.04, nginx o Apache):

1. **Servidor**: Ubuntu 24.04 con nginx (o Apache), PHP 8.3 con PHP-FPM (`pdo_mysql`, `mbstring`, `gd`), Docker y Git. Un dominio apuntando a su IP.
2. **Proyecto** en `/var/www/minimarket_mass` y `.env` copiado de `.env.example`, con **claves largas**, `COMPOSE_PROFILES=` vacío y el **contacto de soporte** (ver abajo).
3. **Base**: `sudo docker compose up -d --wait`.
4. **HTTPS**: certificado de Let's Encrypt y la configuración de `deploy/` (la raíz del sitio es `frontend/public/`, nunca la carpeta del proyecto).
5. **Datos reales**: `quitar_datos_demo.php` con el DNI del primer administrador (sección 9), y él carga tienda, catálogo y personal.
6. **Respaldo diario**: `sudo bash backend/scripts/programar_respaldo.sh`, con `RESPALDO_CLAVE` y `RESPALDO_REMOTO` en `.env` (sección 10).
7. **Revisión final**: `sudo -u www-data php backend/scripts/verificar_instalacion.php` debe terminar en `0 fallas`.

## Contacto de soporte y versión

En `.env` (`.env.example` los trae con el teléfono y el correo vacíos):

```ini
SOPORTE_NOMBRE=Juan Pérez · Sistemas
SOPORTE_TELEFONO=+51 987 654 321
SOPORTE_CORREO=soporte@tu-empresa.pe
SOPORTE_HORARIO=Lunes a sábado, 7:00 a 22:00
```

Salen en el pie de cada página (también en el login) y en **Ayuda**. El teléfono solo acepta dígitos, espacios y `+`; un correo mal escrito no se muestra. Sin teléfono ni correo, `verificar_instalacion.php` lo marca como AVISO.

La versión es el archivo `VERSION` de la raíz (formato `1.2.3`). Al entregar una versión nueva se cambia ahí y se anota en `CHANGELOG.md`; `GET /?ruta=salud` también la devuelve.

## Después de instalar

| Revisar | Cómo |
|---|---|
| Que todo responde | Abrir `/?ruta=salud`: debe decir `"estado":"ok"` |
| Que nadie use la clave de prueba | El sistema obliga a cambiar `Mass2026!` al primer ingreso; `verificar_instalacion.php` avisa si queda alguno |
| Que se sabe qué hacer sin internet | Leer `docs/plan_sin_internet.md` e imprimir la hoja de contingencia para cada caja |
| Que el equipo sabe usarlo | `docs/manual_usuario.md` (y la pantalla **Ayuda** dentro del sistema) |
| Actualizar más adelante | `docs/despliegue.md`, sección 8: respaldo, `git pull` y `migrar.php` |
