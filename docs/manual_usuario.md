# Manual de usuario

Guía para quien usa el sistema en la tienda. Cada empleado entra con su **DNI** y su clave, y ve solo el menú de su rol. Para instalar el sistema, ver `docs/instalacion.md` (corta) o `docs/despliegue.md` (completa).

## En una página

Lo mismo sale dentro del sistema en **Ayuda** (pie de cada página), solo con lo del rol de quien entró.

| Rol | Lo de todos los días |
|---|---|
| **Cajero** | **Caja** → abrir turno con el efectivo inicial · **Nueva venta** → escanear o tocar productos → boleta o factura → efectivo (o tarjeta / Sip) → **Registrar venta** (`F9`) · **Imprimir ticket** en el comprobante · **Caja** → movimientos de efectivo · **Caja** → contar y **Cerrar turno** |
| **Almacenero** | **Ingresos** → registrar lo que llega con su documento, costo y vencimiento · **Reponer** → qué pedir · **Por vencer** · **Traslados**, **Mermas** y **Conteo** · todo queda en el **Kardex** |
| **Administrador** | **Caja** → revisar turnos y diferencias · **Ventas** → anular con motivo · **Reportes** y **Utilidad** · **Catálogo** y precios · **Personal** · **Auditoría** |
| **Todos** | **Mi clave** para cambiarla · **Ayuda** para el soporte y qué hacer sin internet · si sale la franja roja *Sin conexión*, no cerrar la página (sección 7) |

## 1. Entrar y salir

1. Abrir `http://localhost:8000` (o la dirección del servidor). Aparece la pantalla de entrada.
2. Escribir el DNI y la clave en el cuadro de la derecha (en el celular, debajo del título), y pulsar **Entrar**. Aparece un momento el logo con tu nombre y una barra de carga, y luego el panel de inicio.
3. Para salir: **Salir**, al pie del menú de la izquierda.

- Con 5 claves erradas seguidas, ese DNI espera 15 minutos antes de volver a intentar.
- Si nadie usa el sistema por 30 minutos, la sesión se cierra sola y hay que volver a entrar.
- **Mi clave** (al pie del menú): cada empleado cambia su propia clave. La nueva debe tener de 8 a 72 caracteres, con al menos una letra y un número.

### Usuarios de prueba

La clave de los 9 es `Mass2026!`. Fuera del aula, cada uno debe cambiarla en **Mi clave**.

| Tienda | Administrador | Cajero | Almacenero |
|---|---|---|---|
| Mass Cayma | Rosa · `70000001` | Luis · `70000002` | Carmen · `70000003` |
| Mass Cerro Colorado | Jorge · `70000004` | Ana · `70000005` | Pedro · `70000006` |
| Mass Yanahuara | Lucía · `70000007` | Miguel · `70000008` | Sofía · `70000009` |

### Cómo moverse

