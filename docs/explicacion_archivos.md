# Qué hace cada archivo (para estudiar antes de explicar)

Lee cada archivo con esta guía al lado. Si alguna línea no la puedes contar con tus palabras, ábrela y pregunta antes de la entrega.

## Recorrido de un pedido

0. Antes de todo, `index.php` revisa la sesión: sin login manda a `?ruta=login`, y en un POST exige el token CSRF.
1. El navegador pide `http://localhost:8000/?ruta=comprobante&serie=B001&numero=1`.
2. `public/index.php` lee `ruta` y llama a `VentaController->comprobante('B001', '1')`.
3. El controlador valida la serie y el número, y le pide a `VentaRepository` la venta, su detalle y sus pagos. A los otros Repository les pide la tienda, el cajero, el cliente y los productos.
4. Los Repository devuelven objetos (`Venta`, `DetalleVenta`, `PagoEfectivo`...), sin HTML.
5. El controlador carga `views/ventas/comprobante.php`, que dibuja la boleta.

## Pantallas

| URL | Qué muestra |
|---|---|
| `/?ruta=productos&tienda=1` | Catálogo con el stock de la tienda elegida |
| `/?ruta=producto&codigo=7750000000011` o `&id=8` | Ficha de un producto |
| `/?ruta=stock` | Stock de cada producto en las 3 tiendas, con REPONER |
| `/?ruta=ventas&tienda=1` | Ventas de la tienda: comprobante, cajero, cliente, total, estado y si los pagos cuadran |
| `/?ruta=comprobante&serie=B001&numero=1` | Boleta o factura completa: cabecera, detalle, pagos y anulación |
| `/?ruta=tiendas` | Las 3 tiendas con distrito, cajas, series y personal con sus permisos |
| `/?ruta=login` | Entrar con DNI y clave. Sin sesión, cualquier pantalla lleva aquí |
| `/?ruta=caja` | Abrir o cerrar el turno del cajero; arqueo para el administrador |
| `/?ruta=venta_nueva` | Formulario de venta del cajero (productos, cliente, pagos) |
| `/?ruta=kardex&tienda=1` | Movimientos de stock de la tienda |
| `/?ruta=traslados` y `/?ruta=mermas` | Formularios e historial del almacenero |
| `/?ruta=producto_form` y `/?ruta=catalogo` | Administrador: crear y editar productos, categorías y marcas |
| `/?ruta=empleados` y `/?ruta=empleado&id=2` | Administrador: personal de su tienda y clave nueva |
| `/?ruta=clientes` y `/?ruta=cliente&id=1` | Cajero y administrador: clientes, sus compras y completar el documento |
| `/?ruta=reportes` y `/?ruta=reporte_csv&tipo=productos` | Administrador: reportes de venta y su descarga en CSV |
| `/?ruta=mi_clave` | Cambiar la clave propia |
| cualquier otra | 404 |

## Base de datos y configuración

| Archivo | Qué hace |
|---|---|
| `docker-compose.yml` | Levanta MySQL 8.4.11 en Docker en el puerto 3307. La primera vez ejecuta `schema.sql`, `datos.sql`, `boleta.sql` y `login.sql` (columna `clave_hash` con la clave de prueba `Mass2026!` en hash) y `seguridad.sql` (tabla `intento_login`). Los datos quedan en el volumen `mysql_datos`. |
| `.env.example` | Plantilla de la configuración. Se copia como `.env` (que no se sube) y se cambian las claves. |
| `config/Sesion.php` | Sesión con cookie `httponly` y `SameSite=Lax`. Cambia el id al entrar, guarda el token CSRF y el aviso de una sola vez. |
| `config/Database.php` | Crea una sola conexión PDO con los datos del `.env`. Activa excepciones en errores y consultas preparadas reales. |

## models/ (M · datos y reglas)

