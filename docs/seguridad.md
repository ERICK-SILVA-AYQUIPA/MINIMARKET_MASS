# Seguridad de la aplicación

Este documento dice qué protege la aplicación, dónde está en el código y qué prueba lo comprueba. Lo más importante es que **no depende de revisar a mano**: `backend/tests/seguridad.php` lee todo el código y falla si alguien agrega un formulario sin token, una salida sin escapar o un SQL armado con datos de afuera. Corre en cada push de GitHub (`.github/workflows/ci.yml`).

```powershell
php backend/tests/seguridad.php    # sin base de datos, en menos de 1 segundo
```

## 1. Resumen de lo comprobado

| Protección | Qué evita | Dónde está | Cómo se comprueba |
|---|---|---|---|
| Token CSRF en todo POST | Que otra página mande formularios con la sesión del empleado | `Sesion::csrfValido()` en `frontend/public/index.php`, antes del `match` de rutas POST; `campoCsrf()` en las vistas | `seguridad.php` sección 1 (29 rutas POST y 30 formularios) y `web.php` (POST sin token = 403) |
| Escapado de salida | XSS: que un nombre de producto con `<script>` se ejecute | `e()` = `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` en `frontend/views/parciales/funciones.php` | `seguridad.php` sección 2 (las 858 salidas `<?=` de las vistas; `icono('nombre')` solo se acepta con un nombre fijo) y `web.php` (un `<b>` en la búsqueda sale escapado) |
| Consultas preparadas | Inyección SQL | Todos los Repository y Servicios usan `prepare()` con `?`; `PDO::ATTR_EMULATE_PREPARES = false` en `backend/config/Database.php` | `seguridad.php` sección 3 (las 156 llamadas a `prepare()`/`query()`) |
| Permisos por rol | Que un cajero anule ventas o vea reportes | `Usuario::puede...()` en cada rol; el controlador responde 403 y el servicio vuelve a revisar | `web.php` (matriz de rutas × 3 roles) e `integracion.php` |
| Bloqueo por claves erradas | Probar claves por fuerza bruta | 5 claves erradas de un DNI, o 20 desde una misma IP, = 15 minutos de espera (`backend/database/seguridad.sql`, `IntentoLoginRepository`). Si el administrador le reinicia la clave en **Personal**, el empleado queda desbloqueado al instante | `web.php` y `integracion.php` |
| Sesión | Robo o fijación de sesión | Cookie `HttpOnly`, `SameSite=Lax`, `Secure` con HTTPS, `use_strict_mode`, `session_regenerate_id()` al entrar, cierre tras 30 minutos sin uso (`backend/config/Sesion.php`). En la pantalla, `app.js` avisa 2 minutos antes con una cuenta regresiva y el botón **Seguir conectado**, y al vencer lleva al login: una caja olvidada no se queda mostrando ventas. Usar el teclado o el mouse renueva la sesión (`?ruta=sesion_activa`, cada 5 minutos como mucho); las pestañas comparten la cuenta | `web.php` (204 de `sesion_activa`, login sin contador) y prueba en el navegador |
| Claves | Que se lean las claves si roban la base | Solo se guarda el hash bcrypt (`password_hash`), nunca la clave | `unitarios.php` |
| Clave propia segura | Que el empleado se quede con `Mass2026!` o con una fácil de adivinar | La clave que elige cada uno (Mi clave, o el administrador para sí mismo) no puede ser la de prueba, una de las más usadas ni llevar su DNI (`Clave::problemaPropia`) | `unitarios.php`, `web.php` e `integracion.php` |
| Cambiar la clave cierra las otras sesiones | Que quien robó una clave siga dentro después de cambiarla o reiniciarla | `EmpleadoServicio::guardarClave()` sube `empleado.version_clave` y revoca los `api_token` del empleado en la misma transacción; `frontend/public/index.php` cierra la sesión web cuya versión ya no coincide. La sesión desde la que el empleado cambió su clave sigue abierta | `api.php` (sección de clave) e `integracion.php` |
| Clave temporal del administrador | Que el administrador conozca la clave de su cajero y la auditoría no pruebe quién vendió | La clave puesta al registrar o reiniciar en **Personal** (y la de prueba) deja `debe_cambiar_clave = 'S'`: la web solo deja ir a **Mi clave** o salir, y la API responde 403 `DEBE_CAMBIAR_CLAVE` | `web.php`, `api.php` e `integracion.php` |
| Tablas de apoyo acotadas | Que `intento_login` y `api_token` crezcan sin límite | En cada ingreso correcto se borran los intentos de más de 30 días y los tokens vencidos | `integracion.php` |
| Cabeceras HTTP | Clickjacking y scripts inyectados | `Content-Security-Policy` con `script-src 'self'`, `object-src 'none'` y `base-uri 'self'`: solo corre `frontend/public/js/app.js`; un `<script>` inyectado en la página, un `onclick=` o un script de otro sitio no se ejecutan. También `X-Frame-Options: DENY`, `nosniff`, `Referrer-Policy` y, con HTTPS, `Strict-Transport-Security` de un año (`frontend/public/index.php`) | `web.php` y `seguridad.php` sección 5 |
| JavaScript seguro | Que el propio script convierta un dato en código (XSS por DOM) | `frontend/public/js/app.js` escribe siempre con `textContent`; no usa `innerHTML`, `eval` ni llama a otros sitios. Es mejora progresiva: sin JavaScript todo funciona igual y las reglas siguen en el servidor | `seguridad.php` sección 5 |
| Fotos de producto | Subir un archivo con código escondido o que el servidor lo ejecute | Solo el administrador (403 para cajero y almacenero, y `ProductoServicio::cambiarFoto` vuelve a revisar). `FotoProducto::guardar` acepta hasta 2 MB, revisa con `getimagesizefromstring` que sea JPEG, PNG, WebP o GIF y la **vuelve a dibujar** con GD como JPEG de 600 px: lo que no es imagen se pierde. El nombre del archivo lo pone el sistema (`{id}.jpg`), nunca el usuario. Cada cambio queda en la auditoría (`PRODUCTO_FOTO`) | `unitarios.php`, `web.php` (un texto con extensión .jpg da error; cajero y almacenero 403) e `integracion.php` |
| CSV sin fórmulas | Que Excel ejecute `=HIPERVINCULO(...)` escrito en un nombre | `Controller::enviarCsv()` antepone `'` a los textos que empiezan con `= + - @` | `web.php` revisa la descarga; la regla está en un solo método |
| Errores sin detalles | Mostrar rutas, usuario o clave de MySQL al visitante | `frontend/public/index.php` atrapa todo, responde 500/503 con un mensaje simple y guarda el detalle con `error_log()` | Revisión del código |
| `.env` fuera de GitHub | Publicar las claves de la base | `.env` y `respaldos/` en `.gitignore`; solo se sube `.env.example` | `seguridad.php` sección 4 |
| Aviso sin HTTPS | Que se entre desde otra PC por `http://` sin darse cuenta | El login muestra «Conexión sin cifrar» si no hay HTTPS y la dirección no es `localhost` (`AuthController::sinCifrar`) | `web.php` |
| Servidor | Que se descargue `.env` o se entre a MySQL desde internet | La raíz del sitio es `frontend/public/` (`deploy/`), HTTPS obligatorio con redirección desde http, y MySQL publicado solo en `127.0.0.1` (`docker-compose.yml`) | `php backend/scripts/verificar_instalacion.php` (claves de ejemplo, archivos en `frontend/public/`, usuario root, clave de prueba) |

