package pe.mass.minimarket.data

import android.os.Build
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.flow.MutableSharedFlow
import kotlinx.coroutines.flow.SharedFlow
import kotlinx.coroutines.flow.asSharedFlow
import kotlinx.coroutines.withContext
import kotlinx.serialization.SerializationException
import kotlinx.serialization.json.Json
import okhttp3.Interceptor
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import okhttp3.logging.HttpLoggingInterceptor
import pe.mass.minimarket.BuildConfig
import pe.mass.minimarket.data.api.AbrirTurnoPedido
import pe.mass.minimarket.data.api.AnularPedido
import pe.mass.minimarket.data.api.EstadoCaja
import pe.mass.minimarket.data.api.Ingreso
import pe.mass.minimarket.data.api.IngresoPedido
import pe.mass.minimarket.data.api.LoginPedido
import pe.mass.minimarket.data.api.MassApi
import pe.mass.minimarket.data.api.PaginaIngresos
import pe.mass.minimarket.data.api.PaginaProductos
import pe.mass.minimarket.data.api.PaginaVentas
import pe.mass.minimarket.data.api.Perfil
import pe.mass.minimarket.data.api.Producto
import pe.mass.minimarket.data.api.RespuestaApi
import pe.mass.minimarket.data.api.Salud
import pe.mass.minimarket.data.api.Vacio
import pe.mass.minimarket.data.api.VentaDetalle
import pe.mass.minimarket.data.api.VentaPedido
import retrofit2.Response
import retrofit2.Retrofit
import retrofit2.converter.kotlinx.serialization.asConverterFactory
import java.io.IOException
import java.net.SocketTimeoutException
import java.util.concurrent.TimeUnit

/** El resultado de una llamada al backend: los datos, o un mensaje que se puede mostrar al trabajador. */
sealed interface Resultado<out T> {
    data class Ok<T>(val datos: T, val repetida: Boolean = false) : Resultado<T>

    /**
     * @param http 0 cuando no hubo respuesta (sin red, servidor apagado, tiempo agotado):
     *             en una venta o un ingreso no se sabe si llego, por eso se reintenta con la MISMA clave.
     */
    data class Error(val mensaje: String, val codigo: String, val http: Int) : Resultado<Nothing> {
        val sinRespuesta: Boolean get() = http == 0
    }
}

/**
 * Unica puerta de la app hacia el backend PHP. No hay base local: cada pantalla consulta al servidor,
 * asi la app y la pagina web ven siempre los mismos datos de MySQL.
 */
class Repositorio(private val config: Configuracion) {

    private val json = Json {
        ignoreUnknownKeys = true
        explicitNulls = false
        coerceInputValues = true
    }

    // Se emite cuando el servidor rechaza el token (vencio o se cerro la sesion): la app vuelve al login
    private val _sesionVencida = MutableSharedFlow<Unit>(extraBufferCapacity = 1)
    val sesionVencida: SharedFlow<Unit> = _sesionVencida.asSharedFlow()

    private var urlActual: String = config.urlServidor
    private var api: MassApi = crearApi(urlActual)

    val urlServidor: String get() = urlActual
    val perfil: Perfil? get() = config.perfil
    val haySesion: Boolean get() = config.token != null && config.perfil != null

    fun cambiarServidor(url: String) {
        config.urlServidor = url
        urlActual = config.urlServidor
        api = crearApi(urlActual)
    }

    private fun crearApi(url: String): MassApi {
        val conToken = Interceptor { cadena ->
            val pedido = cadena.request().newBuilder().header("Accept", "application/json")
            config.token?.let { pedido.header("Authorization", "Bearer $it") }
            cadena.proceed(pedido.build())
        }
        val cliente = OkHttpClient.Builder()
            .connectTimeout(10, TimeUnit.SECONDS)
            .readTimeout(20, TimeUnit.SECONDS)
            .writeTimeout(20, TimeUnit.SECONDS)
            // Un POST de venta no se reenvia solo: el reintento lo decide la app con la misma Idempotency-Key
            .retryOnConnectionFailure(false)
            .addInterceptor(conToken)
            .apply {
                if (BuildConfig.DEBUG) {
                    // En depuracion se ven las rutas y los codigos, nunca el cuerpo (lleva la clave del login)
                    addInterceptor(HttpLoggingInterceptor().apply {
                        level = HttpLoggingInterceptor.Level.BASIC
                        redactHeader("Authorization")
                    })
                }
            }
            .build()
        return Retrofit.Builder()
            .baseUrl(url)
            .client(cliente)
            .addConverterFactory(json.asConverterFactory("application/json".toMediaType()))
            .build()
            .create(MassApi::class.java)
    }

