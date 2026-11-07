<?php
declare(strict_types=1);

/**
 * Lo que el asistente sabe de COMO se usa el sistema de la tienda: los pasos de cada tarea, con la pantalla donde
 * se hace. Sale del manual de Ayuda (views/ayuda/ayuda.php) y de docs/. Sin IA: se busca por palabras.
 * Cada guia dice quien la usa ('vende', 'almacen', 'admin' o 'todos') para no ensenarle al cajero lo que no puede hacer.
 * Es una clase sin base de datos: se prueba sola en tests/unitarios.php.
 */
final class Conocimiento
{
    // clave => [titulo, quien, palabras para encontrarla, pasos, pantalla (clave de HerramientasAsistente::PAGINAS) o null]
    private const GUIAS = [
        'abrir_caja' => ['Abrir la caja (empezar el día)', 'vende', ['abrir caja', 'abro la caja', 'abrir turno', 'empezar el dia', 'empiezo', 'fondo inicial', 'iniciar turno', 'apertura'],
            "1. Entra a Caja y elige tu caja.\n2. Cuenta el efectivo inicial (o toca «Viene exacto» si el sobre trae el fondo justo).\n3. Toca Abrir turno. Ya puedes vender.", 'caja'],
        'vender' => ['Hacer una venta', 'vende', ['como vendo', 'como hago una venta', 'como se vende', 'registrar venta', 'cobrar', 'cobro', 'boleta', 'factura', 'f9'],
            "1. Nueva venta: escanea el código o toca la foto del producto (los de peso piden los kilos).\n2. Elige boleta o factura (la factura pide RUC).\n3. Toca Cobrar: efectivo que entrega (calcula el vuelto), tarjeta o Sip.\n4. Registrar venta (o F9). Sale el comprobante para imprimir o enviar por WhatsApp o correo.", 'nueva_venta'],
        'imprimir' => ['Imprimir o enviar el comprobante', 'vende', ['imprimir', 'ticket', 'reimprimir', 'whatsapp', 'enviar comprobante', 'correo'],
            "En el comprobante toca Imprimir ticket. Para mandarlo al cliente: escribe su celular o correo y toca WhatsApp o Gmail. Una venta pasada se busca en Ventas.", 'ventas'],
        'devolucion' => ['Devolver productos de una venta', 'vende', ['devolucion', 'devolver', 'devuelve', 'cambio de producto', 'reembolso'],
            "1. Ventas: busca el comprobante.\n2. Abrir comprobante completo → Devoluciones.\n3. Marca lo que devuelve y el motivo. El stock vuelve y la caja descuenta el dinero.", 'ventas'],
        'anular' => ['Anular una venta', 'admin', ['anular', 'anulo', 'anulacion', 'cancelar venta', 'venta equivocada'],
            "1. Ventas: busca el comprobante.\n2. Abrir comprobante → escribe el motivo → Anular.\nSolo el administrador anula; queda en la Auditoría con su nombre y la hora.", 'ventas'],
        'movimiento_efectivo' => ['Sacar o meter efectivo de la caja', 'vende', ['retiro', 'retirar', 'sacar plata', 'sacar dinero', 'ingreso de efectivo', 'sencillo', 'deposito', 'movimiento de efectivo', 'pago a proveedor'],
            "Caja → Movimientos de efectivo: elige Retiro o Ingreso, la categoría (depósito, sencillo, pago a proveedor…), el monto y el motivo. Cuenta en el cierre.", 'caja'],
        'cerrar_caja' => ['Cerrar la caja (terminar el día)', 'vende', ['cerrar caja', 'cierro la caja', 'cierro caja', 'cerrar la caja', 'cerrar turno', 'cierre', 'arqueo', 'terminar el dia', 'cuadrar', 'cuadre'],
            "1. Caja → Cerrar caja.\n2. Cuenta billetes y monedas sin ver lo esperado (conteo a ciegas).\n3. Confirma. Sale el comprobante de cierre con lo esperado, lo contado y la diferencia.", 'caja'],
        'cliente' => ['Registrar o buscar un cliente', 'vende', ['registrar cliente', 'nuevo cliente', 'cliente nuevo', 'dni', 'ruc', 'datos del cliente'],
            "Clientes → Registrar cliente: nombre, DNI (o RUC si pide factura), dirección y teléfono. En la venta se busca por DNI o RUC.", 'clientes'],
        'promocion' => ['Crear una promoción', 'todos', ['promocion', 'oferta', 'descuento', 'pack', '2x1', 'combo'],
            "Promociones → Nueva promoción: porcentaje, precio especial, pack (por ejemplo 3 por S/ 10) o monto mínimo, con fechas. En la venta se aplica sola.", 'promociones'],
        'ingreso' => ['Recibir mercadería del proveedor', 'almacen', ['recibir mercaderia', 'ingreso de mercaderia', 'llego mercaderia', 'llego el proveedor', 'registrar ingreso', 'entrada de stock', 'factura del proveedor'],
            "Movimientos → Ingresos → Registrar ingreso: el documento del proveedor, cada producto con cantidad, costo y fecha de vencimiento. El stock sube al guardar.", 'ingresos'],
        'orden_compra' => ['Hacer un pedido (orden de compra)', 'almacen', ['orden de compra', 'pedido al proveedor', 'hacer pedido', 'pedir mercaderia'],
            "Productos → Reponer → Armar la orden: llena lo sugerido (lo que falta para 14 días de venta). Revisa, elige el proveedor y guarda. Al llegar, regístralo como ingreso desde la orden.", 'compras'],
        'traslado' => ['Enviar mercadería a otra tienda', 'almacen', ['traslado', 'trasladar', 'enviar a otra tienda', 'pasar mercaderia', 'otra sede'],
            "Movimientos → Traslados → Nuevo traslado: tienda de destino, productos y cantidades. El stock sale de aquí y entra allá.", 'traslados'],
        'merma' => ['Dar de baja productos (merma)', 'almacen', ['merma', 'dar de baja', 'vencido', 'roto', 'malogrado', 'botar'],
            "Movimientos → Mermas: producto, cantidad y motivo (vencido, roto, robo). El stock baja y queda en el Kardex. Lo vencido se ve en Productos → Por vencer.", 'mermas'],
        'conteo' => ['Contar el inventario (ajuste)', 'almacen', ['conteo', 'inventario fisico', 'contar stock', 'ajuste', 'ajustar stock', 'stock no cuadra'],
            "Movimientos → Conteo: escribe lo que hay en el estante; el sistema ajusta la diferencia y la deja en el Kardex con tu nombre.", 'kardex'],
        'kardex' => ['Ver de dónde salió o entró un producto', 'almacen', ['kardex', 'historial del producto', 'movimientos del producto', 'quien saco'],
            "Movimientos → Kardex: elige el producto y ves cada entrada y salida (ventas, ingresos, traslados, mermas, ajustes) con la fecha y quién lo hizo.", 'kardex'],
        'producto' => ['Crear un producto o cambiar un precio', 'admin', ['crear producto', 'nuevo producto', 'cambiar precio', 'precio de venta', 'editar producto', 'codigo de barras', 'descontinuar'],
            "Productos → Catálogo → Nuevo producto (o Editar en la ficha): nombre, marca, categoría, precio, código de barras y foto. Para muchos a la vez: Importar CSV.", 'catalogo'],
        'etiquetas' => ['Imprimir etiquetas QR de los productos', 'admin', ['etiqueta', 'qr', 'etiquetas qr'],
            "Catálogo → Etiquetas QR: hoja A4 con el QR, el nombre y el precio, de todo, de una categoría o de un producto. La app del celular reconoce el QR.", 'catalogo'],
        'reportes' => ['Ver reportes y utilidad', 'admin', ['reporte', 'utilidad', 'ganancia', 'excel', 'pdf', 'estadistica'],
            "Administración → Reportes (ventas por día, producto, cajero, pago) y Utilidad (lo ganado sobre el costo). Se descargan en Excel o PDF.", 'reportes'],
        'personal' => ['Registrar a un empleado', 'admin', ['empleado', 'personal', 'trabajador', 'nuevo usuario', 'cajero nuevo', 'dar acceso'],
            "Administración → Personal → Registrar: nombres, DNI (entra con él), rol, tienda, teléfono y una clave inicial que cambia en Mi clave.", 'personal'],
        'auditoria' => ['Ver quién hizo qué (auditoría)', 'admin', ['auditoria', 'quien anulo', 'quien cambio', 'quien borro'],
            "Administración → Auditoría: anulaciones, cambios de precio, de clave y de permisos, con la persona y la hora.", null],
        'clave' => ['Cambiar mi clave o entrar si me bloqueé', 'todos', ['clave', 'contrasena', 'password', 'bloqueado', 'no puedo entrar', 'olvide'],
            "Mi clave (al pie del menú) cambia tu clave. Con 5 claves erradas se espera 15 minutos. Si la olvidaste, el administrador te pone una nueva en Personal.", null],
        'sin_internet' => ['Si se cae el internet o el servidor', 'todos', ['sin internet', 'se cayo', 'no carga', 'no funciona el sistema', 'contingencia', 'luz'],
            "Las ventas siguen en la red de la tienda si el servidor está prendido. Si el servidor se apaga: anota en la hoja de contingencia y regístralas luego. Ayuda tiene el teléfono de soporte.", 'ayuda'],
        'sesion' => ['Por qué se cerró mi sesión', 'todos', ['se cerro la sesion', 'me boto', 'me saco', 'sesion'],
            "Sin usar el sistema 30 minutos, la sesión se cierra sola por seguridad. Vuelve a entrar con tu DNI y clave.", null],
        'asistente' => ['Qué puede hacer el asistente', 'todos', ['que puedes hacer', 'que sabes', 'ayuda del asistente', 'para que sirves', 'como funcionas'],
            "Respondo con los datos de la tienda, con tarjetas, tablas y gráficos: ventas, precios, stock, lo que falta, lo que vence, qué pedir, horas punta, pronóstico y cómo usar cada pantalla. Sé en qué pantalla estás: pregúntame «¿qué hago aquí?». No cambio datos: te doy el botón a la pantalla donde se hace.", null],

        // Mas del manual (docs/manual_usuario.md)
        'cobrar_tarjeta' => ['Cobrar con tarjeta, Sip o con dos medios de pago', 'vende', ['tarjeta', 'debito', 'credito', 'sip', 'voucher', 'pago mixto', 'dos medios', 'parte en efectivo', 'pos'],
            "1. En Nueva venta, en el medio de pago elige Débito o Crédito (marca, últimos 4 dígitos y código de autorización del voucher) o Sip (QR o tarjeta Sip y el código de operación).\n2. Con el monto vacío cobra todo el total; si escribes un monto menor, el resto se paga en efectivo.\n3. En el cierre, tarjeta y Sip salen aparte para cuadrarlos con sus vouchers: no entran a la gaveta.", 'nueva_venta'],
        'descuento_manual' => ['Hacer un descuento a toda la compra', 'vende', ['descuento manual', 'rebaja', 'rebajar', 'descuento a la compra', 'descuento a toda', 'hacer descuento'],
            "1. En Nueva venta abre «Descuento a toda la compra».\n2. Escribe el % o el monto en S/ (hasta el 10 %) y el motivo.\n3. Queda en la Auditoría. Si una promoción a toda la compra descuenta más, se usa la promoción (no se suman).", 'nueva_venta'],
        'retiro_seguridad' => ['Hacer un retiro de seguridad', 'vende', ['retiro de seguridad', 'mucho efectivo', 'mucha plata', 'tope de la gaveta', 'gaveta llena'],
            "Cuando la gaveta pasa el tope de la tienda, Caja lo avisa y dice cuánto retirar (lo que pasa del fondo fijo). «Retirar S/ …» llena el movimiento: solo confírmalo. Con cierre ciego no muestra el monto.", 'caja'],
        'venta_cortada' => ['Si se cortó la conexión al registrar una venta', 'vende', ['se corto', 'franja roja', 'sin conexion', 'no se registro', 'cobre dos veces', 'venta duplicada', 'ya estaba registrada', 'se fue la luz'],
            "1. No cobres otra vez ni cierres la página: la venta queda guardada en el navegador.\n2. Al volver la conexión entra a Nueva venta: dice si quedó registrada (con su número) o te la devuelve armada.\n3. Enviarla de nuevo nunca la duplica. Si el sistema no vuelve: solo efectivo y anótalas en la hoja de contingencia.", 'nueva_venta'],
        'papel_ticket' => ['Imprimir en papel de 58 u 80 mm o en PDF', 'vende', ['58 mm', '80 mm', 'papel', 'ancho del ticket', 'ticketera', 'guardar en pdf', 'ticket interno'],
            "En el comprobante, «Imprimir en ticketera» imprime y vuelve. «Ver ticket / PDF» lo abre para guardarlo: ahí eliges 58 mm u 80 mm y la caja lo recuerda. Mientras no se envíe a SUNAT sale como TICKET INTERNO.", 'ventas'],
        'recibir_orden' => ['Recibir una orden de compra', 'almacen', ['recibir orden', 'recibo una orden de compra', 'recibo una orden', 'recibir una orden', 'orden que llego', 'llego el pedido', 'llego la orden', 'por recibir', 'orden parcial', 'recibir pedido'],
            "1. Ingresos → Por recibir → Recibir (o en la orden, Recibir mercadería).\n2. El proveedor y lo que falta ya vienen: anota el documento y corrige cada cantidad a lo que llegó (0 si no vino).\n3. Registrar ingreso: el stock sube y la orden pasa a PARCIAL o RECIBIDA. Lo que llegue de más va en un ingreso aparte.", 'ingresos'],
        'reponer' => ['Saber qué pedir y cuánto', 'almacen', ['que pedir', 'cuanto pedir', 'lista de pedido', 'reponer', 'sugerido', 'que falta'],
            "Productos → Reponer: lo que llegó a su mínimo, cuánto se vende por día, para cuántos días alcanza y cuánto pedir (para 14 días, descontando lo ya pedido). Si otra tienda tiene sobrante, pide un traslado. «Pedir a …» arma la orden con el último costo.", 'reponer'],
        'vencimientos' => ['Revisar lo que está por vencer', 'almacen', ['por vencer', 'vencimiento', 'lote', 'fecha de vencimiento', 'caduca'],
            "Productos → Por vencer: elige 7, 15, 30 o 60 días. Cada lote dice cuándo vence, cuánto queda y su valor al costo. Lo vencido sale en rojo y «Dar de baja lo vencido» lleva a la merma.", 'por_vencer'],
        'proveedor' => ['Registrar o editar un proveedor', 'admin', ['proveedor nuevo', 'agregar proveedor', 'datos del proveedor', 'contacto del proveedor', 'ruc del proveedor'],
            "Compras → Proveedores → Agregar proveedor: RUC, razón social, contacto, teléfono, correo y días de entrega. El directorio es de toda la cadena; un RUC nuevo de un ingreso se agrega solo.", 'compras'],
        'cerrar_orden' => ['Anular o cerrar una orden de compra', 'admin', ['anular orden', 'cerrar orden', 'cancelar pedido', 'no llego el pedido', 'ya no vendra'],
            "Compras → Órdenes de compra → Ver → escribe el motivo → «Anular orden» (no llegó nada) o «Cerrar orden» (llegó una parte y el resto ya no vendrá). Queda en la Auditoría.", 'compras'],
        'arqueo' => ['Revisar el arqueo de una caja', 'admin', ['arqueo', 'sobrante', 'faltante', 'diferencia de caja', 'revisar turno', 'cuenta del turno'],
            "Caja: cada turno con el efectivo esperado y el contado. «Ver» abre la cuenta (inicial, cobrado, devoluciones, ingresos y retiros con su motivo) y dice si hubo sobrante o faltante.", 'caja'],
        'margen' => ['Ver la utilidad y el margen de cada producto', 'admin', ['margen', 'costo de lo vendido', 'sin costo', 'cuanto gano', 'cuanto ganamos', 'rentable'],
            "Administración → Utilidad: ventas netas, costo de lo vendido, utilidad y margen de cada producto y de cada tienda. El costo sale del promedio de los Ingresos. Margen bajo 15 % en amarillo y negativo en rojo.", 'utilidad'],
        'foto' => ['Poner o cambiar la foto de un producto', 'admin', ['foto', 'imagen', 'fotografia'],
            "Catálogo → toca el producto → Foto del producto → elige un JPG, PNG o WebP de hasta 2 MB (se guarda sola, achicada). «Quitar foto» vuelve al ícono. Mejor cuadrada y sobre fondo claro.", 'catalogo'],
        'importar' => ['Cambiar muchos productos o precios con Excel', 'admin', ['importar', 'csv', 'muchos productos', 'precios en lote', 'cargar catalogo', 'subir catalogo'],
            "1. Catálogo → Importar CSV → Descargar el catálogo actual.\n2. Edítalo en Excel (filas nuevas o precios) y guárdalo como CSV.\n3. Súbelo con «Solo revisar» (dice qué cambia y qué línea falla) y luego con «Importar». Lo nuevo entra con stock 0.", 'catalogo'],
        'stock_minimo' => ['Cambiar el stock mínimo de un producto', 'admin', ['stock minimo', 'cambiar minimo', 'alerta de stock', 'cuando avisa'],
            "Catálogo → toca el producto → stock mínimo de tu tienda. Al llegar a ese número sale en Reponer, en el inicio y en mis alertas.", 'catalogo'],
        'tienda_cajas' => ['Agregar cajas o editar los datos de la tienda', 'admin', ['agregar caja', 'nueva caja', 'desactivar caja', 'datos de mi tienda', 'series', 'abrir una tienda'],
            "Tiendas → Mi tienda, cajas y series: edita la dirección y el teléfono, agrega cajas y actívalas o desactívalas. «Abrir una tienda» crea una sede con su caja 1, sus series y su administrador.", null],
        'stock_otra_tienda' => ['Ver el stock en las otras tiendas', 'todos', ['otra tienda', 'otras tiendas', 'stock por tienda', 'en otra sede', 'otro local'],
            "Inventario → Stock: cada producto en las 3 tiendas; REPONER marca lo que está en su mínimo. También pregúntame «¿hay leche Gloria?» y te digo cuánto hay en cada tienda.", 'stock'],
        'atajos' => ['Atajos del teclado y del menú', 'todos', ['atajo', 'teclado', 'tecla', 'buscador', 'menu lateral', 'plegar el menu', 'escape'],
            "F9 registra la venta en Nueva venta. «/» lleva al buscador y Esc lo limpia o cierra un panel. Al pie del menú eliges Fijo, Íconos o Auto. En el celular, abajo están Inicio, Caja, Vender, Ventas y Menú.", null],
        'mensajes' => ['Qué significa un mensaje de error', 'todos', ['error', '403', '404', 'sin permiso', 'formulario vencio', 'no encontrado', 'base de datos no esta'],
            "403 Sin permiso: esa pantalla no es de tu rol o tienda. 404: no existe. «El formulario venció»: recarga y envía otra vez. Error en rojo en el formulario: falta un dato, no se guardó nada. «La base de datos no está disponible»: avisa a soporte.", 'ayuda'],
        'soporte' => ['A quién llamar si algo falla', 'todos', ['soporte', 'llamar', 'tecnico', 'reportar un problema', 'se malogro el sistema'],
            "El contacto de soporte está en el pie de cada página y en Ayuda. Ten a la mano: la pantalla donde estabas, qué hiciste, el mensaje que salió, el número de comprobante y la versión.", 'ayuda'],

        // Buenas practicas de la tienda (no son pasos del sistema: son ideas para trabajar mejor)
        'consejo_peps' => ['Ordenar el estante: primero lo que vence', 'almacen', ['ordenar el estante', 'peps', 'primero en vencer', 'rotacion', 'rotar'],
            "Lo que vence antes va adelante y lo nuevo atrás (primero en vencer, primero en salir). El sistema estima así cuánto queda de cada lote. Revisa Por vencer cada semana y ofrece lo que vence en 15 días antes de que sea merma.", 'por_vencer'],
        'consejo_merma' => ['Bajar las mermas', 'almacen', ['bajar merma', 'bajo las mermas', 'bajar las mermas', 'reducir las mermas', 'reducir merma', 'menos merma', 'perdidas', 'se pierde'],
            "1. Revisa Por vencer cada lunes y pon en oferta lo de 15 días.\n2. Pide para 14 días de venta, no más (Reponer ya lo calcula).\n3. Lo que no se vende hace 30 días: promoción o traslado a otra tienda.\n4. Cuenta seguido lo que más se pierde (Conteo).", 'mermas'],
        'consejo_promocion' => ['Qué poner en promoción', 'admin', ['que poner en oferta', 'vender mas', 'aumentar ventas', 'subir ventas', 'cuando hacer promocion', 'idea de promocion'],
            "Lo que vence en 15 días, lo que no se vende hace 30 días y los pares que se compran juntos (un combo). En las horas tranquilas una oferta atrae gente. Promociones dice en cuántas ventas se usó y cuánto ahorró.", 'promociones'],
        'consejo_turnos' => ['Armar los turnos de caja', 'admin', ['armar turnos', 'cuantos cajeros', 'horario', 'personal en caja', 'cuantas cajas abrir'],
            "Pregúntame «¿cuáles son las horas punta?»: abre más cajas en esas horas y deja el conteo y la reposición para las horas tranquilas. El pronóstico de la semana dice qué días vienen más fuertes.", 'personal'],
        'consejo_caja' => ['Atender más rápido en caja', 'vende', ['atender rapido', 'cola', 'fila', 'mas rapido', 'cliente apurado'],
            "Escanea con el lector, usa los botones de efectivo (Exacto, S/ 20, S/ 50) y F9 para registrar. Abre con sencillo y pide más con un Ingreso de efectivo antes de que falte.", 'nueva_venta'],
    ];

