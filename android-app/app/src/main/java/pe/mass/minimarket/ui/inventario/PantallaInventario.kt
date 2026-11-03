package pe.mass.minimarket.ui.inventario

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.rememberLazyListState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material.icons.filled.Check
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.filled.Inventory2
import androidx.compose.material.icons.filled.NorthEast
import androidx.compose.material.icons.filled.SearchOff
import androidx.compose.material.icons.filled.SouthWest
import androidx.compose.material.icons.filled.SwapVert
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.FilterChipDefaults
import androidx.compose.material3.Surface
import androidx.compose.material3.pulltorefresh.PullToRefreshBox
import androidx.compose.runtime.derivedStateOf
import androidx.compose.runtime.remember
import pe.mass.minimarket.ui.comun.AccionActualizar
import pe.mass.minimarket.ui.comun.EsqueletoLista
import pe.mass.minimarket.ui.comun.EstadoError
import pe.mass.minimarket.ui.comun.EstadoVacio
import pe.mass.minimarket.ui.comun.Tarjeta
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.QrCodeScanner
import androidx.compose.material.icons.filled.Refresh
import androidx.compose.material.icons.filled.Search
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExtendedFloatingActionButton
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
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.unit.dp
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.ViewModel
import androidx.lifecycle.compose.LifecycleEventEffect
import androidx.lifecycle.viewModelScope
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import pe.mass.minimarket.data.Repositorio
import pe.mass.minimarket.data.Resultado
import pe.mass.minimarket.data.api.Movimiento
import pe.mass.minimarket.data.api.Producto
import pe.mass.minimarket.dominio.textoCantidad
import pe.mass.minimarket.ui.comun.Aviso
import pe.mass.minimarket.ui.comun.BarraSuperior
import pe.mass.minimarket.ui.comun.Cargando
import pe.mass.minimarket.ui.comun.FilaProducto
import pe.mass.minimarket.ui.comun.TarjetaAviso
import pe.mass.minimarket.ui.comun.TipoAviso
import pe.mass.minimarket.ui.comun.fechaCorta
import pe.mass.minimarket.ui.comun.vistaModelo
import pe.mass.minimarket.ui.escaner.TarjetaProducto
import pe.mass.minimarket.ui.tema.Mass

enum class Filtro(val clave: String, val texto: String) {
    TODOS("todos", "Todos"),
    BAJO("bajo", "Stock bajo"),
    AGOTADOS("agotados", "Agotados");

    companion object {
        fun deClave(clave: String?): Filtro = entries.firstOrNull { it.clave == clave } ?: TODOS
    }
}

data class EstadoInventario(
    val texto: String = "",
    val filtro: Filtro = Filtro.TODOS,
    val productos: List<Producto> = emptyList(),
    val total: Int = 0,
    val pagina: Int = 1,
    val hayMas: Boolean = false,
    val cargando: Boolean = false,
    val aviso: Aviso? = null,
    // Solo cuando el usuario desliza hacia abajo
    val refrescando: Boolean = false,
)

/** Inventario de la tienda del usuario, leido del servidor (las mismas tablas que la pagina web). */
class InventarioViewModel(private val repo: Repositorio) : ViewModel() {
    private val _estado = MutableStateFlow(EstadoInventario())
    val estado: StateFlow<EstadoInventario> = _estado
    private var busqueda: Job? = null

    fun texto(valor: String) {
        _estado.update { it.copy(texto = valor.take(60)) }
        // Espera a que deje de escribir
        busqueda?.cancel()
        busqueda = viewModelScope.launch {
            delay(400)
            cargar(pagina = 1)
        }
    }

    fun filtro(valor: Filtro) {
        _estado.update { it.copy(filtro = valor) }
        recargar()
    }

    fun recargar(silencioso: Boolean = false) {
        busqueda?.cancel()
        busqueda = viewModelScope.launch { cargar(pagina = 1, silencioso = silencioso) }
    }

    fun refrescar() {
        _estado.update { it.copy(refrescando = true) }
        busqueda?.cancel()
        busqueda = viewModelScope.launch {
            cargar(pagina = 1, silencioso = true)
            _estado.update { it.copy(refrescando = false) }
        }
    }

    /** El filtro con el que se abre la pantalla (desde los indicadores del inicio). Solo la primera vez. */
    private var filtroPuesto = false
    fun filtroInicial(valor: Filtro) {
        if (filtroPuesto) return
        filtroPuesto = true
        _estado.update { it.copy(filtro = valor) }
    }

    fun siguientePagina() {
        val e = _estado.value
        if (e.cargando || !e.hayMas) return
        viewModelScope.launch { cargar(pagina = e.pagina + 1) }
    }

