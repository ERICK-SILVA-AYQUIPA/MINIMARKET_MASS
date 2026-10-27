# Bitácora de uso de IA · Sesión 4 (POO y MVC)

Herramienta: Claude (asistente de código). Fechas: 23/10/2026 (código) y 25/10/2026 (revisión y auditoría).

> Lo marcado con **[Grupo]** solo lo puede confirmar el grupo: la IA no sabe qué escribió o corrigió cada integrante a mano.

## Contexto común de los briefs

Todos los pasos usaron el mismo bloque de CONTEXTO y SEGURIDAD; solo cambian OBJETIVO, ENTRADAS Y SALIDAS, REGLAS y DÓNDE VA.

- **CONTEXTO:** Proyecto PHP 8.3, arquitectura MVC manual sin framework. MySQL en el puerto 3307, pero todavía NO se conecta: hoy los datos van en una lista fija dentro del Repository. Carpetas: models/, controllers/, views/, public/.
- **SEGURIDAD:** declare(strict_types=1), tipos en parámetros y retornos, propiedades private, ningún dato sensible en el código.
- **Cierre:** Explica cada línea del resultado y dime qué supuestos hiciste.

## Brief por paso

Los pasos 2 a 6 se escribieron con la regla anterior; abajo está cada brief armado con los datos que se usaron en el código entregado. **[Grupo]** confirmen que coincide con lo que pegó cada uno.

### Paso 1 · Clase de la entidad (`models/Producto.php`, `models/Stock.php`)
- **OBJETIVO:** Una clase Producto que represente una fila de mi tabla producto, y una clase Stock para la tabla stock.
- **ENTRADAS Y SALIDAS:** El constructor recibe las columnas de mi tabla: id_producto INT UNSIGNED, nombre_producto VARCHAR(100), codigo_barras VARCHAR(14) NULL, precio_venta DECIMAL(10,2), se_vende_por_peso CHAR(1), estado VARCHAR(10), id_categoria SMALLINT, id_marca SMALLINT, id_unidad TINYINT. Stock: id_tienda, id_producto, cantidad_actual DECIMAL(10,3), stock_minimo DECIMAL(10,3). Un método get por propiedad y métodos para la regla de negocio.
- **REGLAS:** Un producto al peso se vende en kg con hasta 3 decimales y no tiene código de barras; por unidad solo cantidades enteras. El importe es ROUND(cantidad * precio_venta, 2). Se repone cuando cantidad_actual <= stock_minimo.
- **Prueba:** `public/poo_producto.php`.

### Paso 2 · Roles con herencia (`models/Usuario.php`, `Administrador.php`, `Cajero.php`, `Almacenero.php`)
- **OBJETIVO:** Una clase base Usuario (tabla empleado) y una clase hija por cada rol de mi tabla rol.
- **ENTRADAS Y SALIDAS:** id_empleado, nombres VARCHAR(60), apellidos VARCHAR(60), dni CHAR(8), estado ('ACTIVO'/'INACTIVO'), id_tienda. Roles: 1 ADMINISTRADOR, 2 CAJERO, 3 ALMACENERO.
- **REGLAS:** Solo el CAJERO vende; solo el ADMINISTRADOR anula, y solo ventas de su tienda; el ALMACENERO registra traslados y mermas. Un empleado INACTIVO no puede nada. El DNI no se muestra completo.
- **Prueba:** `public/poo_roles.php`.

### Paso 3 · Repository (`models/ProductoRepository.php`)
- **OBJETIVO:** Un ProductoRepository que sea la única clase que sabe de dónde salen los datos.
- **ENTRADAS Y SALIDAS:** todos(), buscarPorCodigo(string), buscarPorId(int), stockEnTienda(idProducto, idTienda), tiendas(). Devuelve objetos o null, nunca HTML.
- **REGLAS:** Los 12 productos, las 3 tiendas y las 36 filas de stock copiados de `database/datos.sql`.
- **Prueba:** `public/poo_repository.php`.

### Paso 4 · Controlador (`controllers/ProductoController.php`)
- **OBJETIVO:** Un ProductoController que reciba el pedido, valide la entrada, pida datos al Repository y elija la vista.
- **ENTRADAS Y SALIDAS:** lista(tienda), detalle(codigo, id, tienda), noEncontrado(mensaje). Sin HTML ni SQL.
- **REGLAS:** El código de barras se valida con `^[0-9]{8,14}$`; la tienda debe existir, si no se usa Mass Cayma; si el producto no existe, 404.

