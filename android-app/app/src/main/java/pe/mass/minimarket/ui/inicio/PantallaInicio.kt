package pe.mass.minimarket.ui.inicio

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.aspectRatio
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowForward
import androidx.compose.material.icons.automirrored.filled.Logout
import androidx.compose.material.icons.automirrored.filled.ReceiptLong
import androidx.compose.material.icons.automirrored.filled.TrendingDown
import androidx.compose.material.icons.automirrored.filled.VolumeUp
import androidx.compose.material.icons.filled.AddBox
import androidx.compose.material.icons.filled.DarkMode
import androidx.compose.material.icons.filled.Dns
import androidx.compose.material.icons.filled.Inventory2
import androidx.compose.material.icons.filled.LocalShipping
import androidx.compose.material.icons.filled.PointOfSale
import androidx.compose.material.icons.filled.QrCodeScanner
import androidx.compose.material.icons.filled.RemoveShoppingCart
import androidx.compose.material.icons.filled.Search
import androidx.compose.material.icons.filled.WarningAmber
import androidx.compose.material.icons.filled.Store
import androidx.compose.material.icons.filled.Vibration
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.ListItem
import androidx.compose.material3.ListItemDefaults
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.material3.SegmentedButton
import androidx.compose.material3.SegmentedButtonDefaults
import androidx.compose.material3.SingleChoiceSegmentedButtonRow
import androidx.compose.material3.Surface
import androidx.compose.material3.Switch
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.pulltorefresh.PullToRefreshBox
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.drawBehind
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.ViewModel
import androidx.lifecycle.compose.LifecycleEventEffect
import androidx.lifecycle.viewModelScope
import kotlinx.coroutines.async
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import pe.mass.minimarket.BuildConfig
import pe.mass.minimarket.Rutas
import pe.mass.minimarket.data.ModoTema
import pe.mass.minimarket.data.Repositorio
import pe.mass.minimarket.data.Resultado
import pe.mass.minimarket.data.api.EstadoCaja
import pe.mass.minimarket.data.api.Producto
import pe.mass.minimarket.data.api.Perfil
import pe.mass.minimarket.dominio.saludo
import pe.mass.minimarket.ui.comun.BarraSuperior
import pe.mass.minimarket.ui.comun.BotonGrande
import pe.mass.minimarket.ui.comun.DialogoBuscarProducto
import pe.mass.minimarket.ui.comun.FilaProducto
import pe.mass.minimarket.ui.comun.FilaReciente
import pe.mass.minimarket.ui.comun.Inicial
import pe.mass.minimarket.ui.comun.Pastilla
import pe.mass.minimarket.ui.comun.Tarjeta
import pe.mass.minimarket.ui.comun.TituloSeccion
import pe.mass.minimarket.ui.comun.preferencias
import pe.mass.minimarket.ui.comun.repositorio
import pe.mass.minimarket.ui.comun.soloHora
import pe.mass.minimarket.ui.comun.vistaModelo
import pe.mass.minimarket.ui.inventario.Filtro
import pe.mass.minimarket.ui.tema.Marca
import pe.mass.minimarket.ui.tema.Mass
import pe.mass.minimarket.ui.tema.Tinta
import pe.mass.minimarket.ui.tema.numeros
import java.time.LocalDate
import java.time.LocalTime

private data class Opcion(
    val titulo: String,
    val detalle: String,
    val icono: ImageVector,
    val ruta: String,
    // Nombre corto para la fila de accesos rapidos (4 por fila)
    val corto: String = titulo,
    // Texto del boton oscuro de la tarjeta amarilla
    val accion: String = "Abrir",
)

