package pe.mass.minimarket.ui.inicio

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.Logout
import androidx.compose.material.icons.automirrored.filled.ReceiptLong
import androidx.compose.material.icons.filled.AccountCircle
import androidx.compose.material.icons.filled.Inventory2
import androidx.compose.material.icons.filled.LocalShipping
import androidx.compose.material.icons.filled.PointOfSale
import androidx.compose.material.icons.filled.QrCodeScanner
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Surface
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
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.launch
import pe.mass.minimarket.BuildConfig
import pe.mass.minimarket.Rutas
import pe.mass.minimarket.data.Resultado
import pe.mass.minimarket.data.api.Perfil
import pe.mass.minimarket.ui.comun.BarraSuperior
import pe.mass.minimarket.ui.comun.BotonGrande
import pe.mass.minimarket.ui.comun.repositorio
import pe.mass.minimarket.ui.tema.Marca
import pe.mass.minimarket.ui.tema.MarcaSuave
import pe.mass.minimarket.ui.tema.Tinta

private data class Opcion(val titulo: String, val detalle: String, val icono: ImageVector, val ruta: String, val principal: Boolean = false)

/** Solo se muestran las opciones que permite el rol (el servidor igual revisa cada permiso). */
private fun opciones(perfil: Perfil): List<Opcion> = buildList {
    val p = perfil.permisos
    if (p.vender) add(Opcion("Nueva venta", "Escanea y cobra", Icons.Filled.PointOfSale, Rutas.VENTA, principal = true))
    if (p.registrarIngreso) add(Opcion("Entrada de stock", "Recibir mercadería con su guía o factura", Icons.Filled.LocalShipping, Rutas.ENTRADA, principal = true))
    add(Opcion("Consultar producto", "Precio y stock, sin cambiar nada", Icons.Filled.QrCodeScanner, Rutas.CONSULTA))
    if (p.verInventario) add(Opcion("Inventario", "Stock de la tienda, agotados y por reponer", Icons.Filled.Inventory2, Rutas.INVENTARIO))
    if (p.verVentas) add(Opcion("Historial de ventas", "Comprobantes de la tienda", Icons.AutoMirrored.Filled.ReceiptLong, Rutas.HISTORIAL))
}

@Composable
fun PantallaInicio(onIr: (String) -> Unit) {
    val repo = repositorio()
    var perfil by remember { mutableStateOf(repo.perfil) }

    // Los permisos pueden haber cambiado en la web (otro rol, otra tienda): se vuelven a pedir al entrar
    LaunchedEffect(Unit) {
        val r = repo.refrescarPerfil()
        if (r is Resultado.Ok) perfil = r.datos
    }

    Scaffold(topBar = {
        BarraSuperior("Mass Tienda", acciones = {
            IconButton(onClick = { onIr(Rutas.PERFIL) }) { Icon(Icons.Filled.AccountCircle, contentDescription = "Mi perfil") }
        })
    }) { relleno ->
        val actual = perfil ?: return@Scaffold
        Column(
            Modifier.fillMaxSize().padding(relleno).verticalScroll(rememberScrollState()).padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            Surface(color = MarcaSuave, shape = RoundedCornerShape(16.dp), modifier = Modifier.fillMaxWidth()) {
                Column(Modifier.padding(16.dp)) {
                    Text("Hola, ${actual.nombres}", style = MaterialTheme.typography.titleLarge)
                    Text("${actual.rol} · ${actual.tienda}", style = MaterialTheme.typography.bodyMedium)
                }
            }
            opciones(actual).forEach { opcion -> TarjetaOpcion(opcion) { onIr(opcion.ruta) } }
        }
    }
}

@Composable
private fun TarjetaOpcion(opcion: Opcion, onClick: () -> Unit) {
    Card(
        modifier = Modifier.fillMaxWidth().heightIn(min = 84.dp).clickable(onClick = onClick),
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(containerColor = if (opcion.principal) Marca else Color.White),
        elevation = CardDefaults.cardElevation(defaultElevation = 1.dp),
    ) {
        Row(Modifier.padding(16.dp), verticalAlignment = Alignment.CenterVertically) {
            Icon(opcion.icono, contentDescription = null, tint = Tinta, modifier = Modifier.size(36.dp))
            Spacer(Modifier.size(16.dp))
            Column {
                Text(opcion.titulo, style = MaterialTheme.typography.titleLarge, color = Tinta)
                Text(opcion.detalle, style = MaterialTheme.typography.bodyMedium, color = Tinta.copy(alpha = 0.75f))
            }
        }
    }
}

@Composable
fun PantallaPerfil(onAtras: () -> Unit, onSalir: () -> Unit) {
    val repo = repositorio()
    val perfil = repo.perfil
    val alcance = rememberCoroutineScope()
    var saliendo by remember { mutableStateOf(false) }
    var confirmar by remember { mutableStateOf(false) }

    Scaffold(topBar = { BarraSuperior("Mi perfil", onAtras = onAtras) }) { relleno ->
        Column(Modifier.fillMaxSize().padding(relleno).padding(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
            if (perfil != null) {
                Card(shape = RoundedCornerShape(16.dp), colors = CardDefaults.cardColors(containerColor = Color.White)) {
                    Column(Modifier.fillMaxWidth().padding(16.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                        Text(perfil.nombreCompleto, style = MaterialTheme.typography.titleLarge)
                        Dato("Rol", perfil.rol)
                        Dato("Tienda", perfil.tienda)
                        Dato("DNI", perfil.dni)
                    }
                }
            }
            Card(shape = RoundedCornerShape(16.dp), colors = CardDefaults.cardColors(containerColor = Color.White)) {
                Column(Modifier.fillMaxWidth().padding(16.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                    Dato("Servidor", repo.urlServidor)
                    Dato("Versión de la app", BuildConfig.VERSION_NAME)
                    Text(
                        "La app no guarda productos ni ventas: todo se lee y se registra en el mismo servidor de la página web.",
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
            }
            Spacer(Modifier.weight(1f))
            BotonGrande("Cerrar sesión", onClick = { confirmar = true }, icono = Icons.AutoMirrored.Filled.Logout, ocupado = saliendo, resaltado = false)
        }
    }

    if (confirmar) {
        AlertDialog(
            onDismissRequest = { confirmar = false },
            title = { Text("¿Cerrar sesión?") },
            text = { Text("Para volver a entrar necesitarás tu DNI y tu clave.") },
            confirmButton = {
                TextButton(onClick = {
                    confirmar = false
                    saliendo = true
                    alcance.launch {
                        repo.salir()
                        onSalir()
                    }
                }) { Text("Cerrar sesión") }
            },
            dismissButton = { TextButton(onClick = { confirmar = false }) { Text("Cancelar") } },
        )
    }
}

@Composable
private fun Dato(etiqueta: String, valor: String) {
    Row {
        Text("$etiqueta: ", style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
        Text(valor, style = MaterialTheme.typography.bodyMedium)
    }
}