### Paso 5 · Vistas (`views/`)
- **OBJETIVO:** Vistas que solo muestren: catálogo, ficha de producto y 404, con un layout común.
- **REGLAS:** Cero consultas y cero reglas en la vista; todo texto pasa por htmlspecialchars(..., ENT_QUOTES, 'UTF-8').

### Paso 6 · Punto de entrada (`public/index.php`)
- **OBJETIVO:** Un index.php que lea `?ruta=` y llame al controlador.
- **REGLAS:** Solo acepta las rutas `productos` y `producto` (switch con default 404) y solo valores string de `$_GET`.

### Paso 7 · Sistema completo de las 3 tiendas (25/10/2026)
- **OBJETIVO:** Ampliar el MVC a tiendas, clientes, ventas con detalle, pagos y comprobante, con las mismas reglas de la guía y sin conectar todavía a MySQL.
- **ENTRADAS Y SALIDAS:** Las columnas exactas de `tienda`, `caja`, `caja_turno`, `cliente`, `serie_comprobante`, `venta`, `detalle_venta`, `anulacion_venta`, `pago_venta`, `efectivo`, `tarjeta` y `billetera_digital_sip` de `schema.sql`. Las filas son las de `datos.sql`.
- **REGLAS:** Los mismos CHECK de la tabla en cada constructor. Los importes y los pagos válidos suman el total, y en una venta anulada todos los pagos están anulados. El documento del cliente se imprime como en `vw_comprobante`. El comprobante se busca por serie + correlativo como en `sp_boleta`. Solo el ADMINISTRADOR activo de la tienda de la venta puede anularla.
- **Pantallas:** `?ruta=stock`, `?ruta=ventas&tienda=`, `?ruta=comprobante&serie=&numero=` y `?ruta=tiendas`, además del catálogo y la ficha.
- **Prueba:** `public/poo_ventas.php` y las 14 boletas y facturas de `datos.sql`.
- **Cambio al código anterior:** las tiendas pasaron de `ProductoRepository` a `TiendaRepository`, y `leerTienda()` y `noEncontrado()` pasaron a la clase base `Controller`.

### Paso 8 · Conexión a MySQL 8.4.11 en Docker (25/10/2026)
- **OBJETIVO:** Que los Repository lean de MySQL 8.4.11 en Docker en vez de listas fijas, sin cambiar controladores ni vistas.
- **ENTRADAS Y SALIDAS:** `docker-compose.yml` con `mysql:8.4.11` que ejecuta `schema.sql`, `datos.sql` y `boleta (1).sql` al crear el contenedor. `config/Database.php` con PDO, que lee `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER` y `DB_PASSWORD` del entorno o de `.env`. Los Repository mantienen los mismos métodos.
- **SEGURIDAD:** consultas preparadas con marcadores y `ATTR_EMULATE_PREPARES = false`. El `.env` está en `.gitignore` y en el repo solo va `.env.example`. Si la base falla, el navegador ve 503 o 500 sin detalle técnico.
- **Prueba:** contenedor creado desde cero con `docker compose down -v` y `up -d`; todas las pantallas contra la base real.

### Paso 9 · Backend completo: login, ventas, anulaciones, inventario y caja (26/10/2026)
- **OBJETIVO:** Que cada tabla del esquema tenga su clase y su Repository, y que la aplicación escriba en MySQL: registrar y anular ventas, traslados, mermas, kardex y turnos de caja, con login por rol.
- **ENTRADAS Y SALIDAS:** `database/login.sql` (columna `clave_hash`). Servicios en `models/servicios/` que reciben el `Usuario` y las líneas del formulario y devuelven el comprobante creado o lanzan `ReglaNegocioException` con un mensaje para la pantalla. Nuevas rutas `login`, `caja`, `venta_nueva`, `kardex`, `traslados` y `mermas`.
- **SEGURIDAD:** login con `password_hash`/`password_verify`, cookie `httponly` y `SameSite=Lax`, id de sesión nuevo al entrar, token CSRF en cada POST, 403 si el rol no corresponde. Cada escritura en una transacción con `FOR UPDATE` (primero la serie, luego el stock por id de producto, para no trabar dos ventas).
- **Prueba:** `php tests/integracion.php` contra la base recién creada (40 correctas, 0 fallas) y las consultas 20 a 28 de `consultas.sql` en 0 filas después de vender, anular, trasladar y registrar mermas desde el navegador.

