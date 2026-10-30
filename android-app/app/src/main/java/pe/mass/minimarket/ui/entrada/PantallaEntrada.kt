package pe.mass.minimarket.ui.entrada

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material.icons.filled.Delete
import androidx.compose.material.icons.filled.DoneAll
import androidx.compose.material.icons.filled.QrCodeScanner
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.FilterChip
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Scaffold
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
import androidx.compose.ui.text.input.KeyboardCapitalization
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import pe.mass.minimarket.data.Repositorio
import pe.mass.minimarket.data.Resultado
import pe.mass.minimarket.data.api.DocumentoIngreso
import pe.mass.minimarket.data.api.Ingreso
import pe.mass.minimarket.data.api.IngresoPedido
import pe.mass.minimarket.data.api.LineaPedido
import pe.mass.minimarket.data.api.Producto
import pe.mass.minimarket.dominio.Validar
import pe.mass.minimarket.dominio.aDecimal
import pe.mass.minimarket.dominio.importe
import pe.mass.minimarket.dominio.soles
import pe.mass.minimarket.dominio.textoCantidad
import pe.mass.minimarket.ui.comun.Aviso
import pe.mass.minimarket.ui.comun.BarraSuperior
import pe.mass.minimarket.ui.comun.BotonGrande
import pe.mass.minimarket.ui.comun.DialogoBuscarProducto
import pe.mass.minimarket.ui.comun.DialogoProductoNuevo
import pe.mass.minimarket.ui.comun.TarjetaAviso
import pe.mass.minimarket.ui.comun.TipoAviso
import pe.mass.minimarket.ui.comun.fechaCorta
import pe.mass.minimarket.ui.comun.vistaModelo
import pe.mass.minimarket.ui.escaner.PanelEscaner
import pe.mass.minimarket.ui.escaner.rememberConfirmacion
import pe.mass.minimarket.ui.tema.Verde
import java.math.BigDecimal
import java.time.LocalDate
import java.util.UUID

/** Un producto recibido: cantidad, costo unitario del documento y vencimiento opcional (AAAA-MM-DD). */
data class LineaEntrada(val producto: Producto, val cantidad: String = "1", val costo: String = "", val vence: String = "") {
    val errorCantidad: String? get() = Validar.cantidad(cantidad, producto.alPeso)
    val errorCosto: String? get() = Validar.monto(costo, puedeSerCero = true)
    val errorVence: String? get() = if (vence.isBlank() || Validar.fecha(vence)) null else "Fecha AAAA-MM-DD"
    val valida: Boolean get() = errorCantidad == null && errorCosto == null && errorVence == null
    val costoTotal: BigDecimal get() = importe(cantidad.aDecimal(), costo.aDecimal())
}

data class EstadoEntrada(
    val lineas: List<LineaEntrada> = emptyList(),
    val tipoDocumento: String = "GUIA",
    val numero: String = "",
    val fecha: String = LocalDate.now().toString(),
    val ruc: String = "",
    val razonSocial: String = "",
    val observacion: String = "",
    val consultando: Boolean = false,
    val enviando: Boolean = false,
    val aviso: Aviso? = null,
    // La clave del envio en curso: si la red falla, el reintento usa la MISMA y el servidor no suma el stock dos veces
    val claveEnvio: String? = null,
    val pendiente: Boolean = false,
    val resultado: Ingreso? = null,
    // Codigo escaneado que no esta en el catalogo y que este usuario puede registrar
    val codigoNuevo: String? = null,
) {
    val errorDocumento: String?
        get() = when {
            !Validar.numeroDocumento(numero) -> "Número del documento: SERIE-NÚMERO, por ejemplo F001-00012345."
            !Validar.fecha(fecha) || LocalDate.parse(fecha).isAfter(LocalDate.now()) -> "Fecha del documento no válida o futura."
            !Validar.ruc(ruc) -> "El RUC del proveedor debe tener 11 dígitos."
            razonSocial.isBlank() -> "Escribe la razón social del proveedor."
            else -> null
        }
    val total: BigDecimal get() = lineas.fold(BigDecimal.ZERO) { suma, l -> suma + l.costoTotal }
}

/**
 * Entrada de mercaderia (recepcion): se escanean los productos, se anota cantidad y costo,
 * se revisa y se confirma. Un codigo que no esta en el catalogo se registra en el momento
 * (DialogoProductoNuevo) y entra a la lista con 1. El servidor registra el ingreso con su documento, sube el stock
 * y deja cada producto en el kardex como INGRESO DE MERCADERIA (InventarioServicio::registrarIngreso).
 */
