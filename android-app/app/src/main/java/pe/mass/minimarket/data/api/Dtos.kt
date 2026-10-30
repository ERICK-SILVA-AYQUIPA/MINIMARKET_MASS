package pe.mass.minimarket.data.api

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

/*
 * Lo que envia y recibe la API /api/v1 del backend PHP (public/api/index.php).
 * Los montos y cantidades llegan como texto ("4.90", "180.000") para no perder centimos con Double:
 * la app los convierte a BigDecimal (dominio/Dinero.kt).
 */

@Serializable
data class RespuestaApi<T>(
    val ok: Boolean,
    val datos: T? = null,
    val error: ErrorApi? = null,
    // true cuando el servidor devuelve una venta o un ingreso ya registrado con la misma Idempotency-Key
    val repetida: Boolean = false,
)

@Serializable
data class ErrorApi(val codigo: String, val mensaje: String)

@Serializable
class Vacio

@Serializable
data class Salud(val servicio: String, val version: Int, val hora: String)

// ---------- Sesion ----------

@Serializable
data class LoginPedido(val dni: String, val clave: String, val dispositivo: String)

@Serializable
data class LoginDatos(val token: String, val expira: String, val usuario: Perfil)

@Serializable
data class Perfil(
    @SerialName("id_empleado") val idEmpleado: Int,
    val nombres: String,
    val apellidos: String,
    val dni: String,
    val rol: String,
    @SerialName("id_tienda") val idTienda: Int,
    val tienda: String,
    val permisos: Permisos,
) {
    val nombreCompleto: String get() = "$nombres $apellidos"
}

@Serializable
data class Permisos(
    val vender: Boolean = false,
    @SerialName("manejar_caja") val manejarCaja: Boolean = false,
    @SerialName("registrar_ingreso") val registrarIngreso: Boolean = false,
    @SerialName("ver_ingresos") val verIngresos: Boolean = false,
    @SerialName("anular_venta") val anularVenta: Boolean = false,
    @SerialName("ver_inventario") val verInventario: Boolean = false,
    @SerialName("ver_ventas") val verVentas: Boolean = false,
)

// ---------- Productos e inventario ----------

@Serializable
data class Producto(
    @SerialName("id_producto") val idProducto: Int,
    val nombre: String,
    @SerialName("codigo_barras") val codigoBarras: String? = null,
    @SerialName("precio_venta") val precioVenta: String,
    @SerialName("al_peso") val alPeso: Boolean = false,
    val estado: String = "ACTIVO",
    val categoria: String = "",
    val marca: String = "",
    val unidad: String = "",
    @SerialName("unidad_nombre") val unidadNombre: String = "",
    val stock: String = "0.000",
    @SerialName("stock_minimo") val stockMinimo: String = "0.000",
    @SerialName("stock_registrado") val stockRegistrado: Boolean = false,
    val agotado: Boolean = false,
    @SerialName("stock_bajo") val stockBajo: Boolean = false,
    // Solo en la ficha (GET /productos/{id})
    val movimientos: List<Movimiento> = emptyList(),
) {
    val activo: Boolean get() = estado == "ACTIVO"
}

@Serializable
data class PaginaProductos(
    val productos: List<Producto>,
    val pagina: Int,
    val total: Int,
    @SerialName("hay_mas") val hayMas: Boolean,
)

@Serializable
data class Movimiento(
    @SerialName("id_movimiento") val idMovimiento: Long,
    @SerialName("fecha_hora") val fechaHora: String,
    @SerialName("id_producto") val idProducto: Int,
    val producto: String,
    val tipo: String,
    val entrada: Boolean,
    val cantidad: String,
    @SerialName("stock_nuevo") val stockNuevo: String,
    val empleado: String,
    val observacion: String? = null,
    val origen: String,
)

// ---------- Caja ----------

@Serializable
data class EstadoCaja(
    @SerialName("puede_vender") val puedeVender: Boolean,
    val turno: Turno? = null,
    val cajas: List<CajaDto> = emptyList(),
)

@Serializable
data class Turno(
    @SerialName("id_caja_turno") val idCajaTurno: Int,
    @SerialName("id_caja") val idCaja: Int,
    @SerialName("numero_caja") val numeroCaja: Int? = null,
    val apertura: String,
    @SerialName("monto_inicial") val montoInicial: String,
)

@Serializable
data class CajaDto(
    @SerialName("id_caja") val idCaja: Int,
    @SerialName("numero_caja") val numeroCaja: Int,
    val estado: String,
)

@Serializable
data class AbrirTurnoPedido(
    @SerialName("id_caja") val idCaja: String,
    @SerialName("monto_inicial") val montoInicial: String,
)

// ---------- Ventas ----------

