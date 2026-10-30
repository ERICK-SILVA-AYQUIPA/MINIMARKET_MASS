package pe.mass.minimarket

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.navigation.NavHostController
import androidx.navigation.NavType
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.rememberNavController
import androidx.navigation.navArgument
import pe.mass.minimarket.ui.comun.repositorio
import pe.mass.minimarket.ui.entrada.PantallaEntrada
import pe.mass.minimarket.ui.escaner.PantallaConsulta
import pe.mass.minimarket.ui.historial.PantallaDetalleVenta
import pe.mass.minimarket.ui.historial.PantallaHistorial
import pe.mass.minimarket.ui.inicio.PantallaInicio
import pe.mass.minimarket.ui.inicio.PantallaPerfil
import pe.mass.minimarket.ui.inventario.PantallaInventario
import pe.mass.minimarket.ui.inventario.PantallaProducto
import pe.mass.minimarket.ui.login.PantallaLogin
import pe.mass.minimarket.ui.tema.TemaMass
import pe.mass.minimarket.ui.venta.PantallaVenta

class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContent { TemaMass { Navegacion() } }
    }
}

object Rutas {
    const val LOGIN = "login"
    const val INICIO = "inicio"
    const val PERFIL = "perfil"
    const val CONSULTA = "consulta"
    const val ESCANER_INVENTARIO = "escaner_inventario"
    const val INVENTARIO = "inventario"
    const val PRODUCTO = "producto/{id}"
    const val VENTA = "venta"
    const val ENTRADA = "entrada"
    const val HISTORIAL = "historial"
    const val DETALLE_VENTA = "venta/{id}"

    fun producto(id: Int) = "producto/$id"
    fun detalleVenta(id: Long) = "venta/$id"
}

@Composable
private fun Navegacion() {
    val repo = repositorio()
    val nav = rememberNavController()

    // Token vencido o sesion cerrada en el servidor: de vuelta al login, sin pantallas atras
    LaunchedEffect(Unit) {
        repo.sesionVencida.collect { irAlLogin(nav) }
    }

    NavHost(navController = nav, startDestination = if (repo.haySesion) Rutas.INICIO else Rutas.LOGIN) {
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
        composable(Rutas.CONSULTA) {
            PantallaConsulta(onAtras = { nav.popBackStack() }, onAbrirFicha = null)
        }
        composable(Rutas.ESCANER_INVENTARIO) {
            PantallaConsulta(
                onAtras = { nav.popBackStack() },
                onAbrirFicha = { id -> nav.navigate(Rutas.producto(id)) { popUpTo(Rutas.ESCANER_INVENTARIO) { inclusive = true } } },
            )
        }
        composable(Rutas.INVENTARIO) {
            PantallaInventario(
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
