package pe.mass.minimarket

import android.graphics.Color
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.SystemBarStyle
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.compose.animation.AnimatedContentTransitionScope
import androidx.compose.animation.core.tween
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.material3.MaterialTheme
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.navigation.NavHostController
import androidx.navigation.NavType
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.rememberNavController
import androidx.navigation.navArgument
import pe.mass.minimarket.ui.comun.preferencias
import pe.mass.minimarket.ui.comun.repositorio
import pe.mass.minimarket.ui.entrada.PantallaEntrada
import pe.mass.minimarket.ui.escaner.ModoConsulta
import pe.mass.minimarket.ui.escaner.PantallaConsulta
import pe.mass.minimarket.ui.historial.PantallaDetalleVenta
import pe.mass.minimarket.ui.historial.PantallaHistorial
import pe.mass.minimarket.ui.inicio.PantallaInicio
import pe.mass.minimarket.ui.inicio.PantallaPerfil
import pe.mass.minimarket.ui.inventario.Filtro
import pe.mass.minimarket.ui.inventario.PantallaInventario
import pe.mass.minimarket.ui.inventario.PantallaProducto
import pe.mass.minimarket.ui.login.PantallaLogin
import pe.mass.minimarket.ui.tema.TemaMass
import pe.mass.minimarket.ui.tema.usaModoOscuro
import pe.mass.minimarket.ui.venta.PantallaVenta

class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        enableEdgeToEdge()
        super.onCreate(savedInstanceState)
        setContent {
            val ajustes by preferencias().ajustes.collectAsState()
            val oscuro = usaModoOscuro(ajustes.tema)
            // Iconos de la barra de estado claros u oscuros segun el tema elegido en la app (no solo el del sistema)
            DisposableEffect(oscuro) {
                enableEdgeToEdge(
                    statusBarStyle = SystemBarStyle.auto(Color.TRANSPARENT, Color.TRANSPARENT) { oscuro },
                    navigationBarStyle = SystemBarStyle.auto(Color.TRANSPARENT, Color.TRANSPARENT) { oscuro },
                )
                onDispose {}
            }
            TemaMass(oscuro = oscuro) {
                Box(Modifier.fillMaxSize().background(MaterialTheme.colorScheme.background)) { Navegacion() }
            }
        }
    }
}

object Rutas {
    const val LOGIN = "login"
    const val INICIO = "inicio"
    const val PERFIL = "perfil"
    const val CONSULTA = "consulta?id={id}"
    const val ESCANER_INVENTARIO = "escaner_inventario"
    const val REGISTRAR = "registrar"
    const val INVENTARIO = "inventario?filtro={filtro}"
    const val PRODUCTO = "producto/{id}"
    const val VENTA = "venta"
    const val ENTRADA = "entrada"
    const val HISTORIAL = "historial"
    const val DETALLE_VENTA = "venta/{id}"

    fun consulta(idProducto: Int? = null) = if (idProducto == null) "consulta" else "consulta?id=$idProducto"
    fun inventario(filtro: Filtro = Filtro.TODOS) = "inventario?filtro=${filtro.clave}"
    fun producto(id: Int) = "producto/$id"
    fun detalleVenta(id: Long) = "venta/$id"
}

private const val DURACION = 260