| Archivo | Qué hace |
|---|---|
| `Tienda.php` | Una fila de `tienda`: nombre, dirección, teléfono opcional, estado y distrito. |
| `TiendaRepository.php` | Lee de MySQL las tiendas, el nombre del distrito y las cajas. |
| `Caja.php` | Una caja registradora de una tienda (número 1 o 2). |
| `CajaTurno.php` | El turno de un cajero en una caja. Valida que un turno ABIERTO no tenga cierre y que uno CERRADO sí lo tenga. De aquí salen la caja y el cajero de cada venta. |
| `Cliente.php` | Cliente con DNI y/o RUC (al menos uno). `documentoParaComprobante()` decide qué se imprime: en factura va el RUC, igual que `vw_comprobante`. |
| `ClienteRepository.php` | Busca un cliente por id en MySQL. |
| `SerieComprobante.php` | Serie B001, F001... de cada tienda. `formatear(1)` da `B001-00000001` y `esFactura()` mira la letra. |
| `Venta.php` | Cabecera de la venta. `detalleCuadra()` comprueba que los importes sumen el total y `pagosCuadran()` que los pagos válidos sumen el total (en una venta anulada, que todos estén anulados). |
| `DetalleVenta.php` | Una línea de la venta. Rechaza un importe distinto de `ROUND(cantidad × precio, 2)`. |
| `Dinero.php` | Pasa montos a céntimos enteros para compararlos sin errores de float. |
| `AnulacionVenta.php` | Motivo, fecha y quién anuló la venta. |
| `pagos/PagoVenta.php` | Clase base `abstract` de un pago (tabla `pago_venta`). Cada hija dice su método y su `detalle()`. |
| `pagos/PagoEfectivo.php` | Hereda de PagoVenta. Monto recibido y vuelto, con vuelto entre 0 y lo recibido. |
| `pagos/PagoTarjeta.php` | Hereda de PagoVenta. Crédito o débito, marca y solo los últimos 4 dígitos. |
| `pagos/PagoSip.php` | Hereda de PagoVenta. Billetera Sip por QR o TARJETA_SIP y código de operación. |
| `VentaRepository.php` | Lee de MySQL series, turnos, ventas, detalle, pagos y anulaciones. Busca por serie + correlativo como `sp_boleta`, y une `pago_venta` con `efectivo`, `tarjeta` y `billetera_digital_sip` para crear la clase de cada pago. |
| `EmpleadoRepository.php` | Lee los empleados de MySQL y, con `match`, crea un Administrador, Cajero o Almacenero según `id_rol`. |
| `Producto.php` | Una fila de la tabla `producto`. El constructor rechaza datos que la base tampoco aceptaría (precio 0, código mal formado). Reglas: `esAlPeso()`, `estaActivo()`, `cantidadValida()` (kg con 3 decimales o unidades enteras) e `importe()` = cantidad × precio redondeado a 2. |
| `Stock.php` | Cuánto hay de un producto en una tienda (tabla `stock`). `necesitaReponer()` es verdadero cuando lo actual llega al mínimo; `hayStock()` dice si alcanza para vender una cantidad. |
| `ProductoRepository.php` | Lee de MySQL los productos, el stock por tienda y las unidades de medida. Busca por código o por id y devuelve `null` si no encuentra. Convierte los DECIMAL, que MySQL entrega como texto (`"4.50"`), a `float`. |
| `Usuario.php` | Clase base `abstract` de los roles (tabla `empleado`). Guarda lo común (nombres, DNI, tienda) y por defecto niega todos los permisos. El DNI solo se muestra oculto. |
| `Administrador.php` | Hereda de Usuario. Puede anular ventas, pero solo de su propia tienda. |
| `Cajero.php` | Hereda de Usuario. Es el único que puede vender. |
| `Almacenero.php` | Hereda de Usuario. Registra traslados y mermas. |
| `Cantidad.php` | Pasa cantidades a milésimas enteras (`1.250` kg = 1250) y calcula el importe redondeado como MySQL. |
| `ReglaNegocioException.php` | Error con un mensaje para el usuario ("No hay stock suficiente..."). |
| `Categoria.php`, `Marca.php`, `UnidadMedida.php` | Catálogo del producto. |
| `CatalogoRepository.php` | Lee categorías, marcas, unidades y tipos de movimiento. |
| `TipoMovimiento.php` | VENTA, ANULACION_VENTA, TRASLADO_SALIDA, TRASLADO_ENTRADA y MERMA, y si cada uno suma o resta. |
| `MovimientoStock.php` | Una fila del kardex. Exige que venga de una sola cosa: venta, traslado o merma. |
| `TrasladoMercaderia.php`, `Merma.php` | Cabeceras de traslado (origen distinto del destino) y de merma (motivo de una lista fija). |
| `InventarioRepository.php` | Lee el kardex, los traslados y las mermas de una tienda. |
| `CajaRepository.php` | Busca el turno abierto de un cajero y calcula el arqueo (como la consulta 19). |
| `servicios/Servicio.php` | Base `abstract` de los servicios: `enTransaccion()` (commit o rollback), lectura de líneas y `moverStock()`, que bloquea la fila con `FOR UPDATE`, no deja stock negativo y escribe el kardex. |
| `servicios/VentaServicio.php` | `registrar()`: cliente por DNI o RUC, siguiente número de la serie, detalle, stock y pagos con vuelto. `anular()`: solo el administrador de esa tienda; devuelve el stock y anula los pagos. |
| `servicios/InventarioServicio.php` | `trasladar()` y `registrarMerma()` del almacenero. |
| `servicios/CajaServicio.php` | `abrirTurno()` y `cerrarTurno()` del cajero. |
| `servicios/ProductoServicio.php` | Crear y editar productos (código, precio, unidad kg si es al peso), descontinuar, stock mínimo, categorías y marcas. |
| `servicios/EmpleadoServicio.php` | Alta, cambios y baja de personal de la tienda; clave nueva y cambio de la clave propia. |
| `servicios/ClienteServicio.php` | Agrega el DNI o RUC que le falta a un cliente (no cambia uno existente). |
| `ReporteRepository.php` | Consultas 10 a 16 de `consultas.sql` filtradas por tienda y fechas. |
| `IntentoLoginRepository.php` | Cuenta las claves erradas recientes y dice si el DNI o la IP deben esperar. |
| `Paginacion.php` | Calcula la página, el `LIMIT` y el `OFFSET` de una lista larga. |
| `Clave.php` | Regla de la clave (8 a 72 caracteres, letras y números) y su hash. |