class EntradaViewModel(private val repo: Repositorio) : ViewModel() {
    private val _estado = MutableStateFlow(EstadoEntrada())
    val estado: StateFlow<EstadoEntrada> = _estado

    val tiposDocumento = listOf("GUIA", "FACTURA", "BOLETA")

    fun leerCodigo(codigo: String, alEncontrar: () -> Unit, alFallar: () -> Unit) {
        if (_estado.value.consultando || _estado.value.pendiente) return
        _estado.update { it.copy(consultando = true, aviso = null) }
        viewModelScope.launch {
            when (val r = repo.productoPorCodigo(codigo)) {
                is Resultado.Ok -> {
                    agregar(r.datos)
                    alEncontrar()
                }
                is Resultado.Error -> {
                    if (r.http == 404 && repo.perfil?.permisos?.crearProducto == true) {
                        // Producto que llega por primera vez: se registra aqui mismo y entra a la lista
                        _estado.update { it.copy(codigoNuevo = codigo) }
                    } else {
                        val texto = if (r.http == 404) "${r.mensaje} Pídele al administrador que lo registre." else r.mensaje
                        _estado.update { it.copy(aviso = Aviso(texto, if (r.http == 404) TipoAviso.ALERTA else TipoAviso.ERROR)) }
                    }
                    alFallar()
                }
            }
            _estado.update { it.copy(consultando = false) }
        }
    }

    /** Un producto ya anotado suma 1 a su cantidad; uno nuevo entra con 1. */
    fun agregar(producto: Producto) {
        if (_estado.value.pendiente) return
        if (!producto.activo) {
            _estado.update { it.copy(aviso = Aviso("${producto.nombre} está descontinuado: no se recibe.", TipoAviso.ALERTA)) }
            return
        }
        _estado.update { e ->
            val i = e.lineas.indexOfFirst { it.producto.idProducto == producto.idProducto }
            val lineas = if (i < 0) listOf(LineaEntrada(producto)) + e.lineas
            else e.lineas.toMutableList().also {
                val actual = it[i]
                val suma = actual.cantidad.aDecimal() + BigDecimal.ONE
                it[i] = actual.copy(producto = producto, cantidad = suma.textoCantidad(producto.alPeso))
            }
            e.copy(lineas = lineas, claveEnvio = null, aviso = Aviso("${producto.nombre}: stock actual ${producto.stock.textoCantidad(producto.alPeso)} ${producto.unidad}", TipoAviso.OK))
        }
    }

    fun cambiarLinea(idProducto: Int, cambio: (LineaEntrada) -> LineaEntrada) = _estado.update { e ->
        if (e.pendiente) e else e.copy(lineas = e.lineas.map { if (it.producto.idProducto == idProducto) cambio(it) else it }, claveEnvio = null)
    }

    fun quitar(idProducto: Int) = _estado.update { e ->
        if (e.pendiente) e else e.copy(lineas = e.lineas.filterNot { it.producto.idProducto == idProducto }, claveEnvio = null)
    }

    fun documento(cambio: (EstadoEntrada) -> EstadoEntrada) = _estado.update { e -> if (e.pendiente) e else cambio(e).copy(claveEnvio = null) }

    /** Lo que impide confirmar, o null si todo esta listo. */
    fun problema(): String? {
        val e = _estado.value
        return when {
            e.lineas.isEmpty() -> "Escanea al menos un producto."
            e.lineas.any { !it.valida } -> "Revisa la cantidad, el costo y el vencimiento de cada producto."
            else -> e.errorDocumento
        }
    }

