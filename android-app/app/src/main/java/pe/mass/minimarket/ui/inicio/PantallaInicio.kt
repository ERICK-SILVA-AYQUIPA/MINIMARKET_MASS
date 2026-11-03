package pe.mass.minimarket.ui.inicio

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
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
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
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
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
import pe.mass.minimarket.data.api.Perfil
import pe.mass.minimarket.dominio.saludo
import pe.mass.minimarket.ui.comun.BarraSuperior
import pe.mass.minimarket.ui.comun.BotonGrande
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
import java.time.LocalDate
import java.time.LocalTime

private data class Opcion(val titulo: String, val detalle: String, val icono: ImageVector, val ruta: String)

/** La accion principal del rol: lo que mas hace en el dia, en grande y arriba. */
private fun principal(perfil: Perfil): Opcion? {
    val p = perfil.permisos
    return when {
        p.vender -> Opcion("Nueva venta", "Escanea y cobra", Icons.Filled.PointOfSale, Rutas.VENTA)
        p.registrarIngreso -> Opcion(
            "Entrada de stock",
            if (p.crearProducto) "Escanea lo que llega; si es nuevo, regístralo ahí mismo" else "Recibe mercadería con su guía o factura",
            Icons.Filled.LocalShipping,
            Rutas.ENTRADA,
        )
        else -> null
    }
}

/** Los accesos rapidos: solo lo que permite el rol (el servidor igual revisa cada permiso). */
private fun accesos(perfil: Perfil): List<Opcion> = buildList {
    val p = perfil.permisos
    add(Opcion("Consultar precio", "Precio y stock al instante", Icons.Filled.QrCodeScanner, Rutas.consulta()))
    if (p.crearProducto) add(Opcion("Agregar producto", "Escanea un código nuevo", Icons.Filled.AddBox, Rutas.REGISTRAR))
    if (p.vender && p.registrarIngreso) add(Opcion("Entrada de stock", "Recibir mercadería", Icons.Filled.LocalShipping, Rutas.ENTRADA))
    if (p.verInventario) add(Opcion("Inventario", "Stock de la tienda", Icons.Filled.Inventory2, Rutas.inventario()))
    if (p.verVentas) add(Opcion("Ventas", "Comprobantes de la tienda", Icons.AutoMirrored.Filled.ReceiptLong, Rutas.HISTORIAL))
}

data class EstadoInicio(
    val perfil: Perfil? = null,
    val caja: EstadoCaja? = null,
    val agotados: Int? = null,
    val bajos: Int? = null,
    val ventasHoy: Int? = null,
    val actualizando: Boolean = false,
    // Solo cuando el usuario desliza hacia abajo se muestra el indicador de "actualizando"
    val refrescando: Boolean = false,
)

/** El resumen del inicio: turno de caja, alertas de stock y ventas del dia. Si algo falla, simplemente no se muestra. */
class InicioViewModel(private val repo: Repositorio) : ViewModel() {
    private val _estado = MutableStateFlow(EstadoInicio(perfil = repo.perfil))
    val estado: StateFlow<EstadoInicio> = _estado

