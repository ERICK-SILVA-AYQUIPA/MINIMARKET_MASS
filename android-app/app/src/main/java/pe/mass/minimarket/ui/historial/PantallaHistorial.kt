package pe.mass.minimarket.ui.historial

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Refresh
import androidx.compose.material.icons.filled.Search
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
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
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.KeyboardCapitalization
import androidx.compose.ui.unit.dp
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.ViewModel
import androidx.lifecycle.compose.LifecycleEventEffect
import androidx.lifecycle.viewModelScope
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import pe.mass.minimarket.data.Repositorio
import pe.mass.minimarket.data.Resultado
import pe.mass.minimarket.data.api.VentaDetalle
import pe.mass.minimarket.data.api.VentaResumen
import pe.mass.minimarket.dominio.soles
import pe.mass.minimarket.dominio.textoCantidad
import pe.mass.minimarket.ui.comun.Aviso
import pe.mass.minimarket.ui.comun.BarraSuperior
import pe.mass.minimarket.ui.comun.BotonGrande
import pe.mass.minimarket.ui.comun.Cargando
import pe.mass.minimarket.ui.comun.TarjetaAviso
import pe.mass.minimarket.ui.comun.TipoAviso
import pe.mass.minimarket.ui.comun.fechaCorta
import pe.mass.minimarket.ui.comun.vistaModelo
import pe.mass.minimarket.ui.tema.Rojo
import pe.mass.minimarket.ui.tema.RojoFondo
import pe.mass.minimarket.ui.tema.Verde
import pe.mass.minimarket.ui.tema.VerdeFondo
import java.time.LocalDate

enum class Periodo(val texto: String, val dias: Long?) {
    HOY("Hoy", 0),
    SEMANA("7 días", 6),
    MES("30 días", 29),
    TODO("Todo", null),
}

data class EstadoHistorial(
    val periodo: Periodo = Periodo.HOY,
    val comprobante: String = "",
    val ventas: List<VentaResumen> = emptyList(),
    val total: Int = 0,
    val pagina: Int = 1,
    val hayMas: Boolean = false,
    val cargando: Boolean = false,
    val aviso: Aviso? = null,
)

/** Ventas reales de la tienda (de la web y de la app), leidas del servidor. */
class HistorialViewModel(private val repo: Repositorio) : ViewModel() {
    private val _estado = MutableStateFlow(EstadoHistorial())
    val estado: StateFlow<EstadoHistorial> = _estado

    fun periodo(p: Periodo) {
        _estado.update { it.copy(periodo = p) }
        cargar(1)
    }

    fun comprobante(texto: String) = _estado.update { it.copy(comprobante = texto.uppercase().take(13)) }

    fun cargar(pagina: Int = 1) {
        val e = _estado.value
        val hoy = LocalDate.now()
        // Buscando un comprobante no se limita por fecha
        val porNumero = e.comprobante.isNotBlank()
        val desde = if (porNumero) null else e.periodo.dias?.let { hoy.minusDays(it).toString() }
        val hasta = if (porNumero || e.periodo.dias == null) null else hoy.toString()
        _estado.update { it.copy(cargando = true) }
        viewModelScope.launch {
            when (val r = repo.ventas(desde, hasta, null, e.comprobante.trim(), pagina)) {
                is Resultado.Ok -> _estado.update {
                    it.copy(
                        ventas = if (pagina == 1) r.datos.ventas else it.ventas + r.datos.ventas,
                        total = r.datos.total, pagina = r.datos.pagina, hayMas = r.datos.hayMas, cargando = false, aviso = null,
                    )
                }
                is Resultado.Error -> _estado.update { it.copy(cargando = false, aviso = Aviso(r.mensaje, TipoAviso.ERROR)) }
            }
        }
    }
}

