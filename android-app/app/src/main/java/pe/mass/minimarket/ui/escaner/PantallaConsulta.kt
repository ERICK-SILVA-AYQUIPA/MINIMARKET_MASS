package pe.mass.minimarket.ui.escaner

import androidx.compose.animation.AnimatedContent
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.scaleIn
import androidx.compose.animation.togetherWith
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.AddBox
import androidx.compose.material.icons.filled.QrCodeScanner
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import pe.mass.minimarket.data.Repositorio
import pe.mass.minimarket.data.Resultado
import pe.mass.minimarket.data.api.Producto
import pe.mass.minimarket.dominio.soles
import pe.mass.minimarket.dominio.textoCantidad
import pe.mass.minimarket.ui.comun.Aviso
import pe.mass.minimarket.ui.comun.BarraSuperior
import pe.mass.minimarket.ui.comun.BotonGrande
import pe.mass.minimarket.ui.comun.DialogoBuscarProducto
import pe.mass.minimarket.ui.comun.DialogoProductoNuevo
import pe.mass.minimarket.ui.comun.EstadoVacio
import pe.mass.minimarket.ui.comun.EtiquetaStock
import pe.mass.minimarket.ui.comun.Inicial
import pe.mass.minimarket.ui.comun.Tarjeta
import pe.mass.minimarket.ui.comun.TarjetaAviso
import pe.mass.minimarket.ui.comun.TipoAviso
import pe.mass.minimarket.ui.comun.preferencias
import pe.mass.minimarket.ui.comun.vistaModelo

/** Para que se abre el escaner de consulta. */
enum class ModoConsulta(val titulo: String, val ayuda: String) {
    /** Ver precio y stock sin cambiar nada (cualquier rol). */
    PRECIO("Consultar precio", "Escanea un producto para ver su precio y stock."),
    /** Desde Inventario: abre la ficha del producto encontrado. */
    INVENTARIO("Escanear para inventario", "Escanea un producto para abrir su ficha y su kardex."),
    /** Acceso directo del inicio: escanear un codigo nuevo y registrarlo de una vez. */
    REGISTRAR("Agregar producto", "Escanea el código del producto nuevo. Si ya existe, verás su precio y stock."),
}

data class EstadoConsulta(
    val consultando: Boolean = false,
    val producto: Producto? = null,
    val aviso: Aviso? = null,
    // Ultimo codigo que no esta en el catalogo: el almacenero o el administrador pueden registrarlo
    val codigoDesconocido: String? = null,
)

/** Consulta de producto: lee el codigo, pregunta al servidor y muestra precio y stock. No cambia nada. */
class ConsultaViewModel(private val repo: Repositorio) : ViewModel() {
    private val _estado = MutableStateFlow(EstadoConsulta())
    val estado: StateFlow<EstadoConsulta> = _estado

    val puedeCrear: Boolean get() = repo.perfil?.permisos?.crearProducto == true

    fun consultar(codigo: String, alEncontrar: (Producto) -> Unit = {}, alNoEstar: (String) -> Unit = {}) {
        if (_estado.value.consultando) return
        _estado.update { it.copy(consultando = true, aviso = null, codigoDesconocido = null) }
        viewModelScope.launch {
            when (val r = repo.productoPorCodigo(codigo)) {
                is Resultado.Ok -> {
                    _estado.update { it.copy(consultando = false, producto = r.datos) }
                    alEncontrar(r.datos)
                }
                is Resultado.Error -> {
                    val noEsta = r.http == 404
                    _estado.update {
                        // 404: el codigo no esta en el catalogo. No se inventa un producto.
                        it.copy(
                            consultando = false,
                            producto = null,
                            aviso = Aviso(r.mensaje, if (noEsta) TipoAviso.ALERTA else TipoAviso.ERROR),
                            codigoDesconocido = codigo.takeIf { noEsta && puedeCrear },
                        )
                    }
                    if (noEsta && puedeCrear) alNoEstar(codigo)
                }
            }
        }
    }