### Paso 10 · Mantenimiento, clientes, reportes y seguridad del ingreso (26/10/2026)
- **OBJETIVO:** Que el administrador mantenga el catálogo, el stock mínimo y su personal; que cajero y administrador consulten clientes; reportes de venta; listas con páginas; y un ingreso más seguro. Sin proveedores, por decisión del grupo.
- **ENTRADAS Y SALIDAS:** servicios `ProductoServicio`, `EmpleadoServicio` y `ClienteServicio`; `ReporteRepository` con las consultas 10 a 16 filtradas por tienda y fecha; `Paginacion`; `database/seguridad.sql` (tabla `intento_login`).
- **SEGURIDAD:** permisos nuevos en `Usuario` (`puedeAdministrarCatalogo()`, `puedeAdministrarTienda()`); bloqueo de 15 minutos tras 5 claves erradas por DNI o 20 por IP; sesión que vence a los 30 minutos sin uso; claves de 8 a 72 caracteres con letras y números; cabecera `Content-Security-Policy` sin scripts; el CSV antepone `'` a un texto que empieza con `=`, `+`, `-` o `@` (Excel no lo ejecuta como fórmula).
- **Prueba:** `php tests/integracion.php` con la base recién creada: 72 correctas, 0 fallas.

### Paso 11 · Conteo físico, tiendas y cajas (27/10/2026)
- **OBJETIVO:** Cerrar lo pendiente sin proveedores: ajustar el stock con un conteo físico y mantener tiendas, cajas, series y distritos.
- **ENTRADAS Y SALIDAS:** `database/ajustes.sql` (migración); `InventarioServicio::ajustarPorConteo()` recibe lo contado por producto (vacío = no contado, 0 = no hay) y devuelve el id del ajuste y cuántos productos cambiaron. `TiendaServicio` abre una tienda con su caja 1, sus series, stock en 0 y su administrador; edita la tienda propia; agrega y activa o desactiva cajas; agrega distritos.
- **SEGURIDAD:** permisos `puedeAjustarStock()` (almacenero o administrador de esa tienda) y `puedeAbrirTiendas()` (administrador). Las filas de stock se bloquean en orden de producto, como en las ventas. Las series nuevas se calculan con `FOR UPDATE` sobre toda la cadena.
- **Prueba:** `php tests/integracion.php` con la base recién creada: 91 correctas, 0 fallas.

### Paso 12 · Panel de inicio, interfaz, pruebas unitarias y CI (27/10/2026)
- **OBJETIVO:** Que cada empleado vea al entrar lo que necesita de SU tienda, pulir la interfaz y revisar el proyecto automáticamente en cada cambio.
- **ENTRADAS Y SALIDAS:** `PanelRepository` devuelve los productos en su mínimo (los más urgentes primero), los turnos abiertos con lo cobrado en efectivo y el total emitido de cada uno de los últimos 14 días (los días sin ventas en 0). `PanelController::inicio()` arma hoy contra ayer con `ReporteRepository::resumen()`. `tests/unitarios.php` prueba Dinero, Cantidad, Paginacion, Clave, Producto, Stock y los permisos de cada rol sin base. `.github/workflows/ci.yml` corre `php -l`, las unitarias y las de integración contra un MySQL nuevo.
- **SEGURIDAD:** el panel solo lee la tienda del usuario (no acepta `?tienda=`). El gráfico es HTML y CSS, sin JavaScript, así la `Content-Security-Policy` sigue sin permitir scripts. La ruta del menú activo solo se compara, no se imprime. En CI el `.env` sale de `.env.example` y vive solo en el runner.
- **Prueba:** `php tests/unitarios.php`: 51 correctas, 0 fallas. `php tests/integracion.php` con la base recién creada: 91 correctas, 0 fallas.

