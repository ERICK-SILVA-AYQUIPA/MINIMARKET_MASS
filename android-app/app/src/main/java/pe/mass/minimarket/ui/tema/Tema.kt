package pe.mass.minimarket.ui.tema

import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.ColorScheme
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Shapes
import androidx.compose.material3.Typography
import androidx.compose.material3.darkColorScheme
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.runtime.CompositionLocalProvider
import androidx.compose.runtime.Immutable
import androidx.compose.runtime.ReadOnlyComposable
import androidx.compose.runtime.staticCompositionLocalOf
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import pe.mass.minimarket.data.ModoTema

// La marca: el mismo amarillo y la misma tinta de la pagina web (public/estilos.css)
val Marca = Color(0xFFFFD200)
val Tinta = Color(0xFF16202B)

/**
 * Colores con significado (exito, alerta, peligro, informacion) que cambian con el modo claro u oscuro.
 * Se leen con `Mass.colores` en vez de usar un color fijo, asi nada queda ilegible en modo oscuro.
 */
@Immutable
data class ColoresMass(
    val marca: Color,
    val enMarca: Color,
    val exito: Color,
    val exitoFondo: Color,
    val alerta: Color,
    val alertaFondo: Color,
    val peligro: Color,
    val peligroFondo: Color,
    val info: Color,
    val infoFondo: Color,
    val oscuro: Boolean,
)

private val ColoresClaros = ColoresMass(
    marca = Marca,
    enMarca = Tinta,
    exito = Color(0xFF166534),
    exitoFondo = Color(0xFFDCFCE7),
    alerta = Color(0xFF92400E),
    alertaFondo = Color(0xFFFEF3C7),
    peligro = Color(0xFFB42318),
    peligroFondo = Color(0xFFFEE4E2),
    info = Color(0xFF1D4ED8),
    infoFondo = Color(0xFFDBEAFE),
    oscuro = false,
)

private val ColoresOscuros = ColoresMass(
    marca = Marca,
    enMarca = Tinta,
    exito = Color(0xFF86EFAC),
    exitoFondo = Color(0xFF14361F),
    alerta = Color(0xFFFCD34D),
    alertaFondo = Color(0xFF3B2A0B),
    peligro = Color(0xFFFCA5A5),
    peligroFondo = Color(0xFF441A17),
    info = Color(0xFF93C5FD),
    infoFondo = Color(0xFF172A4D),
    oscuro = true,
)

private val EsquemaClaro: ColorScheme = lightColorScheme(
    primary = Tinta,
    onPrimary = Color.White,
    primaryContainer = Marca,
    onPrimaryContainer = Tinta,
    secondary = Color(0xFF5B6775),
    onSecondary = Color.White,
    secondaryContainer = Color(0xFFFFF3B0),
    onSecondaryContainer = Tinta,
    tertiary = Color(0xFF1D4ED8),
    onTertiary = Color.White,
    tertiaryContainer = Color(0xFFDBEAFE),
    onTertiaryContainer = Color(0xFF0B2A6B),
    background = Color(0xFFF6F7F9),
    onBackground = Tinta,
    surface = Color(0xFFF6F7F9),
    onSurface = Tinta,
    surfaceVariant = Color(0xFFE8EBEF),
    onSurfaceVariant = Color(0xFF5B6775),
    surfaceContainerLowest = Color.White,
    surfaceContainerLow = Color.White,
    surfaceContainer = Color(0xFFF0F2F5),
    surfaceContainerHigh = Color(0xFFEAEDF1),
    surfaceContainerHighest = Color(0xFFE3E7EC),
    error = Color(0xFFB42318),
    onError = Color.White,
    errorContainer = Color(0xFFFEE4E2),
    onErrorContainer = Color(0xFF7A1A12),
    outline = Color(0xFFC3CAD3),
    outlineVariant = Color(0xFFE1E5EA),
    inverseSurface = Tinta,
    inverseOnSurface = Color.White,
    inversePrimary = Marca,
)

