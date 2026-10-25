# Qué hace cada archivo (para estudiar antes de explicar)

Lee cada archivo con esta guía al lado. Si alguna línea no la puedes contar con tus palabras, ábrela y pregunta antes de la entrega.

## Recorrido de un pedido

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
| cualquier otra | 404 |

## Base de datos y configuración

| Archivo | Qué hace |
|---|---|
| `docker-compose.yml` | Levanta MySQL 8.4.11 en Docker en el puerto 3307. La primera vez ejecuta `schema.sql`, `datos.sql` y `boleta.sql`. Los datos quedan en el volumen `mysql_datos`. |
| `.env.example` | Plantilla de la configuración. Se copia como `.env` (que no se sube) y se cambian las claves. |
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

## controllers/ (C · coordina)

| Archivo | Qué hace |
|---|---|
| `Controller.php` | Clase base `abstract` de los controladores: lee la tienda pedida (si no existe usa Mass Cayma) y responde 404. |
| `ProductoController.php` | Catálogo, ficha y stock de las 3 tiendas. Valida el código o el id; si el producto no existe responde 404. No tiene HTML ni SQL. |
| `VentaController.php` | Lista de ventas por tienda y comprobante. Valida la serie (`^[BF][0-9]{3}$`) y el número (1 a 8 dígitos) antes de buscar. También calcula quién puede anular la venta, preguntando a cada empleado `puedeAnularVenta()`. |
| `TiendaController.php` | Junta cada tienda con su distrito, cajas, series y personal. |

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
| `errores/404.php` | Página "No encontrado" con un mensaje fijo, que no copia nada de la URL. |
| `errores/500.php` | Página de error cuando la base no responde (503) o algo falla (500). No muestra el detalle técnico. |

## public/ (lo único que abre el navegador)

| Archivo | Qué hace |
|---|---|
| `index.php` | Punto de entrada. Abre la conexión a MySQL (si falla responde 503), crea los Repository, lee `?ruta=` (solo strings) y con un `switch` llama al método del controlador. Cualquier otra ruta da 404, y un error inesperado da 500 sin mostrar la traza. |
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