### Paso 13 · Revisión completa de coherencia y pruebas web (27/10/2026)
- **OBJETIVO:** Revisar cada controlador, servicio y vista contra el esquema y la documentación, y dejar el sistema coherente de punta a punta.
- **ENTRADAS Y SALIDAS:** `tests/web.php` levanta su propio `php -S`, entra como Rosa, Luis, Carmen y Jorge y revisa qué responde cada pantalla y cada formulario. No guarda datos: todo lo que envía es rechazado a propósito.
- **SEGURIDAD:** todos los POST que cambian datos revisan el permiso en el controlador (403) además del servicio. Se agregaron topes de las columnas DECIMAL. El CSV cubre también tabulador y retorno. Un POST a una ruta de lectura responde 405 (antes salía 404).
- **Prueba:** unitarias 51, web 93 e integración 96 correctas, 0 fallas, con la base recién creada.

## Supuestos que hizo la IA y cómo los revisamos

Revisados el 25/10/2026 contra los .sql del repositorio.

| Supuesto | Por qué | Revisado |
|---|---|---|
| No se calcula IGV | `database/boleta (1).sql`, línea 12: "el modelo no guarda subtotales ni impuestos aparte" | ☑ |
| `Usuario` representa la tabla `empleado` | `schema.sql` no tiene tabla usuario; `empleado.dni` tiene el comentario "con el DNI ingresa al sistema" | ☑ |
| El stock va en una clase `Stock` aparte | La tabla `producto` no tiene columna de stock; existe la tabla `stock` por tienda | ☑ |
| Anular solo ADMINISTRADOR de la misma tienda | Consulta 27 de `consultas.sql` marca como incoherente la anulación hecha por alguien que no es administrador de su tienda | ☑ |
| Vender solo CAJERO | Consulta 25 de `consultas.sql` marca las ventas hechas por un empleado que no es cajero | ☑ |
| Tienda por defecto: Mass Cayma (id 1) | `datos.sql`: id_tienda 1 = 'Mass Cayma'. Elegirla como defecto es decisión del grupo **[Grupo]** | ☑ |
| `se_vende_por_peso` y `estado` se guardan como el texto de la columna ('S'/'N', 'ACTIVO') | `schema.sql`: CHAR(1) con CHECK IN ('S','N') y VARCHAR(13) con CHECK IN ('ACTIVO','DESCONTINUADO') | ☑ |
| Cada subtipo de pago es una clase hija de `PagoVenta` | `efectivo`, `tarjeta` y `billetera_digital_sip` tienen como llave `id_pago`, que es foránea a `pago_venta` | ☑ |
| El id de método de pago va fijo en cada clase (1, 2, 3) | `datos.sql`: 1 EFECTIVO, 2 TARJETA, 3 BILLETERA DIGITAL SIP | ☑ |
| Boleta o factura según la letra de la serie | `tipo_comprobante.letra_serie` es B o F, y `serie_comprobante` tiene un CHECK `^[BF][0-9]{3}$` | ☑ |
| Sin cliente se imprime "CLIENTE VARIOS" | Rama `ELSE` de la columna `cliente` de `vw_comprobante` | ☑ |
| Las fechas van como texto `'AAAA-MM-DD HH:MM:SS'` | Es como PDO devuelve un DATETIME; se puede cambiar a `DateTimeImmutable` en la sesión 5 **[Grupo]** | ☑ |
| Los montos se comparan en céntimos | Un float no suma exacto (0.1 + 0.2); DECIMAL(10,2) sí | ☑ |
| Los DECIMAL se convierten a `float` en el Repository | PDO con MySQL entrega DECIMAL como texto (`"4.50"`); comprobado contra el contenedor | ☑ |
| Puerto 3307 en la PC | Es el que ya usaba el README para Laragon; se cambia con `DB_PORT` **[Grupo]** | ☑ |
| La aplicación entra con un usuario propio (`mass_app`), no con root | Lo crea el contenedor con `MYSQL_USER`; tiene permisos solo sobre `minimarket_mass` | ☑ |
| La clave va en una columna nueva `empleado.clave_hash` | El esquema no tenía dónde guardarla; se agrega con `login.sql` sin tocar `schema.sql` **[Grupo]** | ☑ |
| Clave de prueba `Mass2026!` para los 9 empleados | Solo para el aula; en la base va el hash bcrypt **[Grupo]** | ☑ |
| El cajero vende solo con un turno ABIERTO de su tienda, y la venta toma la caja de ese turno | `venta.id_caja_turno` es obligatorio; consulta 24 revisa que caja y venta sean de la misma tienda | ☑ |
| Tarjeta y Sip cobran el monto escrito; el efectivo cubre el resto y calcula el vuelto | `efectivo.monto_recibido` y `vuelto`; consulta 22 exige que los pagos sumen el total | ☑ |
| Una factura exige RUC; una boleta acepta DNI o nada (CLIENTE VARIOS) | `vw_comprobante` imprime el RUC en la factura | ☑ |
| Anular devuelve el stock con movimientos ANULACION_VENTA y deja los pagos ANULADO | Consultas 21 y 23 de `consultas.sql` | ☑ |
| Un traslado deja dos movimientos: salida en el origen y entrada en el destino | `tipo_movimiento` 3 y 4 de `datos.sql` | ☑ |
| Cualquier administrador mantiene el catálogo; el personal, el mínimo y los reportes, solo el de esa tienda | El precio es único para las 3 tiendas (`schema.sql`, tabla `producto`) **[Grupo]** | ☑ |
| Un producto nuevo nace con stock 0 en las 3 tiendas | Sin proveedores no hay cómo ingresar stock; la fila existe para fijar su mínimo | ☑ |
| Un producto por unidad necesita código de barras; uno al peso usa la unidad kg | Comentario de `codigo_barras` en `schema.sql` ("opcional (productos al peso)") y datos de `datos.sql` **[Grupo]** | ☑ |
| La forma de venta (al peso o por unidad) no se cambia al editar | El stock y las ventas guardadas dependen de ella | ☑ |
| Del cliente solo se completa el documento que falta | `vw_comprobante` lee el documento en vivo: cambiarlo cambiaría comprobantes pasados | ☑ |
| El administrador no cambia su propio rol ni se desactiva | Así la tienda nunca se queda sin administrador | ☑ |
| El ajuste es una tabla nueva con su propio origen en el kardex, no una merma | Una merma solo resta; un conteo puede sumar o restar. Se agrega con migración (`ajustes.sql`) sin tocar `schema.sql` **[Grupo]** | ☑ |
| Cualquier administrador abre una tienda nueva, que nace con su propio administrador | No hay un rol de gerente de la cadena en `rol` **[Grupo]** | ☑ |
| La serie nueva es la siguiente libre de toda la cadena (B004, F004) | `datos.sql` ya usa B001 a B003 sin repetir entre tiendas | ☑ |
| Una tienda INACTIVA no vende, no abre caja y no recibe traslados, pero sí puede enviar su mercadería | Al cerrar una tienda hay que sacar su stock | ☑ |