    private suspend fun cargar(pagina: Int, silencioso: Boolean = false) {
        val e = _estado.value
        if (!silencioso) _estado.update { it.copy(cargando = true) }
        when (val r = repo.productos(e.texto.trim(), e.filtro.clave, pagina)) {
            is Resultado.Ok -> _estado.update {
                it.copy(
                    productos = if (pagina == 1) r.datos.productos else it.productos + r.datos.productos,
                    total = r.datos.total,
                    pagina = r.datos.pagina,
                    hayMas = r.datos.hayMas,
                    cargando = false,
                    aviso = null,
                )
            }
            is Resultado.Error -> _estado.update { it.copy(cargando = false, aviso = Aviso(r.mensaje, TipoAviso.ERROR)) }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun PantallaInventario(onAtras: () -> Unit, onAbrir: (Int) -> Unit, onEscanear: () -> Unit, filtroInicial: Filtro = Filtro.TODOS) {
    val vm = vistaModelo { InventarioViewModel(it) }
    val estado by vm.estado.collectAsState()
    val lista = rememberLazyListState()
    // Al bajar por la lista, el boton de escanear se achica para no tapar los productos
    val extendido by remember { derivedStateOf { lista.firstVisibleItemIndex == 0 } }

    // Datos frescos al entrar o volver a la pantalla, y cada minuto mientras esta abierta
    LifecycleEventEffect(Lifecycle.Event.ON_RESUME) {
        vm.filtroInicial(filtroInicial)
        vm.recargar()
    }
    LaunchedEffect(Unit) {
        while (true) {
            delay(60_000)
            vm.recargar(silencioso = true)
        }
    }

    Scaffold(
        topBar = {
            BarraSuperior("Inventario", onAtras = onAtras, subtitulo = "${estado.total} producto(s)", acciones = {
                AccionActualizar(onClick = { vm.recargar() })
            })
        },
        floatingActionButton = {
            ExtendedFloatingActionButton(
                onClick = onEscanear,
                expanded = extendido,
                icon = { Icon(Icons.Filled.QrCodeScanner, contentDescription = null) },
                text = { Text("Escanear") },
                containerColor = MaterialTheme.colorScheme.primaryContainer,
                contentColor = MaterialTheme.colorScheme.onPrimaryContainer,
            )
        },
    ) { relleno ->
        Column(Modifier.fillMaxSize().padding(relleno)) {
            Column(Modifier.padding(horizontal = 16.dp, vertical = 8.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                OutlinedTextField(
                    value = estado.texto,
                    onValueChange = vm::texto,
                    placeholder = { Text("Buscar por nombre o código") },
                    leadingIcon = { Icon(Icons.Filled.Search, contentDescription = null) },
                    trailingIcon = {
                        if (estado.texto.isNotEmpty()) {
                            IconButton(onClick = { vm.texto("") }) { Icon(Icons.Filled.Close, contentDescription = "Borrar búsqueda") }
                        }
                    },
                    singleLine = true,
                    shape = RoundedCornerShape(50),
                    keyboardOptions = KeyboardOptions(imeAction = ImeAction.Search),
                    modifier = Modifier.fillMaxWidth(),
                )
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    Filtro.entries.forEach { f ->
                        FilterChip(
                            selected = estado.filtro == f,
                            onClick = { vm.filtro(f) },
                            label = { Text(f.texto) },
                            leadingIcon = if (estado.filtro == f) {
                                { Icon(Icons.Filled.Check, contentDescription = null, modifier = Modifier.size(FilterChipDefaults.IconSize)) }
                            } else {
                                null
                            },
                        )
                    }
                }
            }
            PullToRefreshBox(isRefreshing = estado.refrescando, onRefresh = vm::refrescar, modifier = Modifier.fillMaxSize()) {
                when {
                    estado.cargando && estado.productos.isEmpty() -> EsqueletoLista()
                    estado.aviso != null && estado.productos.isEmpty() ->
                        EstadoError(estado.aviso!!.texto, onReintentar = { vm.recargar() })
                    estado.productos.isEmpty() -> EstadoVacio(
                        if (estado.filtro == Filtro.TODOS) Icons.Filled.SearchOff else Icons.Filled.Inventory2,
                        when (estado.filtro) {
                            Filtro.AGOTADOS -> "No hay productos agotados"
                            Filtro.BAJO -> "Nada por reponer"
                            Filtro.TODOS -> "Sin resultados"
                        },
                        if (estado.filtro == Filtro.TODOS) "Prueba con otro nombre o escanea el código." else "¡Todo el stock está en orden!",
                    )
                    else -> LazyColumn(Modifier.fillMaxSize(), state = lista, contentPadding = PaddingValues(bottom = 96.dp)) {
                        item { TarjetaAviso(estado.aviso, Modifier.padding(horizontal = 16.dp)) }
                        items(estado.productos, key = { it.idProducto }) { p ->
                            FilaProducto(p, onClick = { onAbrir(p.idProducto) }, modifier = Modifier.animateItem())
                            HorizontalDivider(Modifier.padding(start = 72.dp), color = MaterialTheme.colorScheme.outlineVariant)
                        }
                        if (estado.hayMas) {
                            item {
                                // Cargar la siguiente pagina sola al llegar al final
                                LaunchedEffect(estado.pagina) { vm.siguientePagina() }
                                Box(Modifier.fillMaxWidth().padding(16.dp), contentAlignment = Alignment.Center) {
                                    if (estado.cargando) CircularProgressIndicator() else TextButton(onClick = vm::siguientePagina) { Text("Cargar más") }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

// ---------- Ficha del producto ----------

data class EstadoProducto(val producto: Producto? = null, val cargando: Boolean = true, val aviso: Aviso? = null)

class ProductoViewModel(private val repo: Repositorio) : ViewModel() {
    private val _estado = MutableStateFlow(EstadoProducto())
    val estado: StateFlow<EstadoProducto> = _estado

    fun cargar(idProducto: Int) {
        _estado.update { it.copy(cargando = true) }
        viewModelScope.launch {
            when (val r = repo.producto(idProducto)) {
                is Resultado.Ok -> _estado.update { EstadoProducto(producto = r.datos, cargando = false) }
                is Resultado.Error -> _estado.update { it.copy(cargando = false, aviso = Aviso(r.mensaje, TipoAviso.ERROR)) }
            }
        }
    }
}

@Composable
fun PantallaProducto(idProducto: Int, onAtras: () -> Unit) {
    val vm = vistaModelo(key = "producto_$idProducto") { ProductoViewModel(it) }
    val estado by vm.estado.collectAsState()
    LifecycleEventEffect(Lifecycle.Event.ON_RESUME) { vm.cargar(idProducto) }

    Scaffold(topBar = {
        BarraSuperior("Ficha del producto", onAtras = onAtras, acciones = { AccionActualizar(onClick = { vm.cargar(idProducto) }) })
    }) { relleno ->
        val producto = estado.producto
        if (producto == null) {
            if (estado.cargando) {
                EsqueletoLista(Modifier.padding(relleno), filas = 5)
            } else {
                EstadoError(estado.aviso?.texto ?: "No se encontró el producto.", onReintentar = { vm.cargar(idProducto) }, modifier = Modifier.padding(relleno))
            }
            return@Scaffold
        }
        LazyColumn(Modifier.fillMaxSize().padding(relleno), contentPadding = androidx.compose.foundation.layout.PaddingValues(16.dp),
            verticalArrangement = Arrangement.spacedBy(8.dp)) {
            item { TarjetaProducto(producto) }
            item { TarjetaAviso(estado.aviso) }
            item { Text("Últimos movimientos en tu tienda", style = MaterialTheme.typography.titleMedium, modifier = Modifier.padding(top = 8.dp)) }
            if (producto.movimientos.isEmpty()) {
                item { EstadoVacio(Icons.Filled.SwapVert, "Sin movimientos todavía", "Aquí verás las ventas, entradas y ajustes de este producto.") }
            }
            items(producto.movimientos, key = { it.idMovimiento }) { m -> FilaMovimiento(m, producto.alPeso) }
        }
    }
}

@Composable
private fun FilaMovimiento(m: Movimiento, alPeso: Boolean) {
    val c = Mass.colores
    Tarjeta(Modifier.fillMaxWidth()) {
        Row(Modifier.padding(12.dp), verticalAlignment = Alignment.CenterVertically) {
            Surface(
                shape = CircleShape,
                color = if (m.entrada) c.exitoFondo else c.peligroFondo,
                contentColor = if (m.entrada) c.exito else c.peligro,
                modifier = Modifier.size(40.dp),
            ) {
                Box(contentAlignment = Alignment.Center) {
                    Icon(if (m.entrada) Icons.Filled.SouthWest else Icons.Filled.NorthEast, contentDescription = if (m.entrada) "Entrada" else "Salida")
                }
            }
            Spacer(Modifier.width(12.dp))
            Column(Modifier.weight(1f)) {
                Text(m.tipo, style = MaterialTheme.typography.titleSmall)
                Text("${fechaCorta(m.fechaHora)} · ${m.origen} · ${m.empleado}", style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant)
                m.observacion?.let { Text(it, style = MaterialTheme.typography.bodySmall) }
            }
            Column(horizontalAlignment = Alignment.End) {
                Text((if (m.entrada) "+" else "−") + m.cantidad.textoCantidad(alPeso), color = if (m.entrada) c.exito else c.peligro,
                    style = MaterialTheme.typography.titleMedium)
                Text("Queda ${m.stockNuevo.textoCantidad(alPeso)}", style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant)
            }
        }
    }
}
