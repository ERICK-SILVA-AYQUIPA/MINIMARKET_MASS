package pe.mass.minimarket.ui.venta

import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.animateColorAsState
import androidx.compose.animation.core.Spring
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.spring
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.slideInVertically
import androidx.compose.animation.slideOutVertically
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ReceiptLong
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material.icons.filled.CreditCard
import androidx.compose.material.icons.filled.Delete
import androidx.compose.material.icons.filled.Payments
import androidx.compose.material.icons.filled.PointOfSale
import androidx.compose.material.icons.filled.QrCode2
import androidx.compose.material.icons.filled.Remove
import androidx.compose.material.icons.filled.Search
import androidx.compose.material.icons.filled.ShoppingCart
import androidx.compose.material.icons.filled.Videocam
import androidx.compose.material.icons.filled.VideocamOff
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.FilledTonalIconButton
import androidx.compose.material3.FilterChip
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Scaffold
import androidx.compose.material3.SegmentedButton
import androidx.compose.material3.SegmentedButtonDefaults
import androidx.compose.material3.SingleChoiceSegmentedButtonRow
import androidx.compose.material3.SnackbarDuration
import androidx.compose.material3.SnackbarHost
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.SnackbarResult
import androidx.compose.material3.Surface
import androidx.compose.material3.SuggestionChip
import androidx.compose.material3.SwipeToDismissBox
import androidx.compose.material3.SwipeToDismissBoxValue
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.rememberModalBottomSheetState
import androidx.compose.material3.rememberSwipeToDismissBoxState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberUpdatedState
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.scale
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import pe.mass.minimarket.data.api.EstadoCaja
import pe.mass.minimarket.data.api.VentaDetalle
import pe.mass.minimarket.dominio.ItemCarrito
import pe.mass.minimarket.dominio.montosSugeridos
import pe.mass.minimarket.dominio.soles
import pe.mass.minimarket.dominio.textoCantidad
import pe.mass.minimarket.dominio.textoMonto
import pe.mass.minimarket.ui.comun.Aviso
import pe.mass.minimarket.ui.comun.BarraSuperior
import pe.mass.minimarket.ui.comun.BotonGrande
import pe.mass.minimarket.ui.comun.Cargando
import pe.mass.minimarket.ui.comun.DialogoBuscarProducto
import pe.mass.minimarket.ui.comun.DialogoProductoNuevo
import pe.mass.minimarket.ui.comun.EstadoError
import pe.mass.minimarket.ui.comun.EstadoVacio
import pe.mass.minimarket.ui.comun.Inicial
import pe.mass.minimarket.ui.comun.Pastilla
import pe.mass.minimarket.ui.comun.PreguntaStock
import pe.mass.minimarket.ui.comun.Tarjeta
import pe.mass.minimarket.ui.comun.TarjetaAviso
import pe.mass.minimarket.ui.comun.TipoAviso
import pe.mass.minimarket.ui.comun.fechaCorta
import pe.mass.minimarket.ui.comun.preferencias
import pe.mass.minimarket.ui.comun.soloHora
import pe.mass.minimarket.ui.comun.vistaModelo
import pe.mass.minimarket.ui.escaner.PanelEscaner
import pe.mass.minimarket.ui.escaner.rememberConfirmacion
import pe.mass.minimarket.ui.tema.Mass

