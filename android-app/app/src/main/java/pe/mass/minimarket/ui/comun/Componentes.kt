package pe.mass.minimarket.ui.comun

import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.tween
import androidx.compose.animation.expandVertically
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.shrinkVertically
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.RowScope
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.filled.CloudOff
import androidx.compose.material.icons.filled.ErrorOutline
import androidx.compose.material.icons.filled.History
import androidx.compose.material.icons.filled.Info
import androidx.compose.material.icons.filled.Refresh
import androidx.compose.material.icons.filled.Search
import androidx.compose.material.icons.filled.SearchOff
import androidx.compose.material.icons.filled.WarningAmber
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.FilledTonalButton
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.TopAppBar
import androidx.compose.material3.TopAppBarDefaults
import androidx.compose.material3.rememberModalBottomSheetState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.alpha
import androidx.compose.ui.draw.clip
import androidx.compose.ui.focus.FocusRequester
import androidx.compose.ui.focus.focusRequester
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewmodel.compose.viewModel
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import pe.mass.minimarket.MassApp
import pe.mass.minimarket.data.Preferencias
import pe.mass.minimarket.data.ProductoReciente
import pe.mass.minimarket.data.Repositorio
import pe.mass.minimarket.data.Resultado
import pe.mass.minimarket.data.api.Producto
import pe.mass.minimarket.dominio.soles
import pe.mass.minimarket.dominio.textoCantidad
import pe.mass.minimarket.ui.tema.Mass

/** El repositorio unico de la app (MassApp). */
@Composable
fun repositorio(): Repositorio = (LocalContext.current.applicationContext as MassApp).repositorio

/** Tema, sonido, vibracion y productos recientes del celular. */
@Composable
fun preferencias(): Preferencias = (LocalContext.current.applicationContext as MassApp).preferencias

/** Un ViewModel que recibe el repositorio. */
@Composable
inline fun <reified VM : ViewModel> vistaModelo(key: String? = null, crossinline crear: (Repositorio) -> VM): VM {
    val repo = repositorio()
    return viewModel(key = key) { crear(repo) }
}

enum class TipoAviso { OK, ERROR, ALERTA, INFO }