## Qué corregimos del código generado

- 24/10/2026: el mensaje de commit pasaba de 70 caracteres; se acortó a `feat: agregar estructura MVC de la sesion 4`.
- 25/10/2026, auditoría (abajo): no hubo hallazgos reales, así que el código PHP no cambió.
- 25/10/2026, paso 8: los Repository dejaron las listas fijas y ahora usan PDO. Reciben la conexión en el constructor, así que `index.php`, `poo_repository.php` y `poo_ventas.php` se ajustaron para pasarla. Ningún controlador ni vista existente cambió; solo se agregó la vista `errores/500.php`.
- 27/10/2026, paso 13, lo que encontró la revisión:
  - Varios formularios (producto, estado, mínimo, categoría, marca, tienda, cajas, distrito, personal, cliente y anular venta) dejaban que otro rol llegara al servicio. El servicio igual lo rechazaba, pero la respuesta era 303 o 422 en vez de 403. Ahora el controlador responde 403 antes.
  - Un traslado o un conteo que dejaba el stock por encima de 9 999 999.999, o una venta de más de S/ 99 999 999.99, daba error 500 de MySQL. Ahora sale un mensaje claro.
  - El POST a una ruta de lectura respondía 404, porque la página de error pisaba el 405.
  - El mínimo "0.000" se rechazaba aunque "0" sí pasaba.
  - El traslado ofrecía tiendas inactivas como destino.
  - Algunos mensajes no tenían tilde ("valido", "pagina") y otros decían "las 3 tiendas" aunque ya se pueden abrir más.