    /**
     * Cada pantalla (la ruta de la URL): su nombre, para que sirve, las guias que se usan ahi y las preguntas rapidas
     * (claves de RespuestasRapidas) que mas sirven ahi. Asi el asistente sabe donde esta el usuario.
     */
    private const PANTALLAS = [
        'inicio'       => ['Inicio', 'el resumen del día de tu tienda', ['asistente', 'atajos'], ['alertas', 'ventas_hoy', 'pronostico', 'stock_bajo']],
        'venta_nueva'  => ['Nueva venta', 'aquí se cobra: productos, boleta o factura y el medio de pago', ['vender', 'cobrar_tarjeta', 'descuento_manual', 'venta_cortada', 'consejo_caja'], ['mas_vendidos', 'datos_tienda']],
        'caja'         => ['Caja', 'abrir y cerrar tu turno, retiros e ingresos de efectivo y el arqueo', ['abrir_caja', 'cerrar_caja', 'movimiento_efectivo', 'retiro_seguridad', 'arqueo'], ['ventas_hoy', 'medios_pago', 'por_cajero']],
        'ventas'       => ['Ventas', 'las boletas y facturas emitidas: verlas, imprimirlas, devolver o anular', ['imprimir', 'devolucion', 'anular', 'papel_ticket'], ['ventas_hoy', 'medios_pago', 'ventas_ayer']],
        'comprobante'  => ['el comprobante', 'una venta: imprimirla, enviarla, devolver productos o anularla', ['imprimir', 'devolucion', 'anular', 'papel_ticket'], ['ventas_hoy']],
        'clientes'     => ['Clientes', 'buscar un cliente por DNI o RUC y ver sus compras', ['cliente'], ['mejores_clientes']],
        'promociones'  => ['Promociones', 'las ofertas vigentes y crear nuevas', ['promocion', 'consejo_promocion'], ['juntos', 'sin_venta', 'por_vencer']],
        'productos'    => ['Catálogo', 'los productos con su precio, foto y stock', ['producto', 'foto', 'importar', 'stock_minimo', 'etiquetas'], ['stock_bajo', 'mas_vendidos', 'sin_venta']],
        'stock'        => ['Stock', 'el stock de cada producto en cada tienda', ['stock_otra_tienda', 'conteo'], ['stock_bajo', 'pedido']],
        'reponer'      => ['Reponer', 'lo que llegó a su mínimo y cuánto pedir', ['reponer', 'orden_compra', 'traslado'], ['pedido', 'stock_bajo']],
        'vencimientos' => ['Por vencer', 'los lotes que vencen pronto', ['vencimientos', 'merma', 'consejo_peps'], ['por_vencer', 'sin_venta']],
        'ingresos'     => ['Ingresos', 'la mercadería que llega de los proveedores', ['ingreso', 'recibir_orden'], ['pedido', 'por_vencer']],
        'ordenes'      => ['Órdenes de compra', 'los pedidos a los proveedores', ['orden_compra', 'recibir_orden', 'cerrar_orden', 'proveedor'], ['pedido', 'stock_bajo']],
        'proveedores'  => ['Proveedores', 'el directorio de proveedores de la cadena', ['proveedor'], ['pedido']],
        'traslados'    => ['Traslados', 'enviar mercadería a otra tienda', ['traslado', 'stock_otra_tienda'], ['stock_bajo', 'sin_venta']],
        'mermas'       => ['Mermas', 'dar de baja lo vencido, roto o robado', ['merma', 'consejo_merma'], ['por_vencer', 'sin_venta']],
        'kardex'       => ['Kardex', 'cada entrada y salida de un producto', ['kardex', 'conteo'], ['stock_bajo']],
        'reportes'     => ['Reportes', 'las ventas de un periodo y sus descargas', ['reportes'], ['ventas_mes', 'mas_vendidos', 'por_cajero', 'tiendas_mes']],
        'estadisticas' => ['Estadísticas', 'las ventas por día de la semana y por hora', ['reportes', 'consejo_turnos'], ['horas_punta', 'pronostico', 'juntos']],
        'utilidad'     => ['Utilidad', 'lo ganado sobre el costo y el margen', ['margen'], ['ventas_mes', 'mas_vendidos']],
        'empleados'    => ['Personal', 'los empleados de la tienda', ['personal', 'clave', 'consejo_turnos'], ['horas_punta', 'por_cajero']],
        'tiendas'      => ['Tiendas', 'las sedes de la cadena, sus cajas y series', ['tienda_cajas'], ['datos_tienda', 'tiendas_mes']],
        'auditoria'    => ['Auditoría', 'quién anuló, cambió precios o permisos, y cuándo', ['auditoria'], []],
        'ayuda'        => ['Ayuda', 'el manual corto y el contacto de soporte', ['soporte', 'sin_internet', 'atajos', 'mensajes'], ['como_usar']],
    ];