    fun confirmar() {
        val e = _estado.value
        if (e.enviando) return
        problema()?.let { msg -> _estado.update { it.copy(aviso = Aviso(msg, TipoAviso.ALERTA)) }; return }
        val clave = e.claveEnvio ?: UUID.randomUUID().toString()
        _estado.update { it.copy(enviando = true, claveEnvio = clave, aviso = null) }
        val pedido = IngresoPedido(
            documento = DocumentoIngreso(e.tipoDocumento, e.numero.trim().uppercase(), e.fecha.trim(), e.ruc.trim(), e.razonSocial.trim(), e.observacion.trim()),
            lineas = e.lineas.map {
                LineaPedido(
                    idProducto = it.producto.idProducto.toString(),
                    cantidad = it.cantidad.trim().replace(',', '.'),
                    costo = it.costo.trim().replace(',', '.'),
                    vence = it.vence.trim(),
                )
            },
        )
        viewModelScope.launch {
            when (val r = repo.registrarIngreso(clave, pedido)) {
                is Resultado.Ok -> _estado.update { it.copy(enviando = false, pendiente = false, resultado = r.datos) }
                is Resultado.Error -> _estado.update {
                    if (r.sinRespuesta) {
                        // No se sabe si llego: se bloquea la lista y se reintenta con la misma clave
                        it.copy(enviando = false, pendiente = true, aviso = Aviso(
                            "${r.mensaje} No sabemos si el ingreso quedó registrado. Toca «Reintentar»: el stock no se sumará dos veces.",
                            TipoAviso.ERROR,
                        ))
                    } else {
                        // El servidor lo rechazo y no guardo nada: se corrige y se vuelve a confirmar
                        it.copy(enviando = false, pendiente = false, claveEnvio = null, aviso = Aviso(r.mensaje, TipoAviso.ERROR))
                    }
                }
            }
        }
    }

    fun nuevaEntrada() {
        _estado.value = EstadoEntrada()
    }

    fun avisar(texto: String) = _estado.update { it.copy(aviso = Aviso(texto, TipoAviso.ALERTA)) }

    fun cerrarAviso() = _estado.update { it.copy(aviso = null) }

    fun productoCreado(producto: Producto) {
        _estado.update { it.copy(codigoNuevo = null) }
        agregar(producto)
    }

    fun cancelarProductoNuevo() = _estado.update { it.copy(codigoNuevo = null) }
}

@Composable
fun PantallaEntrada(onAtras: () -> Unit) {
    val vm = vistaModelo { EntradaViewModel(it) }
    val estado by vm.estado.collectAsState()
    val confirmacion = rememberConfirmacion()
    var buscar by remember { mutableStateOf(false) }
    var verCamara by remember { mutableStateOf(true) }
    var revisar by remember { mutableStateOf(false) }

    val resultado = estado.resultado
    if (resultado != null) {
        PantallaRecepcionConfirmada(resultado, onNueva = vm::nuevaEntrada, onSalir = onAtras)
        return
    }

    Scaffold(topBar = {
        BarraSuperior("Entrada de stock", onAtras = onAtras, acciones = {
            IconButton(onClick = { verCamara = !verCamara }) { Icon(Icons.Filled.QrCodeScanner, contentDescription = "Mostrar u ocultar cámara") }
        })
    }) { relleno ->
        LazyColumn(
            Modifier.fillMaxSize().padding(relleno),
            contentPadding = androidx.compose.foundation.layout.PaddingValues(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            if (verCamara && !estado.pendiente) {
                item {
                    PanelEscaner(
                        onCodigo = { codigo -> vm.leerCodigo(codigo, alEncontrar = confirmacion::exito, alFallar = confirmacion::error) },
                        onBuscarPorNombre = { buscar = true },
                        activo = !estado.consultando && estado.codigoNuevo == null,
                    )
                }
            }
            item { TarjetaAviso(estado.aviso, onCerrar = vm::cerrarAviso) }
            item {
                Text("Productos recibidos (${estado.lineas.size})", style = MaterialTheme.typography.titleMedium)
            }
            if (estado.lineas.isEmpty()) {
                item { Text("Escanea el primer producto de la entrega.", color = MaterialTheme.colorScheme.onSurfaceVariant) }
            }
            items(estado.lineas, key = { it.producto.idProducto }) { linea ->
                TarjetaLinea(linea, bloqueada = estado.pendiente, vm = vm)
            }
            item { FormularioDocumento(estado, vm) }
            item {
                if (estado.pendiente) {
                    BotonGrande("Reintentar", onClick = vm::confirmar, ocupado = estado.enviando)
                } else {
                    BotonGrande(
                        "Revisar y confirmar",
                        icono = Icons.Filled.DoneAll,
                        onClick = {
                            val problema = vm.problema()
                            if (problema == null) revisar = true else vm.avisar(problema)
                        },
                        ocupado = estado.enviando,
                        habilitado = estado.lineas.isNotEmpty(),
                    )
                }
            }
        }
    }

    if (revisar) {
        DialogoRevision(estado, onConfirmar = { revisar = false; vm.confirmar() }, onCancelar = { revisar = false })
    }
    if (buscar) {
        DialogoBuscarProducto(onElegir = { buscar = false; vm.agregar(it) }, onCerrar = { buscar = false })
    }
    estado.codigoNuevo?.let { codigo ->
        DialogoProductoNuevo(codigo, onCreado = vm::productoCreado, onCerrar = vm::cancelarProductoNuevo)
    }
}

@Composable
private fun TarjetaLinea(linea: LineaEntrada, bloqueada: Boolean, vm: EntradaViewModel) {
    val p = linea.producto
    Card(shape = RoundedCornerShape(14.dp), colors = CardDefaults.cardColors(containerColor = Color.White)) {
        Column(Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Column(Modifier.weight(1f)) {
                    Text(p.nombre, style = MaterialTheme.typography.titleMedium)
                    Text(
                        "${p.codigoBarras ?: "al peso"} · stock actual ${p.stock.textoCantidad(p.alPeso)} ${p.unidad}",
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
                IconButton(onClick = { vm.quitar(p.idProducto) }, enabled = !bloqueada) { Icon(Icons.Filled.Delete, contentDescription = "Quitar") }
            }
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                OutlinedTextField(
                    value = linea.cantidad,
                    onValueChange = { v -> vm.cambiarLinea(p.idProducto) { it.copy(cantidad = v.take(11)) } },
                    label = { Text(if (p.alPeso) "Kilos" else "Cantidad") },
                    isError = linea.errorCantidad != null,
                    supportingText = { linea.errorCantidad?.let { Text(it) } },
                    enabled = !bloqueada,
                    singleLine = true,
                    keyboardOptions = KeyboardOptions(keyboardType = if (p.alPeso) KeyboardType.Decimal else KeyboardType.Number),
                    modifier = Modifier.weight(1f),
                )
                OutlinedTextField(
                    value = linea.costo,
                    onValueChange = { v -> vm.cambiarLinea(p.idProducto) { it.copy(costo = v.take(11)) } },
                    label = { Text("Costo unit. S/") },
                    isError = linea.errorCosto != null && linea.costo.isNotEmpty(),
                    supportingText = { if (linea.costo.isNotEmpty()) linea.errorCosto?.let { Text(it) } },
                    enabled = !bloqueada,
                    singleLine = true,
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Decimal),
                    modifier = Modifier.weight(1f),
                )
            }
            OutlinedTextField(
                value = linea.vence,
                onValueChange = { v -> vm.cambiarLinea(p.idProducto) { it.copy(vence = v.take(10)) } },
                label = { Text("Vence (AAAA-MM-DD, opcional)") },
                isError = linea.errorVence != null,
                enabled = !bloqueada,
                singleLine = true,
                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                modifier = Modifier.fillMaxWidth(),
            )
        }
    }
}