- **Menú de la izquierda**: agrupado en Ventas, Inventario y Administración. Arriba dice la tienda de quien entró. En el celular se abre con el botón **Menú**.
- **Menú lateral** (al pie del menú, en la computadora): cada empleado elige cómo verlo y se recuerda en ese navegador. **Fijo** lo deja siempre abierto; **Íconos** lo pliega y muestra el nombre de cada opción al pasar el cursor; **Auto** lo deja plegado y lo abre solo al pasar el cursor o al llegar con `Tab`. El botón junto al logo lo pliega o lo abre con un clic.
- **Paneles laterales**: al tocar un producto, un cliente, un comprobante, un turno de caja, un movimiento del kardex, un ingreso o una fila de la auditoría, su detalle se abre a la derecha sin salir de la lista. Se cierra con la **X**, tocando el fondo oscuro o con **Atrás** del navegador.
- **Botón amarillo**: la acción principal de cada pantalla (Nueva venta, Registrar ingreso, Enviar mercadería, Registrar merma, Nuevo producto, Registrar empleado).
- Si una lista está vacía, la pantalla dice por qué y qué hacer.
- **Buscador en vivo**: en Catálogo, Stock, Kardex, Personal y Auditoría, escribir en **Buscar** deja solo las filas que coinciden. La tecla `/` lleva al buscador y `Esc` lo limpia.
- **Guías rápidas**: Catálogo, Nueva venta, Caja, Ingresos, Reponer, Por vencer, Kardex, Traslados, Mermas, Conteo, Reportes y Utilidad empiezan con una guía corta de pasos. Al cerrarla, el navegador recuerda no volver a abrirla; se vuelve a ver tocando su título.
- **Avisos flotantes**: al agregar un producto a la venta o si un código no existe, sale un aviso corto abajo de la pantalla que se va solo.
- **Teclado**: `Esc` cierra un panel lateral o el menú del celular. En **Nueva venta**, `F9` registra la venta.
- **Celular**: quien maneja la caja tiene abajo una barra con **Inicio**, **Caja**, **Vender** (el botón amarillo del centro), **Ventas** y **Menú**. En **Nueva venta**, mientras se eligen productos, una barra oscura muestra el total y **Cobrar** baja directo al carrito y al cobro.
- **Confirmación**: anular una venta, descontinuar un producto, cerrar el turno, desactivar una caja, y registrar una merma, un traslado, un ingreso o un conteo piden confirmar antes de guardar.
- **Pie de página**: la versión del sistema (por ejemplo `v1.0.0`), a quién llamar si algo falla y el enlace a **Ayuda**.
- Si falta un dato o tiene mal formato, el mensaje sale debajo del campo. Mientras se guarda, el botón dice **Guardando…** para no registrar dos veces lo mismo.

## 2. Lo que ven todos

| Menú | Para qué |
|---|---|
| **Inicio** | Panel de la tienda: vendido hoy contra ayer, comprobantes, ticket promedio, gráfico de 14 días, productos por reponer, lo que vence esta semana (almacenero y administrador), cajas abiertas y últimos movimientos |
| **Catálogo** | Productos en tarjetas con su foto (o el ícono de su categoría), precio y stock: verde hay, amarillo llegó al mínimo, rojo agotado. Arriba, los filtros por categoría con cuántos productos tiene cada una y el botón **Tabla** para verlos en lista. Al tocar uno se abre su ficha a la derecha, con la foto grande |
| **Stock** | Stock de cada producto en las 3 tiendas. **REPONER** marca lo que llegó al mínimo. Botón **Descargar en CSV (Excel)** |
| **Ventas** | Boletas y facturas por fecha y estado. Al tocar una se ve su detalle y pagos a la derecha; **Abrir comprobante completo** lleva a la página para imprimir o anular |
| **Traslados**, **Mermas**, **Kardex**, **Tiendas** | Consulta (registrar depende del rol) |

**Imprimir un comprobante**: en el comprobante, **Imprimir en ticketera** imprime el ticket y vuelve al comprobante. **Ver ticket / PDF** lo abre para guardarlo en PDF; ahí se elige el papel de **58 mm** u **80 mm** (la caja lo recuerda). Mientras las ventas no se envíen a SUNAT, el ticket sale como **TICKET INTERNO, no válido como comprobante de pago**. Si la venta está anulada, el ticket lo dice. Para imprimir sin la ventana de impresión, ver `docs/facturacion_electronica.md`.

## 3. Cajero