- 27/10/2026, paso 11: la prueba de "no se desactiva una caja con turno abierto" usaba la caja 2, pero el turno abierto de Luis está en la caja 1 (`datos.sql`); se corrigió la prueba, no el código. Se quitó `VentaRepository::ventasDeTienda()`, que ya nadie usaba desde la paginación.
- 26/10/2026, paso 10: `producto.estado` era `VARCHAR(10)` pero `'DESCONTINUADO'` tiene 13 letras, así que nunca se podía descontinuar un producto (MySQL respondía "Data too long"). Se cambió a `VARCHAR(13)` en `schema.sql` y en `diseno_bd.md`. La prueba del reporte comparaba solo el 17/10; el total de S/ 323.00 es del 17 al 19/10.
- 26/10/2026, paso 9: `Catalogo.php` tenía varias clases en un archivo; se separó en una clase por archivo como pide la guía. El layout se podía incluir dos veces y redeclarar `e()`; se protegió con `function_exists`. La prueba fallaba al correrla dos veces (código Sip repetido); ahora se detiene si la base no está recién creada.
- 25/10/2026, paso 7: el `ProductoController` ya no recibe las tiendas de `ProductoRepository`, ahora vienen de `TiendaRepository`. `poo_repository.php` se ajustó a ese cambio.
- **[Grupo]** Anoten aquí lo que cada integrante cambió a mano al revisar su parte. Si nadie cambió nada, escriban "sin cambios manuales".

## Auditoría de seguridad (25/10/2026)

Prompt de auditor usado:

> Actúa como auditor de seguridad de PHP 8.3. Revisa estos archivos MVC y reporta solo problemas reales, con archivo y línea: (1) XSS: datos impresos sin htmlspecialchars(..., ENT_QUOTES, 'UTF-8'); (2) datos de la URL que se ejecutan o usan sin validar; (3) propiedades public; (4) archivos sin declare(strict_types=1) o funciones sin tipos en parámetros y retornos; (5) contraseñas, claves o datos reales en el código o en los .sql. Para cada hallazgo di cómo reproducirlo.

| Revisión | Resultado |
|---|---|
| XSS | Todo texto en `views/` y `public/poo_*.php` pasa por `e()` o `$e()`. Lo que se imprime sin escapar son enteros (`$idTienda`, `count()`, `getIdRol()`), números ya formateados o textos fijos. Probado: `?ruta=<script>…`, `?tienda=<script>` y `?ruta=producto&codigo=<b>` no devuelven ninguna etiqueta inyectada. |
| Datos de la URL | `index.php` solo lee strings (`?ruta[]=x` se ignora) y solo ejecuta `productos` y `producto`; lo demás da 404. El código se valida con regex, el id con `ctype_digit` y la tienda contra la lista. Probado: `id=-1`, `id=99999999999999999999999` y `tienda=1e1` no fallan. |
| Encapsulamiento | Ninguna propiedad `public`: todas son `private`. El DNI no tiene getter, solo `dniOculto()`. |
| Tipos | Todos los archivos PHP con código tienen `declare(strict_types=1)` y tipos en parámetros y retornos. |
| Secretos | Sin contraseñas ni claves. Los DNI (`70000001`…), códigos y precios son los ficticios de `datos.sql`. |
| Archivos fuera de `public/` | `models/`, `controllers/` y `views/` no se pueden abrir desde el navegador, porque el servidor sirve solo `public/`. |

Para decidir **[Grupo]**: las páginas de prueba `public/poo_*.php` se pueden abrir desde el navegador. Sirven para la sesión, pero en una versión real se quitarían de `public/`.

## Pendientes del diseño que todavía no tienen pantalla

Desde el paso 9 ya tienen pantalla los traslados, las mermas, el kardex, el arqueo de caja, las ventas y las anulaciones. Desde el paso 10, el mantenimiento de productos, categorías, marcas, stock mínimo, empleados y claves, los clientes y los reportes.

Desde el paso 11, el ajuste por conteo físico y el mantenimiento de tiendas, cajas, series y distritos. Desde el paso 12, el panel de inicio con indicadores.