@Composable
private fun FormularioDocumento(estado: EstadoEntrada, vm: EntradaViewModel) {
    val bloqueado = estado.pendiente
    Card(shape = RoundedCornerShape(14.dp), colors = CardDefaults.cardColors(containerColor = Color.White)) {
        Column(Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
            Text("Documento del proveedor", style = MaterialTheme.typography.titleMedium)
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                vm.tiposDocumento.forEach { tipo ->
                    FilterChip(
                        selected = estado.tipoDocumento == tipo,
                        onClick = { vm.documento { it.copy(tipoDocumento = tipo) } },
                        label = { Text(tipo.lowercase().replaceFirstChar(Char::uppercase).replace("Guia", "Guía")) },
                        enabled = !bloqueado,
                    )
                }
            }
            OutlinedTextField(
                value = estado.numero, onValueChange = { v -> vm.documento { it.copy(numero = v.uppercase().take(13)) } },
                label = { Text("Serie y número (T001-00012345)") }, singleLine = true, enabled = !bloqueado,
                keyboardOptions = KeyboardOptions(capitalization = KeyboardCapitalization.Characters), modifier = Modifier.fillMaxWidth(),
            )
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                OutlinedTextField(
                    value = estado.fecha, onValueChange = { v -> vm.documento { it.copy(fecha = v.take(10)) } },
                    label = { Text("Fecha (AAAA-MM-DD)") }, singleLine = true, enabled = !bloqueado, modifier = Modifier.weight(1f),
                )
                OutlinedTextField(
                    value = estado.ruc, onValueChange = { v -> vm.documento { it.copy(ruc = v.filter(Char::isDigit).take(11)) } },
                    label = { Text("RUC") }, singleLine = true, enabled = !bloqueado,
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number), modifier = Modifier.weight(1f),
                )
            }
            OutlinedTextField(
                value = estado.razonSocial, onValueChange = { v -> vm.documento { it.copy(razonSocial = v.take(100)) } },
                label = { Text("Razón social del proveedor") }, singleLine = true, enabled = !bloqueado, modifier = Modifier.fillMaxWidth(),
            )
            OutlinedTextField(
                value = estado.observacion, onValueChange = { v -> vm.documento { it.copy(observacion = v.take(200)) } },
                label = { Text("Observaciones (opcional)") }, enabled = !bloqueado, modifier = Modifier.fillMaxWidth(),
            )
        }
    }
}

