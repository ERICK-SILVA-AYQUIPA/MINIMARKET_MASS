# Seguridad de la aplicación

Este documento dice qué protege la aplicación, dónde está en el código y qué prueba lo comprueba. Lo más importante es que **no depende de revisar a mano**: `tests/seguridad.php` lee todo el código y falla si alguien agrega un formulario sin token, una salida sin escapar o un SQL armado con datos de afuera. Corre en cada push de GitHub (`.github/workflows/ci.yml`).

```powershell
php tests/seguridad.php    # 16 comprobaciones, sin base de datos, en menos de 1 segundo
```

## 1. Resumen de lo comprobado

| Protección | Qué evita | Dónde está | Cómo se comprueba |
|---|---|---|---|
| Token CSRF en todo POST | Que otra página mande formularios con la sesión del empleado | `Sesion::csrfValido()` en `public/index.php`, antes del `match` de rutas POST; `campoCsrf()` en las vistas | `seguridad.php` sección 1 (25 rutas POST y 25 formularios) y `web.php` (POST sin token = 403) |
| Escapado de salida | XSS: que un nombre de producto con `<script>` se ejecute | `e()` = `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` en `views/parciales/funciones.php` | `seguridad.php` sección 2 (las 504 salidas `<?=` de las vistas) y `web.php` (un `<b>` en la búsqueda sale escapado) |
| Consultas preparadas | Inyección SQL | Todos los Repository y Servicios usan `prepare()` con `?`; `PDO::ATTR_EMULATE_PREPARES = false` en `config/Database.php` | `seguridad.php` sección 3 (las 130 llamadas a `prepare()`/`query()`) |
| Permisos por rol | Que un cajero anule ventas o vea reportes | `Usuario::puede...()` en cada rol; el controlador responde 403 y el servicio vuelve a revisar | `web.php` (matriz de rutas × 3 roles) e `integracion.php` |
| Bloqueo por claves erradas | Probar claves por fuerza bruta | 5 claves erradas de un DNI, o 20 desde una misma IP, = 15 minutos de espera (`database/seguridad.sql`, `IntentoLoginRepository`) | `web.php` y `integracion.php` |
| Sesión | Robo o fijación de sesión | Cookie `HttpOnly`, `SameSite=Lax`, `Secure` con HTTPS, `use_strict_mode`, `session_regenerate_id()` al entrar, cierre tras 30 minutos sin uso (`config/Sesion.php`) | Revisión del código; `web.php` usa una cookie por rol |
| Claves | Que se lean las claves si roban la base | Solo se guarda el hash bcrypt (`password_hash`), nunca la clave | `unitarios.php` |
| Cabeceras HTTP | Clickjacking y scripts inyectados | `Content-Security-Policy` con `script-src 'none'`, `X-Frame-Options: DENY`, `nosniff`, `Referrer-Policy` (`public/index.php`) | `web.php` |
| CSV sin fórmulas | Que Excel ejecute `=HIPERVINCULO(...)` escrito en un nombre | `Controller::enviarCsv()` antepone `'` a los textos que empiezan con `= + - @` | `web.php` revisa la descarga; la regla está en un solo método |
| Errores sin detalles | Mostrar rutas, usuario o clave de MySQL al visitante | `public/index.php` atrapa todo, responde 500/503 con un mensaje simple y guarda el detalle con `error_log()` | Revisión del código |
| `.env` fuera de GitHub | Publicar las claves de la base | `.env` y `respaldos/` en `.gitignore`; solo se sube `.env.example` | `seguridad.php` sección 4 |

## 2. Reglas que revisa `tests/seguridad.php`

