package pe.mass.minimarket.ui.tema

import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Typography
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.sp

// Los mismos colores de la pagina web (public/estilos.css)
val Marca = Color(0xFFFFD200)
val MarcaSuave = Color(0xFFFFF6CC)
val Tinta = Color(0xFF16202B)
val Suave = Color(0xFF5B6775)
val Fondo = Color(0xFFF4F5F7)
val Verde = Color(0xFF166534)
val VerdeFondo = Color(0xFFDCFCE7)
val Rojo = Color(0xFFB42318)
val RojoFondo = Color(0xFFFEE4E2)
val Ambar = Color(0xFF92400E)
val AmbarFondo = Color(0xFFFEF3C7)
val Azul = Color(0xFF1D4ED8)
val AzulFondo = Color(0xFFDBEAFE)

private val colores = lightColorScheme(
    primary = Tinta,
    onPrimary = Color.White,
    primaryContainer = Marca,
    onPrimaryContainer = Tinta,
    secondary = Marca,
    onSecondary = Tinta,
    secondaryContainer = MarcaSuave,
    onSecondaryContainer = Tinta,
    background = Fondo,
    onBackground = Tinta,
    surface = Color.White,
    onSurface = Tinta,
    surfaceVariant = Color(0xFFEAECF0),
    onSurfaceVariant = Suave,
    error = Rojo,
    errorContainer = RojoFondo,
    onErrorContainer = Rojo,
    outline = Color(0xFFC9D0D8),
)

private val base = Typography()
private val letras = base.copy(
    // Precios y totales grandes, faciles de leer en la caja
    displaySmall = base.displaySmall.copy(fontWeight = FontWeight.Bold),
    headlineSmall = base.headlineSmall.copy(fontWeight = FontWeight.Bold),
    titleLarge = base.titleLarge.copy(fontWeight = FontWeight.SemiBold),
    titleMedium = base.titleMedium.copy(fontWeight = FontWeight.SemiBold),
    labelLarge = TextStyle(fontSize = 16.sp, fontWeight = FontWeight.SemiBold),
)

@Composable
fun TemaMass(contenido: @Composable () -> Unit) {
    MaterialTheme(colorScheme = colores, typography = letras, content = contenido)
}
