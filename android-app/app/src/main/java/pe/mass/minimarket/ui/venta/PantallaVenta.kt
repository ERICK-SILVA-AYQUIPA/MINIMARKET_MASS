package pe.mass.minimarket.ui.venta

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ReceiptLong
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material.icons.filled.Delete
import androidx.compose.material.icons.filled.PointOfSale
import androidx.compose.material.icons.filled.QrCodeScanner
import androidx.compose.material.icons.filled.Remove
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.FilterChip
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import pe.mass.minimarket.data.api.EstadoCaja
import pe.mass.minimarket.data.api.VentaDetalle
import pe.mass.minimarket.dominio.ItemCarrito
import pe.mass.minimarket.dominio.soles
import pe.mass.minimarket.dominio.textoCantidad
import pe.mass.minimarket.ui.comun.BarraSuperior
import pe.mass.minimarket.ui.comun.BotonGrande
import pe.mass.minimarket.ui.comun.Cargando
import pe.mass.minimarket.ui.comun.DialogoBuscarProducto
import pe.mass.minimarket.ui.comun.TarjetaAviso
import pe.mass.minimarket.ui.comun.fechaCorta
import pe.mass.minimarket.ui.comun.vistaModelo
import pe.mass.minimarket.ui.escaner.PanelEscaner
import pe.mass.minimarket.ui.escaner.rememberConfirmacion
import pe.mass.minimarket.ui.tema.Ambar
import pe.mass.minimarket.ui.tema.Tinta
import pe.mass.minimarket.ui.tema.Verde