1. **Formularios y CSRF**: lee las rutas POST de `public/index.php`, revisa que el token se valide antes de ejecutarlas, que cada `<form method="post">` de `views/` tenga `campoCsrf($csrf)` y apunte a una ruta POST conocida, y que ningún formulario GET apunte a una ruta que cambia datos.
2. **Escapado**: cada `<?= ... ?>` de las vistas tiene que ser `e(...)`, un número (`(int)`, `getId...()`, `count()`) o un texto fijo. Si alguien escribe `<?= $producto->getNombreProducto() ?>` sin `e()`, la prueba falla y dice el archivo y la línea.
3. **SQL**: recorre con el analizador de PHP (`token_get_all`) todo `models/` y `config/`. Dentro de `prepare()` y `query()` solo se aceptan variables que son partes fijas del SQL armadas por el propio código (`$donde`, `$condicion`, `$sql`...) o números forzados con `(int)`. Los datos del usuario siempre van como parámetro `?`. También revisa que controladores y vistas no tengan SQL.
4. **Reglas generales**: `declare(strict_types=1)` en todos los archivos, ninguna propiedad `public`, `$_GET` y `$_POST` solo en `public/index.php` y ninguna clave escrita en el código.

Se probó que la revisión sí detecta errores: al quitar a propósito un `campoCsrf()`, un `e()` y al pegar una variable dentro de un `prepare()`, las tres secciones fallaron.

## 3. Auditoría (bitácora de acciones)

La tabla `auditoria` (`database/auditoria.sql`) guarda quién hizo cada cambio sensible, cuándo y en qué tienda. Se escribe **dentro de la misma transacción** que el cambio: si la operación falla, no queda registro falso.

| Acción | Cuándo se registra |
|---|---|
| `VENTA_ANULADA` | Un administrador anula una boleta o factura (con el motivo) |
| `PRECIO_CAMBIADO` | Se edita un producto y cambia el precio (precio anterior y nuevo) |
| `PRODUCTO_CREADO`, `PRODUCTO_EDITADO`, `PRODUCTO_ESTADO`, `STOCK_MINIMO` | Cambios del catálogo y del stock mínimo |
| `CATEGORIA_CREADA`, `MARCA_CREADA` | Altas en el catálogo |
| `EMPLEADO_CREADO`, `EMPLEADO_EDITADO`, `CLAVE_REINICIADA`, `CLAVE_CAMBIADA` | Personal y claves (nunca se guarda la clave) |
| `TIENDA_ABIERTA`, `TIENDA_EDITADA`, `CAJA_AGREGADA`, `CAJA_ESTADO`, `DISTRITO_CREADO` | Tiendas y cajas |
| `CLIENTE_DOCUMENTO` | Se completa el DNI o RUC de un cliente (no se guarda el número) |
| `INGRESO_REGISTRADO` | El almacenero registra mercadería recibida (documento, emisor, número de productos y total) |

El administrador la ve en `?ruta=auditoria`, filtrada por acción y de 50 en 50; solo ve la de su tienda. Ventas, traslados, mermas y ajustes no se repiten aquí porque ya quedan en el kardex (`movimiento_stock`) con empleado y fecha.

**Registro de errores**: los errores inesperados se escriben con `error_log()` en el log de PHP (con `php -S` salen en la consola; con Apache en `error.log`).

## 4. Respaldos

`scripts/respaldo.ps1` (Windows) o `scripts/respaldo.sh` (Linux/macOS) sacan una copia completa con `mysqldump --single-transaction` a la carpeta `respaldos/`. La clave de root no se escribe en el comando: la lee MySQL dentro del contenedor. Los respaldos tienen DNIs y hashes de claves, por eso `respaldos/` está en `.gitignore`. Ver `docs/despliegue.md`.

## 5. Lo que queda fuera (para un sistema real)

- **HTTPS**: en el aula se usa `http://localhost`. En un servidor real se pone HTTPS (por ejemplo con Let's Encrypt) y la cookie pasa a `Secure` sola.
- **Cambiar las claves de prueba**: los 9 empleados de `datos.sql` tienen `Mass2026!`. Fuera del aula, cada uno la cambia en `?ruta=mi_clave`.
- **Facturación electrónica SUNAT**: los comprobantes se numeran sin huecos, pero no se envían a SUNAT ni a un OSE.