**[Grupo]** decidan si se agregan:
- **Ingreso de mercadería de proveedores:** quedó fuera por decisión del grupo. Mientras tanto, un producto nuevo entra a una tienda con un **conteo físico** (motivo CONTEO_FISICO).
- El diagrama ER (`docs/diagrama-er.png` y el Mermaid de `docs/diseno_bd.md`) ya tiene las 2 tablas de apoyo (`intento_login`, `ajuste_inventario`). El informe en Word (`final.docx`) trata los 5 diagramas UML y no se tocó: si el docente pide la base de datos en el informe, agreguen allí el ER nuevo.

## Verificación

- `php -l` sin errores en todos los archivos.
- `php -S localhost:8000 -t public`: catálogo 200, ficha por código y por id 200, código inexistente 404, ruta desconocida 404, `<script>` en la URL no se ejecuta.
- 25/10/2026, paso 7: 22 URLs probadas. Las 6 pantallas y las 4 páginas de prueba responden 200. Serie inválida, `<script>` como serie, número de 9 dígitos y comprobante inexistente responden 404. Las 14 ventas de `datos.sql` cuadran detalle y pagos, y B001-00000002 sale ANULADA con sus pagos anulados. Total emitido de Cayma: S/ 323.00.
- 27/10/2026, paso 13: `tests/web.php` da 93 correctas. Por rol: Luis recibe 403 en 17 formularios de administrador o almacenero, Carmen en 4 de caja y clientes, Rosa en vender y trasladar, y Jorge al anular una venta de Cayma. Un nombre con `<b>` vuelve escapado. Consultas 20 a 28 en 0 filas.
- 27/10/2026, paso 12: después de entrar, Rosa, Luis, Carmen y Miguel llegan a `?ruta=inicio` (200) con el menú "Inicio" resaltado. Luis ve su turno 5 abierto y Miguel el aviso de abrir turno. El gráfico tiene 14 barras y el total de Cayma del 17 al 19/10 es S/ 323.00. Sin sesión, `/` lleva al login. Revisado en 1200 px y 390 px (celular). En `views/` todo lo impreso pasa por `e()` o es un entero o un texto fijo.
- 27/10/2026, paso 11: con Rosa, tiendas, mi tienda, tienda nueva y conteo responden 200. Un conteo con texto da 422; uno válido aparece en el kardex como "Ajuste". Un teléfono con letras da 422. Rosa agrega una caja, un distrito y abre "Mass Socabaya" con B005/F005; su administradora entra y ve sus series. Luis recibe 403 en mi tienda, tienda nueva, agregar caja y conteo; Carmen ve la hoja de conteo con los 13 productos. Consultas 20 a 28 en 0 filas.
- 26/10/2026, paso 10: con Rosa, las 19 pantallas nuevas o cambiadas responden 200 (empleado de otra tienda 404). Crear un producto sin código da 422 y el nombre con `<b>` sale escapado. Luis recibe 403 en personal, reportes, CSV y formulario de producto, y su POST de producto o empleado da 403. Carmen recibe 403 en clientes. Un cajero nuevo entra y cambia su clave. Al sexto intento con clave errada el login responde 429. El CSV abre con tildes. Consultas 20 a 28 en 0 filas y `tests/integracion.php` 72 correctas, 0 fallas.
- 26/10/2026, paso 9: sin sesión toda pantalla lleva al login; clave errada 401; POST sin token 403. Luis vende B001-7 y una venta sin stock responde 422 sin guardar nada. Luis no puede trasladar (403) ni anular; Rosa anula B001-7 y el stock vuelve. Carmen traslada y registra una merma; no puede abrir caja (403). Miguel abre y cierra su turno. Las consultas 20 a 28 siguen en 0 filas y `tests/integracion.php` da 40 correctas, 0 fallas.
- 25/10/2026, paso 8, contra MySQL 8.4.11 en Docker: las mismas pantallas responden 200 y dan los mismos resultados (14 comprobantes cuadran, S/ 323.00, 2 casos REPONER). Al cambiar un stock en MySQL, la página lo refleja al instante. `' OR 1=1--` en la serie y en el código devuelve 404, y llamado directo al Repository devuelve `null`. Con el contenedor detenido, la página responde 503 sin mostrar usuario ni host.