    fun actualizar(desdeGesto: Boolean = false) {
        if (_estado.value.actualizando) return
        _estado.update { it.copy(actualizando = true, refrescando = desdeGesto) }
        viewModelScope.launch {
            // Los permisos pueden haber cambiado en la web (otro rol, otra tienda): se vuelven a pedir
            val perfil = (repo.refrescarPerfil() as? Resultado.Ok)?.datos ?: repo.perfil
            val p = perfil?.permisos
            val hoy = LocalDate.now().toString()
            val caja = async { if (p?.vender == true) (repo.caja() as? Resultado.Ok)?.datos else null }
            val agotados = async { if (p?.verInventario == true) (repo.productos(null, Filtro.AGOTADOS.clave, 1) as? Resultado.Ok)?.datos?.total else null }
            val bajos = async { if (p?.verInventario == true) (repo.productos(null, Filtro.BAJO.clave, 1) as? Resultado.Ok)?.datos?.total else null }
            val ventas = async { if (p?.verVentas == true) (repo.ventas(hoy, hoy, null, null, 1) as? Resultado.Ok)?.datos?.total else null }
            _estado.value = EstadoInicio(
                perfil = perfil,
                caja = caja.await(),
                agotados = agotados.await(),
                bajos = bajos.await(),
                ventasHoy = ventas.await(),
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
    LifecycleEventEffect(Lifecycle.Event.ON_RESUME) { vm.actualizar() }

    Scaffold { relleno ->
        val perfil = estado.perfil ?: return@Scaffold
        PullToRefreshBox(
            isRefreshing = estado.refrescando,
            onRefresh = { vm.actualizar(desdeGesto = true) },
            modifier = Modifier.fillMaxSize().padding(relleno),
        ) {
            LazyColumn(
                Modifier.fillMaxSize(),
                contentPadding = PaddingValues(start = 16.dp, end = 16.dp, top = 8.dp, bottom = 24.dp),
                verticalArrangement = Arrangement.spacedBy(16.dp),
            ) {
                item { Encabezado(perfil, onPerfil = { onIr(Rutas.PERFIL) }) }
                principal(perfil)?.let { op ->
                    item { TarjetaPrincipal(op, detalle = textoCaja(perfil, estado.caja) ?: op.detalle) { onIr(op.ruta) } }
                }
                item { Resumen(estado, onIr) }
                item {
                    Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                        TituloSeccion("Accesos rápidos")
                        accesos(perfil).chunked(2).forEach { fila ->
                            Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                                fila.forEach { op -> TarjetaAcceso(op, Modifier.weight(1f)) { onIr(op.ruta) } }
                                if (fila.size == 1) Spacer(Modifier.weight(1f))
                            }
                        }
                    }
                }
                if (ajustes.recientes.isNotEmpty()) {
                    item {
                        Tarjeta(Modifier.fillMaxWidth()) {
                            TituloSeccion("Vistos hace poco", Modifier.padding(start = 16.dp, end = 4.dp, top = 4.dp))
                            ajustes.recientes.take(5).forEach { r ->
                                FilaReciente(r, onClick = { onIr(Rutas.consulta(r.id)) })
                            }
                            Spacer(Modifier.size(4.dp))
                        }
                    }
                }
            }
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
                .background(Brush.linearGradient(listOf(Marca, Color(0xFFFFC300))))
                .padding(20.dp),
        ) {
            Column(verticalArrangement = Arrangement.spacedBy(14.dp)) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Surface(shape = CircleShape, color = Tinta, contentColor = Marca, modifier = Modifier.size(52.dp)) {
                        Box(contentAlignment = Alignment.Center) { Icon(opcion.icono, contentDescription = null, modifier = Modifier.size(28.dp)) }
                    }
                    Spacer(Modifier.weight(1f))
                    Surface(shape = CircleShape, color = Tinta.copy(alpha = 0.08f), contentColor = Tinta, modifier = Modifier.size(40.dp)) {
                        Box(contentAlignment = Alignment.Center) { Icon(Icons.AutoMirrored.Filled.ArrowForward, contentDescription = null) }
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
    Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
        datos.forEach { (ind, colores, ruta) ->
            Surface(
                onClick = { onIr(ruta) },
                shape = MaterialTheme.shapes.medium,
                color = colores.first,
                contentColor = colores.second,
                modifier = Modifier.weight(1f),
            ) {
                Column(Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(2.dp)) {
                    Icon(ind.icono, contentDescription = null, modifier = Modifier.size(20.dp))
                    Text(ind.valor.toString(), style = MaterialTheme.typography.headlineSmall)
                    Text(ind.titulo, style = MaterialTheme.typography.labelMedium, maxLines = 1, overflow = TextOverflow.Ellipsis)
                }
            }
        }
    }
}

private data class Indicador(val titulo: String, val valor: Int, val icono: ImageVector)

@Composable
private fun TarjetaAcceso(opcion: Opcion, modifier: Modifier = Modifier, onClick: () -> Unit) {
    Tarjeta(modifier.heightIn(min = 120.dp), onClick = onClick) {
        Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
            Surface(
                shape = MaterialTheme.shapes.small,
                color = MaterialTheme.colorScheme.secondaryContainer,
                contentColor = MaterialTheme.colorScheme.onSecondaryContainer,
                modifier = Modifier.size(42.dp),
            ) {
                Box(contentAlignment = Alignment.Center) { Icon(opcion.icono, contentDescription = null) }
            }
            Column {
                Text(opcion.titulo, style = MaterialTheme.typography.titleMedium, maxLines = 1, overflow = TextOverflow.Ellipsis)
                Text(
                    opcion.detalle,
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    maxLines = 2,
                    overflow = TextOverflow.Ellipsis,
                )
            }
        }
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