| Tarea | Pasos |
|---|---|
| Abrir turno | **Caja** → elegir la caja → escribir el monto inicial en efectivo → **Abrir turno**. Sin turno abierto no se puede vender |
| Registrar una venta | **Nueva venta** → arriba, **escanear** el código con el lector (o escribirlo y Enter), o **tocar** el producto en la cuadrícula: cada toque suma 1, y en los productos al peso el cursor va a los kilos. Los filtros por categoría y **Buscar producto** achican la cuadrícula; con un solo resultado, Enter lo agrega. También se puede elegir en las filas de abajo, productos y cantidades (los productos al peso aceptan decimales, por ejemplo `0.750` kg) → a la derecha, boleta o factura, el DNI o RUC del cliente (vacío = CLIENTE VARIOS; la factura exige RUC) y el efectivo que entrega (los botones **Exacto**, **S/ 20.00**, **S/ 50.00**… lo escriben de un toque; junto al DNI o RUC una etiqueta dice al momento si es DNI, RUC o cliente varios). Si paga una parte con tarjeta o SIP, abrir **Pagar una parte con tarjeta** o **con billetera Sip** → botón amarillo **Registrar venta**. Mientras se escribe, el cuadro oscuro muestra el importe de cada producto, el total, lo que falta en efectivo y el vuelto, y avisa si no alcanza el stock, si un producto por unidad tiene decimales o si la factura no tiene RUC. Al registrar, el servidor vuelve a calcular todo con los precios de la base. El sistema descuenta el stock, toma el siguiente número de la serie y muestra el comprobante |
| Devolver parte de una venta | **Ventas** → tocar el comprobante → **Abrir comprobante completo** → en **Devoluciones**, escribir cuánto devuelve de cada producto (vacío = se queda) y el motivo → **Registrar devolución**. Se paga al precio de la venta, en efectivo de tu gaveta, y el producto vuelve al stock. Se acepta hasta 7 días después de la venta y con tu turno abierto. Una venta con devoluciones ya no se puede anular |
| Sacar o meter efectivo | **Caja** → **Movimientos de efectivo** → elegir **Retiro** o **Ingreso**, la **categoría** (retiro de seguridad, pago a proveedor, gasto menor u otro; o sencillo, aporte de la tienda u otro), el monto, el detalle y, si hay, el N.° del comprobante → **Registrar**. Las devoluciones a un cliente no van aquí: se registran desde el comprobante de la venta |
| Retiro de seguridad | Si la gaveta pasa el tope de la tienda, **Caja** lo avisa. Sin cierre ciego, dice cuánto retirar (lo que pasa del fondo fijo, en billetes de S/ 10) y **Retirar S/ …** llena el movimiento para confirmarlo; con cierre ciego no muestra el monto, para no revelar lo esperado |
| Cerrar turno | **Caja** → escribir el efectivo contado → **Cerrar turno** |
| Clientes | **Clientes** → buscar por DNI o RUC (o sus primeros dígitos), ver sus compras y completar el documento que falta |

Si algo no se puede (no hay stock, el pago no cuadra con el total, el RUC no tiene 11 dígitos), el formulario vuelve con el error y no se guarda nada.

## 4. Almacenero

| Tarea | Pasos |
|---|---|
| Recibir mercadería | **Ingresos** → **Registrar ingreso** (o, desde el inicio, **Recibir mercadería**) → elegir el documento que trae el repartidor (guía, factura o boleta), su número (por ejemplo `F001-00012345`), su fecha, el RUC y la razón social del emisor → cada producto con su cantidad, su costo unitario y, si tiene, su fecha de vencimiento → **Registrar ingreso**. El stock sube en ese momento y cada producto queda en el kardex como INGRESO DE MERCADERIA. Un mismo documento no se puede registrar dos veces |
| Pedir a un proveedor | **Compras** → **Órdenes de compra** → **Nueva orden** (o, desde **Reponer**, **Armar orden de compra** o **Pedir a ...**, que la llenan con lo sugerido y el último costo) → proveedor, fecha de entrega, productos, cantidad y costo pactado → **Emitir orden**. La orden no cambia el stock. **Imprimir o PDF** la deja lista para enviarla al proveedor |
| Recibir una orden de compra | **Ingresos** → en **Por recibir**, **Recibir** (o, en la orden, **Recibir mercadería**) → el proveedor y lo que falta ya vienen puestos: anotar el documento, corregir cada cantidad a lo que de verdad llegó (0 si no vino) y el costo si la factura trae otro → **Registrar ingreso**. El stock sube, el kardex dice el documento y la orden, y la orden pasa a PARCIAL o RECIBIDA. No se recibe más de lo pedido: lo que llegue de más va en un ingreso aparte |
| Trasladar mercadería | **Traslados** → **Enviar mercadería** → tienda de destino, productos y cantidades → **Registrar traslado**. Sale de su tienda y entra en la otra, y las dos quedan en el kardex |
| Qué pedir | **Reponer** → lista de lo que llegó a su stock mínimo: cuánto hay, cuánto se vende por día, para cuántos días alcanza y cuánto pedir (para 14 días de venta, descontando lo que ya está pedido en una orden abierta) y a quién se le compró por última vez. Si otra tienda tiene sobrante, aparece para pedir un traslado. **Lista de pedido en CSV** la baja para Excel |
| Productos por vencer | **Por vencer** → elegir 7, 15, 30 o 60 días. Cada lote dice cuándo vence, cuánto queda (estimado: se vende primero lo más antiguo) y su valor al costo. Lo vencido sale en rojo; **Dar de baja lo vencido** lleva a registrar la merma |
| Registrar una merma | **Mermas** → **Registrar merma** → producto, cantidad y motivo (vencido, dañado, robo u otro) → registrar |
| Conteo físico | **Conteo** → escribir lo que se contó en el anaquel. Lo contado reemplaza al stock y la diferencia va al kardex como ajuste de entrada o de salida |
| Revisar movimientos | **Kardex** → filtrar por producto; **Ver** en una fila muestra el saldo anterior, la cantidad y el nuevo. Botón **Descargar CSV** |