## controllers/ (C · coordina)

| Archivo | Qué hace |
|---|---|
| `Controller.php` | Clase base `abstract` de los controladores: lee la tienda pedida (por defecto la del usuario), carga vistas con el usuario, el token CSRF y el aviso, redirige después de un POST y responde 403 o 404. |
| `ProductoController.php` | Catálogo, ficha y stock de las 3 tiendas. Valida el código o el id; si el producto no existe responde 404. No tiene HTML ni SQL. |
| `VentaController.php` | Lista de ventas por tienda y comprobante. Valida la serie (`^[BF][0-9]{3}$`) y el número (1 a 8 dígitos) antes de buscar. También calcula quién puede anular la venta, preguntando a cada empleado `puedeAnularVenta()`. |
| `TiendaController.php` | Junta cada tienda con su distrito, cajas, series y personal. |
| `AuthController.php` | Login con `password_verify()`. Si el DNI no existe igual compara contra un hash de relleno, para que tarde lo mismo. |
| `InventarioController.php` | Kardex, traslados y mermas. Solo el almacenero puede registrar. |
| `CajaController.php` | Panel de caja: abrir y cerrar turno, y arqueo para el administrador. |
| `EmpleadoController.php` | Personal de la tienda del administrador. |
| `ClienteController.php` | Lista paginada, búsqueda por documento, compras del cliente. |
| `ReporteController.php` | Reportes en pantalla y CSV; el rango por defecto son los últimos 30 días y como máximo un año. |

## views/ (V · muestra)

| Archivo | Qué hace |
|---|---|
| `layout_inicio.php` | Cabecera HTML, menú y estilos comunes. Define `e()` (escapa texto con `htmlspecialchars` para evitar XSS) y `soles()` (formato S/ 0.00). |
| `layout_fin.php` | Cierra el HTML que abrió el layout de inicio. |
| `productos/lista.php` | Tabla del catálogo con el stock de la tienda elegida y la etiqueta REPONER. Solo muestra. |
| `productos/detalle.php` | Ficha de un producto: precio, forma de venta, importe de ejemplo y stock en la tienda. |
| `productos/stock.php` | Tabla producto × tienda con el mínimo y REPONER. |
| `ventas/lista.php` | Ventas de la tienda elegida, con enlace a cada comprobante. |
| `ventas/comprobante.php` | La boleta o factura en los tres bloques de `sp_boleta`: cabecera, detalle y pagos, más la revisión y la anulación. |
| `tiendas/lista.php` | Una tarjeta por tienda con su personal. El DNI se muestra oculto. |
| `parciales/selector_tienda.php` | Los enlaces para cambiar de tienda; lo usan el catálogo y las ventas. |
| `auth/login.php` | Formulario de DNI y clave. |
| `ventas/nueva.php` | Formulario de venta con líneas de producto y pagos. |
| `parciales/lineas_producto.php` | Filas de producto y cantidad que usan venta, traslado y merma. |
| `inventario/kardex.php`, `traslados.php`, `mermas.php` | Tablas e historial de inventario. |
| `caja/panel.php` | Turno abierto, botones y arqueo. |
| `productos/formulario.php`, `productos/catalogo.php` | Formulario de producto; categorías, marcas y unidades. |
| `empleados/lista.php`, `empleados/formulario.php` | Personal y su edición. |
| `clientes/lista.php`, `clientes/detalle.php` | Clientes y sus compras. |
| `reportes/ventas.php` | Tarjetas de resumen y tablas de reporte. |
| `auth/mi_clave.php` | Cambiar la clave propia. |
| `parciales/paginacion.php` | Enlaces Anterior / Siguiente. |
| `errores/403.php` | "No tienes permiso". |
| `errores/404.php` | Página "No encontrado" con un mensaje fijo, que no copia nada de la URL. |
| `errores/500.php` | Página de error cuando la base no responde (503) o algo falla (500). No muestra el detalle técnico. |

