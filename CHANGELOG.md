# Cambios por versión

La versión actual está en el archivo `VERSION` y sale en el pie de cada página, en **Ayuda** y en `GET /?ruta=salud`.
Al entregar una versión nueva: cambiar `VERSION` (formato `1.2.3`) y agregar aquí lo que cambió, lo más nuevo arriba.

## Sin publicar

- **Asistente en ventanita y con el conocimiento de la tienda**: el asistente se abre abajo a la derecha sin tapar
  la pantalla (botón amarillo en cada pantalla menos Caja y Nueva venta, o desde el menú) y sigue abierto al cambiar
  de pantalla. Responde "¿cómo…?" con los pasos del manual de cada rol (abrir y cerrar caja, devolver, anular, recibir
  mercadería, traslados, mermas, conteo, promociones, clave, sin internet) y da los datos de la cadena (sedes,
  cajas, productos por categoría, promociones de hoy). Nuevo: *¿Qué productos se compran juntos?* (pares que salen en
  la misma compra, para armar combos y ordenar el estante).
- **Asistente: sistema inteligente de la tienda** (sin IA ni clave, desde la base): *¿Qué pido al proveedor y
  cuánto?* (lo que se vende por día, para cuántos días alcanza y cuánto pedir, con el botón para armar la orden de
  compra), *¿Cuánto venderemos esta semana?* (pronóstico de cada día con el mismo día de las últimas 8 semanas y si
  las ventas suben o bajan), *¿Qué productos están por vencer?*, *¿Cuáles son las horas punta?* (para armar los
  turnos) y *¿Qué alertas hay en la tienda?* (stock bajo, por vencer y lo que no se vende, juntos). Claude también
  las usa cuando hay `ANTHROPIC_API_KEY`. Cada rol ve solo lo suyo.
- **Asistente: preguntas rápidas de la tienda, sin IA**: botones con las preguntas de siempre (ventas de hoy, ayer y
  del mes, más vendidos, por cajero, medios de pago, cada tienda, stock bajo, lo que no se vende hace 30 días, mejores
  clientes, hacer una venta) que se responden al instante desde la base, sin clave ni internet. Sin clave, lo escrito
  se entiende por palabras clave o se busca como producto. El asistente ya está en el menú de todos; con
  `ANTHROPIC_API_KEY`, Claude responde lo escrito libre y solo habla de la tienda.
- **Asistente inteligente** (menú *Asistente* y botón amarillo abajo a la derecha): cualquier empleado pregunta en
  español, escribiendo o con el micrófono, y responde con datos del sistema: cuánto se vendió (hoy, ayer, el mes), los
  más vendidos, por cajero o método de pago, precio y stock de un producto, qué falta reponer. Si se le pide *hacer*
  algo (vender, recibir mercadería) da el botón a esa pantalla: no cambia datos. Respeta los permisos de cada rol (el
  cajero no ve reportes ni otra tienda). Usa Claude (Anthropic) desde el servidor: se activa con `ANTHROPIC_API_KEY`
  en el `.env`; sin clave el menú queda igual que antes. Ver `docs/asistente.md`.
- **Personal: dirección, DNI y teléfono** (migración `026_datos_de_contacto.sql`): al registrar o editar a un
  empleado (Personal → *Editar*) el administrador pone su dirección y su teléfono, y ahora también corrige su DNI
  (es con lo que entra; su clave no cambia). La lista de Personal muestra el teléfono.
- **Clientes: registrar un cliente a mano** (Clientes → *Registrar cliente*): nombre, DNI (o RUC si pide factura),
  dirección y teléfono. En la ficha de cualquier cliente se cambian nombre, dirección y teléfono; la lista muestra el
  nombre y se busca también por nombre. DNI de 8 dígitos; teléfono de 6 a 9 dígitos (se aceptan espacios y guiones).
- **Etiquetas QR para reconocer cada producto** (Catálogo → *Etiquetas QR*, o *Imprimir etiqueta* en la ficha):
  hoja A4 de 3 etiquetas por fila con el QR, el nombre y el precio, de todo el catálogo, de una categoría o de un
  producto (con copias). El QR lleva el código del producto: la app (Nueva venta, Entrada de stock, Consultar) lo
  reconoce al instante aunque el empaque traiga otro código, ninguno o uno que la cámara no lee. El QR se arma en el
  servidor sin librerías ni internet (`CodigoQr.php`); las 84 etiquetas de la base de ejemplo se leyeron y la API
  de la app reconoció las 84.
- **Producto sin código de barras**: al crearlo en la web por unidad con el código vacío, recibe uno interno
  (EAN-13 que empieza con 20, rango de GS1 para uso dentro de la tienda) para tener su etiqueta QR.
- **Nuevo producto se completa solo**: en la web, al escanear o escribir el código se llenan el nombre real, la marca
  y la categoría (Open Food Facts y Open Products Facts, por el servidor); si el código ya es de un producto, avisa
  cuál. En la app, al escanear un código nuevo, además del nombre y la marca ahora se elige la categoría y se consulta
  también Open Products Facts (limpieza y cuidado personal). Sin internet se escribe a mano, como antes.
- **Solo para la demo: la misma clave para los 12 empleados de ejemplo** (migración `025_clave_demo_para_todos.sql`):
  todos vuelven a entrar con `Mass2026!` (en la base solo el hash bcrypt) y ya no se les pide cambiarla en el primer
  ingreso. A quien ya la había cambiado se le cierran las sesiones abiertas. Solo cambia la base de ejemplo; en una
  tienda real cada empleado debe tener su propia clave y `verificar_instalacion.php` sigue marcando la de prueba como FALLA.
- **Un segundo cajero por sede** (migración `024_cajero_caja_2_por_sede.sql`): Diego (Cayma), Elena (Cerro Colorado)
  y Raúl (Yanahuara), uno para la caja 2 de cada tienda. Los dos cajeros de una sede pueden tener su turno abierto
  a la vez. Solo se agregan en la base de ejemplo y no tocan a los empleados que ya estaban.
- **Corregido**: en Nueva venta, la venta armada ya no se guardaba en el navegador (el campo `tarjeta_tipo` se
  quitó al rediseñar los medios de pago y el guardado fallaba con un error de JavaScript). Ahora guarda y recupera
  también el medio de pago y el descuento manual.

## 1.0.0 · 2026-11-05

Primera versión de entrega: ventas con boleta y factura, caja con arqueo a ciegas, inventario por tienda
(ingresos, traslados, mermas, conteo, vencimientos, reposición y kardex), reportes y utilidad, auditoría,
roles con permisos distintos y la app Android.

En esta versión, además:

- **Plan si se cae el internet** (`docs/plan_sin_internet.md`): franja roja *Sin conexión con el servidor*,
  la venta armada se guarda en el navegador, una venta reenviada después de un corte no se registra dos veces
  y, al volver, el sistema dice si la última venta quedó registrada.
- **Versión y soporte visibles**: en el pie de cada página y en la nueva pantalla **Ayuda** (con el manual corto
  de cada rol). El contacto se configura en `.env` (`SOPORTE_*`).
- **Documentación de entrega**: guía de instalación corta (`docs/instalacion.md`) y manual de usuario con
  resumen en una página (`docs/manual_usuario.md`).
- `GET /?ruta=salud` para monitorear el servidor.
