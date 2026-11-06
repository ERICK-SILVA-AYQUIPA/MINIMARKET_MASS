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
import androidx.compose.ui.text.font.Font
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import pe.mass.minimarket.R
import pe.mass.minimarket.data.ModoTema

// La marca: el mismo amarillo y la misma tinta de la pagina web (frontend/public/css/tokens.css)
val Marca = Color(0xFFFFC400)
val Tinta = Color(0xFF0F172A)

// Fondo y tarjetas del tema oscuro de punto de venta (los mismos de la web)
private val FondoOscuro = Color(0xFF0B0F17)
private val TarjetaOscura = Color(0xFF161B26)
private val TarjetaOscura2 = Color(0xFF1E2532)

/** Plus Jakarta Sans: toda la interfaz (res/font, licencia OFL). */
val LetraMass = FontFamily(
    Font(R.font.plus_jakarta_sans_regular, FontWeight.Normal),
    Font(R.font.plus_jakarta_sans_medium, FontWeight.Medium),
    Font(R.font.plus_jakarta_sans_semibold, FontWeight.SemiBold),
    Font(R.font.plus_jakarta_sans_bold, FontWeight.Bold),
    Font(R.font.plus_jakarta_sans_extrabold, FontWeight.ExtraBold),
)

/** JetBrains Mono: precios, totales, stock y codigos de barras (cifras del mismo ancho, como en la web). */
val LetraNumeros = FontFamily(
    Font(R.font.jetbrains_mono_medium, FontWeight.Medium),
    Font(R.font.jetbrains_mono_bold, FontWeight.Bold),
)

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

// Esmeralda, ambar y coral de la referencia: claros sobre su fondo translucido (ya mezclado con la tarjeta)
private val ColoresOscuros = ColoresMass(
    marca = Marca,
    enMarca = Tinta,
    exito = Color(0xFF34D399),
    exitoFondo = Color(0xFF13302B),
    alerta = Color(0xFFFBBF24),
    alertaFondo = Color(0xFF33291A),
    peligro = Color(0xFFFB7185),
    peligroFondo = Color(0xFF3A1C26),
    info = Color(0xFF60A5FA),
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
    background = Color(0xFFF1F5F9),
    onBackground = Tinta,
    surface = Color(0xFFF1F5F9),
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
    background = FondoOscuro,
    onBackground = Color(0xFFF8FAFC),
    surface = FondoOscuro,
    onSurface = Color(0xFFF8FAFC),
    surfaceVariant = TarjetaOscura2,
    onSurfaceVariant = Color(0xFF94A3B8),
    surfaceContainerLowest = Color(0xFF080B11),
    surfaceContainerLow = TarjetaOscura,
    surfaceContainer = TarjetaOscura,
    surfaceContainerHigh = TarjetaOscura2,
    surfaceContainerHighest = Color(0xFF252D3B),
    error = Color(0xFFFCA5A5),
    onError = Color(0xFF441A17),
    errorContainer = Color(0xFF5A1F1A),
    onErrorContainer = Color(0xFFFEE4E2),
    outline = Color(0xFF475569),
    outlineVariant = Color(0xFF232A36),
    inverseSurface = Color(0xFFF8FAFC),
    inverseOnSurface = Tinta,
    inversePrimary = Tinta,
)

private val base = Typography()
private fun TextStyle.jakarta() = copy(fontFamily = LetraMass)
private val letras = Typography(
    // Precios y totales grandes, faciles de leer en la caja: en JetBrains Mono, como en la web
    displayLarge = base.displayLarge.jakarta(),
    displayMedium = base.displayMedium.copy(fontFamily = LetraNumeros, fontWeight = FontWeight.Bold, letterSpacing = (-1).sp),
    displaySmall = base.displaySmall.copy(fontFamily = LetraNumeros, fontWeight = FontWeight.Bold, letterSpacing = (-0.5).sp),
    headlineLarge = base.headlineLarge.jakarta().copy(fontWeight = FontWeight.ExtraBold, letterSpacing = (-0.5).sp),
    headlineMedium = base.headlineMedium.jakarta().copy(fontWeight = FontWeight.ExtraBold, letterSpacing = (-0.5).sp),
    headlineSmall = base.headlineSmall.jakarta().copy(fontWeight = FontWeight.ExtraBold, letterSpacing = (-0.25).sp),
    titleLarge = base.titleLarge.jakarta().copy(fontWeight = FontWeight.Bold),
    titleMedium = base.titleMedium.jakarta().copy(fontWeight = FontWeight.Bold),
    titleSmall = base.titleSmall.jakarta().copy(fontWeight = FontWeight.Bold),
    bodyLarge = base.bodyLarge.jakarta(),
    bodyMedium = base.bodyMedium.jakarta(),
    bodySmall = base.bodySmall.jakarta(),
    labelLarge = TextStyle(fontFamily = LetraMass, fontSize = 16.sp, fontWeight = FontWeight.Bold, letterSpacing = 0.1.sp),
    labelMedium = base.labelMedium.jakarta().copy(fontWeight = FontWeight.SemiBold),
    labelSmall = base.labelSmall.jakarta().copy(fontWeight = FontWeight.SemiBold),
)

/** Estilo de un precio o una cantidad: JetBrains Mono en negrita, sobre el estilo de texto que se pase. */
fun TextStyle.numeros(): TextStyle = copy(fontFamily = LetraNumeros, fontWeight = FontWeight.Bold, letterSpacing = (-0.25).sp)

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