@Serializable
data class LineaPedido(
    @SerialName("id_producto") val idProducto: String,
    val cantidad: String,
    // Solo en el ingreso de mercaderia
    val costo: String? = null,
    val vence: String? = null,
)

@Serializable
data class PagoPedido(
    val metodo: String,
    @SerialName("efectivo_recibido") val efectivoRecibido: String? = null,
    @SerialName("tarjeta_tipo") val tarjetaTipo: String? = null,
    @SerialName("tarjeta_marca") val tarjetaMarca: String? = null,
    @SerialName("tarjeta_ultimos4") val tarjetaUltimos4: String? = null,
    @SerialName("tarjeta_autorizacion") val tarjetaAutorizacion: String? = null,
    @SerialName("sip_modalidad") val sipModalidad: String? = null,
    @SerialName("sip_operacion") val sipOperacion: String? = null,
)

@Serializable
data class VentaPedido(
    @SerialName("tipo_comprobante") val tipoComprobante: String,
    val documento: String,
    @SerialName("total_esperado") val totalEsperado: String,
    val lineas: List<LineaPedido>,
    val pago: PagoPedido,
)

@Serializable
data class VentaResumen(
    @SerialName("id_venta") val idVenta: Long,
    val comprobante: String,
    @SerialName("fecha_hora") val fechaHora: String,
    val total: String,
    val estado: String,
    val cajero: String,
    val metodos: String = "",
    val productos: Int = 0,
)

@Serializable
data class PaginaVentas(
    val ventas: List<VentaResumen>,
    val pagina: Int,
    val total: Int,
    @SerialName("hay_mas") val hayMas: Boolean,
)

@Serializable
data class VentaDetalle(
    @SerialName("id_venta") val idVenta: Long,
    val comprobante: String,
    val tipo: String,
    @SerialName("id_tienda") val idTienda: Int,
    val tienda: String,
    @SerialName("fecha_hora") val fechaHora: String,
    val total: String,
    val estado: String,
    val caja: Int = 0,
    val cajero: String,
    val cliente: String,
    val lineas: List<LineaVenta> = emptyList(),
    val pagos: List<PagoDto> = emptyList(),
    val anulacion: Anulacion? = null,
    @SerialName("puede_anular") val puedeAnular: Boolean = false,
) {
    val anulada: Boolean get() = estado == "ANULADA"
}

@Serializable
data class LineaVenta(
    @SerialName("id_producto") val idProducto: Int,
    val nombre: String,
    @SerialName("codigo_barras") val codigoBarras: String? = null,
    val unidad: String = "",
    val cantidad: String,
    @SerialName("precio_unitario") val precioUnitario: String,
    val importe: String,
)

@Serializable
data class PagoDto(
    val metodo: String,
    val monto: String,
    val estado: String,
    val detalle: String = "",
    val recibido: String? = null,
    val vuelto: String? = null,
)

@Serializable
data class Anulacion(
    @SerialName("fecha_hora") val fechaHora: String,
    val motivo: String,
)

@Serializable
data class AnularPedido(val motivo: String)

// ---------- Ingreso de mercaderia ----------

@Serializable
data class DocumentoIngreso(
    val tipo: String,
    val numero: String,
    val fecha: String,
    val ruc: String,
    @SerialName("razon_social") val razonSocial: String,
    val observacion: String,
)

@Serializable
data class IngresoPedido(val documento: DocumentoIngreso, val lineas: List<LineaPedido>)

@Serializable
data class Ingreso(
    @SerialName("id_ingreso") val idIngreso: Long,
    @SerialName("fecha_hora") val fechaHora: String,
    @SerialName("tipo_documento") val tipoDocumento: String,
    @SerialName("numero_documento") val numeroDocumento: String,
    @SerialName("fecha_documento") val fechaDocumento: String,
    @SerialName("ruc_emisor") val rucEmisor: String,
    @SerialName("razon_social") val razonSocial: String,
    val total: String,
    val observacion: String? = null,
    val empleado: String,
    val productos: Int = 0,
    val lineas: List<LineaIngreso> = emptyList(),
)

@Serializable
data class LineaIngreso(
    @SerialName("id_producto") val idProducto: Int,
    val nombre: String,
    val unidad: String = "",
    val cantidad: String,
    @SerialName("costo_unitario") val costoUnitario: String,
    @SerialName("costo_total") val costoTotal: String,
    @SerialName("fecha_vencimiento") val fechaVencimiento: String? = null,
    @SerialName("stock_actual") val stockActual: String,
)

@Serializable
data class PaginaIngresos(
    val ingresos: List<Ingreso>,
    val pagina: Int,
    @SerialName("tipos_documento") val tiposDocumento: List<String> = emptyList(),
)