@Composable
fun PantallaVenta(onAtras: () -> Unit, onVerVenta: (Long) -> Unit, onHistorial: () -> Unit) {
    val vm = vistaModelo { VentaViewModel(it) }
    val estado by vm.estado.collectAsState()
    val confirmacion = rememberConfirmacion()
    var buscar by remember { mutableStateOf(false) }
    var verCamara by remember { mutableStateOf(true) }
    var cobrar by remember { mutableStateOf(false) }
    var editar by remember { mutableStateOf<ItemCarrito?>(null) }

    val registrada = estado.registrada
    if (registrada != null) {
        PantallaVentaRegistrada(registrada, onNueva = vm::nuevaVenta, onVer = { onVerVenta(registrada.idVenta) }, onSalir = onAtras)
        return
    }

    Scaffold(
        topBar = {
            BarraSuperior("Nueva venta", onAtras = onAtras, acciones = {
                IconButton(onClick = { verCamara = !verCamara }) { Icon(Icons.Filled.QrCodeScanner, contentDescription = "Mostrar u ocultar cámara") }
            })
        },
        bottomBar = {
            if (estado.caja?.turno != null) {
                BarraTotal(estado, onCobrar = { cobrar = true }, onReintentar = vm::cobrar, onDescartar = { vm.descartarPendiente(); onHistorial() })
            }
        },
    ) { relleno ->
        val caja = estado.caja
        when {
            estado.cargandoCaja -> Cargando(Modifier.padding(relleno))
            caja == null -> Column(Modifier.padding(relleno).padding(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
                TarjetaAviso(estado.aviso)
                BotonGrande("Reintentar", onClick = vm::cargarCaja)
            }
            caja.turno == null -> AbrirTurno(caja, estado, vm, Modifier.padding(relleno))
            else -> LazyColumn(
                Modifier.fillMaxSize().padding(relleno),
                contentPadding = PaddingValues(16.dp),
                verticalArrangement = Arrangement.spacedBy(10.dp),
            ) {
                item {
                    Text(
                        "Caja ${caja.turno.numeroCaja ?: caja.turno.idCaja} · turno abierto desde ${fechaCorta(caja.turno.apertura)}",
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
                if (verCamara && !estado.pendiente) {
                    item {
                        PanelEscaner(
                            onCodigo = { codigo -> vm.leerCodigo(codigo, alEncontrar = confirmacion::exito, alFallar = confirmacion::error) },
                            onBuscarPorNombre = { buscar = true },
                            activo = !estado.consultando && !estado.cobrando,
                            altoCamara = 180,
                        )
                    }
                }
                item { TarjetaAviso(estado.aviso, onCerrar = vm::cerrarAviso) }
                if (estado.carrito.vacio) {
                    item { Text("Escanea el primer producto para empezar la venta.", color = MaterialTheme.colorScheme.onSurfaceVariant) }
                }
                items(estado.carrito.items, key = { it.producto.idProducto }) { item ->
                    FilaCarrito(
                        item,
                        bloqueada = estado.pendiente || estado.cobrando,
                        onSumar = { vm.sumar(item.producto.idProducto) },
                        onRestar = { vm.restar(item.producto.idProducto) },
                        onQuitar = { vm.quitar(item.producto.idProducto) },
                        onEditar = { editar = item },
                    )
                }
            }
        }
    }

    if (buscar) {
        DialogoBuscarProducto(onElegir = { buscar = false; if (vm.agregar(it)) confirmacion.exito() }, onCerrar = { buscar = false })
    }
    editar?.let { item ->
        DialogoCantidad(item, onGuardar = { texto -> vm.fijarCantidad(item.producto.idProducto, texto).also { if (it == null) editar = null } }, onCerrar = { editar = null })
    }
    if (cobrar && !estado.carrito.vacio) {
        DialogoCobro(
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

@Composable
private fun AbrirTurno(caja: EstadoCaja, estado: EstadoVenta, vm: VentaViewModel, modifier: Modifier) {
    val activas = caja.cajas.filter { it.estado == "ACTIVA" }
    var elegida by remember { mutableStateOf(activas.firstOrNull()?.idCaja) }
    var monto by remember { mutableStateOf("") }
    Column(modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
        if (!caja.puedeVender) {
            Text("Solo un cajero registra ventas.", style = MaterialTheme.typography.titleMedium)
            return@Column
        }
        Text("Abre tu turno de caja", style = MaterialTheme.typography.titleLarge)
        Text(
            "Para vender necesitas un turno abierto, igual que en la página web. Cuenta el sencillo con el que empiezas.",
            style = MaterialTheme.typography.bodyMedium,
        )
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            activas.forEach { c ->
                FilterChip(selected = elegida == c.idCaja, onClick = { elegida = c.idCaja }, label = { Text("Caja ${c.numeroCaja}") })
            }
        }
        if (activas.isEmpty()) Text("Tu tienda no tiene cajas activas. Pide al administrador que active una en la web.")
        OutlinedTextField(
            value = monto,
            onValueChange = { monto = it.take(11) },
            label = { Text("Monto inicial S/") },
            singleLine = true,
            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Decimal),
            modifier = Modifier.fillMaxWidth(),
        )
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

@Composable
private fun FilaCarrito(
    item: ItemCarrito,
    bloqueada: Boolean,
    onSumar: () -> Unit,
    onRestar: () -> Unit,
    onQuitar: () -> Unit,
    onEditar: () -> Unit,
) {
    val p = item.producto
    Card(shape = RoundedCornerShape(14.dp), colors = CardDefaults.cardColors(containerColor = Color.White)) {
        Row(Modifier.padding(start = 12.dp, top = 8.dp, bottom = 8.dp, end = 4.dp), verticalAlignment = Alignment.CenterVertically) {
            Column(Modifier.weight(1f)) {
                Text(p.nombre, style = MaterialTheme.typography.titleSmall, maxLines = 2, overflow = TextOverflow.Ellipsis)
                Text(
                    "${p.precioVenta.soles()}${if (p.alPeso) " / kg" else ""} · stock ${p.stock.textoCantidad(p.alPeso)}",
                    style = MaterialTheme.typography.bodySmall,
                    color = if (item.superaStock) Ambar else MaterialTheme.colorScheme.onSurfaceVariant,
                )
                Text(item.importe.soles(), style = MaterialTheme.typography.titleMedium, color = Tinta)
            }
            IconButton(onClick = onRestar, enabled = !bloqueada) { Icon(Icons.Filled.Remove, contentDescription = "Uno menos") }
            Text(
                item.cantidad.textoCantidad(p.alPeso),
                style = MaterialTheme.typography.titleLarge,
                modifier = Modifier.clickable(enabled = !bloqueada, onClick = onEditar).padding(horizontal = 6.dp),
            )
            IconButton(onClick = onSumar, enabled = !bloqueada) { Icon(Icons.Filled.Add, contentDescription = "Uno más") }
            IconButton(onClick = onQuitar, enabled = !bloqueada) { Icon(Icons.Filled.Delete, contentDescription = "Quitar del carrito") }
        }
    }
}

@Composable
private fun BarraTotal(estado: EstadoVenta, onCobrar: () -> Unit, onReintentar: () -> Unit, onDescartar: () -> Unit) {
    Surface(color = Color.White, shadowElevation = 8.dp) {
        Column(Modifier.fillMaxWidth().navigationBarsPadding().padding(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text("${estado.carrito.unidades} producto(s)", Modifier.weight(1f), style = MaterialTheme.typography.bodyMedium)
                Text("Total ${estado.carrito.total.soles()}", style = MaterialTheme.typography.headlineSmall)
            }
            if (estado.pendiente) {
                BotonGrande("Reintentar envío", onClick = onReintentar, ocupado = estado.cobrando)
                OutlinedButton(onClick = onDescartar, modifier = Modifier.fillMaxWidth()) { Text("Revisar en el historial") }
            } else {
                BotonGrande("Cobrar", onClick = onCobrar, habilitado = !estado.carrito.vacio, ocupado = estado.cobrando)
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

@Composable
private fun DialogoCobro(estado: EstadoVenta, vm: VentaViewModel, onCerrar: () -> Unit, onCobrar: () -> Unit) {
    val c = estado.cobro
    val total = estado.carrito.total
    val problema = c.problema(total)
    AlertDialog(
        onDismissRequest = onCerrar,
        title = { Text("Cobrar ${total.soles()}") },
        text = {
            Column(Modifier.verticalScroll(rememberScrollState()), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                if (estado.carrito.sinStockSuficiente.isNotEmpty()) {
                    Text(
                        "Ojo: ${estado.carrito.sinStockSuficiente.joinToString { it.producto.nombre }} pasa(n) el stock que conoce la app. El servidor lo revisará.",
                        color = Ambar, style = MaterialTheme.typography.bodySmall,
                    )
                }
                Text("Comprobante", style = MaterialTheme.typography.labelLarge)
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    FilterChip(selected = c.tipoComprobante == "B", onClick = { vm.cobro { it.copy(tipoComprobante = "B") } }, label = { Text("Boleta") })
                    FilterChip(selected = c.tipoComprobante == "F", onClick = { vm.cobro { it.copy(tipoComprobante = "F") } }, label = { Text("Factura") })
                }
                OutlinedTextField(
                    value = c.documento,
                    onValueChange = { v -> vm.cobro { it.copy(documento = v.filter(Char::isDigit).take(11)) } },
                    label = { Text(if (c.tipoComprobante == "F") "RUC del cliente" else "DNI o RUC (opcional)") },
                    singleLine = true,
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                    modifier = Modifier.fillMaxWidth(),
                )
                Text("Método de pago", style = MaterialTheme.typography.labelLarge)
                Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                    MetodoPago.entries.forEach { m ->
                        FilterChip(selected = c.metodo == m, onClick = { vm.cobro { it.copy(metodo = m) } }, label = { Text(m.texto) })
                    }
                }
                when (c.metodo) {
                    MetodoPago.EFECTIVO -> {
                        OutlinedTextField(
                            value = c.recibido,
                            onValueChange = { v -> vm.cobro { it.copy(recibido = v.take(11)) } },
                            label = { Text("Recibido S/") },
                            singleLine = true,
                            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Decimal),
                            modifier = Modifier.fillMaxWidth(),
                        )
                        Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                            // Atajos: monto exacto y billetes comunes
                            listOf(total.setScale(2, java.math.RoundingMode.UP).toPlainString(), "20", "50", "100", "200").distinct().forEach { b ->
                                OutlinedButton(onClick = { vm.cobro { it.copy(recibido = b) } }, contentPadding = PaddingValues(horizontal = 8.dp)) { Text(b) }
                            }
                        }
                        c.vuelto(total)?.let { Text("Vuelto: ${it.soles()}", style = MaterialTheme.typography.headlineSmall, color = Verde) }
                    }
                    MetodoPago.TARJETA -> {
                        Text("Cobra con el POS de la tienda y copia los datos del voucher. La app no cobra la tarjeta.",
                            style = MaterialTheme.typography.bodySmall)
                        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                            FilterChip(selected = c.tarjetaTipo == "DEBITO", onClick = { vm.cobro { it.copy(tarjetaTipo = "DEBITO") } }, label = { Text("Débito") })
                            FilterChip(selected = c.tarjetaTipo == "CREDITO", onClick = { vm.cobro { it.copy(tarjetaTipo = "CREDITO") } }, label = { Text("Crédito") })
                        }
                        OutlinedTextField(value = c.tarjetaMarca, onValueChange = { v -> vm.cobro { it.copy(tarjetaMarca = v.take(20)) } },
                            label = { Text("Marca (VISA, MASTERCARD…)") }, singleLine = true, modifier = Modifier.fillMaxWidth())
                        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                            OutlinedTextField(value = c.tarjetaUltimos4, onValueChange = { v -> vm.cobro { it.copy(tarjetaUltimos4 = v.filter(Char::isDigit).take(4)) } },
                                label = { Text("Últimos 4") }, singleLine = true, keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                                modifier = Modifier.weight(1f))
                            OutlinedTextField(value = c.tarjetaAutorizacion, onValueChange = { v -> vm.cobro { it.copy(tarjetaAutorizacion = v.take(10)) } },
                                label = { Text("Autorización") }, singleLine = true, modifier = Modifier.weight(1f))
                        }
                    }
                    MetodoPago.SIP -> {
                        Text("El cliente paga con el QR o la tarjeta Sip; copia el código de operación. La app no procesa el pago.",
                            style = MaterialTheme.typography.bodySmall)
                        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                            FilterChip(selected = c.sipModalidad == "QR", onClick = { vm.cobro { it.copy(sipModalidad = "QR") } }, label = { Text("QR") })
                            FilterChip(selected = c.sipModalidad == "TARJETA_SIP", onClick = { vm.cobro { it.copy(sipModalidad = "TARJETA_SIP") } }, label = { Text("Tarjeta Sip") })
                        }
                        OutlinedTextField(value = c.sipOperacion, onValueChange = { v -> vm.cobro { it.copy(sipOperacion = v.take(30)) } },
                            label = { Text("Código de operación") }, singleLine = true, modifier = Modifier.fillMaxWidth())
                    }
                }
                problema?.let { Text(it, color = MaterialTheme.colorScheme.error, style = MaterialTheme.typography.bodySmall) }
            }
        },
        confirmButton = { TextButton(onClick = onCobrar, enabled = problema == null) { Text("Registrar venta", fontWeight = FontWeight.Bold) } },
        dismissButton = { TextButton(onClick = onCerrar) { Text("Volver al carrito") } },
    )
}

@Composable
private fun PantallaVentaRegistrada(venta: VentaDetalle, onNueva: () -> Unit, onVer: () -> Unit, onSalir: () -> Unit) {
    Scaffold(topBar = { BarraSuperior("Venta registrada", onAtras = onSalir) }) { relleno ->
        Column(
            Modifier.fillMaxSize().padding(relleno).verticalScroll(rememberScrollState()).padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
        ) {
            Icon(Icons.Filled.CheckCircle, contentDescription = null, tint = Verde, modifier = Modifier.padding(top = 16.dp).size(72.dp))
            Text(venta.tipo + " " + venta.comprobante, style = MaterialTheme.typography.titleLarge)
            Text(venta.total.soles(), style = MaterialTheme.typography.displaySmall)
            venta.pagos.forEach { p ->
                Text("${p.metodo}: ${p.monto.soles()}", style = MaterialTheme.typography.bodyLarge)
                p.vuelto?.let { Text("Vuelto: ${it.soles()}", style = MaterialTheme.typography.headlineSmall, color = Verde) }
            }
            Text("${venta.cliente} · ${fechaCorta(venta.fechaHora)}", style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
            Text("La venta ya está en el servidor: la página web la muestra y el stock bajó.", style = MaterialTheme.typography.bodySmall)
            Spacer(Modifier.padding(4.dp))
            BotonGrande("Nueva venta", icono = Icons.Filled.PointOfSale, onClick = onNueva)
            OutlinedButton(onClick = onVer, modifier = Modifier.fillMaxWidth()) {
                Icon(Icons.AutoMirrored.Filled.ReceiptLong, contentDescription = null)
                Spacer(Modifier.width(8.dp))
                Text("Ver detalle del comprobante")
            }
        }
    }
}