    /** Abre un producto ya conocido (desde "Vistos hace poco"), con precio y stock de ahora. */
    fun abrir(idProducto: Int, alEncontrar: (Producto) -> Unit = {}) {
        _estado.update { it.copy(consultando = true, aviso = null) }
        viewModelScope.launch {
            when (val r = repo.producto(idProducto)) {
                is Resultado.Ok -> {
                    _estado.update { it.copy(consultando = false, producto = r.datos) }
                    alEncontrar(r.datos)
                }
                is Resultado.Error -> _estado.update { it.copy(consultando = false, aviso = Aviso(r.mensaje, TipoAviso.ERROR)) }
            }
        }
    }

    fun mostrar(producto: Producto, aviso: Aviso? = null) =
        _estado.update { it.copy(producto = producto, aviso = aviso, codigoDesconocido = null) }
}

/**
 * @param onAbrirFicha solo en modo [ModoConsulta.INVENTARIO]: abre la ficha del producto encontrado.
 * @param idInicial producto a mostrar al entrar (desde "Vistos hace poco" del inicio).
 */
@Composable
fun PantallaConsulta(
    onAtras: () -> Unit,
    onAbrirFicha: ((Int) -> Unit)?,
    modo: ModoConsulta = if (onAbrirFicha == null) ModoConsulta.PRECIO else ModoConsulta.INVENTARIO,
    idInicial: Int? = null,
) {
    val vm = vistaModelo { ConsultaViewModel(it) }
    val estado by vm.estado.collectAsState()
    val prefs = preferencias()
    val confirmacion = rememberConfirmacion()
    var buscar by remember { mutableStateOf(false) }
    var registrar by remember { mutableStateOf(false) }

    // Codigo no registrado o error: pitido distinto al de exito
    LaunchedEffect(estado.aviso) { if (estado.aviso?.tipo == TipoAviso.ERROR || estado.aviso?.tipo == TipoAviso.ALERTA) confirmacion.error() }
    LaunchedEffect(idInicial) { if (idInicial != null) vm.abrir(idInicial) { prefs.recordar(it) } }

    val alEncontrar: (Producto) -> Unit = { producto ->
        confirmacion.exito()
        prefs.recordar(producto)
        if (modo == ModoConsulta.REGISTRAR) {
            vm.mostrar(producto, Aviso("Este código ya está registrado: ${producto.nombre}.", TipoAviso.INFO))
        }
        if (modo == ModoConsulta.INVENTARIO) onAbrirFicha?.invoke(producto.idProducto)
    }
    val alLeer: (String) -> Unit = { codigo ->
        vm.consultar(
            codigo,
            alEncontrar = alEncontrar,
            // En "Agregar producto" el formulario se abre solo: un toque menos
            alNoEstar = { if (modo == ModoConsulta.REGISTRAR) registrar = true },
        )
    }

    Scaffold(topBar = { BarraSuperior(modo.titulo, onAtras = onAtras) }) { relleno ->
        Column(
            Modifier.fillMaxSize().padding(relleno).verticalScroll(rememberScrollState()).padding(horizontal = 16.dp, vertical = 8.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            PanelEscaner(
                onCodigo = alLeer,
                onBuscarPorNombre = { buscar = true },
                activo = !estado.consultando && !registrar,
                altoCamara = 280,
            )
            TarjetaAviso(estado.aviso)
            if (estado.codigoDesconocido != null) {
                BotonGrande("Registrar este producto", onClick = { registrar = true }, icono = Icons.Filled.AddBox)
            }
            AnimatedContent(
                targetState = estado.producto,
                transitionSpec = { (fadeIn() + scaleIn(initialScale = 0.96f)) togetherWith fadeOut() },
                contentKey = { it?.idProducto },
                label = "producto",
            ) { producto ->
                when {
                    producto != null -> TarjetaProducto(producto)
                    estado.aviso == null && !estado.consultando ->
                        EstadoVacio(
                            if (modo == ModoConsulta.REGISTRAR) Icons.Filled.AddBox else Icons.Filled.QrCodeScanner,
                            "Listo para escanear",
                            modo.ayuda,
                        )
                    else -> Spacer(Modifier.fillMaxWidth())
                }
            }
        }
    }

    val codigoNuevo = estado.codigoDesconocido
    if (registrar && codigoNuevo != null) {
        DialogoProductoNuevo(
            codigoNuevo,
            onCreado = { p ->
                registrar = false
                confirmacion.exito()
                prefs.recordar(p)
                if (modo == ModoConsulta.INVENTARIO && onAbrirFicha != null) {
                    onAbrirFicha(p.idProducto)
                } else {
                    vm.mostrar(p, Aviso("Producto registrado. Ya se puede vender y aparece en la web.", TipoAviso.OK))
                }
            },
            onVinculado = { p ->
                registrar = false
                confirmacion.exito()
                prefs.recordar(p)
                if (modo == ModoConsulta.INVENTARIO && onAbrirFicha != null) {
                    onAbrirFicha(p.idProducto)
                } else {
                    vm.mostrar(p, Aviso("Código $codigoNuevo vinculado: el escáner ya lo reconoce como este producto.", TipoAviso.OK))
                }
            },
            onCerrar = { registrar = false },
        )
    }
    if (buscar) {
        DialogoBuscarProducto(
            soloActivos = false,
            onElegir = { p ->
                buscar = false
                prefs.recordar(p)
                if (modo == ModoConsulta.INVENTARIO && onAbrirFicha != null) onAbrirFicha(p.idProducto) else vm.mostrar(p)
            },
            onCerrar = { buscar = false },
        )
    }
}

/** Ficha corta del producto: precio grande, stock con color y los datos del catalogo. */
@Composable
fun TarjetaProducto(producto: Producto, modifier: Modifier = Modifier) {
    Tarjeta(modifier.fillMaxWidth()) {
        Column(Modifier.padding(20.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Inicial(producto.nombre, tamano = 52)
                Spacer(Modifier.width(14.dp))
                Column(Modifier.weight(1f)) {
                    Text(producto.nombre, style = MaterialTheme.typography.titleLarge, maxLines = 3, overflow = TextOverflow.Ellipsis)
                    Text(
                        producto.codigoBarras ?: "Sin código (al peso)",
                        style = MaterialTheme.typography.bodyMedium,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
            }
            Row(verticalAlignment = Alignment.Bottom) {
                Text(producto.precioVenta.soles(), style = MaterialTheme.typography.displaySmall)
                if (producto.alPeso) {
                    Text(" / kg", style = MaterialTheme.typography.titleMedium, color = MaterialTheme.colorScheme.onSurfaceVariant,
                        modifier = Modifier.padding(bottom = 6.dp))
                }
                Spacer(Modifier.weight(1f))
                EtiquetaStock(producto, Modifier.padding(bottom = 6.dp))
            }
            HorizontalDivider(color = MaterialTheme.colorScheme.outlineVariant)
            Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                Dato("Mínimo", "${producto.stockMinimo.textoCantidad(producto.alPeso)} ${producto.unidad}".trim(), Modifier.weight(1f))
                Dato("Categoría", producto.categoria.ifBlank { "—" }, Modifier.weight(1f))
                Dato("Marca", producto.marca.ifBlank { "—" }, Modifier.weight(1f))
            }
            if (!producto.stockRegistrado) {
                Text(
                    "Este producto todavía no llegó a tu tienda.",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
        }
    }
}

@Composable
private fun Dato(etiqueta: String, valor: String, modifier: Modifier = Modifier) {
    Column(modifier) {
        Text(etiqueta, style = MaterialTheme.typography.labelMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
        Text(valor, style = MaterialTheme.typography.bodyMedium, maxLines = 2, overflow = TextOverflow.Ellipsis)
    }
}