/** La accion principal del rol: lo que mas hace en el dia, en grande y arriba. */
private fun principal(perfil: Perfil): Opcion? {
    val p = perfil.permisos
    return when {
        p.vender -> Opcion("Nueva venta", "Escanea y cobra", Icons.Filled.PointOfSale, Rutas.VENTA, accion = "Vender")
        p.registrarIngreso -> Opcion(
            "Entrada de stock",
            if (p.crearProducto) "Escanea lo que llega; si es nuevo, regístralo ahí mismo" else "Recibe mercadería con su guía o factura",
            Icons.Filled.LocalShipping,
            Rutas.ENTRADA,
            accion = "Escanear",
        )
        else -> null
    }
}

/** Los accesos rapidos: solo lo que permite el rol (el servidor igual revisa cada permiso). */
private fun accesos(perfil: Perfil): List<Opcion> = buildList {
    val p = perfil.permisos
    add(Opcion("Consultar precio", "Precio y stock al instante", Icons.Filled.QrCodeScanner, Rutas.consulta(), corto = "Consultar"))
    if (p.crearProducto) add(Opcion("Agregar producto", "Escanea un código nuevo", Icons.Filled.AddBox, Rutas.REGISTRAR, corto = "Agregar"))
    if (p.vender && p.registrarIngreso) add(Opcion("Entrada de stock", "Recibir mercadería", Icons.Filled.LocalShipping, Rutas.ENTRADA, corto = "Entrada"))
    if (p.verInventario) add(Opcion("Inventario", "Stock de la tienda", Icons.Filled.Inventory2, Rutas.inventario()))
    if (p.verVentas) add(Opcion("Ventas", "Comprobantes de la tienda", Icons.AutoMirrored.Filled.ReceiptLong, Rutas.HISTORIAL))
}

data class EstadoInicio(
    val perfil: Perfil? = null,
    val caja: EstadoCaja? = null,
    val agotados: Int? = null,
    val bajos: Int? = null,
    val ventasHoy: Int? = null,
    // Primeros productos agotados o con poco stock, para nombrarlos en el aviso "Requiere atención"
    val porReponer: List<Producto> = emptyList(),
    // Los productos vistos hace poco, con su stock de ahora (pedido al servidor)
    val recientes: List<Producto> = emptyList(),
    val actualizando: Boolean = false,
    // Solo cuando el usuario desliza hacia abajo se muestra el indicador de "actualizando"
    val refrescando: Boolean = false,
)

/** El resumen del inicio: turno de caja, alertas de stock y ventas del dia. Si algo falla, simplemente no se muestra. */
class InicioViewModel(private val repo: Repositorio) : ViewModel() {
    private val _estado = MutableStateFlow(EstadoInicio(perfil = repo.perfil))
    val estado: StateFlow<EstadoInicio> = _estado