private val EsquemaOscuro: ColorScheme = darkColorScheme(
    primary = Marca,
    onPrimary = Tinta,
    primaryContainer = Marca,
    onPrimaryContainer = Tinta,
    secondary = Color(0xFFB7C1CD),
    onSecondary = Tinta,
    secondaryContainer = Color(0xFF3A3420),
    onSecondaryContainer = Color(0xFFFFF3B0),
    tertiary = Color(0xFF93C5FD),
    onTertiary = Color(0xFF0B2A6B),
    tertiaryContainer = Color(0xFF172A4D),
    onTertiaryContainer = Color(0xFFDBEAFE),
    background = Color(0xFF0E1318),
    onBackground = Color(0xFFE6E9ED),
    surface = Color(0xFF0E1318),
    onSurface = Color(0xFFE6E9ED),
    surfaceVariant = Color(0xFF26303B),
    onSurfaceVariant = Color(0xFFA9B3BF),
    surfaceContainerLowest = Color(0xFF0A0E12),
    surfaceContainerLow = Color(0xFF161C23),
    surfaceContainer = Color(0xFF1A2129),
    surfaceContainerHigh = Color(0xFF212932),
    surfaceContainerHighest = Color(0xFF29323C),
    error = Color(0xFFFCA5A5),
    onError = Color(0xFF441A17),
    errorContainer = Color(0xFF5A1F1A),
    onErrorContainer = Color(0xFFFEE4E2),
    outline = Color(0xFF4A5562),
    outlineVariant = Color(0xFF2C3540),
    inverseSurface = Color(0xFFE6E9ED),
    inverseOnSurface = Tinta,
    inversePrimary = Tinta,
)

private val base = Typography()
private val letras = base.copy(
    // Precios y totales grandes, faciles de leer en la caja
    displayMedium = base.displayMedium.copy(fontWeight = FontWeight.Bold, letterSpacing = (-0.5).sp),
    displaySmall = base.displaySmall.copy(fontWeight = FontWeight.Bold, letterSpacing = (-0.25).sp),
    headlineMedium = base.headlineMedium.copy(fontWeight = FontWeight.Bold),
    headlineSmall = base.headlineSmall.copy(fontWeight = FontWeight.Bold),
    titleLarge = base.titleLarge.copy(fontWeight = FontWeight.SemiBold),
    titleMedium = base.titleMedium.copy(fontWeight = FontWeight.SemiBold),
    titleSmall = base.titleSmall.copy(fontWeight = FontWeight.SemiBold),
    labelLarge = TextStyle(fontSize = 16.sp, fontWeight = FontWeight.SemiBold, letterSpacing = 0.1.sp),
)

private val formas = Shapes(
    extraSmall = RoundedCornerShape(8.dp),
    small = RoundedCornerShape(12.dp),
    medium = RoundedCornerShape(16.dp),
    large = RoundedCornerShape(24.dp),
    extraLarge = RoundedCornerShape(32.dp),
)

private val LocalColoresMass = staticCompositionLocalOf { ColoresClaros }

/** Acceso a los colores con significado: `Mass.colores.exito`, `Mass.colores.peligroFondo`… */
object Mass {
    val colores: ColoresMass
        @Composable @ReadOnlyComposable get() = LocalColoresMass.current
}

/** true si la app se esta mostrando en modo oscuro (por la preferencia o por el sistema). */
@Composable
fun usaModoOscuro(modo: ModoTema): Boolean = when (modo) {
    ModoTema.SISTEMA -> isSystemInDarkTheme()
    ModoTema.CLARO -> false
    ModoTema.OSCURO -> true
}

@Composable
fun TemaMass(oscuro: Boolean = isSystemInDarkTheme(), contenido: @Composable () -> Unit) {
    CompositionLocalProvider(LocalColoresMass provides if (oscuro) ColoresOscuros else ColoresClaros) {
        MaterialTheme(
            colorScheme = if (oscuro) EsquemaOscuro else EsquemaClaro,
            typography = letras,
            shapes = formas,
            content = contenido,
        )
    }
}