## 5. Administrador

El administrador maneja **su tienda**. El catálogo y los precios son de toda la cadena.

| Tarea | Pasos |
|---|---|
| Anular una venta | **Ventas** → tocar el comprobante → **Abrir comprobante (para anular)** → escribir el motivo → **Anular venta**. Devuelve el stock y anula los pagos. No se puede deshacer |
| Arqueo de caja | **Caja**: ve cada turno con el efectivo esperado y el contado; **Ver** abre la cuenta del turno (inicial, cobrado, devoluciones, ingresos y retiros, con su motivo) y dice si hubo sobrante o faltante |
| Reportes | **Reportes** → elegir fechas: vendido, devuelto y neto, ventas por día, más vendidos, por categoría, por cajero y por método de pago. Cada uno se descarga en CSV |
| Utilidad | **Utilidad** → elegir fechas: ventas netas, costo de lo vendido, utilidad y margen de cada producto, y la comparación entre las tiendas de la cadena. El costo sale del promedio de lo comprado en **Ingresos**; un producto que nunca entró por un ingreso aparece "sin costo". Margen bajo 15 % en amarillo y negativo en rojo |
| Reponer | **Reponer**, igual que el almacenero, para decidir el pedido |
| Productos | **Catálogo** → **Nuevo producto**, o tocar uno para editar, descontinuar o reactivar y fijar el stock mínimo de su tienda |
| Foto de un producto | **Catálogo** → tocar el producto → **Foto del producto** → elegir una foto JPG, PNG o WebP de hasta 2 MB. Se guarda sola al elegirla, achicada a 600 px. **Quitar foto** vuelve al ícono de la categoría. Cada cambio queda en la **Auditoría**. Conviene una foto cuadrada del producto sobre fondo claro |
| Categorías y marcas | **Categorías y marcas** → agregar nuevas |
| Importar el catálogo | **Catálogo** → **Importar CSV** → **Descargar el catálogo actual**, editarlo en Excel (agregar filas o cambiar precios) y guardarlo como CSV. Subirlo con **Solo revisar**: dice cuántos productos son nuevos, cuáles cambian y qué línea tiene un error, sin guardar nada. Si está bien, subirlo otra vez con **Importar**. Los productos nuevos entran con stock 0: el stock llega con **Ingresos**. Cada cambio de precio queda en la **Auditoría** |
| Personal | **Personal** → **Registrar empleado**, editar, desactivar y poner una clave nueva a un empleado de su tienda |
| Tienda y cajas | **Tiendas** → **Mi tienda, cajas y series**: editar los datos de su tienda, agregar cajas y activarlas o desactivarlas |
| Abrir una tienda nueva | **Tiendas** → **Abrir una tienda**: se crea con su caja 1, sus series de boleta y factura, stock en 0 y su primer administrador |
| Auditoría | **Auditoría**: quién anuló ventas, cambió precios, editó personal o reinició claves en su tienda, con fecha y detalle. Se filtra por acción |
| Revisar compras | **Ingresos**: ve lo que recibió su tienda, con documento, emisor, costos y quién lo registró. **Descargar en CSV (Excel)** saca una fila por producto. Cada ingreso también queda en la **Auditoría** |
| Proveedores | **Compras** → **Proveedores** → **Agregar proveedor** (RUC, razón social, contacto, teléfono, correo y días de entrega), **Editar** o **Desactivar**. El directorio es de toda la cadena. Un RUC nuevo que llega en un ingreso se agrega solo; aquí se completa su contacto. Uno con órdenes abiertas no se desactiva |
| Anular o cerrar una orden | **Compras** → **Órdenes de compra** → **Ver** → escribir el motivo → **Anular orden** (no llegó nada) o **Cerrar orden** (llegó una parte y el resto ya no vendrá). Queda en la **Auditoría** |
| Conteo físico | **Conteo**, igual que el almacenero |
| Por vencer | **Por vencer**: lo mismo que ve el almacenero, para decidir ofertas o reclamos al proveedor |