    fun actualizar(idsRecientes: List<Int>, desdeGesto: Boolean = false) {
        if (_estado.value.actualizando) return
        _estado.update { it.copy(actualizando = true, refrescando = desdeGesto) }
        viewModelScope.launch {
            // Los permisos pueden haber cambiado en la web (otro rol, otra tienda): se vuelven a pedir
            val perfil = (repo.refrescarPerfil() as? Resultado.Ok)?.datos ?: repo.perfil
            val p = perfil?.permisos
            val hoy = LocalDate.now().toString()
            val caja = async { if (p?.vender == true) (repo.caja() as? Resultado.Ok)?.datos else null }
            val agotados = async { if (p?.verInventario == true) (repo.productos(null, Filtro.AGOTADOS.clave, 1) as? Resultado.Ok)?.datos else null }
            val bajos = async { if (p?.verInventario == true) (repo.productos(null, Filtro.BAJO.clave, 1) as? Resultado.Ok)?.datos else null }
            val ventas = async { if (p?.verVentas == true) (repo.ventas(hoy, hoy, null, null, 1) as? Resultado.Ok)?.datos?.total else null }
            // Precio y stock al dia de los vistos hace poco (si alguno falla, se muestra como antes, sin stock)
            val recientes = idsRecientes.map { id -> async { (repo.producto(id) as? Resultado.Ok)?.datos } }
            val pagAgotados = agotados.await()
            val pagBajos = bajos.await()
            _estado.value = EstadoInicio(
                perfil = perfil,
                caja = caja.await(),
                agotados = pagAgotados?.total,
                bajos = pagBajos?.total,
                ventasHoy = ventas.await(),
                porReponer = (pagAgotados?.productos.orEmpty() + pagBajos?.productos.orEmpty()).distinctBy { it.idProducto },
                recientes = recientes.mapNotNull { it.await() },
                actualizando = false,
            )
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun PantallaInicio(onIr: (String) -> Unit) {
    val vm = vistaModelo { InicioViewModel(it) }
    val estado by vm.estado.collectAsState()
    val ajustes by preferencias().ajustes.collectAsState()
    val idsRecientes = ajustes.recientes.take(MAX_EN_INICIO).map { it.id }
    LifecycleEventEffect(Lifecycle.Event.ON_RESUME) { vm.actualizar(idsRecientes) }
    var buscar by remember { mutableStateOf(false) }

    Scaffold { relleno ->
        val perfil = estado.perfil ?: return@Scaffold
        PullToRefreshBox(
            isRefreshing = estado.refrescando,
            onRefresh = { vm.actualizar(idsRecientes, desdeGesto = true) },
            modifier = Modifier.fillMaxSize().padding(relleno),
        ) {
            LazyColumn(
                Modifier.fillMaxSize(),
                contentPadding = PaddingValues(start = 16.dp, end = 16.dp, top = 8.dp, bottom = 24.dp),
                verticalArrangement = Arrangement.spacedBy(16.dp),
            ) {
                item { Encabezado(perfil, onPerfil = { onIr(Rutas.PERFIL) }) }
                item { Buscador(onBuscar = { buscar = true }, onEscanear = { onIr(Rutas.consulta()) }) }
                principal(perfil)?.let { op ->
                    item { TarjetaPrincipal(op, detalle = textoCaja(perfil, estado.caja) ?: op.detalle) { onIr(op.ruta) } }
                }
                item { Resumen(estado, onIr) }
                item {
                    Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                        TituloSeccion("Accesos rápidos")
                        accesos(perfil).chunked(POR_FILA).forEach { fila ->
                            Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                                fila.forEach { op -> TarjetaAcceso(op, Modifier.weight(1f)) { onIr(op.ruta) } }
                                repeat(POR_FILA - fila.size) { Spacer(Modifier.weight(1f)) }
                            }
                        }
                    }
                }
                if ((estado.agotados ?: 0) > 0 || (estado.bajos ?: 0) > 0) {
                    item {
                        Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                            TituloSeccion("Requiere atención")
                            AvisoStock(estado) { filtro -> onIr(Rutas.inventario(filtro)) }
                        }
                    }
                }
                if (ajustes.recientes.isNotEmpty()) {
                    item {
                        val alDia = estado.recientes.associateBy { it.idProducto }
                        Tarjeta(Modifier.fillMaxWidth()) {
                            TituloSeccion(
                                "Vistos hace poco",
                                Modifier.padding(start = 16.dp, end = 4.dp, top = 4.dp),
                                accion = if (perfil.permisos.verInventario) "Ver todo" else null,
                                onAccion = { onIr(Rutas.inventario()) },
                            )
                            ajustes.recientes.take(MAX_EN_INICIO).forEach { r ->
                                val producto = alDia[r.id]
                                // Con el stock de ahora se ve la etiqueta (agotado, bajo o cuantos hay)
                                if (producto != null) {
                                    FilaProducto(producto, onClick = { onIr(Rutas.consulta(r.id)) })
                                } else {
                                    FilaReciente(r, onClick = { onIr(Rutas.consulta(r.id)) })
                                }
                            }
                            Spacer(Modifier.size(4.dp))
                        }
                    }
                }
            }
        }
    }