data class Aviso(val texto: String, val tipo: TipoAviso = TipoAviso.INFO)

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun BarraSuperior(
    titulo: String,
    onAtras: (() -> Unit)? = null,
    subtitulo: String? = null,
    acciones: @Composable RowScope.() -> Unit = {},
) {
    TopAppBar(
        title = {
            Column {
                Text(titulo, maxLines = 1, overflow = TextOverflow.Ellipsis, style = MaterialTheme.typography.titleLarge)
                if (subtitulo != null) {
                    Text(
                        subtitulo,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis,
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
            }
        },
        navigationIcon = {
            if (onAtras != null) {
                IconButton(onClick = onAtras) { Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Atrás") }
            }
        },
        actions = acciones,
        colors = TopAppBarDefaults.topAppBarColors(
            containerColor = MaterialTheme.colorScheme.surface,
            scrolledContainerColor = MaterialTheme.colorScheme.surfaceContainer,
        ),
    )
}

/** Colores e icono de cada tipo de aviso, en modo claro u oscuro. */
@Composable
fun estiloAviso(tipo: TipoAviso): Triple<Color, Color, ImageVector> {
    val c = Mass.colores
    return when (tipo) {
        TipoAviso.OK -> Triple(c.exitoFondo, c.exito, Icons.Filled.CheckCircle)
        TipoAviso.ERROR -> Triple(c.peligroFondo, c.peligro, Icons.Filled.ErrorOutline)
        TipoAviso.ALERTA -> Triple(c.alertaFondo, c.alerta, Icons.Filled.WarningAmber)
        TipoAviso.INFO -> Triple(c.infoFondo, c.info, Icons.Filled.Info)
    }
}

/** Aviso que aparece y desaparece con una animacion corta (no hace saltar la pantalla de golpe). */
@Composable
fun TarjetaAviso(aviso: Aviso?, modifier: Modifier = Modifier, onCerrar: (() -> Unit)? = null) {
    // Se recuerda el ultimo aviso para que se vea mientras se va (animacion de salida)
    val ultimo = remember { arrayOfNulls<Aviso>(1) }
    if (aviso != null) ultimo[0] = aviso
    AnimatedVisibility(
        visible = aviso != null,
        modifier = modifier,
        enter = fadeIn() + expandVertically(),
        exit = fadeOut() + shrinkVertically(),
    ) {
        val mostrado = aviso ?: ultimo[0] ?: return@AnimatedVisibility
        val (fondo, tinta, icono) = estiloAviso(mostrado.tipo)
        Surface(color = fondo, contentColor = tinta, shape = MaterialTheme.shapes.medium, modifier = Modifier.fillMaxWidth()) {
            Row(Modifier.padding(start = 14.dp, top = 12.dp, bottom = 12.dp, end = 4.dp), verticalAlignment = Alignment.CenterVertically) {
                Icon(icono, contentDescription = null)
                Spacer(Modifier.width(12.dp))
                Text(mostrado.texto, style = MaterialTheme.typography.bodyMedium, modifier = Modifier.weight(1f).padding(end = 8.dp))
                if (onCerrar != null) {
                    IconButton(onClick = onCerrar) { Icon(Icons.Filled.Close, contentDescription = "Cerrar aviso") }
                }
            }
        }
    }
}

@Composable
fun Cargando(modifier: Modifier = Modifier) {
    Box(modifier.fillMaxSize(), contentAlignment = Alignment.Center) { CircularProgressIndicator() }
}

/** Filas grises que "laten" mientras llega la lista: se nota que algo carga y donde va a aparecer. */
@Composable
fun EsqueletoLista(modifier: Modifier = Modifier, filas: Int = 6) {
    val transicion = rememberInfiniteTransition(label = "esqueleto")
    val opacidad by transicion.animateFloat(
        initialValue = 0.35f,
        targetValue = 0.85f,
        animationSpec = infiniteRepeatable(tween(800), RepeatMode.Reverse),
        label = "opacidad",
    )
    val gris = MaterialTheme.colorScheme.surfaceContainerHighest
    Column(modifier.fillMaxWidth().padding(16.dp).alpha(opacidad), verticalArrangement = Arrangement.spacedBy(14.dp)) {
        repeat(filas) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Box(Modifier.size(44.dp).clip(CircleShape).background(gris))
                Spacer(Modifier.width(12.dp))
                Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                    Box(Modifier.fillMaxWidth(0.7f).height(14.dp).clip(RoundedCornerShape(6.dp)).background(gris))
                    Box(Modifier.fillMaxWidth(0.4f).height(10.dp).clip(RoundedCornerShape(6.dp)).background(gris))
                }
                Spacer(Modifier.width(12.dp))
                Box(Modifier.width(56.dp).height(18.dp).clip(RoundedCornerShape(6.dp)).background(gris))
            }
        }
    }
}