## public/ (lo único que abre el navegador)

| Archivo | Qué hace |
|---|---|
| `index.php` | Punto de entrada. Envía cabeceras de seguridad, abre la sesión y busca al usuario. Los POST (login, venta, anular, traslado, merma, turno) exigen login y token CSRF. Abre la conexión a MySQL (si falla responde 503), crea los Repository, lee `?ruta=` (solo strings) y con un `switch` llama al método del controlador. Cualquier otra ruta da 404, y un error inesperado da 500 sin mostrar la traza. |
| `poo_producto.php` | Prueba del paso 1: un producto por unidad, uno al peso y un stock por debajo del mínimo. |
| `poo_roles.php` | Prueba del paso 2: tres empleados de Cayma. El mismo método `puedeAnularVenta()` responde distinto según la clase (polimorfismo). |
| `poo_ventas.php` | Prueba de ventas y pagos: la factura F001-1 cuadra con sus dos pagos y no con uno solo, y cinco datos inválidos se rechazan. |
| `poo_repository.php` | Prueba del paso 3: busca un código que existe y uno que no (`null`), y lista lo que hay que reponer. |

## Conceptos que te pueden preguntar

- **Clase y objeto:** `Producto` es el molde; el yogurt de S/ 7.90 es un objeto hecho con ese molde.
- **private:** nadie cambia la propiedad desde afuera; solo se lee con `get...()`.
- **Herencia:** `Cajero extends Usuario` reutiliza lo común y cambia solo lo suyo.
- **abstract:** no se puede crear un `Usuario` sin rol ni un `PagoVenta` sin método.
- **Polimorfismo:** la boleta llama `$pago->detalle()` sin saber si es efectivo, tarjeta o Sip; cada clase responde a su manera.
- **Repository:** al pasar de listas fijas a MySQL solo cambiaron los Repository; controladores y vistas quedaron iguales.
- **Consulta preparada:** el SQL va con marcadores (`WHERE serie = :serie`) y el valor se manda aparte con `execute()`, así un texto como `' OR 1=1` no cambia la consulta (inyección SQL).
- **Variables de entorno:** las claves viven en `.env`, fuera del código y fuera de GitHub.
- **strict_types:** PHP no convierte tipos por su cuenta; si pasas un texto donde va un número, falla.
- **htmlspecialchars:** convierte `<` en `&lt;` para que un `<script>` se muestre como texto y no se ejecute.
- **Transacción:** `beginTransaction()` … `commit()`; si algo falla, `rollBack()` deja la base como estaba. Una venta sin stock no deja ni la cabecera.
- **FOR UPDATE:** bloquea la fila mientras dura la transacción, así dos cajeros no toman el mismo número de boleta ni venden el mismo último yogurt.
- **password_hash / password_verify:** en la base solo va el hash; la clave nunca se guarda.
- **CSRF:** cada formulario lleva un token secreto de la sesión; un sitio ajeno no puede enviar una venta en tu nombre.
- **LIMIT y OFFSET:** traen una página de filas (por ejemplo 25 desde la 26) en vez de toda la tabla.
- **Bloqueo por intentos:** frena a quien prueba claves al azar; el mensaje es el mismo exista o no el DNI.
