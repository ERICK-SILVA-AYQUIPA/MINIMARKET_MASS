# Manual de usuario

Guía para quien usa el sistema en la tienda. Cada empleado entra con su **DNI** y su clave, y ve solo el menú de su rol. Para instalar el sistema, ver `docs/despliegue.md`.

## 1. Entrar y salir

1. Abrir `http://localhost:8000` (o la dirección del servidor). Aparece un momento el logo con una barra de carga y luego la pantalla de entrada.
2. Escribir el DNI y la clave en el cuadro de la derecha (en el celular, debajo del título), y pulsar **Entrar**.
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
- **Paneles laterales**: al tocar un producto, un cliente, un comprobante, un turno de caja, un movimiento del kardex, un ingreso o una fila de la auditoría, su detalle se abre a la derecha sin salir de la lista. Se cierra con la **X**, tocando el fondo oscuro o con **Atrás** del navegador.
- **Botón amarillo**: la acción principal de cada pantalla (Nueva venta, Registrar ingreso, Enviar mercadería, Registrar merma, Nuevo producto, Registrar empleado).
- Si una lista está vacía, la pantalla dice por qué y qué hacer.
- **Buscador en vivo**: en Catálogo, Stock, Kardex, Personal y Auditoría, escribir en **Buscar** deja solo las filas que coinciden. La tecla `/` lleva al buscador y `Esc` lo limpia.
- **Teclado**: `Esc` cierra un panel lateral o el menú del celular.
- **Confirmación**: anular una venta, descontinuar un producto, cerrar el turno, desactivar una caja, y registrar una merma, un traslado, un ingreso o un conteo piden confirmar antes de guardar.
- Si falta un dato o tiene mal formato, el mensaje sale debajo del campo. Mientras se guarda, el botón dice **Guardando…** para no registrar dos veces lo mismo.

## 2. Lo que ven todos

| Menú | Para qué |
|---|---|
| **Inicio** | Panel de la tienda: vendido hoy contra ayer, comprobantes, ticket promedio, gráfico de 14 días, productos por reponer, cajas abiertas y últimos movimientos |
| **Catálogo** | Productos con su precio y stock. Al tocar uno se abre su ficha a la derecha |
| **Stock** | Stock de cada producto en las 3 tiendas. **REPONER** marca lo que llegó al mínimo. Botón **Descargar en CSV (Excel)** |
| **Ventas** | Boletas y facturas por fecha y estado. Al tocar una se ve su detalle y pagos a la derecha; **Abrir comprobante completo** lleva a la página para imprimir o anular |
| **Traslados**, **Mermas**, **Kardex**, **Tiendas** | Consulta (registrar depende del rol) |

**Imprimir un comprobante**: tocar la venta y pulsar **Imprimir ticket** (o, en el comprobante completo, **Imprimir ticket / PDF**). Sale el ticket de 80 mm; con `Ctrl + P` se imprime en la ticketera o se elige **Guardar como PDF**. Si la venta está anulada, el ticket lo dice.

## 3. Cajero

| Tarea | Pasos |
|---|---|
| Abrir turno | **Caja** → elegir la caja → escribir el monto inicial en efectivo → **Abrir turno**. Sin turno abierto no se puede vender |
| Registrar una venta | **Nueva venta** → a la izquierda, productos y cantidades (los productos al peso aceptan decimales, por ejemplo `0.750` kg) → a la derecha, boleta o factura, el DNI o RUC del cliente (vacío = CLIENTE VARIOS; la factura exige RUC) y el efectivo que entrega. Si paga una parte con tarjeta o SIP, abrir **Pagar una parte con tarjeta** o **con billetera Sip** → botón amarillo **Registrar venta**. Mientras se escribe, el cuadro oscuro muestra el importe de cada producto, el total, lo que falta en efectivo y el vuelto, y avisa si no alcanza el stock, si un producto por unidad tiene decimales o si la factura no tiene RUC. Al registrar, el servidor vuelve a calcular todo con los precios de la base. El sistema descuenta el stock, toma el siguiente número de la serie y muestra el comprobante |
| Cerrar turno | **Caja** → escribir el efectivo contado → **Cerrar turno** |
| Clientes | **Clientes** → buscar por DNI o RUC (o sus primeros dígitos), ver sus compras y completar el documento que falta |