@Composable
fun PantallaVenta(onAtras: () -> Unit, onVerVenta: (Long) -> Unit, onHistorial: () -> Unit) {
    val vm = vistaModelo { VentaViewModel(it) }
    val estado by vm.estado.collectAsState()
    val prefs = preferencias()
    val confirmacion = rememberConfirmacion()
    val mensajes = remember { SnackbarHostState() }
    var buscar by remember { mutableStateOf(false) }
    var verCamara by remember { mutableStateOf(true) }
    var cobrar by remember { mutableStateOf(false) }
    var editar by remember { mutableStateOf<ItemCarrito?>(null) }

    // Quitar un producto se puede deshacer durante unos segundos
    LaunchedEffect(estado.quitado) {
        val q = estado.quitado ?: return@LaunchedEffect
        val r = mensajes.showSnackbar(
            "Quitaste ${q.item.producto.nombre}",
            actionLabel = "Deshacer",
            withDismissAction = true,
            duration = SnackbarDuration.Short,
        )
        if (r == SnackbarResult.ActionPerformed) vm.deshacerQuitar() else vm.olvidarQuitado()
    }

    val registrada = estado.registrada
    if (registrada != null) {
        PantallaVentaRegistrada(registrada, onNueva = vm::nuevaVenta, onVer = { onVerVenta(registrada.idVenta) }, onSalir = onAtras)
        return
    }

    val turno = estado.caja?.turno
    Scaffold(
        topBar = {
            BarraSuperior(
                "Nueva venta",
                onAtras = onAtras,
                subtitulo = turno?.let { "Caja ${it.numeroCaja ?: it.idCaja} · desde las ${soloHora(it.apertura)}" },
                acciones = {
                    if (turno != null) {
                        IconButton(onClick = { buscar = true }) { Icon(Icons.Filled.Search, contentDescription = "Buscar por nombre") }
                        IconButton(onClick = { verCamara = !verCamara }) {
                            Icon(
                                if (verCamara) Icons.Filled.VideocamOff else Icons.Filled.Videocam,
                                contentDescription = if (verCamara) "Ocultar cámara" else "Mostrar cámara",
                            )
                        }
                    }
                },
            )
        },
        snackbarHost = { SnackbarHost(mensajes) },
        bottomBar = {
            if (turno != null) {
                BarraTotal(estado, onCobrar = { cobrar = true }, onReintentar = vm::cobrar, onDescartar = { vm.descartarPendiente(); onHistorial() })
            }
        },
    ) { relleno ->
        val caja = estado.caja
        when {
            estado.cargandoCaja -> Cargando(Modifier.padding(relleno))
            caja == null -> EstadoError(
                estado.aviso?.texto ?: "No se pudo leer el estado de tu caja.",
                onReintentar = vm::cargarCaja,
                modifier = Modifier.padding(relleno),
            )
            caja.turno == null -> AbrirTurno(caja, estado, vm, Modifier.padding(relleno))
            else -> LazyColumn(
                Modifier.fillMaxSize().padding(relleno),
                contentPadding = PaddingValues(start = 16.dp, end = 16.dp, top = 4.dp, bottom = 16.dp),
                verticalArrangement = Arrangement.spacedBy(10.dp),
            ) {
                item(key = "escaner") {
                    AnimatedVisibility(
                        visible = verCamara && !estado.pendiente,
                        enter = fadeIn() + slideInVertically(),
                        exit = fadeOut() + slideOutVertically(),
                    ) {
                        PanelEscaner(
                            onCodigo = { codigo ->
                                vm.leerCodigo(
                                    codigo,
                                    alEncontrar = { producto ->
                                        confirmacion.exito()
                                        prefs.recordar(producto)
                                    },
                                    alFallar = confirmacion::error,
                                )
                            },
                            onBuscarPorNombre = { buscar = true },
                            activo = !estado.consultando && !estado.cobrando && estado.codigoNuevo == null,
                            altoCamara = 190,
                        )
                    }
                }
                item(key = "aviso") { TarjetaAviso(estado.aviso, onCerrar = vm::cerrarAviso) }
                if (estado.carrito.vacio) {
                    item(key = "vacio") {
                        EstadoVacio(
                            Icons.Filled.ShoppingCart,
                            "El carrito está vacío",
                            "Escanea el primer producto o búscalo por nombre.",
                            accion = if (verCamara) null else "Mostrar cámara",
                            onAccion = { verCamara = true },
                        )
                    }
                } else {
                    item(key = "titulo") {
                        Text(
                            "${estado.carrito.unidades} producto(s) · desliza para quitar",
                            style = MaterialTheme.typography.labelMedium,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                    }
                }
                // Lo ultimo escaneado arriba, junto a la camara
                items(estado.carrito.items.asReversed(), key = { it.producto.idProducto }) { item ->
                    val bloqueada = estado.pendiente || estado.cobrando
                    FilaCarrito(
                        item,
                        bloqueada = bloqueada,
                        onSumar = { confirmacion.toque(); vm.sumar(item.producto.idProducto) },
                        onRestar = { confirmacion.toque(); vm.restar(item.producto.idProducto) },
                        onQuitar = { vm.quitar(item.producto.idProducto) },
                        onEditar = { editar = item },
                        modifier = Modifier.animateItem(),
                    )
                }
            }
        }
    }

    if (buscar) {
        DialogoBuscarProducto(
            onElegir = { p ->
                buscar = false
                if (vm.agregar(p)) {
                    confirmacion.exito()
                    prefs.recordar(p)
                }
            },
            onCerrar = { buscar = false },
        )
    }
    estado.codigoNuevo?.let { codigo ->
        DialogoProductoNuevo(
            codigo,
            stock = PreguntaStock.OBLIGATORIO,
            onCreado = { if (vm.productoCreado(it)) confirmacion.exito() },
            onCerrar = vm::cancelarProductoNuevo,
        )
    }
    editar?.let { item ->
        DialogoCantidad(item, onGuardar = { texto -> vm.fijarCantidad(item.producto.idProducto, texto).also { if (it == null) editar = null } }, onCerrar = { editar = null })
    }
    if (cobrar && !estado.carrito.vacio) {
        HojaCobro(
            estado = estado,
            vm = vm,
            onCerrar = { cobrar = false },
            onCobrar = {
                cobrar = false
                vm.cobrar()
            },
        )
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun AbrirTurno(caja: EstadoCaja, estado: EstadoVenta, vm: VentaViewModel, modifier: Modifier) {
    val activas = caja.cajas.filter { it.estado == "ACTIVA" }
    var elegida by remember { mutableStateOf(activas.firstOrNull()?.idCaja) }
    var monto by remember { mutableStateOf("") }
    if (!caja.puedeVender) {
        EstadoVacio(Icons.Filled.PointOfSale, "Solo un cajero registra ventas", "Tu rol puede consultar precios y ver el historial.", modifier)
        return
    }
    Column(
        modifier.fillMaxSize().verticalScroll(rememberScrollState()).imePadding().padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(16.dp),
    ) {
        Column(Modifier.fillMaxWidth(), horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.spacedBy(8.dp)) {
            Surface(shape = CircleShape, color = MaterialTheme.colorScheme.primaryContainer, modifier = Modifier.size(80.dp)) {
                Box(contentAlignment = Alignment.Center) {
                    Icon(Icons.Filled.PointOfSale, contentDescription = null, modifier = Modifier.size(40.dp), tint = MaterialTheme.colorScheme.onPrimaryContainer)
                }
            }
            Text("Abre tu turno de caja", style = MaterialTheme.typography.headlineSmall)
            Text(
                "Para vender necesitas un turno abierto, igual que en la página web. Cuenta el sencillo con el que empiezas.",
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                textAlign = TextAlign.Center,
            )
        }
        Tarjeta(Modifier.fillMaxWidth()) {
            Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
                Text("Caja", style = MaterialTheme.typography.labelLarge)
                if (activas.isEmpty()) {
                    Text("Tu tienda no tiene cajas activas. Pide al administrador que active una en la web.")
                } else {
                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        activas.forEach { c ->
                            FilterChip(selected = elegida == c.idCaja, onClick = { elegida = c.idCaja }, label = { Text("Caja ${c.numeroCaja}") })
                        }
                    }
                }
                OutlinedTextField(
                    value = monto,
                    onValueChange = { monto = it.take(11) },
                    label = { Text("Monto inicial") },
                    prefix = { Text("S/ ") },
                    singleLine = true,
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Decimal),
                    modifier = Modifier.fillMaxWidth(),
                )
            }
        }
        TarjetaAviso(estado.aviso)
        BotonGrande(
            "Abrir turno",
            icono = Icons.Filled.PointOfSale,
            onClick = { elegida?.let { vm.abrirTurno(it, monto) } },
            habilitado = elegida != null && monto.isNotBlank(),
            ocupado = estado.abriendoTurno,
        )
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun FilaCarrito(
    item: ItemCarrito,
    bloqueada: Boolean,
    onSumar: () -> Unit,
    onRestar: () -> Unit,
    onQuitar: () -> Unit,
    onEditar: () -> Unit,
    modifier: Modifier = Modifier,
) {
    val p = item.producto
    // El estado del gesto se recuerda una vez: lee siempre los valores de ahora
    val bloqueadaAhora by rememberUpdatedState(bloqueada)
    val quitarAhora by rememberUpdatedState(onQuitar)
    val deslizar = rememberSwipeToDismissBoxState(
        confirmValueChange = { valor ->
            if (valor == SwipeToDismissBoxValue.EndToStart && !bloqueadaAhora) {
                quitarAhora()
                true
            } else {
                false
            }
        },
    )
    SwipeToDismissBox(
        state = deslizar,
        modifier = modifier,
        enableDismissFromStartToEnd = false,
        backgroundContent = {
            val fondo by animateColorAsState(
                if (deslizar.targetValue == SwipeToDismissBoxValue.EndToStart) Mass.colores.peligroFondo else MaterialTheme.colorScheme.surface,
                label = "fondo",
            )
            Box(
                Modifier.fillMaxSize().background(fondo, MaterialTheme.shapes.medium).padding(horizontal = 24.dp),
                contentAlignment = Alignment.CenterEnd,
            ) {
                Icon(Icons.Filled.Delete, contentDescription = null, tint = Mass.colores.peligro)
            }
        },
    ) {
        Tarjeta(Modifier.fillMaxWidth()) {
            Row(Modifier.padding(start = 12.dp, top = 10.dp, bottom = 10.dp, end = 8.dp), verticalAlignment = Alignment.CenterVertically) {
                Inicial(p.nombre, tamano = 40)
                Spacer(Modifier.width(10.dp))
                Column(Modifier.weight(1f)) {
                    Text(p.nombre, style = MaterialTheme.typography.titleSmall, maxLines = 2, overflow = TextOverflow.Ellipsis)
                    Text(
                        "${p.precioVenta.soles()}${if (p.alPeso) " / kg" else ""}" +
                            if (item.superaStock) " · solo hay ${p.stock.textoCantidad(p.alPeso)}" else "",
                        style = MaterialTheme.typography.bodySmall,
                        color = if (item.superaStock) Mass.colores.alerta else MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                    Text(item.importe.soles(), style = MaterialTheme.typography.titleMedium)
                }
                // Selector de cantidad: - [cantidad] +
                Surface(shape = CircleShape, color = MaterialTheme.colorScheme.surfaceContainerHigh) {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        FilledTonalIconButton(onClick = onRestar, enabled = !bloqueada, modifier = Modifier.size(36.dp)) {
                            Icon(Icons.Filled.Remove, contentDescription = "Uno menos")
                        }
                        Text(
                            item.cantidad.textoCantidad(p.alPeso),
                            style = MaterialTheme.typography.titleMedium,
                            textAlign = TextAlign.Center,
                            modifier = Modifier.clickable(enabled = !bloqueada, onClick = onEditar).padding(horizontal = 10.dp),
                        )
                        FilledTonalIconButton(onClick = onSumar, enabled = !bloqueada, modifier = Modifier.size(36.dp)) {
                            Icon(Icons.Filled.Add, contentDescription = "Uno más")
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun BarraTotal(estado: EstadoVenta, onCobrar: () -> Unit, onReintentar: () -> Unit, onDescartar: () -> Unit) {
    Surface(color = MaterialTheme.colorScheme.surfaceContainer, shadowElevation = 8.dp, tonalElevation = 2.dp) {
        Column(Modifier.fillMaxWidth().navigationBarsPadding().padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Column(Modifier.weight(1f)) {
                    Text("Total", style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    Text(
                        if (estado.descuento.signum() > 0) "${estado.carrito.unidades} producto(s) · ahorra ${estado.descuento.soles()}"
                        else "${estado.carrito.unidades} producto(s)",
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
                Text(estado.totalCobrar.soles(), style = MaterialTheme.typography.headlineMedium)
            }
            if (estado.pendiente) {
                BotonGrande("Reintentar envío", onClick = onReintentar, ocupado = estado.cobrando)
                OutlinedButton(onClick = onDescartar, modifier = Modifier.fillMaxWidth()) { Text("Revisar en el historial") }
            } else {
                BotonGrande(
                    if (estado.carrito.vacio) "Cobrar" else "Cobrar ${estado.totalCobrar.soles()}",
                    onClick = onCobrar,
                    icono = Icons.Filled.Payments,
                    habilitado = !estado.carrito.vacio,
                    ocupado = estado.cobrando,
                )
            }
        }
    }
}

@Composable
private fun DialogoCantidad(item: ItemCarrito, onGuardar: (String) -> String?, onCerrar: () -> Unit) {
    var texto by remember { mutableStateOf(item.cantidad.textoCantidad(item.producto.alPeso)) }
    var error by remember { mutableStateOf<String?>(null) }
    AlertDialog(
        onDismissRequest = onCerrar,
        title = { Text(item.producto.nombre) },
        text = {
            OutlinedTextField(
                value = texto,
                onValueChange = { texto = it.take(11); error = null },
                label = { Text(if (item.producto.alPeso) "Kilos (hasta 3 decimales)" else "Cantidad") },
                isError = error != null,
                supportingText = { error?.let { Text(it) } },
                singleLine = true,
                keyboardOptions = KeyboardOptions(keyboardType = if (item.producto.alPeso) KeyboardType.Decimal else KeyboardType.Number),
            )
        },
        confirmButton = { TextButton(onClick = { error = onGuardar(texto) }) { Text("Guardar") } },
        dismissButton = { TextButton(onClick = onCerrar) { Text("Cancelar") } },
    )
}

/** Opciones de un grupo de botones segmentados: (valor, texto). */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun <T> Segmentos(opciones: List<Pair<T, String>>, elegido: T, onElegir: (T) -> Unit, iconos: List<ImageVector>? = null) {
    SingleChoiceSegmentedButtonRow(Modifier.fillMaxWidth()) {
        opciones.forEachIndexed { i, (valor, texto) ->
            SegmentedButton(
                selected = elegido == valor,
                onClick = { onElegir(valor) },
                shape = SegmentedButtonDefaults.itemShape(i, opciones.size),
                icon = {
                    val icono = iconos?.getOrNull(i)
                    if (elegido == valor || icono == null) SegmentedButtonDefaults.Icon(active = elegido == valor)
                    else Icon(icono, contentDescription = null, modifier = Modifier.size(SegmentedButtonDefaults.IconSize))
                },
            ) { Text(texto, maxLines = 1) }
        }
    }
}

/** Cobro en una hoja que sube desde abajo: comprobante, metodo de pago y vuelto, con atajos de billetes. */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun HojaCobro(estado: EstadoVenta, vm: VentaViewModel, onCerrar: () -> Unit, onCobrar: () -> Unit) {
    val c = estado.cobro
    val total = estado.totalCobrar
    val problema = c.problema(total)
    val hoja = rememberModalBottomSheetState(skipPartiallyExpanded = true)

    ModalBottomSheet(onDismissRequest = onCerrar, sheetState = hoja) {
        Column(
            Modifier.fillMaxWidth().verticalScroll(rememberScrollState()).imePadding().padding(start = 20.dp, end = 20.dp, bottom = 24.dp),
            verticalArrangement = Arrangement.spacedBy(14.dp),
        ) {
            Column(Modifier.fillMaxWidth(), horizontalAlignment = Alignment.CenterHorizontally) {
                Text("Total a cobrar", style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.onSurfaceVariant)
                Text(total.soles(), style = MaterialTheme.typography.displayMedium)
                Text("${estado.carrito.unidades} producto(s)", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
            }
            // Promociones de hoy: subtotal de lista y cada descuento, como en el ticket
            if (estado.descuento.signum() > 0) {
                Column(Modifier.fillMaxWidth(), verticalArrangement = Arrangement.spacedBy(2.dp)) {
                    Text(
                        "Subtotal ${estado.carrito.total.soles()} · Promociones −${estado.descuento.soles()}",
                        style = MaterialTheme.typography.bodyMedium,
                        color = Mass.colores.exito,
                    )
                    estado.textosDescuento.forEach { texto ->
                        Text(texto, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    }
                }
            }
            if (estado.carrito.sinStockSuficiente.isNotEmpty()) {
                TarjetaAviso(
                    Aviso(
                        "${estado.carrito.sinStockSuficiente.joinToString { it.producto.nombre }} pasa(n) el stock que conoce la app. El servidor lo revisará.",
                        TipoAviso.ALERTA,
                    ),
                )
            }

            Text("Comprobante", style = MaterialTheme.typography.labelLarge)
            Segmentos(listOf("B" to "Boleta", "F" to "Factura"), c.tipoComprobante, { t -> vm.cobro { it.copy(tipoComprobante = t) } })
            OutlinedTextField(
                value = c.documento,
                onValueChange = { v -> vm.cobro { it.copy(documento = v.filter(Char::isDigit).take(11)) } },
                label = { Text(if (c.tipoComprobante == "F") "RUC del cliente" else "DNI o RUC (opcional)") },
                singleLine = true,
                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                modifier = Modifier.fillMaxWidth(),
            )

            Text("Método de pago", style = MaterialTheme.typography.labelLarge)
            Segmentos(
                MetodoPago.entries.map { it to if (it == MetodoPago.SIP) "Sip" else it.texto },
                c.metodo,
                { m -> vm.cobro { it.copy(metodo = m) } },
                iconos = listOf(Icons.Filled.Payments, Icons.Filled.CreditCard, Icons.Filled.QrCode2),
            )
            when (c.metodo) {
                MetodoPago.EFECTIVO -> {
                    OutlinedTextField(
                        value = c.recibido,
                        onValueChange = { v -> vm.cobro { it.copy(recibido = v.take(11)) } },
                        label = { Text("Recibido") },
                        prefix = { Text("S/ ") },
                        singleLine = true,
                        keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Decimal),
                        modifier = Modifier.fillMaxWidth(),
                    )
                    // Atajos: monto exacto y los billetes con los que suele pagar el cliente
                    Row(horizontalArrangement = Arrangement.spacedBy(6.dp), modifier = Modifier.fillMaxWidth()) {
                        montosSugeridos(total, cuantos = 4).forEach { m ->
                            val texto = m.textoMonto()
                            SuggestionChip(
                                onClick = { vm.cobro { it.copy(recibido = texto) } },
                                label = { Text(if (m.compareTo(total) == 0) "Exacto" else "S/ ${m.stripTrailingZeros().toPlainString()}", maxLines = 1) },
                                modifier = Modifier.weight(1f),
                            )
                        }
                    }
                    val vuelto = c.vuelto(total)
                    AnimatedVisibility(visible = vuelto != null) {
                        Surface(color = Mass.colores.exitoFondo, contentColor = Mass.colores.exito, shape = MaterialTheme.shapes.medium, modifier = Modifier.fillMaxWidth()) {
                            Row(Modifier.padding(16.dp), verticalAlignment = Alignment.CenterVertically) {
                                Text("Vuelto", style = MaterialTheme.typography.titleMedium, modifier = Modifier.weight(1f))
                                Text((vuelto ?: total).soles(), style = MaterialTheme.typography.headlineMedium)
                            }
                        }
                    }
                }
                MetodoPago.TARJETA -> {
                    Text(
                        "Cobra con el POS de la tienda y copia los datos del voucher. La app no cobra la tarjeta.",
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                    Segmentos(listOf("DEBITO" to "Débito", "CREDITO" to "Crédito"), c.tarjetaTipo, { t -> vm.cobro { it.copy(tarjetaTipo = t) } })
                    OutlinedTextField(
                        value = c.tarjetaMarca, onValueChange = { v -> vm.cobro { it.copy(tarjetaMarca = v.take(20)) } },
                        label = { Text("Marca (VISA, MASTERCARD…)") }, singleLine = true, modifier = Modifier.fillMaxWidth(),
                    )
                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        OutlinedTextField(
                            value = c.tarjetaUltimos4, onValueChange = { v -> vm.cobro { it.copy(tarjetaUltimos4 = v.filter(Char::isDigit).take(4)) } },
                            label = { Text("Últimos 4") }, singleLine = true, keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                            modifier = Modifier.weight(1f),
                        )
                        OutlinedTextField(
                            value = c.tarjetaAutorizacion, onValueChange = { v -> vm.cobro { it.copy(tarjetaAutorizacion = v.take(10)) } },
                            label = { Text("Autorización") }, singleLine = true, modifier = Modifier.weight(1f),
                        )
                    }
                }
                MetodoPago.SIP -> {
                    Text(
                        "El cliente paga con el QR o la tarjeta Sip; copia el código de operación. La app no procesa el pago.",
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                    Segmentos(listOf("QR" to "QR", "TARJETA_SIP" to "Tarjeta Sip"), c.sipModalidad, { m -> vm.cobro { it.copy(sipModalidad = m) } })
                    OutlinedTextField(
                        value = c.sipOperacion, onValueChange = { v -> vm.cobro { it.copy(sipOperacion = v.take(30)) } },
                        label = { Text("Código de operación") }, singleLine = true, modifier = Modifier.fillMaxWidth(),
                    )
                }
            }
            problema?.let { Text(it, color = MaterialTheme.colorScheme.error, style = MaterialTheme.typography.bodySmall) }
            BotonGrande("Registrar venta", onClick = onCobrar, icono = Icons.Filled.CheckCircle, habilitado = problema == null)
            TextButton(onClick = onCerrar, modifier = Modifier.fillMaxWidth()) { Text("Volver al carrito", fontWeight = FontWeight.SemiBold) }
        }
    }
}

@Composable
private fun PantallaVentaRegistrada(venta: VentaDetalle, onNueva: () -> Unit, onVer: () -> Unit, onSalir: () -> Unit) {
    // El check "rebota" al aparecer: confirma de un vistazo que la venta quedo registrada
    var mostrado by remember { mutableStateOf(false) }
    LaunchedEffect(Unit) { mostrado = true }
    val escala by animateFloatAsState(
        if (mostrado) 1f else 0.3f,
        animationSpec = spring(dampingRatio = Spring.DampingRatioMediumBouncy, stiffness = Spring.StiffnessLow),
        label = "check",
    )
    Scaffold(topBar = { BarraSuperior("Venta registrada", onAtras = onSalir) }) { relleno ->
        Column(
            Modifier.fillMaxSize().padding(relleno).verticalScroll(rememberScrollState()).padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(14.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
        ) {
            Spacer(Modifier.height(8.dp))
            Surface(shape = CircleShape, color = Mass.colores.exitoFondo, modifier = Modifier.size(112.dp).scale(escala)) {
                Box(contentAlignment = Alignment.Center) {
                    Icon(Icons.Filled.CheckCircle, contentDescription = null, tint = Mass.colores.exito, modifier = Modifier.size(72.dp))
                }
            }
            Text("¡Venta registrada!", style = MaterialTheme.typography.headlineSmall)
            Pastilla(venta.tipo + " " + venta.comprobante, MaterialTheme.colorScheme.secondaryContainer, MaterialTheme.colorScheme.onSecondaryContainer)
            Text(venta.total.soles(), style = MaterialTheme.typography.displayMedium)
            Tarjeta(Modifier.fillMaxWidth()) {
                Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    venta.pagos.forEach { p ->
                        Row {
                            Text(p.metodo, style = MaterialTheme.typography.bodyLarge, modifier = Modifier.weight(1f))
                            Text(p.monto.soles(), style = MaterialTheme.typography.bodyLarge)
                        }
                        p.vuelto?.let {
                            Surface(color = Mass.colores.exitoFondo, contentColor = Mass.colores.exito, shape = MaterialTheme.shapes.small, modifier = Modifier.fillMaxWidth()) {
                                Row(Modifier.padding(12.dp), verticalAlignment = Alignment.CenterVertically) {
                                    Text("Vuelto", style = MaterialTheme.typography.titleMedium, modifier = Modifier.weight(1f))
                                    Text(it.soles(), style = MaterialTheme.typography.headlineSmall)
                                }
                            }
                        }
                    }
                    Text(
                        "${venta.cliente} · ${fechaCorta(venta.fechaHora)}",
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
            }
            Text(
                "La venta ya está en el servidor: la página web la muestra y el stock bajó.",
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                textAlign = TextAlign.Center,
            )
            BotonGrande("Nueva venta", icono = Icons.Filled.PointOfSale, onClick = onNueva)
            OutlinedButton(onClick = onVer, modifier = Modifier.fillMaxWidth().height(48.dp), shape = MaterialTheme.shapes.medium) {
                Icon(Icons.AutoMirrored.Filled.ReceiptLong, contentDescription = null)
                Spacer(Modifier.width(8.dp))
                Text("Ver detalle del comprobante")
            }
        }
    }
}