    private const RELLENO = ['como', 'donde', 'que', 'hago', 'hacer', 'se', 'puedo', 'para', 'el', 'la', 'los', 'las', 'un', 'una', 'de', 'del',
        'en', 'mi', 'me', 'y', 'a', 'es', 'por', 'cual', 'pasos', 'quiero', 'necesito', 'debo', 'tengo'];

    /** @param string[] $roles lo que puede el usuario: 'vende', 'almacen', 'admin' ('todos' siempre) */
    public function __construct(private array $roles) {}

    private const QUIEN = ['vende' => 'a quien vende en caja', 'almacen' => 'al almacenero o al administrador de la tienda',
        'admin' => 'al administrador de la tienda'];

    /**
     * Las guias que mejor responden la pregunta: la mejor y las que empatan con ella (como mucho $cuantas).
     * Si la mejor es de otro rol, devuelve solo esa con 'permitida' false (para decir quien lo hace, sin los pasos).
     * @return array<int, array{clave: string, titulo: string, pasos: string, pantalla: ?string, permitida: bool, quien: string}>
     */
    public function buscar(string $pregunta, int $cuantas = 2): array
    {
        $texto = ' ' . self::normalizar($pregunta) . ' ';
        $palabras = array_values(array_diff(explode(' ', trim($texto)), self::RELLENO, ['']));
        $puntajes = [];
        foreach (self::GUIAS as $clave => [$titulo, , $claves]) {
            $puntos = 0;
            foreach ($claves as $c) {
                if (str_contains($texto, ' ' . $c)) {
                    $puntos += 3 * count(explode(' ', $c));   // una frase entera vale mas que una palabra suelta
                }
            }
            // Palabras sueltas de la pregunta que aparecen en el titulo ("merma" en "Dar de baja productos (merma)")
            $enTitulo = ' ' . self::normalizar($titulo) . ' ';
            foreach ($palabras as $p) {
                if (mb_strlen($p) >= 4 && str_contains($enTitulo, ' ' . $p)) {
                    $puntos++;
                }
            }
            if ($puntos > 0) {
                $puntajes[$clave] = $puntos;
            }
        }
        if ($puntajes === []) {
            return [];
        }
        arsort($puntajes);
        $guias = self::GUIAS;
        $disponibles = $this->disponibles();
        $mejor = (string) array_key_first($puntajes);
        $dar = fn(string $c): array => ['clave' => $c, 'titulo' => $guias[$c][0], 'pasos' => $guias[$c][3], 'pantalla' => $guias[$c][4],
            'permitida' => isset($disponibles[$c]), 'quien' => self::QUIEN[$guias[$c][1]] ?? 'todos'];
        if (!isset($disponibles[$mejor])) {
            return [$dar($mejor)];
        }
        $empatan = array_keys(array_filter($puntajes, fn(int $p, string $c): bool => $p === $puntajes[$mejor] && isset($disponibles[$c]),
            ARRAY_FILTER_USE_BOTH));
        return array_map($dar, array_slice($empatan, 0, max(1, $cuantas)));
    }