/** Pantalla o seccion sin datos: un icono grande, que pasa y que se puede hacer. */
@Composable
fun EstadoVacio(
    icono: ImageVector,
    titulo: String,
    detalle: String? = null,
    modifier: Modifier = Modifier,
    accion: String? = null,
    onAccion: (() -> Unit)? = null,
) {
    Column(
        modifier.fillMaxWidth().padding(horizontal = 24.dp, vertical = 32.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(8.dp),
    ) {
        Surface(shape = CircleShape, color = MaterialTheme.colorScheme.surfaceContainerHigh, modifier = Modifier.size(80.dp)) {
            Box(contentAlignment = Alignment.Center) {
                Icon(icono, contentDescription = null, modifier = Modifier.size(40.dp), tint = MaterialTheme.colorScheme.onSurfaceVariant)
            }
        }
        Spacer(Modifier.height(4.dp))
        Text(titulo, style = MaterialTheme.typography.titleMedium, textAlign = TextAlign.Center)
        if (detalle != null) {
            Text(detalle, style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.onSurfaceVariant, textAlign = TextAlign.Center)
        }
        if (accion != null && onAccion != null) {
            Spacer(Modifier.height(4.dp))
            FilledTonalButton(onClick = onAccion) { Text(accion) }
        }
    }
}

/** Error al cargar (sin internet, servidor caido…) con el boton para volver a intentar. */
@Composable
fun EstadoError(mensaje: String, onReintentar: () -> Unit, modifier: Modifier = Modifier) {
    EstadoVacio(
        icono = Icons.Filled.CloudOff,
        titulo = "No se pudo cargar",
        detalle = mensaje,
        modifier = modifier,
        accion = "Reintentar",
        onAccion = onReintentar,
    )
}

/** Boton grande y alto, facil de tocar con el celular en una mano. */
@Composable
fun BotonGrande(
    texto: String,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
    icono: ImageVector? = null,
    habilitado: Boolean = true,
    ocupado: Boolean = false,
    resaltado: Boolean = true,
) {
    val colores = if (resaltado) {
        ButtonDefaults.buttonColors(
            containerColor = MaterialTheme.colorScheme.primaryContainer,
            contentColor = MaterialTheme.colorScheme.onPrimaryContainer,
        )
    } else {
        ButtonDefaults.buttonColors(
            containerColor = MaterialTheme.colorScheme.surfaceContainerHighest,
            contentColor = MaterialTheme.colorScheme.onSurface,
        )
    }
    Button(
        onClick = onClick,
        enabled = habilitado && !ocupado,
        modifier = modifier.fillMaxWidth().heightIn(min = 56.dp),
        shape = MaterialTheme.shapes.medium,
        colors = colores,
        elevation = ButtonDefaults.buttonElevation(defaultElevation = if (resaltado) 2.dp else 0.dp),
    ) {
        if (ocupado) {
            CircularProgressIndicator(Modifier.size(22.dp), strokeWidth = 2.dp, color = colores.contentColor)
            Spacer(Modifier.width(10.dp))
        } else if (icono != null) {
            Icon(icono, contentDescription = null)
            Spacer(Modifier.width(10.dp))
        }
        Text(texto, style = MaterialTheme.typography.labelLarge)
    }
}

/** Tarjeta base de la app: fondo de superficie, sin sombra fuerte, esquinas redondeadas. */
@Composable
fun Tarjeta(
    modifier: Modifier = Modifier,
    onClick: (() -> Unit)? = null,
    contenido: @Composable ColumnScope.() -> Unit,
) {
    val colores = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceContainerLow)
    // En modo claro un borde fino separa la tarjeta del fondo; en oscuro basta el cambio de tono
    val borde = if (Mass.colores.oscuro) null else BorderStroke(1.dp, MaterialTheme.colorScheme.outlineVariant)
    if (onClick != null) {
        Card(onClick = onClick, modifier = modifier, shape = MaterialTheme.shapes.medium, colors = colores, border = borde, content = contenido)
    } else {
        Card(modifier = modifier, shape = MaterialTheme.shapes.medium, colors = colores, border = borde, content = contenido)
    }
}

/** Etiqueta redondeada de color (estado, stock, rol…). */
@Composable
fun Pastilla(texto: String, fondo: Color, tinta: Color, modifier: Modifier = Modifier) {
    Text(
        texto,
        color = tinta,
        style = MaterialTheme.typography.labelMedium,
        fontWeight = FontWeight.SemiBold,
        maxLines = 1,
        modifier = modifier.background(fondo, RoundedCornerShape(50)).padding(horizontal = 10.dp, vertical = 4.dp),
    )
}

/** Circulo con la inicial del producto o de la persona: ayuda a reconocer las filas de un vistazo. */
@Composable
fun Inicial(texto: String, modifier: Modifier = Modifier, tamano: Int = 44) {
    val letra = texto.trim().firstOrNull { it.isLetterOrDigit() }?.uppercaseChar()?.toString() ?: "?"
    // El mismo nombre siempre da el mismo color
    val paleta = listOf(
        MaterialTheme.colorScheme.primaryContainer to MaterialTheme.colorScheme.onPrimaryContainer,
        MaterialTheme.colorScheme.tertiaryContainer to MaterialTheme.colorScheme.onTertiaryContainer,
        Mass.colores.exitoFondo to Mass.colores.exito,
        Mass.colores.alertaFondo to Mass.colores.alerta,
        MaterialTheme.colorScheme.secondaryContainer to MaterialTheme.colorScheme.onSecondaryContainer,
    )
    val (fondo, tinta) = paleta[Math.floorMod(texto.hashCode(), paleta.size)]
    Surface(shape = CircleShape, color = fondo, contentColor = tinta, modifier = modifier.size(tamano.dp)) {
        Box(contentAlignment = Alignment.Center) {
            Text(letra, style = if (tamano >= 44) MaterialTheme.typography.titleMedium else MaterialTheme.typography.labelLarge)
        }
    }
}

/** Titulo de una seccion, con una accion opcional a la derecha ("Ver todo"). */
@Composable
fun TituloSeccion(texto: String, modifier: Modifier = Modifier, accion: String? = null, onAccion: (() -> Unit)? = null) {
    Row(modifier.fillMaxWidth().heightIn(min = 40.dp), verticalAlignment = Alignment.CenterVertically) {
        Text(texto, style = MaterialTheme.typography.titleMedium, modifier = Modifier.weight(1f))
        if (accion != null && onAccion != null) {
            TextButton(onClick = onAccion) { Text(accion) }
        }
    }
}