@Composable
fun PantallaHistorial(onAtras: () -> Unit, onAbrir: (Long) -> Unit) {
    val vm = vistaModelo { HistorialViewModel(it) }
    val estado by vm.estado.collectAsState()
    LifecycleEventEffect(Lifecycle.Event.ON_RESUME) { vm.cargar(1) }

    Scaffold(topBar = {
        BarraSuperior("Historial de ventas", onAtras = onAtras, acciones = {
            IconButton(onClick = { vm.cargar(1) }) { Icon(Icons.Filled.Refresh, contentDescription = "Actualizar") }
        })
    }) { relleno ->
        LazyColumn(Modifier.fillMaxSize().padding(relleno), contentPadding = PaddingValues(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
            item {
                Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                    Periodo.entries.forEach { p ->
                        FilterChip(selected = estado.periodo == p && estado.comprobante.isBlank(), onClick = { vm.periodo(p) }, label = { Text(p.texto) })
                    }
                }
            }
            item {
                OutlinedTextField(
                    value = estado.comprobante,
                    onValueChange = vm::comprobante,
                    label = { Text("N.° de comprobante (B001-25)") },
                    singleLine = true,
                    trailingIcon = { IconButton(onClick = { vm.cargar(1) }) { Icon(Icons.Filled.Search, contentDescription = "Buscar") } },
                    keyboardOptions = KeyboardOptions(capitalization = KeyboardCapitalization.Characters, imeAction = ImeAction.Search),
                    keyboardActions = KeyboardActions(onSearch = { vm.cargar(1) }),
                    modifier = Modifier.fillMaxWidth(),
                )
            }
            item { TarjetaAviso(estado.aviso) }
            item { Text("${estado.total} venta(s)", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant) }
            if (estado.cargando && estado.ventas.isEmpty()) {
                item { Box(Modifier.fillMaxWidth().padding(24.dp), contentAlignment = Alignment.Center) { CircularProgressIndicator() } }
            }
            items(estado.ventas, key = { it.idVenta }) { v -> TarjetaVenta(v) { onAbrir(v.idVenta) } }
            if (estado.hayMas) {
                item {
                    Box(Modifier.fillMaxWidth(), contentAlignment = Alignment.Center) {
                        if (estado.cargando) CircularProgressIndicator() else TextButton(onClick = { vm.cargar(estado.pagina + 1) }) { Text("Cargar más") }
                    }
                }
            }
        }
    }
}

@Composable
fun EtiquetaEstado(estado: String) {
    val anulada = estado == "ANULADA"
    Text(
        if (anulada) "Anulada" else "Emitida",
        color = if (anulada) Rojo else Verde,
        style = MaterialTheme.typography.labelMedium,
        modifier = Modifier.padding(top = 2.dp)
            .background(if (anulada) RojoFondo else VerdeFondo, RoundedCornerShape(50))
            .padding(horizontal = 8.dp, vertical = 2.dp),
    )
}