    if (buscar) {
        DialogoBuscarProducto(
            onElegir = { producto ->
                buscar = false
                onIr(Rutas.consulta(producto.idProducto))
            },
            onCerrar = { buscar = false },
            soloActivos = false,
        )
    }
}

private const val POR_FILA = 4
private const val MAX_EN_INICIO = 5

/** Campo de busqueda (abre la busqueda por nombre o codigo) con el boton amarillo del escaner. */
@Composable
private fun Buscador(onBuscar: () -> Unit, onEscanear: () -> Unit) {
    Tarjeta(Modifier.fillMaxWidth(), onClick = onBuscar) {
        Row(Modifier.heightIn(min = 54.dp).padding(start = 14.dp, end = 8.dp), verticalAlignment = Alignment.CenterVertically) {
            Icon(Icons.Filled.Search, contentDescription = null, tint = MaterialTheme.colorScheme.onSurfaceVariant)
            Spacer(Modifier.width(10.dp))
            Text(
                "Buscar producto o código…",
                style = MaterialTheme.typography.bodyLarge,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis,
                modifier = Modifier.weight(1f),
            )
            Surface(
                onClick = onEscanear,
                shape = MaterialTheme.shapes.small,
                color = Marca,
                contentColor = Tinta,
                modifier = Modifier.size(40.dp),
            ) {
                Box(contentAlignment = Alignment.Center) { Icon(Icons.Filled.QrCodeScanner, contentDescription = "Escanear código") }
            }
        }
    }
}

/** "5 agotados y 8 por reponer · Fanta, Leche Gloria y más": lleva a la lista del inventario. */
@Composable
private fun AvisoStock(estado: EstadoInicio, onIr: (Filtro) -> Unit) {
    val c = Mass.colores
    val agotados = estado.agotados ?: 0
    val bajos = estado.bajos ?: 0
    val titulo = when {
        agotados > 0 && bajos > 0 -> "$agotados agotado${if (agotados == 1) "" else "s"} y $bajos por reponer"
        agotados > 0 -> if (agotados == 1) "1 producto agotado" else "$agotados productos agotados"
        else -> if (bajos == 1) "1 producto con poco stock" else "$bajos productos con poco stock"
    }
    val nombres = estado.porReponer.map { it.nombre }
    val detalle = when {
        nombres.isEmpty() -> "Revisa el inventario para reponer"
        nombres.size <= 2 && agotados + bajos <= 2 -> nombres.joinToString(" y ")
        else -> nombres.take(2).joinToString(", ") + " y más por reponer"
    }
    Surface(
        onClick = { onIr(if (agotados > 0) Filtro.AGOTADOS else Filtro.BAJO) },
        shape = MaterialTheme.shapes.medium,
        color = c.peligroFondo,
        contentColor = c.peligro,
        modifier = Modifier.fillMaxWidth(),
    ) {
        Row(Modifier.padding(14.dp), verticalAlignment = Alignment.CenterVertically) {
            Surface(shape = MaterialTheme.shapes.small, color = c.peligro, contentColor = c.peligroFondo, modifier = Modifier.size(42.dp)) {
                Box(contentAlignment = Alignment.Center) { Icon(Icons.Filled.WarningAmber, contentDescription = null) }
            }
            Spacer(Modifier.width(12.dp))
            Column(Modifier.weight(1f)) {
                Text(titulo, style = MaterialTheme.typography.titleSmall, color = MaterialTheme.colorScheme.onSurface)
                Text(
                    detalle,
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                )
            }
            Icon(Icons.AutoMirrored.Filled.ArrowForward, contentDescription = null, modifier = Modifier.size(20.dp))
        }
    }
}