/** Agotado (rojo), stock bajo (ambar) o la cantidad (verde). */
@Composable
fun EtiquetaStock(producto: Producto, modifier: Modifier = Modifier) {
    val c = Mass.colores
    val cantidad = producto.stock.textoCantidad(producto.alPeso) + " " + producto.unidad
    val (texto, fondo, tinta) = when {
        !producto.activo -> Triple("Descontinuado", MaterialTheme.colorScheme.surfaceVariant, MaterialTheme.colorScheme.onSurfaceVariant)
        producto.agotado -> Triple("Agotado", c.peligroFondo, c.peligro)
        producto.stockBajo -> Triple("Bajo: $cantidad", c.alertaFondo, c.alerta)
        else -> Triple(cantidad.trim(), c.exitoFondo, c.exito)
    }
    Pastilla(texto, fondo, tinta, modifier)
}

@Composable
fun FilaProducto(producto: Producto, onClick: () -> Unit, modifier: Modifier = Modifier) {
    Row(
        modifier.fillMaxWidth().clickable(onClick = onClick).padding(horizontal = 16.dp, vertical = 12.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Inicial(producto.nombre)
        Spacer(Modifier.width(12.dp))
        Column(Modifier.weight(1f)) {
            Text(producto.nombre, style = MaterialTheme.typography.titleMedium, maxLines = 2, overflow = TextOverflow.Ellipsis)
            Text(
                listOfNotNull(producto.codigoBarras ?: "Sin código (al peso)", producto.categoria.ifBlank { null }).joinToString(" · "),
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis,
            )
        }
        Spacer(Modifier.width(8.dp))
        Column(horizontalAlignment = Alignment.End) {
            Text(producto.precioVenta.soles(), style = MaterialTheme.typography.titleMedium)
            Spacer(Modifier.size(4.dp))
            EtiquetaStock(producto)
        }
    }
}

/** Fila de un producto visto hace poco (sin stock: se vuelve a pedir al abrirlo). */
@Composable
fun FilaReciente(reciente: ProductoReciente, onClick: () -> Unit, modifier: Modifier = Modifier) {
    Row(
        modifier.fillMaxWidth().clickable(onClick = onClick).padding(horizontal = 16.dp, vertical = 10.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Icon(Icons.Filled.History, contentDescription = null, tint = MaterialTheme.colorScheme.onSurfaceVariant)
        Spacer(Modifier.width(16.dp))
        Column(Modifier.weight(1f)) {
            Text(reciente.nombre, style = MaterialTheme.typography.bodyLarge, maxLines = 1, overflow = TextOverflow.Ellipsis)
            reciente.codigo?.let {
                Text(it, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
            }
        }
        Text(reciente.precio.soles() + if (reciente.alPeso) " /kg" else "", style = MaterialTheme.typography.titleSmall)
    }
}

/**
 * Buscar un producto por nombre o codigo cuando no se puede escanear (producto al peso, etiqueta rota, sin camara).
 * Hoja inferior con el teclado abierto; consulta al servidor mientras se escribe (espera 350 ms entre teclas).
 * Sin texto muestra los productos vistos hace poco, para elegirlos con un toque.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun DialogoBuscarProducto(onElegir: (Producto) -> Unit, onCerrar: () -> Unit, soloActivos: Boolean = true) {
    val repo = repositorio()
    val ajustes by preferencias().ajustes.collectAsState()
    val alcance = rememberCoroutineScope()
    val hoja = rememberModalBottomSheetState(skipPartiallyExpanded = true)
    val foco = remember { FocusRequester() }
    var texto by remember { mutableStateOf("") }
    var resultados by remember { mutableStateOf<List<Producto>>(emptyList()) }
    var cargando by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }
    var intento by remember { mutableStateOf(0) }

    LaunchedEffect(texto, intento) {
        error = null
        if (texto.trim().length < 2) {
            resultados = emptyList()
            cargando = false
            return@LaunchedEffect
        }
        delay(350)
        cargando = true
        when (val r = repo.productos(texto.trim(), null, 1)) {
            is Resultado.Ok -> resultados = r.datos.productos.filter { !soloActivos || it.activo }
            is Resultado.Error -> error = r.mensaje
        }
        cargando = false
    }
    LaunchedEffect(Unit) { runCatching { foco.requestFocus() } }

    fun elegirReciente(reciente: ProductoReciente) {
        cargando = true
        alcance.launch {
            // Precio y stock de ahora, no los de cuando se vio
            when (val r = repo.producto(reciente.id)) {
                is Resultado.Ok -> onElegir(r.datos.copy(movimientos = emptyList()))
                is Resultado.Error -> error = r.mensaje
            }
            cargando = false
        }
    }

    ModalBottomSheet(onDismissRequest = onCerrar, sheetState = hoja) {
        Column(Modifier.fillMaxWidth().fillMaxHeight(0.92f).imePadding()) {
            Text("Buscar producto", style = MaterialTheme.typography.titleLarge, modifier = Modifier.padding(horizontal = 16.dp))
            Spacer(Modifier.height(12.dp))
            OutlinedTextField(
                value = texto,
                onValueChange = { texto = it.take(60) },
                placeholder = { Text("Nombre o código") },
                leadingIcon = { Icon(Icons.Filled.Search, contentDescription = null) },
                trailingIcon = {
                    if (texto.isNotEmpty()) {
                        IconButton(onClick = { texto = "" }) { Icon(Icons.Filled.Close, contentDescription = "Borrar") }
                    }
                },
                singleLine = true,
                shape = RoundedCornerShape(50),
                keyboardOptions = KeyboardOptions(imeAction = ImeAction.Search),
                keyboardActions = KeyboardActions(onSearch = {}),
                modifier = Modifier.fillMaxWidth().padding(horizontal = 16.dp).focusRequester(foco),
            )
            Spacer(Modifier.height(8.dp))
            val recientes = ajustes.recientes
            when {
                cargando -> EsqueletoLista(filas = 4)
                error != null -> EstadoError(error!!, onReintentar = { intento++ })
                texto.trim().length >= 2 && resultados.isEmpty() ->
                    EstadoVacio(Icons.Filled.SearchOff, "Sin resultados", "No hay productos con «${texto.trim()}».")
                texto.trim().length < 2 && recientes.isNotEmpty() -> LazyColumn(contentPadding = PaddingValues(bottom = 24.dp)) {
                    item { TituloSeccion("Vistos hace poco", Modifier.padding(horizontal = 16.dp)) }
                    items(recientes, key = { "r" + it.id }) { r -> FilaReciente(r, onClick = { elegirReciente(r) }) }
                }
                texto.trim().length < 2 ->
                    EstadoVacio(Icons.Filled.Search, "Escribe al menos 2 letras", "Por ejemplo «leche» o parte del código de barras.")
                else -> LazyColumn(contentPadding = PaddingValues(bottom = 24.dp)) {
                    items(resultados, key = { it.idProducto }) { p ->
                        FilaProducto(p, onClick = { onElegir(p) })
                        HorizontalDivider(Modifier.padding(start = 72.dp), color = MaterialTheme.colorScheme.outlineVariant)
                    }
                }
            }
        }
    }
}

/** Boton de contorno con icono, del ancho completo. */
@Composable
fun BotonContorno(texto: String, icono: ImageVector, onClick: () -> Unit, modifier: Modifier = Modifier, habilitado: Boolean = true) {
    OutlinedButton(onClick = onClick, enabled = habilitado, modifier = modifier.fillMaxWidth().heightIn(min = 48.dp), shape = MaterialTheme.shapes.medium) {
        Icon(icono, contentDescription = null)
        Spacer(Modifier.width(8.dp))
        Text(texto)
    }
}

/** Icono de actualizar para la barra superior. */
@Composable
fun AccionActualizar(onClick: () -> Unit) {
    IconButton(onClick = onClick) { Icon(Icons.Filled.Refresh, contentDescription = "Actualizar") }
}

/** Fecha y hora del servidor "2026-10-30 08:49:09" -> "30/10/2026 08:49" */
fun fechaCorta(fechaHora: String): String {
    val partes = fechaHora.split(" ")
    val fecha = partes.getOrNull(0)?.split("-") ?: return fechaHora
    if (fecha.size != 3) return fechaHora
    val hora = partes.getOrNull(1)?.take(5).orEmpty()
    return "${fecha[2]}/${fecha[1]}/${fecha[0]} $hora".trim()
}

/** "2026-10-30 08:49:09" -> "08:49" */
fun soloHora(fechaHora: String): String = fechaHora.split(" ").getOrNull(1)?.take(5) ?: fechaHora