    /** @return array{nombre: string, para: string}|null la pantalla de esa ruta (null si no la conoce) */
    public static function pantalla(string $ruta): ?array
    {
        $p = self::PANTALLAS[$ruta] ?? null;
        return $p === null ? null : ['nombre' => $p[0], 'para' => $p[1]];
    }

    /** @return string[] claves de RespuestasRapidas que mas sirven en esa pantalla, en orden */
    public static function preguntasDePantalla(string $ruta): array
    {
        return self::PANTALLAS[$ruta][3] ?? [];
    }

    /**
     * Las guias de lo que se hace en esa pantalla que este usuario puede usar.
     * @return array<int, array{clave: string, titulo: string, pasos: string, pantalla: ?string, permitida: bool, quien: string}>
     */
    public function deLaPantalla(string $ruta): array
    {
        $disponibles = $this->disponibles();
        $guias = [];
        foreach (self::PANTALLAS[$ruta][2] ?? [] as $c) {
            if (isset($disponibles[$c])) {
                $g = $disponibles[$c];
                $guias[] = ['clave' => $c, 'titulo' => $g[0], 'pasos' => $g[3], 'pantalla' => $g[4], 'permitida' => true, 'quien' => self::QUIEN[$g[1]] ?? 'todos'];
            }
        }
        return $guias;
    }

    /** @return array<int, string> los titulos de las guias que este usuario puede usar */
    public function titulos(): array
    {
        return array_values(array_map(fn(array $g): string => $g[0], $this->disponibles()));
    }

    // ¿Pregunta como hacer algo? ("como abro la caja", "donde veo el kardex", "pasos para anular")
    public static function esComoHacer(string $pregunta): bool
    {
        $t = ' ' . self::normalizar($pregunta) . ' ';
        foreach ([' como ', ' donde ', ' pasos ', ' que hago ', ' se puede ', ' puedo ', ' por que ', ' que puedes ', ' que sabes ', ' para que sirves '] as $pista) {
            if (str_contains($t, $pista)) {
                return true;
            }
        }
        return false;
    }

    /** @return array<string, array{0: string, 1: string, 2: string[], 3: string, 4: ?string}> */
    private function disponibles(): array
    {
        return array_filter(self::GUIAS, fn(array $g): bool => $g[1] === 'todos' || in_array($g[1], $this->roles, true));
    }

    public static function normalizar(string $texto): string
    {
        $texto = mb_strtolower(trim($texto));
        $texto = strtr($texto, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
        return trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9 ]+/', ' ', $texto) ?? '') ?? '');
    }
}