/** "Turno abierto en Caja 1 desde las 08:00" o "Abre tu turno para empezar". */
private fun textoCaja(perfil: Perfil, caja: EstadoCaja?): String? {
    if (!perfil.permisos.vender || caja == null) return null
    val turno = caja.turno ?: return "Abre tu turno de caja para empezar a vender"
    return "Caja ${turno.numeroCaja ?: turno.idCaja} abierta desde las ${soloHora(turno.apertura)}"
}

@Composable
private fun Encabezado(perfil: Perfil, onPerfil: () -> Unit) {
    Row(verticalAlignment = Alignment.CenterVertically, modifier = Modifier.padding(top = 8.dp)) {
        Column(Modifier.weight(1f)) {
            Text(
                "${saludo(LocalTime.now().hour)},",
                style = MaterialTheme.typography.titleMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
            Text(perfil.nombres, style = MaterialTheme.typography.headlineMedium, maxLines = 1, overflow = TextOverflow.Ellipsis)
            Spacer(Modifier.size(6.dp))
            Row(horizontalArrangement = Arrangement.spacedBy(6.dp), verticalAlignment = Alignment.CenterVertically) {
                Pastilla(perfil.rol, MaterialTheme.colorScheme.secondaryContainer, MaterialTheme.colorScheme.onSecondaryContainer)
                Icon(Icons.Filled.Store, contentDescription = null, modifier = Modifier.size(16.dp), tint = MaterialTheme.colorScheme.onSurfaceVariant)
                Text(
                    perfil.tienda,
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                )
            }
        }
        Box(Modifier.clip(CircleShape).clickable(onClick = onPerfil)) {
            Inicial(perfil.nombreCompleto, tamano = 52)
        }
    }
}

/** La tarjeta amarilla grande: la accion del dia (vender o recibir mercaderia). */
@Composable
private fun TarjetaPrincipal(opcion: Opcion, detalle: String, onClick: () -> Unit) {
    Surface(
        onClick = onClick,
        shape = MaterialTheme.shapes.large,
        color = Color.Transparent,
        shadowElevation = 3.dp,
        modifier = Modifier.fillMaxWidth(),
    ) {
        Box(
            Modifier
                .fillMaxWidth()
                // Del amarillo de la marca al ambar, como el banner de la web
                .background(Brush.linearGradient(listOf(Marca, Color(0xFFF59E0B))))
                // Circulo claro de adorno en la esquina de abajo
                .drawBehind {
                    drawCircle(
                        color = Color.White.copy(alpha = 0.12f),
                        radius = 85.dp.toPx(),
                        center = Offset(size.width - 45.dp.toPx(), size.height - 35.dp.toPx()),
                    )
                }
                .padding(20.dp),
        ) {
            Column(verticalArrangement = Arrangement.spacedBy(14.dp)) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Surface(shape = MaterialTheme.shapes.medium, color = Tinta, contentColor = Marca, modifier = Modifier.size(52.dp)) {
                        Box(contentAlignment = Alignment.Center) { Icon(opcion.icono, contentDescription = null, modifier = Modifier.size(28.dp)) }
                    }
                    Spacer(Modifier.weight(1f))
                    Surface(shape = MaterialTheme.shapes.small, color = Tinta, contentColor = Marca) {
                        Row(Modifier.padding(horizontal = 16.dp, vertical = 10.dp), verticalAlignment = Alignment.CenterVertically) {
                            Text(opcion.accion, style = MaterialTheme.typography.labelLarge)
                            Spacer(Modifier.width(6.dp))
                            Icon(Icons.AutoMirrored.Filled.ArrowForward, contentDescription = null, modifier = Modifier.size(18.dp))
                        }
                    }
                }
                Column {
                    Text(opcion.titulo, style = MaterialTheme.typography.headlineSmall, color = Tinta)
                    Text(detalle, style = MaterialTheme.typography.bodyMedium, color = Tinta.copy(alpha = 0.8f))
                }
            }
        }
    }
}

