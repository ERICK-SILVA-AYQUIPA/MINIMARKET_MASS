package pe.mass.minimarket.ui.comun

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ArrowDropDown
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.SuggestionChip
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.input.KeyboardCapitalization
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.async
import kotlinx.coroutines.launch
import pe.mass.minimarket.data.Resultado
import pe.mass.minimarket.data.api.OpcionCatalogo
import pe.mass.minimarket.data.api.OpcionesCatalogo
import pe.mass.minimarket.data.api.Producto
import pe.mass.minimarket.data.api.ProductoNuevoPedido
import pe.mass.minimarket.dominio.Validar

/**
 * Producto nuevo con el codigo recien escaneado (almacenero y administrador).
 * Si Open Food Facts conoce el codigo, el nombre y la marca vienen escritos; el trabajador los revisa y pone el precio.
 * El servidor lo crea con stock 0 en todas las tiendas; el stock se suma despues con la entrada de mercaderia.
 */
@Composable
fun DialogoProductoNuevo(codigo: String, onCreado: (Producto) -> Unit, onCerrar: () -> Unit) {
    val repo = repositorio()
    val alcance = rememberCoroutineScope()
    var opciones by remember { mutableStateOf<OpcionesCatalogo?>(null) }
    var cargando by remember { mutableStateOf(true) }
    var sugerido by remember { mutableStateOf(false) }
    var nombre by remember { mutableStateOf("") }
    var precio by remember { mutableStateOf("") }
    var marca by remember { mutableStateOf("") }
    var categoria by remember { mutableStateOf<OpcionCatalogo?>(null) }
    var unidad by remember { mutableStateOf<OpcionCatalogo?>(null) }
    var guardando by remember { mutableStateOf(false) }
    var intento by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }

    LaunchedEffect(codigo) {
        // Las listas de nuestro servidor y la sugerencia de internet, a la vez
        val sugerencia = async { repo.sugerenciaPublica(codigo) }
        when (val r = repo.opcionesCatalogo()) {
            is Resultado.Ok -> {
                opciones = r.datos
                unidad = r.datos.unidades.firstOrNull()
            }
            is Resultado.Error -> error = r.mensaje
        }
        sugerencia.await()?.let { s ->
            if (nombre.isBlank()) nombre = s.nombre
            if (marca.isBlank()) marca = s.marca
            sugerido = true
        }
        cargando = false
    }

    val errorNombre = if (nombre.isBlank()) "Escribe el nombre." else null
    val errorPrecio = Validar.monto(precio)
    val faltante = when {
        errorNombre != null -> errorNombre
        errorPrecio != null -> "Precio de venta: $errorPrecio"
        categoria == null -> "Elige la categoría."
        marca.isBlank() -> "Escribe o elige la marca."
        unidad == null -> "Elige la unidad."
        else -> null
    }

    fun guardar() {
        intento = true
        if (faltante != null || guardando) return
        val existente = opciones?.marcas?.firstOrNull { it.nombre.equals(marca.trim(), ignoreCase = true) }
        val pedido = ProductoNuevoPedido(
            codigo = codigo,
            nombre = nombre.trim(),
            precio = precio.trim().replace(',', '.'),
            categoria = categoria!!.id.toString(),
            marca = existente?.id?.toString().orEmpty(),
            marcaNombre = if (existente == null) marca.trim() else "",
            unidad = unidad!!.id.toString(),
        )
        guardando = true
        error = null
        alcance.launch {
            when (val r = repo.crearProducto(pedido)) {
                is Resultado.Ok -> onCreado(r.datos)
                is Resultado.Error -> {
                    // 409: otro celular (o un reintento) ya lo registro: se usa ese
                    val yaExiste = if (r.http == 409) repo.productoPorCodigo(codigo) else null
                    if (yaExiste is Resultado.Ok) onCreado(yaExiste.datos) else error = r.mensaje
                }
            }
            guardando = false
        }
    }

    AlertDialog(
        onDismissRequest = { if (!guardando) onCerrar() },
        title = { Text("Producto nuevo") },
        confirmButton = {
            TextButton(onClick = { guardar() }, enabled = !cargando && !guardando && opciones != null) {
                Text(if (guardando) "Guardando…" else "Registrar")
            }
        },
        dismissButton = { TextButton(onClick = onCerrar, enabled = !guardando) { Text("Cancelar") } },
        text = {
            if (cargando) {
                Box(Modifier.fillMaxWidth().padding(24.dp), contentAlignment = Alignment.Center) { CircularProgressIndicator() }
            } else Column(Modifier.verticalScroll(rememberScrollState()), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                Text("Código $codigo: no está en el catálogo.", style = MaterialTheme.typography.bodyMedium)
                if (sugerido) {
                    Text("Nombre sugerido por Open Food Facts: revísalo antes de guardar.",
                        style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                }
                error?.let { TarjetaAviso(Aviso(it, TipoAviso.ERROR)) }
                OutlinedTextField(
                    value = nombre, onValueChange = { nombre = it.take(100) },
                    label = { Text("Nombre (con su tamaño, ej. 400 g)") },
                    isError = intento && errorNombre != null,
                    keyboardOptions = KeyboardOptions(capitalization = KeyboardCapitalization.Sentences),
                    modifier = Modifier.fillMaxWidth(),
                )
                OutlinedTextField(
                    value = precio, onValueChange = { precio = it.take(11) },
                    label = { Text("Precio de venta S/") },
                    isError = intento && errorPrecio != null,
                    supportingText = { if (intento) errorPrecio?.let { Text(it) } },
                    singleLine = true,
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Decimal),
                    modifier = Modifier.fillMaxWidth(),
                )
                Lista("Categoría", opciones?.categorias.orEmpty(), categoria, intento) { categoria = it }
                OutlinedTextField(
                    value = marca, onValueChange = { marca = it.take(50) },
                    label = { Text("Marca") },
                    isError = intento && marca.isBlank(),
                    singleLine = true,
                    keyboardOptions = KeyboardOptions(capitalization = KeyboardCapitalization.Words),
                    modifier = Modifier.fillMaxWidth(),
                )
                // Las marcas que ya existen y se parecen: asi no se crea "Gloria" dos veces
                val parecidas = opciones?.marcas.orEmpty().filter {
                    marca.trim().length >= 2 && it.nombre.contains(marca.trim(), ignoreCase = true) && !it.nombre.equals(marca.trim(), ignoreCase = true)
                }.take(3)
                parecidas.forEach { m -> SuggestionChip(onClick = { marca = m.nombre }, label = { Text(m.nombre) }) }
                if (marca.isNotBlank() && opciones?.marcas.orEmpty().none { it.nombre.equals(marca.trim(), ignoreCase = true) }) {
                    Text("Marca nueva: se agregará al catálogo.", style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant)
                }
                Lista("Unidad", opciones?.unidades.orEmpty(), unidad, intento) { unidad = it }
                if (intento && faltante != null) {
                    Text(faltante, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.error)
                }
                Text("Queda con stock 0. Luego súmalo con la entrada de mercadería.", style = MaterialTheme.typography.bodySmall)
            }
        },
    )
}

/** Un boton que abre la lista para elegir (categoria o unidad). */
@Composable
private fun Lista(titulo: String, opciones: List<OpcionCatalogo>, elegida: OpcionCatalogo?, intento: Boolean, onElegir: (OpcionCatalogo) -> Unit) {
    var abierta by remember { mutableStateOf(false) }
    Box {
        OutlinedButton(onClick = { abierta = true }, modifier = Modifier.fillMaxWidth()) {
            Text(
                elegida?.let { "$titulo: ${it.nombre}" } ?: "Elegir $titulo".lowercase().replaceFirstChar(Char::uppercase),
                modifier = Modifier.weight(1f),
                color = if (intento && elegida == null) MaterialTheme.colorScheme.error else MaterialTheme.colorScheme.onSurface,
            )
            Icon(Icons.Filled.ArrowDropDown, contentDescription = null)
        }
        DropdownMenu(expanded = abierta, onDismissRequest = { abierta = false }) {
            opciones.forEach { o ->
                DropdownMenuItem(text = { Text(o.nombre) }, onClick = { onElegir(o); abierta = false })
            }
        }
    }
}
