package pe.mass.minimarket.data.api

import retrofit2.Response
import retrofit2.http.Body
import retrofit2.http.GET
import retrofit2.http.Header
import retrofit2.http.POST
import retrofit2.http.Path
import retrofit2.http.Query

/**
 * Las rutas de public/api/index.php. La URL base es la del servidor (por ejemplo http://10.0.2.2:8000/).
 * El token va en la cabecera Authorization (lo pone ApiCliente); las ventas y los ingresos llevan
 * Idempotency-Key para que un reintento no los registre dos veces.
 */
interface MassApi {

    @GET("api/v1/salud")
    suspend fun salud(): Response<RespuestaApi<Salud>>

    @POST("api/v1/auth/login")
    suspend fun login(@Body pedido: LoginPedido): Response<RespuestaApi<LoginDatos>>

    @POST("api/v1/auth/logout")
    suspend fun logout(@Body vacio: Vacio): Response<RespuestaApi<Map<String, String>>>

    @GET("api/v1/auth/yo")
    suspend fun yo(): Response<RespuestaApi<Perfil>>

    @GET("api/v1/productos")
    suspend fun productos(
        @Query("q") texto: String?,
        @Query("filtro") filtro: String?,
        @Query("pagina") pagina: Int,
    ): Response<RespuestaApi<PaginaProductos>>

    @GET("api/v1/productos/codigo/{codigo}")
    suspend fun productoPorCodigo(@Path("codigo") codigo: String): Response<RespuestaApi<Producto>>

    // Producto nuevo con el codigo escaneado: 201, o 409 YA_EXISTE si otro lo registro antes
    @POST("api/v1/productos")
    suspend fun crearProducto(@Body pedido: ProductoNuevoPedido): Response<RespuestaApi<Producto>>

    // Categorias, marcas y unidades del formulario de producto nuevo
    @GET("api/v1/catalogo")
    suspend fun opcionesCatalogo(): Response<RespuestaApi<OpcionesCatalogo>>

    @GET("api/v1/productos/{id}")
    suspend fun producto(@Path("id") idProducto: Int): Response<RespuestaApi<Producto>>

    @GET("api/v1/caja")
    suspend fun caja(): Response<RespuestaApi<EstadoCaja>>

    @POST("api/v1/caja/turno")
    suspend fun abrirTurno(@Body pedido: AbrirTurnoPedido): Response<RespuestaApi<EstadoCaja>>

    @GET("api/v1/ventas")
    suspend fun ventas(
        @Query("desde") desde: String?,
        @Query("hasta") hasta: String?,
        @Query("estado") estado: String?,
        @Query("comprobante") comprobante: String?,
        @Query("pagina") pagina: Int,
    ): Response<RespuestaApi<PaginaVentas>>

    @POST("api/v1/ventas")
    suspend fun registrarVenta(
        @Header("Idempotency-Key") clave: String,
        @Body pedido: VentaPedido,
    ): Response<RespuestaApi<VentaDetalle>>

    @GET("api/v1/ventas/{id}")
    suspend fun venta(@Path("id") idVenta: Long): Response<RespuestaApi<VentaDetalle>>

    @POST("api/v1/ventas/{id}/anular")
    suspend fun anularVenta(@Path("id") idVenta: Long, @Body pedido: AnularPedido): Response<RespuestaApi<VentaDetalle>>

    @GET("api/v1/ingresos")
    suspend fun ingresos(@Query("pagina") pagina: Int): Response<RespuestaApi<PaginaIngresos>>

    @POST("api/v1/ingresos")
    suspend fun registrarIngreso(
        @Header("Idempotency-Key") clave: String,
        @Body pedido: IngresoPedido,
    ): Response<RespuestaApi<Ingreso>>

    @GET("api/v1/ingresos/{id}")
    suspend fun ingreso(@Path("id") idIngreso: Long): Response<RespuestaApi<Ingreso>>
}