/** Indicadores del dia: agotados, por reponer y ventas de hoy. Cada uno lleva a su lista. */
@Composable
private fun Resumen(estado: EstadoInicio, onIr: (String) -> Unit) {
    val c = Mass.colores
    val datos = buildList {
        estado.agotados?.let { add(Triple(Indicador("Agotados", it, Icons.Filled.RemoveShoppingCart), c.peligroFondo to c.peligro, Rutas.inventario(Filtro.AGOTADOS))) }
        estado.bajos?.let { add(Triple(Indicador("Por reponer", it, Icons.AutoMirrored.Filled.TrendingDown), c.alertaFondo to c.alerta, Rutas.inventario(Filtro.BAJO))) }
        estado.ventasHoy?.let { add(Triple(Indicador("Ventas hoy", it, Icons.AutoMirrored.Filled.ReceiptLong), c.exitoFondo to c.exito, Rutas.HISTORIAL)) }
    }
    if (datos.isEmpty()) return
    // Tarjeta con borde fino: punto e icono de su color arriba, el numero en JetBrains Mono y el nombre debajo
    Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
        datos.forEach { (ind, colores, ruta) ->
            Surface(
                onClick = { onIr(ruta) },
                shape = MaterialTheme.shapes.medium,
                color = MaterialTheme.colorScheme.surfaceContainerLow,
                border = BorderStroke(1.dp, MaterialTheme.colorScheme.outlineVariant),
                modifier = Modifier.weight(1f),
            ) {
                Column(Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(2.dp)) {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Box(Modifier.size(8.dp).clip(CircleShape).background(colores.second))
                        Spacer(Modifier.weight(1f))
                        Icon(ind.icono, contentDescription = null, tint = colores.second, modifier = Modifier.size(16.dp))
                    }
                    Spacer(Modifier.size(2.dp))
                    Text(ind.valor.toString(), style = MaterialTheme.typography.headlineSmall.numeros(), color = colores.second)
                    Text(
                        ind.titulo,
                        style = MaterialTheme.typography.labelMedium,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis,
                    )
                }
            }
        }
    }
}

private data class Indicador(val titulo: String, val valor: Int, val icono: ImageVector)

/** Acceso rapido: un cuadro con el icono y el nombre corto debajo (4 por fila, como en la web). */
@Composable
private fun TarjetaAcceso(opcion: Opcion, modifier: Modifier = Modifier, onClick: () -> Unit) {
    Column(modifier, horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.spacedBy(6.dp)) {
        Tarjeta(Modifier.fillMaxWidth().aspectRatio(1f), onClick = onClick) {
            Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                Icon(opcion.icono, contentDescription = opcion.titulo, modifier = Modifier.size(28.dp))
            }
        }
        Text(
            opcion.corto,
            style = MaterialTheme.typography.labelMedium,
            textAlign = TextAlign.Center,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis,
        )
    }
}