@Composable
private fun DialogoRevision(estado: EstadoEntrada, onConfirmar: () -> Unit, onCancelar: () -> Unit) {
    AlertDialog(
        onDismissRequest = onCancelar,
        title = { Text("¿Registrar esta entrada?") },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
                Text("${estado.tipoDocumento} ${estado.numero.uppercase()} · ${estado.razonSocial}", style = MaterialTheme.typography.bodyMedium)
                HorizontalDivider()
                LazyColumn(Modifier.heightIn(max = 280.dp)) {
                    items(estado.lineas, key = { it.producto.idProducto }) { l ->
                        Row(Modifier.fillMaxWidth().padding(vertical = 4.dp)) {
                            Text("${l.cantidad} × ${l.producto.nombre}", Modifier.weight(1f), style = MaterialTheme.typography.bodyMedium)
                            Text(l.costoTotal.soles(), style = MaterialTheme.typography.bodyMedium)
                        }
                    }
                }
                HorizontalDivider()
                Text("Total del documento: ${estado.total.soles()}", style = MaterialTheme.typography.titleMedium)
                Text("El stock de tu tienda subirá con estas cantidades y quedará en el kardex con tu nombre.",
                    style = MaterialTheme.typography.bodySmall)
            }
        },
        confirmButton = { TextButton(onClick = onConfirmar) { Text("Confirmar entrada") } },
        dismissButton = { TextButton(onClick = onCancelar) { Text("Seguir editando") } },
    )
}

@Composable
private fun PantallaRecepcionConfirmada(ingreso: Ingreso, onNueva: () -> Unit, onSalir: () -> Unit) {
    // La recepcion ya quedo en el servidor: aqui solo se muestra lo que respondio
    Scaffold(topBar = { BarraSuperior("Recepción registrada", onAtras = onSalir) }) { relleno ->
        LazyColumn(
            Modifier.fillMaxSize().padding(relleno),
            contentPadding = androidx.compose.foundation.layout.PaddingValues(16.dp),
            verticalArrangement = Arrangement.spacedBy(10.dp),
        ) {
            item {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Icon(Icons.Filled.CheckCircle, contentDescription = null, tint = Verde)
                    Spacer(Modifier.width(8.dp))
                    Text("Ingreso ${ingreso.idIngreso} registrado", style = MaterialTheme.typography.headlineSmall)
                }
            }
            item {
                Text(
                    "${ingreso.tipoDocumento} ${ingreso.numeroDocumento} · ${ingreso.razonSocial} (RUC ${ingreso.rucEmisor})\n" +
                        "Recibió ${ingreso.empleado} el ${fechaCorta(ingreso.fechaHora)} · Total ${ingreso.total.soles()}",
                    style = MaterialTheme.typography.bodyMedium,
                )
            }
            items(ingreso.lineas, key = { it.idProducto }) { l ->
                Card(shape = RoundedCornerShape(12.dp), colors = CardDefaults.cardColors(containerColor = Color.White)) {
                    Row(Modifier.fillMaxWidth().padding(12.dp), verticalAlignment = Alignment.CenterVertically) {
                        Column(Modifier.weight(1f)) {
                            Text(l.nombre, style = MaterialTheme.typography.titleSmall)
                            Text("+${l.cantidad} ${l.unidad} a ${l.costoUnitario.soles()}" + (l.fechaVencimiento?.let { " · vence $it" } ?: ""),
                                style = MaterialTheme.typography.bodySmall)
                        }
                        Text("Stock: ${l.stockActual}", style = MaterialTheme.typography.titleSmall, color = Verde)
                    }
                }
            }
            item { BotonGrande("Registrar otra entrada", onClick = onNueva) }
            item { TextButton(onClick = onSalir, modifier = Modifier.fillMaxWidth()) { Text("Volver al inicio") } }
        }
    }
}