Si algo no se puede (no hay stock, el pago no cuadra con el total, el RUC no tiene 11 dígitos), el formulario vuelve con el error y no se guarda nada.

## 4. Almacenero

| Tarea | Pasos |
|---|---|
| Recibir mercadería | **Ingresos** → **Registrar ingreso** (o, desde el inicio, **Recibir mercadería**) → elegir el documento que trae el repartidor (guía, factura o boleta), su número (por ejemplo `F001-00012345`), su fecha, el RUC y la razón social del emisor → cada producto con su cantidad y su costo unitario → **Registrar ingreso**. El stock sube en ese momento y cada producto queda en el kardex como INGRESO DE MERCADERIA. Un mismo documento no se puede registrar dos veces |
| Trasladar mercadería | **Traslados** → **Enviar mercadería** → tienda de destino, productos y cantidades → **Registrar traslado**. Sale de su tienda y entra en la otra, y las dos quedan en el kardex |
| Registrar una merma | **Mermas** → **Registrar merma** → producto, cantidad y motivo (vencido, dañado, robo u otro) → registrar |
| Conteo físico | **Conteo** → escribir lo que se contó en el anaquel. Lo contado reemplaza al stock y la diferencia va al kardex como ajuste de entrada o de salida |
| Revisar movimientos | **Kardex** → filtrar por producto; **Ver** en una fila muestra el saldo anterior, la cantidad y el nuevo. Botón **Descargar CSV** |

## 5. Administrador

El administrador maneja **su tienda**. El catálogo y los precios son de toda la cadena.

| Tarea | Pasos |
|---|---|
| Anular una venta | **Ventas** → tocar el comprobante → **Abrir comprobante (para anular)** → escribir el motivo → **Anular venta**. Devuelve el stock y anula los pagos. No se puede deshacer |
| Arqueo de caja | **Caja**: ve cada turno con el efectivo esperado y el contado; **Ver** abre la cuenta del turno y dice si hubo sobrante o faltante |
| Reportes | **Reportes** → elegir fechas: vendido, ventas por día, más vendidos, por categoría, por cajero y por método de pago. Cada uno se descarga en CSV |
| Productos | **Catálogo** → **Nuevo producto**, o tocar uno para editar, descontinuar o reactivar y fijar el stock mínimo de su tienda |
| Categorías y marcas | **Categorías y marcas** → agregar nuevas |
| Personal | **Personal** → **Registrar empleado**, editar, desactivar y poner una clave nueva a un empleado de su tienda |
| Tienda y cajas | **Tiendas** → **Mi tienda, cajas y series**: editar los datos de su tienda, agregar cajas y activarlas o desactivarlas |
| Abrir una tienda nueva | **Tiendas** → **Abrir una tienda**: se crea con su caja 1, sus series de boleta y factura, stock en 0 y su primer administrador |
| Auditoría | **Auditoría**: quién anuló ventas, cambió precios, editó personal o reinició claves en su tienda, con fecha y detalle. Se filtra por acción |
| Revisar compras | **Ingresos**: ve lo que recibió su tienda, con documento, emisor, costos y quién lo registró. **Descargar en CSV (Excel)** saca una fila por producto. Cada ingreso también queda en la **Auditoría** |
| Conteo físico | **Conteo**, igual que el almacenero |

## 6. Mensajes que pueden salir

| Mensaje | Qué significa |
|---|---|
| **403 · Sin permiso** | Esa pantalla no es de su rol o de su tienda |
| **404 · No encontrado** | El producto, venta o página no existe |
| Error en rojo dentro del formulario | Falta un dato o no cumple una regla. No se guardó nada; corregir y volver a enviar |
| **El formulario venció** (403) | La página estuvo abierta mucho tiempo. Recargar y volver a enviar |
| **La base de datos no está disponible** (503) | MySQL está apagado. Avisar a quien administra el sistema |