@Composable
private fun Navegacion() {
    val repo = repositorio()
    val nav = rememberNavController()

    // Token vencido o sesion cerrada en el servidor: de vuelta al login, sin pantallas atras
    LaunchedEffect(Unit) {
        repo.sesionVencida.collect { irAlLogin(nav) }
    }

    NavHost(
        navController = nav,
        startDestination = if (repo.haySesion) Rutas.INICIO else Rutas.LOGIN,
        // Transiciones cortas: la pantalla nueva entra deslizandose y la anterior se desvanece
        enterTransition = {
            slideIntoContainer(AnimatedContentTransitionScope.SlideDirection.Start, tween(DURACION)) + fadeIn(tween(DURACION))
        },
        exitTransition = { fadeOut(tween(DURACION / 2)) },
        popEnterTransition = { fadeIn(tween(DURACION)) },
        popExitTransition = {
            slideOutOfContainer(AnimatedContentTransitionScope.SlideDirection.End, tween(DURACION)) + fadeOut(tween(DURACION))
        },
    ) {
        composable(Rutas.LOGIN) {
            PantallaLogin(onEntrar = {
                nav.navigate(Rutas.INICIO) { popUpTo(Rutas.LOGIN) { inclusive = true } }
            })
        }
        composable(Rutas.INICIO) {
            PantallaInicio(onIr = { ruta -> nav.navigate(ruta) })
        }
        composable(Rutas.PERFIL) {
            PantallaPerfil(onAtras = { nav.popBackStack() }, onSalir = { irAlLogin(nav) })
        }
        composable(
            Rutas.CONSULTA,
            arguments = listOf(navArgument("id") { type = NavType.IntType; defaultValue = 0 }),
        ) { entrada ->
            PantallaConsulta(
                onAtras = { nav.popBackStack() },
                modo = ModoConsulta.PRECIO,
                idInicial = entrada.arguments?.getInt("id")?.takeIf { it > 0 },
                onAbrirFicha = null,
            )
        }
        composable(Rutas.ESCANER_INVENTARIO) {
            PantallaConsulta(
                onAtras = { nav.popBackStack() },
                modo = ModoConsulta.INVENTARIO,
                onAbrirFicha = { id -> nav.navigate(Rutas.producto(id)) { popUpTo(Rutas.ESCANER_INVENTARIO) { inclusive = true } } },
            )
        }
        composable(Rutas.REGISTRAR) {
            PantallaConsulta(onAtras = { nav.popBackStack() }, modo = ModoConsulta.REGISTRAR, onAbrirFicha = null)
        }
        composable(
            Rutas.INVENTARIO,
            arguments = listOf(navArgument("filtro") { type = NavType.StringType; defaultValue = Filtro.TODOS.clave }),
        ) { entrada ->
            PantallaInventario(
                filtroInicial = Filtro.deClave(entrada.arguments?.getString("filtro")),
                onAtras = { nav.popBackStack() },
                onAbrir = { id -> nav.navigate(Rutas.producto(id)) },
                onEscanear = { nav.navigate(Rutas.ESCANER_INVENTARIO) },
            )
        }
        composable(Rutas.PRODUCTO, arguments = listOf(navArgument("id") { type = NavType.IntType })) { entrada ->
            PantallaProducto(idProducto = entrada.arguments?.getInt("id") ?: 0, onAtras = { nav.popBackStack() })
        }
        composable(Rutas.VENTA) {
            PantallaVenta(
                onAtras = { nav.popBackStack() },
                onVerVenta = { id -> nav.navigate(Rutas.detalleVenta(id)) },
                onHistorial = { nav.navigate(Rutas.HISTORIAL) },
            )
        }
        composable(Rutas.ENTRADA) {
            PantallaEntrada(onAtras = { nav.popBackStack() })
        }
        composable(Rutas.HISTORIAL) {
            PantallaHistorial(onAtras = { nav.popBackStack() }, onAbrir = { id -> nav.navigate(Rutas.detalleVenta(id)) })
        }
        composable(Rutas.DETALLE_VENTA, arguments = listOf(navArgument("id") { type = NavType.LongType })) { entrada ->
            PantallaDetalleVenta(idVenta = entrada.arguments?.getLong("id") ?: 0L, onAtras = { nav.popBackStack() })
        }
    }
}

private fun irAlLogin(nav: NavHostController) {
    nav.navigate(Rutas.LOGIN) {
        popUpTo(nav.graph.id) { inclusive = true }
        launchSingleTop = true
    }
}