## 2. Reglas que revisa `backend/tests/seguridad.php`

1. **Formularios y CSRF**: lee las rutas POST de `frontend/public/index.php`, revisa que el token se valide antes de ejecutarlas, que cada `<form method="post">` de `frontend/views/` tenga `campoCsrf($csrf)` y apunte a una ruta POST conocida, y que ningún formulario GET apunte a una ruta que cambia datos.
2. **Escapado**: cada `<?= ... ?>` de las vistas tiene que ser `e(...)`, un número (`(int)`, `getId...()`, `count()`) o un texto fijo. Si alguien escribe `<?= $producto->getNombreProducto() ?>` sin `e()`, la prueba falla y dice el archivo y la línea.
3. **SQL**: recorre con el analizador de PHP (`token_get_all`) todo `backend/models/` y `backend/config/`. Dentro de `prepare()` y `query()` solo se aceptan variables que son partes fijas del SQL armadas por el propio código (`$donde`, `$condicion`, `$sql`...) o números forzados con `(int)`. Los datos del usuario siempre van como parámetro `?`. También revisa que controladores y vistas no tengan SQL.
4. **Reglas generales**: `declare(strict_types=1)` en todos los archivos, ninguna propiedad `public`, `$_GET` y `$_POST` solo en `frontend/public/index.php` y ninguna clave escrita en el código.
5. **JavaScript**: las vistas solo cargan `<script src="/app.js" defer>` (ningún `<script>` pegado, `onclick=` ni `href="javascript:`); `frontend/public/js/app.js` no usa `innerHTML`, `outerHTML`, `insertAdjacentHTML`, `document.write`, `eval`, `new Function`, `fetch` ni direcciones de otros sitios; y la CSP de `frontend/public/index.php` tiene `script-src 'self'` sin `'unsafe-inline'`.

Se probó que la revisión sí detecta errores: al quitar a propósito un `campoCsrf()`, un `e()` y al pegar una variable dentro de un `prepare()`, las tres secciones fallaron.

## 3. Auditoría (bitácora de acciones)

La tabla `auditoria` (`backend/database/auditoria.sql`) guarda quién hizo cada cambio sensible, cuándo y en qué tienda. Se escribe **dentro de la misma transacción** que el cambio: si la operación falla, no queda registro falso.

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

`backend/scripts/respaldo.ps1` (Windows) o `backend/scripts/respaldo.sh` (Linux/macOS) sacan una copia completa (base + fotos) con `mysqldump --single-transaction` a la carpeta `respaldos/`, con Docker o sin Docker (Laragon o MySQL instalado, `RESPALDO_MODO`). La clave de MySQL nunca va en la línea de comandos: con Docker la lee MySQL dentro del contenedor y sin Docker va en un archivo temporal que solo lee quien corre el script. Se pueden cifrar, copiar fuera del servidor y programar a diario; la CI prueba la restauración en los dos modos. Los respaldos tienen DNIs y hashes de claves, por eso `respaldos/` está en `.gitignore`. Ver `docs/despliegue.md`.

## 5. Lo que queda fuera (para un sistema real)

- **HTTPS**: en el aula se usa `http://localhost`. En un servidor real se pone HTTPS (por ejemplo con Let's Encrypt, `docs/despliegue.md` sección 6) y la cookie pasa a `Secure` sola. Si las cajas de la tienda entran por la red local a una PC servidor, ver la sección 6.5 de esa guía.
- **Cambiar las claves de prueba**: los 9 empleados de `datos.sql` tienen `Mass2026!`. Al entrar por primera vez, el sistema les pide cambiarla en `?ruta=mi_clave` (migración `013_clave_y_sesiones.sql`). Para una tienda real, `backend/scripts/quitar_datos_demo.php` deja solo un administrador con clave temporal (`docs/despliegue.md`, sección 9).
- **Facturación electrónica SUNAT**: los comprobantes se numeran sin huecos, pero no se envían a SUNAT ni a un OSE.