## 6. Mensajes que pueden salir

| Mensaje | Qué significa |
|---|---|
| **403 · Sin permiso** | Esa pantalla no es de su rol o de su tienda |
| **404 · No encontrado** | El producto, venta o página no existe |
| Error en rojo dentro del formulario | Falta un dato o no cumple una regla. No se guardó nada; corregir y volver a enviar |
| **El formulario venció** (403) | La página estuvo abierta mucho tiempo. Recargar y volver a enviar |
| **La base de datos no está disponible** (503) | MySQL está apagado. Avisar a quien administra el sistema |
| **Sin conexión con el servidor** (franja roja arriba) | Se cortó el internet o el servidor no responde. No cerrar la página: ver la sección 7 |
| **Esta venta ya estaba registrada** (amarillo) | La venta se envió dos veces (por ejemplo, después de un corte). Se registró una sola vez: no cobrar otra vez |

## 7. Si se cae el internet

1. **Franja roja *Sin conexión con el servidor***: no cerrar la página. La venta que se está armando queda guardada en ese navegador y **Registrar venta** no la envía hasta que vuelva la conexión (sale *Conexión recuperada* en verde).
2. **Se pulsó Registrar y el navegador mostró un error**: **no cobrar otra vez**. Al volver la conexión, entrar a **Nueva venta**: dice si la venta quedó registrada (con su número y **Ver comprobante**) o la devuelve armada para registrarla. Enviarla de nuevo nunca la duplica.
3. **Se cerró el navegador o se fue la luz**: entrar otra vez, en la misma PC y con el mismo usuario, a **Nueva venta**: la venta aparece armada con un aviso amarillo (hasta 12 horas). Si ya no se quiere, **Vaciar**.
4. **El sistema no vuelve y hay clientes**: solo efectivo, anotar cada venta en la hoja de contingencia y, al volver, registrarlas en **Nueva venta** anotando el número de comprobante en la hoja.

El plan completo (qué funciona sin internet, la hoja de contingencia para imprimir y cómo preparar la tienda) está en `docs/plan_sin_internet.md`.

## 8. Versión y soporte

- La versión sale en el pie de cada página y en **Ayuda**. Los cambios de cada versión están en `CHANGELOG.md`.
- El contacto de soporte (nombre, teléfono, correo y horario) también sale en el pie y en **Ayuda**. Lo configura quien instala el sistema en el `.env` (`docs/instalacion.md`).
- Al pedir ayuda, tener a la mano: la pantalla donde se estaba, qué se hizo, el mensaje que salió, el número de comprobante (si hay) y la versión.
