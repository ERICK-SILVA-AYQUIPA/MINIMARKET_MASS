package pe.mass.minimarket.ui.escaner

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.AddBox
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
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
import androidx.compose.ui.graphics.Color
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
import pe.mass.minimarket.ui.comun.DialogoProductoNuevo
import pe.mass.minimarket.ui.comun.DialogoBuscarProducto
import pe.mass.minimarket.ui.comun.EtiquetaStock
import pe.mass.minimarket.ui.comun.TarjetaAviso
import pe.mass.minimarket.ui.comun.TipoAviso
import pe.mass.minimarket.ui.comun.vistaModelo

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

    fun consultar(codigo: String, alEncontrar: (Producto) -> Unit = {}) {
        if (_estado.value.consultando) return
        _estado.update { it.copy(consultando = true, aviso = null, codigoDesconocido = null) }
        viewModelScope.launch {
            when (val r = repo.productoPorCodigo(codigo)) {
                is Resultado.Ok -> {
                    _estado.update { it.copy(consultando = false, producto = r.datos) }
                    alEncontrar(r.datos)
                }
                is Resultado.Error -> _estado.update {
                    // 404: el codigo no esta en el catalogo. No se inventa un producto.
                    it.copy(consultando = false, producto = null, aviso = Aviso(r.mensaje, if (r.http == 404) TipoAviso.ALERTA else TipoAviso.ERROR),
                        codigoDesconocido = codigo.takeIf { r.http == 404 && repo.perfil?.permisos?.crearProducto == true })
                }
            }
        }
    }

    fun mostrar(producto: Producto) = _estado.update { it.copy(producto = producto, aviso = null, codigoDesconocido = null) }
}

/**
 * @param onAbrirFicha null: modo "Consulta de producto" (muestra los datos aqui).
 *                     Con valor: modo Inventario (abre la ficha del producto encontrado).
 */
@Composable
fun PantallaConsulta(onAtras: () -> Unit, onAbrirFicha: ((Int) -> Unit)?) {
    val vm = vistaModelo { ConsultaViewModel(it) }
    val estado by vm.estado.collectAsState()
    val confirmacion = rememberConfirmacion()
    var buscar by remember { mutableStateOf(false) }
    var registrar by remember { mutableStateOf(false) }

    // Codigo no registrado o error: pitido distinto al de exito
    LaunchedEffect(estado.aviso) { if (estado.aviso != null) confirmacion.error() }

    val alLeer: (String) -> Unit = { codigo ->
        vm.consultar(codigo) { producto ->
            confirmacion.exito()
            onAbrirFicha?.invoke(producto.idProducto)
        }
    }

    Scaffold(topBar = { BarraSuperior(if (onAbrirFicha == null) "Consultar producto" else "Escanear para inventario", onAtras = onAtras) }) { relleno ->
        Column(
            Modifier.fillMaxSize().padding(relleno).verticalScroll(rememberScrollState()).padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            PanelEscaner(onCodigo = alLeer, onBuscarPorNombre = { buscar = true }, activo = !estado.consultando, altoCamara = 260)
            TarjetaAviso(estado.aviso)
            if (estado.codigoDesconocido != null) {
                BotonGrande("Registrar este producto", onClick = { registrar = true }, icono = Icons.Filled.AddBox)
            }
            estado.producto?.let { TarjetaProducto(it) }
        }
    }

    val codigoNuevo = estado.codigoDesconocido
    if (registrar && codigoNuevo != null) {
        DialogoProductoNuevo(
            codigoNuevo,
            onCreado = { p ->
                registrar = false
                if (onAbrirFicha != null) onAbrirFicha(p.idProducto) else vm.mostrar(p)
            },
            onCerrar = { registrar = false },
        )
    }
    if (buscar) {
        DialogoBuscarProducto(
            soloActivos = false,
            onElegir = { p ->
                buscar = false
                if (onAbrirFicha != null) onAbrirFicha(p.idProducto) else vm.mostrar(p)
            },
            onCerrar = { buscar = false },
        )
    }
}

@Composable
fun TarjetaProducto(producto: Producto, modifier: Modifier = Modifier) {
    Card(modifier.fillMaxWidth(), shape = RoundedCornerShape(16.dp), colors = CardDefaults.cardColors(containerColor = Color.White)) {
        Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
            Text(producto.nombre, style = MaterialTheme.typography.titleLarge)
            Text(
                "Código: ${producto.codigoBarras ?: "sin código (al peso)"}",
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
            Text(producto.precioVenta.soles() + if (producto.alPeso) " / kg" else "", style = MaterialTheme.typography.displaySmall)
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                Text("Stock en tu tienda:", style = MaterialTheme.typography.bodyLarge)
                EtiquetaStock(producto)
            }
            Text(
                "Mínimo: ${producto.stockMinimo.textoCantidad(producto.alPeso)} ${producto.unidad} · ${producto.categoria} · ${producto.marca}",
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
            if (!producto.stockRegistrado) {
                Text("Este producto todavía no llegó a tu tienda.", style = MaterialTheme.typography.bodySmall)
            }
        }
    }
}