    private suspend fun <T> llamar(conSesion: Boolean = true, accion: suspend MassApi.() -> Response<RespuestaApi<T>>): Resultado<T> =
        withContext(Dispatchers.IO) {
            try {
                val respuesta = api.accion()
                val cuerpo = respuesta.body()
                if (respuesta.isSuccessful && cuerpo != null && cuerpo.ok && cuerpo.datos != null) {
                    return@withContext Resultado.Ok(cuerpo.datos, cuerpo.repetida)
                }
                val error = cuerpo?.error ?: respuesta.errorBody()?.string()?.let {
                    runCatching { json.decodeFromString<RespuestaApi<Unit>>(it).error }.getOrNull()
                }
                if (respuesta.code() == 401 && conSesion) {
                    config.borrarSesion()
                    _sesionVencida.tryEmit(Unit)
                }
                Resultado.Error(
                    error?.mensaje ?: "El servidor respondió con el código ${respuesta.code()}.",
                    error?.codigo ?: "HTTP_${respuesta.code()}",
                    respuesta.code(),
                )
            } catch (e: SocketTimeoutException) {
                Resultado.Error("El servidor tardó demasiado en responder. Revisa la conexión e intenta otra vez.", "RED", 0)
            } catch (e: IOException) {
                Resultado.Error("No hay conexión con el servidor ($urlActual). Revisa el Wi-Fi o los datos móviles.", "RED", 0)
            } catch (e: SerializationException) {
                Resultado.Error("La respuesta del servidor no se pudo leer. ¿La dirección es la de Minimarket Mass?", "FORMATO", -1)
            } catch (e: IllegalArgumentException) {
                Resultado.Error("La respuesta del servidor no se pudo leer. ¿La dirección es la de Minimarket Mass?", "FORMATO", -1)
            }
        }

    // ---------- Sesion ----------

    suspend fun probarServidor(): Resultado<Salud> = llamar(conSesion = false) { salud() }

    suspend fun entrar(dni: String, clave: String): Resultado<Perfil> {
        val dispositivo = "${Build.MANUFACTURER} ${Build.MODEL}".take(60)
        return when (val r = llamar(conSesion = false) { login(LoginPedido(dni, clave, dispositivo)) }) {
            is Resultado.Ok -> {
                config.guardarSesion(r.datos.token, r.datos.usuario)
                Resultado.Ok(r.datos.usuario)
            }
            is Resultado.Error -> r
        }
    }

    suspend fun refrescarPerfil(): Resultado<Perfil> {
        val r = llamar { yo() }
        if (r is Resultado.Ok) config.actualizarPerfil(r.datos)
        return r
    }

    suspend fun salir() {
        // Aunque no haya red, en el celular la sesion se cierra igual
        llamar(conSesion = false) { logout(Vacio()) }
        config.borrarSesion()
    }

    // ---------- Productos ----------

    suspend fun productos(texto: String?, filtro: String?, pagina: Int): Resultado<PaginaProductos> =
        llamar { productos(texto?.takeIf { it.isNotBlank() }, filtro, pagina) }

    suspend fun productoPorCodigo(codigo: String): Resultado<Producto> = llamar { productoPorCodigo(codigo) }

    suspend fun producto(idProducto: Int): Resultado<Producto> = llamar { producto(idProducto) }

    // ---------- Caja y ventas ----------

    suspend fun caja(): Resultado<EstadoCaja> = llamar { caja() }

    suspend fun abrirTurno(idCaja: Int, montoInicial: String): Resultado<EstadoCaja> =
        llamar { abrirTurno(AbrirTurnoPedido(idCaja.toString(), montoInicial)) }

    suspend fun registrarVenta(clave: String, pedido: VentaPedido): Resultado<VentaDetalle> =
        llamar { registrarVenta(clave, pedido) }

    suspend fun ventas(desde: String?, hasta: String?, estado: String?, comprobante: String?, pagina: Int): Resultado<PaginaVentas> =
        llamar { ventas(desde, hasta, estado, comprobante?.takeIf { it.isNotBlank() }, pagina) }

    suspend fun venta(idVenta: Long): Resultado<VentaDetalle> = llamar { venta(idVenta) }

    suspend fun anularVenta(idVenta: Long, motivo: String): Resultado<VentaDetalle> =
        llamar { anularVenta(idVenta, AnularPedido(motivo)) }

    // ---------- Ingreso de mercaderia ----------

    suspend fun registrarIngreso(clave: String, pedido: IngresoPedido): Resultado<Ingreso> =
        llamar { registrarIngreso(clave, pedido) }

    suspend fun ingresos(pagina: Int): Resultado<PaginaIngresos> = llamar { ingresos(pagina) }

    suspend fun ingreso(idIngreso: Long): Resultado<Ingreso> = llamar { ingreso(idIngreso) }
}
