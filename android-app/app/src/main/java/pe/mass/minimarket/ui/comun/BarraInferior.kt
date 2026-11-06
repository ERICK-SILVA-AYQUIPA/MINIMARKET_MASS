package pe.mass.minimarket.ui.comun

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.outlined.ReceiptLong
import androidx.compose.material.icons.filled.Home
import androidx.compose.material.icons.filled.Inventory2
import androidx.compose.material.icons.filled.Person
import androidx.compose.material.icons.filled.QrCodeScanner
import androidx.compose.material.icons.automirrored.filled.ReceiptLong
import androidx.compose.material.icons.outlined.Home
import androidx.compose.material.icons.outlined.Inventory2
import androidx.compose.material.icons.outlined.Person
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import pe.mass.minimarket.data.api.Perfil
import pe.mass.minimarket.ui.tema.Marca
import pe.mass.minimarket.ui.tema.Tinta

/** Las pestañas de la barra de abajo. Inventario y Ventas solo aparecen si el rol tiene ese permiso. */
enum class Pestana(val texto: String, val icono: ImageVector, val iconoActivo: ImageVector) {
    INICIO("Inicio", Icons.Outlined.Home, Icons.Filled.Home),
    INVENTARIO("Inventario", Icons.Outlined.Inventory2, Icons.Filled.Inventory2),
    VENTAS("Ventas", Icons.AutoMirrored.Outlined.ReceiptLong, Icons.AutoMirrored.Filled.ReceiptLong),
    CUENTA("Cuenta", Icons.Outlined.Person, Icons.Filled.Person),
}

fun pestanasDe(perfil: Perfil?): List<Pestana> = buildList {
    add(Pestana.INICIO)
    if (perfil?.permisos?.verInventario == true) add(Pestana.INVENTARIO)
    if (perfil?.permisos?.verVentas == true) add(Pestana.VENTAS)
    add(Pestana.CUENTA)
}

/**
 * Barra inferior flotante: dos pestañas a cada lado y el boton amarillo del escaner al centro,
 * siempre a mano con el pulgar.
 */
@Composable
fun BarraInferior(
    pestanas: List<Pestana>,
    actual: Pestana?,
    onPestana: (Pestana) -> Unit,
    onEscanear: () -> Unit,
) {
    val mitad = (pestanas.size + 1) / 2
    val izquierda = pestanas.take(mitad)
    val derecha = pestanas.drop(mitad)
    Box(
        Modifier
            .fillMaxWidth()
            .navigationBarsPadding()
            .padding(start = 16.dp, end = 16.dp, bottom = 10.dp, top = 22.dp),
        contentAlignment = Alignment.BottomCenter,
    ) {
        Surface(
            shape = RoundedCornerShape(28.dp),
            color = MaterialTheme.colorScheme.surfaceContainerLow,
            shadowElevation = 8.dp,
            modifier = Modifier.fillMaxWidth().height(66.dp),
        ) {
            Row(Modifier.padding(horizontal = 6.dp), verticalAlignment = Alignment.CenterVertically) {
                izquierda.forEach { p -> ItemBarra(p, p == actual, Modifier.weight(1f)) { onPestana(p) } }
                // Hueco para el boton del escaner
                Spacer(Modifier.weight(1f))
                derecha.forEach { p -> ItemBarra(p, p == actual, Modifier.weight(1f)) { onPestana(p) } }
            }
        }
        Surface(
            onClick = onEscanear,
            shape = CircleShape,
            color = Marca,
            contentColor = Tinta,
            shadowElevation = 6.dp,
            modifier = Modifier.size(60.dp).align(Alignment.TopCenter).offset(y = (-20).dp),
        ) {
            Box(contentAlignment = Alignment.Center) {
                Icon(Icons.Filled.QrCodeScanner, contentDescription = "Escanear producto", modifier = Modifier.size(28.dp))
            }
        }
    }
}

@Composable
private fun ItemBarra(pestana: Pestana, activa: Boolean, modifier: Modifier, onClick: () -> Unit) {
    val color = if (activa) MaterialTheme.colorScheme.onSurface else MaterialTheme.colorScheme.onSurfaceVariant
    Column(
        modifier
            .clip(RoundedCornerShape(16.dp))
            .clickable(role = Role.Tab, onClick = onClick)
            .padding(vertical = 8.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(2.dp),
    ) {
        Icon(if (activa) pestana.iconoActivo else pestana.icono, contentDescription = null, tint = color, modifier = Modifier.size(24.dp))
        Text(
            pestana.texto,
            style = MaterialTheme.typography.labelSmall,
            fontWeight = if (activa) FontWeight.Bold else FontWeight.Medium,
            color = color,
            maxLines = 1,
        )
    }
}