@Composable
private fun TarjetaVenta(v: VentaResumen, onClick: () -> Unit) {
    Card(Modifier.fillMaxWidth().clickable(onClick = onClick), shape = RoundedCornerShape(14.dp), colors = CardDefaults.cardColors(containerColor = Color.White)) {
        Row(Modifier.padding(12.dp), verticalAlignment = Alignment.CenterVertically) {
            Column(Modifier.weight(1f)) {
                Text(v.comprobante, style = MaterialTheme.typography.titleMedium)
                Text("${fechaCorta(v.fechaHora)} · ${v.cajero}", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                Text("${v.productos} producto(s) · ${v.metodos}", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
            }
            Column(horizontalAlignment = Alignment.End) {
                Text(v.total.soles(), style = MaterialTheme.typography.titleMedium)
                EtiquetaEstado(v.estado)
            }
        }
    }
}

// ---------- Detalle de una venta ----------

data class EstadoDetalle(
    val venta: VentaDetalle? = null,
    val cargando: Boolean = true,
    val anulando: Boolean = false,
    val aviso: Aviso? = null,
)

class DetalleVentaViewModel(private val repo: Repositorio) : ViewModel() {
    private val _estado = MutableStateFlow(EstadoDetalle())
    val estado: StateFlow<EstadoDetalle> = _estado

    fun cargar(idVenta: Long) {
        viewModelScope.launch {
            when (val r = repo.venta(idVenta)) {
                is Resultado.Ok -> _estado.update { it.copy(venta = r.datos, cargando = false) }
                is Resultado.Error -> _estado.update { it.copy(cargando = false, aviso = Aviso(r.mensaje, TipoAviso.ERROR)) }
            }
        }
    }

    /** La anulacion la hace el servidor (VentaServicio::anular): queda el motivo, los pagos anulados y el stock devuelto en el kardex. */
    fun anular(motivo: String) {
        val venta = _estado.value.venta ?: return
        if (motivo.isBlank()) {
            _estado.update { it.copy(aviso = Aviso("Escribe el motivo de la anulación.", TipoAviso.ALERTA)) }
            return
        }
        _estado.update { it.copy(anulando = true, aviso = null) }
        viewModelScope.launch {
            when (val r = repo.anularVenta(venta.idVenta, motivo.trim())) {
                is Resultado.Ok -> _estado.update { it.copy(venta = r.datos, anulando = false, aviso = Aviso("Venta anulada: el stock volvió a la tienda.", TipoAviso.OK)) }
                is Resultado.Error -> _estado.update { it.copy(anulando = false, aviso = Aviso(r.mensaje, TipoAviso.ERROR)) }
            }
        }
    }
}

@Composable
fun PantallaDetalleVenta(idVenta: Long, onAtras: () -> Unit) {
    val vm = vistaModelo(key = "venta_$idVenta") { DetalleVentaViewModel(it) }
    val estado by vm.estado.collectAsState()
    var anular by remember { mutableStateOf(false) }
    LifecycleEventEffect(Lifecycle.Event.ON_RESUME) { vm.cargar(idVenta) }

    Scaffold(topBar = { BarraSuperior(estado.venta?.comprobante ?: "Detalle de venta", onAtras = onAtras) }) { relleno ->
        val venta = estado.venta
        if (venta == null) {
            if (estado.cargando) Cargando(Modifier.padding(relleno)) else TarjetaAviso(estado.aviso, Modifier.padding(relleno).padding(16.dp))
            return@Scaffold
        }
        LazyColumn(Modifier.fillMaxSize().padding(relleno), contentPadding = PaddingValues(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
            item {
                Card(shape = RoundedCornerShape(14.dp), colors = CardDefaults.cardColors(containerColor = Color.White)) {
                    Column(Modifier.fillMaxWidth().padding(16.dp), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Text("${venta.tipo} ${venta.comprobante}", style = MaterialTheme.typography.titleMedium, modifier = Modifier.weight(1f))
                            EtiquetaEstado(venta.estado)
                        }
                        Text(venta.total.soles(), style = MaterialTheme.typography.displaySmall)
                        Text("${fechaCorta(venta.fechaHora)} · ${venta.tienda} · caja ${venta.caja}", style = MaterialTheme.typography.bodyMedium)
                        Text("Cajero: ${venta.cajero}", style = MaterialTheme.typography.bodyMedium)
                        Text("Cliente: ${venta.cliente}", style = MaterialTheme.typography.bodyMedium)
                    }
                }
            }
            item { TarjetaAviso(estado.aviso) }
            venta.anulacion?.let { a ->
                item {
                    TarjetaAviso(Aviso("Anulada el ${fechaCorta(a.fechaHora)}: ${a.motivo}", TipoAviso.ALERTA))
                }
            }
            item { Text("Productos", style = MaterialTheme.typography.titleMedium) }
            items(venta.lineas, key = { it.idProducto }) { l ->
                Row(Modifier.fillMaxWidth().padding(vertical = 4.dp)) {
                    Column(Modifier.weight(1f)) {
                        Text(l.nombre, style = MaterialTheme.typography.bodyLarge)
                        val alPeso = l.unidad.equals("kg", ignoreCase = true)
                        Text("${l.cantidad.textoCantidad(alPeso)} ${l.unidad} × ${l.precioUnitario.soles()}", style = MaterialTheme.typography.bodySmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant)
                    }
                    Text(l.importe.soles(), style = MaterialTheme.typography.bodyLarge)
                }
                HorizontalDivider()
            }
            item { Text("Pagos", style = MaterialTheme.typography.titleMedium, modifier = Modifier.padding(top = 8.dp)) }
            items(venta.pagos) { p ->
                Column(Modifier.padding(vertical = 4.dp)) {
                    Text("${p.metodo} · ${p.monto.soles()}" + if (p.estado == "ANULADO") " (anulado)" else "", style = MaterialTheme.typography.bodyLarge)
                    Text(p.detalle, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                }
            }
            if (venta.puedeAnular) {
                item { BotonGrande("Anular venta", onClick = { anular = true }, ocupado = estado.anulando, resaltado = false) }
            }
        }
    }

    if (anular) {
        var motivo by remember { mutableStateOf("") }
        AlertDialog(
            onDismissRequest = { anular = false },
            title = { Text("Anular ${estado.venta?.comprobante.orEmpty()}") },
            text = {
                Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    Text("La venta no se borra: queda como ANULADA con tu nombre y el motivo, sus pagos se anulan y el stock vuelve a la tienda.")
                    OutlinedTextField(value = motivo, onValueChange = { motivo = it.take(150) }, label = { Text("Motivo") }, modifier = Modifier.fillMaxWidth())
                }
            },
            confirmButton = { TextButton(onClick = { anular = false; vm.anular(motivo) }, enabled = motivo.isNotBlank()) { Text("Anular") } },
            dismissButton = { TextButton(onClick = { anular = false }) { Text("Cancelar") } },
        )
    }
}