// ---------- Mi perfil y ajustes ----------

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun PantallaPerfil(onAtras: () -> Unit, onSalir: () -> Unit) {
    val repo = repositorio()
    val prefs = preferencias()
    val ajustes by prefs.ajustes.collectAsState()
    val perfil = repo.perfil
    val alcance = rememberCoroutineScope()
    var saliendo by remember { mutableStateOf(false) }
    var confirmar by remember { mutableStateOf(false) }

    Scaffold(topBar = { BarraSuperior("Mi perfil", onAtras = onAtras) }) { relleno ->
        Column(
            Modifier.fillMaxSize().padding(relleno).verticalScroll(rememberScrollState()).padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(16.dp),
        ) {
            if (perfil != null) {
                Column(Modifier.fillMaxWidth(), horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.spacedBy(6.dp)) {
                    Inicial(perfil.nombreCompleto, tamano = 80)
                    Text(perfil.nombreCompleto, style = MaterialTheme.typography.titleLarge)
                    Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                        Pastilla(perfil.rol, MaterialTheme.colorScheme.secondaryContainer, MaterialTheme.colorScheme.onSecondaryContainer)
                        Pastilla(perfil.tienda, MaterialTheme.colorScheme.surfaceContainerHigh, MaterialTheme.colorScheme.onSurfaceVariant)
                    }
                    Text("DNI ${perfil.dni}", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                }
            }

            TituloSeccion("Apariencia")
            Tarjeta(Modifier.fillMaxWidth()) {
                Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Icon(Icons.Filled.DarkMode, contentDescription = null, tint = MaterialTheme.colorScheme.onSurfaceVariant)
                        Spacer(Modifier.width(12.dp))
                        Text("Tema", style = MaterialTheme.typography.bodyLarge)
                    }
                    SingleChoiceSegmentedButtonRow(Modifier.fillMaxWidth()) {
                        ModoTema.entries.forEachIndexed { i, modo ->
                            SegmentedButton(
                                selected = ajustes.tema == modo,
                                onClick = { prefs.tema(modo) },
                                shape = SegmentedButtonDefaults.itemShape(i, ModoTema.entries.size),
                            ) { Text(modo.texto) }
                        }
                    }
                }
            }

            TituloSeccion("Al escanear")
            Tarjeta(Modifier.fillMaxWidth()) {
                Interruptor(Icons.AutoMirrored.Filled.VolumeUp, "Sonido", "Pitido al leer un código", ajustes.sonido, prefs::sonido)
                HorizontalDivider(color = MaterialTheme.colorScheme.outlineVariant)
                Interruptor(Icons.Filled.Vibration, "Vibración", "Vibra al encontrar o no encontrar el producto", ajustes.vibracion, prefs::vibracion)
            }

            TituloSeccion("Conexión")
            Tarjeta(Modifier.fillMaxWidth()) {
                ListItem(
                    leadingContent = { Icon(Icons.Filled.Dns, contentDescription = null) },
                    headlineContent = { Text("Servidor") },
                    supportingContent = { Text(repo.urlServidor) },
                    colors = ListItemDefaults.colors(containerColor = Color.Transparent),
                )
                Text(
                    "La app no guarda productos ni ventas: todo se lee y se registra en el mismo servidor de la página web. Versión ${BuildConfig.VERSION_NAME}.",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    modifier = Modifier.padding(start = 16.dp, end = 16.dp, bottom = 16.dp),
                )
            }

            BotonGrande("Cerrar sesión", onClick = { confirmar = true }, icono = Icons.AutoMirrored.Filled.Logout, ocupado = saliendo, resaltado = false)
        }
    }

    if (confirmar) {
        AlertDialog(
            onDismissRequest = { confirmar = false },
            icon = { Icon(Icons.AutoMirrored.Filled.Logout, contentDescription = null) },
            title = { Text("¿Cerrar sesión?") },
            text = { Text("Para volver a entrar necesitarás tu DNI y tu clave.") },
            confirmButton = {
                TextButton(onClick = {
                    confirmar = false
                    saliendo = true
                    alcance.launch {
                        repo.salir()
                        // Los productos vistos son de esta persona: el siguiente usuario empieza de cero
                        prefs.borrarRecientes()
                        onSalir()
                    }
                }) { Text("Cerrar sesión") }
            },
            dismissButton = { TextButton(onClick = { confirmar = false }) { Text("Cancelar") } },
        )
    }
}

@Composable
private fun Interruptor(icono: ImageVector, titulo: String, detalle: String, activo: Boolean, onCambiar: (Boolean) -> Unit) {
    ListItem(
        leadingContent = { Icon(icono, contentDescription = null) },
        headlineContent = { Text(titulo) },
        supportingContent = { Text(detalle) },
        trailingContent = { Switch(checked = activo, onCheckedChange = onCambiar) },
        colors = ListItemDefaults.colors(containerColor = Color.Transparent),
        modifier = Modifier.clickable { onCambiar(!activo) },
    )
}
