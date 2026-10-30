package pe.mass.minimarket.ui.comun

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.RowScope
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.filled.ErrorOutline
import androidx.compose.material.icons.filled.Info
import androidx.compose.material.icons.filled.Search
import androidx.compose.material.icons.filled.WarningAmber
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.TopAppBar
import androidx.compose.material3.TopAppBarDefaults
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewmodel.compose.viewModel
import kotlinx.coroutines.delay
import pe.mass.minimarket.MassApp
import pe.mass.minimarket.data.Repositorio
import pe.mass.minimarket.data.Resultado
import pe.mass.minimarket.data.api.Producto
import pe.mass.minimarket.dominio.soles
import pe.mass.minimarket.dominio.textoCantidad
import pe.mass.minimarket.ui.tema.Ambar
import pe.mass.minimarket.ui.tema.AmbarFondo
import pe.mass.minimarket.ui.tema.Azul
import pe.mass.minimarket.ui.tema.AzulFondo
import pe.mass.minimarket.ui.tema.Rojo
import pe.mass.minimarket.ui.tema.RojoFondo
import pe.mass.minimarket.ui.tema.Tinta
import pe.mass.minimarket.ui.tema.Verde
import pe.mass.minimarket.ui.tema.VerdeFondo

/** El repositorio unico de la app (MassApp). */
@Composable
fun repositorio(): Repositorio = (LocalContext.current.applicationContext as MassApp).repositorio

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
fun BarraSuperior(titulo: String, onAtras: (() -> Unit)? = null, acciones: @Composable RowScope.() -> Unit = {}) {
    TopAppBar(
        title = { Text(titulo, maxLines = 1, overflow = TextOverflow.Ellipsis) },
        navigationIcon = {
            if (onAtras != null) {
                IconButton(onClick = onAtras) { Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Atrás") }
            }
        },
        actions = acciones,
        colors = TopAppBarDefaults.topAppBarColors(
            containerColor = Tinta,
            titleContentColor = Color.White,
            navigationIconContentColor = Color.White,
            actionIconContentColor = Color.White,
        ),
    )
}

@Composable
fun TarjetaAviso(aviso: Aviso?, modifier: Modifier = Modifier, onCerrar: (() -> Unit)? = null) {
    if (aviso == null) return
    val (fondo, tinta, icono) = when (aviso.tipo) {
        TipoAviso.OK -> Triple(VerdeFondo, Verde, Icons.Filled.CheckCircle)
        TipoAviso.ERROR -> Triple(RojoFondo, Rojo, Icons.Filled.ErrorOutline)
        TipoAviso.ALERTA -> Triple(AmbarFondo, Ambar, Icons.Filled.WarningAmber)
        TipoAviso.INFO -> Triple(AzulFondo, Azul, Icons.Filled.Info)
    }
    Surface(color = fondo, contentColor = tinta, shape = RoundedCornerShape(12.dp), modifier = modifier.fillMaxWidth()) {
        Row(Modifier.padding(start = 12.dp, top = 10.dp, bottom = 10.dp, end = 4.dp), verticalAlignment = Alignment.CenterVertically) {
            Icon(icono, contentDescription = null)
            Spacer(Modifier.width(10.dp))
            Text(aviso.texto, style = MaterialTheme.typography.bodyMedium, modifier = Modifier.weight(1f))
            if (onCerrar != null) {
                IconButton(onClick = onCerrar) { Icon(Icons.Filled.Close, contentDescription = "Cerrar aviso") }
            }
        }
    }
}

@Composable
fun Cargando(modifier: Modifier = Modifier) {
    Box(modifier.fillMaxSize(), contentAlignment = Alignment.Center) { CircularProgressIndicator() }
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
    Button(
        onClick = onClick,
        enabled = habilitado && !ocupado,
        modifier = modifier.fillMaxWidth().heightIn(min = 56.dp),
        shape = RoundedCornerShape(12.dp),
        colors = if (resaltado) ButtonDefaults.buttonColors(containerColor = MaterialTheme.colorScheme.primaryContainer, contentColor = Tinta)
        else ButtonDefaults.buttonColors(),
    ) {
        if (ocupado) {
            CircularProgressIndicator(Modifier.size(22.dp), strokeWidth = 2.dp, color = Tinta)
            Spacer(Modifier.width(10.dp))
        } else if (icono != null) {
            Icon(icono, contentDescription = null)
            Spacer(Modifier.width(10.dp))
        }
        Text(texto, style = MaterialTheme.typography.labelLarge)
    }
}

/** Agotado (rojo), stock bajo (ambar) o la cantidad (verde). */
@Composable
fun EtiquetaStock(producto: Producto, modifier: Modifier = Modifier) {
    val cantidad = producto.stock.textoCantidad(producto.alPeso) + " " + producto.unidad
    val (texto, fondo, tinta) = when {
        !producto.activo -> Triple("Descontinuado", MaterialTheme.colorScheme.surfaceVariant, MaterialTheme.colorScheme.onSurfaceVariant)
        producto.agotado -> Triple("Agotado", RojoFondo, Rojo)
        producto.stockBajo -> Triple("Bajo: $cantidad", AmbarFondo, Ambar)
        else -> Triple(cantidad, VerdeFondo, Verde)
    }
    Text(
        texto,
        color = tinta,
        style = MaterialTheme.typography.labelMedium,
        fontWeight = FontWeight.SemiBold,
        modifier = modifier.background(fondo, RoundedCornerShape(50)).padding(horizontal = 10.dp, vertical = 4.dp),
    )
}

@Composable
fun FilaProducto(producto: Producto, onClick: () -> Unit, modifier: Modifier = Modifier) {
    Row(
        modifier.fillMaxWidth().clickable(onClick = onClick).padding(horizontal = 16.dp, vertical = 12.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Column(Modifier.weight(1f)) {
            Text(producto.nombre, style = MaterialTheme.typography.titleMedium, maxLines = 2, overflow = TextOverflow.Ellipsis)
            Text(
                listOfNotNull(producto.codigoBarras ?: "Sin código (al peso)", producto.categoria.ifBlank { null }).joinToString(" · "),
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
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

/**
 * Buscar un producto por nombre o codigo cuando no se puede escanear (producto al peso, etiqueta rota, sin camara).
 * Consulta al servidor mientras se escribe (espera 350 ms entre teclas).
 */
@Composable
fun DialogoBuscarProducto(onElegir: (Producto) -> Unit, onCerrar: () -> Unit, soloActivos: Boolean = true) {
    val repo = repositorio()
    var texto by remember { mutableStateOf("") }
    var resultados by remember { mutableStateOf<List<Producto>>(emptyList()) }
    var cargando by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }

    LaunchedEffect(texto) {
        error = null
        if (texto.trim().length < 2) {
            resultados = emptyList()
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

    AlertDialog(
        onDismissRequest = onCerrar,
        confirmButton = {},
        dismissButton = { TextButton(onClick = onCerrar) { Text("Cerrar") } },
        title = { Text("Buscar producto") },
        text = {
            Column {
                OutlinedTextField(
                    value = texto,
                    onValueChange = { texto = it },
                    label = { Text("Nombre o código") },
                    leadingIcon = { Icon(Icons.Filled.Search, contentDescription = null) },
                    singleLine = true,
                    keyboardOptions = KeyboardOptions(imeAction = ImeAction.Search),
                    keyboardActions = KeyboardActions(onSearch = {}),
                    modifier = Modifier.fillMaxWidth(),
                )
                Spacer(Modifier.size(8.dp))
                when {
                    cargando -> Box(Modifier.fillMaxWidth().padding(16.dp), contentAlignment = Alignment.Center) { CircularProgressIndicator() }
                    error != null -> TarjetaAviso(Aviso(error!!, TipoAviso.ERROR))
                    texto.trim().length >= 2 && resultados.isEmpty() -> Text("No hay productos con «${texto.trim()}».")
                    else -> LazyColumn(Modifier.heightIn(max = 360.dp), verticalArrangement = Arrangement.spacedBy(0.dp)) {
                        items(resultados, key = { it.idProducto }) { p ->
                            FilaProducto(p, onClick = { onElegir(p) })
                            HorizontalDivider()
                        }
                    }
                }
            }
        },
    )
}

/** Fecha y hora del servidor "2026-10-30 08:49:09" -> "30/10/2026 08:49" */
fun fechaCorta(fechaHora: String): String {
    val partes = fechaHora.split(" ")
    val fecha = partes.getOrNull(0)?.split("-") ?: return fechaHora
    if (fecha.size != 3) return fechaHora
    val hora = partes.getOrNull(1)?.take(5).orEmpty()
    return "${fecha[2]}/${fecha[1]}/${fecha[0]} $hora".trim()
}
