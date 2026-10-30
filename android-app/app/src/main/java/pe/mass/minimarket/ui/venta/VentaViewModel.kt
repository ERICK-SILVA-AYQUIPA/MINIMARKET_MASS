package pe.mass.minimarket.ui.venta

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import pe.mass.minimarket.data.Repositorio
import pe.mass.minimarket.data.Resultado
import pe.mass.minimarket.data.api.EstadoCaja
import pe.mass.minimarket.data.api.PagoPedido
import pe.mass.minimarket.data.api.Producto
import pe.mass.minimarket.data.api.VentaDetalle
import pe.mass.minimarket.data.api.VentaPedido
import pe.mass.minimarket.dominio.Carrito
import pe.mass.minimarket.dominio.Validar
import pe.mass.minimarket.dominio.aDecimal
import pe.mass.minimarket.dominio.soles
import pe.mass.minimarket.dominio.textoCantidad
import pe.mass.minimarket.dominio.textoMonto
import pe.mass.minimarket.ui.comun.Aviso
import pe.mass.minimarket.ui.comun.TipoAviso
import java.math.BigDecimal
import java.util.UUID

enum class MetodoPago(val clave: String, val texto: String) {
    EFECTIVO("EFECTIVO", "Efectivo"),
    TARJETA("TARJETA", "Tarjeta"),
    SIP("SIP", "Billetera Sip"),
}

/** Lo que se escribe en el dialogo de cobro. */
data class DatosCobro(
    val tipoComprobante: String = "B",
    val documento: String = "",
    val metodo: MetodoPago = MetodoPago.EFECTIVO,
    val recibido: String = "",
    val tarjetaTipo: String = "DEBITO",
    val tarjetaMarca: String = "VISA",
    val tarjetaUltimos4: String = "",
    val tarjetaAutorizacion: String = "",
    val sipModalidad: String = "QR",
    val sipOperacion: String = "",
) {
    fun vuelto(total: BigDecimal): BigDecimal? {
        if (Validar.monto(recibido) != null) return null
        val v = recibido.aDecimal() - total
        return if (v.signum() >= 0) v else null
    }

    /** Lo que falta para poder cobrar, o null. Son las mismas reglas que revisa el servidor. */
    fun problema(total: BigDecimal): String? = when {
        tipoComprobante == "F" && !Validar.ruc(documento) -> "La factura necesita el RUC del cliente (11 dígitos)."
        tipoComprobante == "B" && documento.isNotBlank() && !Validar.dni(documento) && !Validar.ruc(documento) ->
            "El documento del cliente debe ser un DNI (8) o un RUC (11), o déjalo vacío."
        metodo == MetodoPago.EFECTIVO && Validar.monto(recibido) != null -> "Escribe cuánto te entrega el cliente."
        metodo == MetodoPago.EFECTIVO && recibido.aDecimal() < total -> "El efectivo recibido no alcanza para ${total.soles()}."
        metodo == MetodoPago.TARJETA && (tarjetaMarca.isBlank() || !Regex("^[0-9]{4}$").matches(tarjetaUltimos4)
            || !Regex("^[A-Za-z0-9]{1,10}$").matches(tarjetaAutorizacion)) ->
            "Tarjeta: escribe la marca, los últimos 4 dígitos y el código de autorización del voucher."
        metodo == MetodoPago.SIP && !Regex("^[A-Za-z0-9-]{1,30}$").matches(sipOperacion) -> "Sip: escribe el código de operación."
        else -> null
    }

    fun pago(): PagoPedido = when (metodo) {
        MetodoPago.EFECTIVO -> PagoPedido(metodo = metodo.clave, efectivoRecibido = recibido.trim().replace(',', '.'))
        MetodoPago.TARJETA -> PagoPedido(
            metodo = metodo.clave, tarjetaTipo = tarjetaTipo, tarjetaMarca = tarjetaMarca.trim(),
            tarjetaUltimos4 = tarjetaUltimos4.trim(), tarjetaAutorizacion = tarjetaAutorizacion.trim().uppercase(),
        )
        MetodoPago.SIP -> PagoPedido(metodo = metodo.clave, sipModalidad = sipModalidad, sipOperacion = sipOperacion.trim())
    }
}

data class EstadoVenta(
    val carrito: Carrito = Carrito(),
    val caja: EstadoCaja? = null,
    val cargandoCaja: Boolean = true,
    val abriendoTurno: Boolean = false,
    val consultando: Boolean = false,
    val aviso: Aviso? = null,
    val cobro: DatosCobro = DatosCobro(),
    val cobrando: Boolean = false,
    // Clave de la venta en curso (Idempotency-Key). Se conserva si la red falla, para reintentar sin duplicar.
    val claveVenta: String? = null,
    // true: la venta se envio pero no hubo respuesta. El carrito queda bloqueado hasta reintentar.
    val pendiente: Boolean = false,
    val registrada: VentaDetalle? = null,
)

/**
 * Nueva venta (POS movil). El carrito vive en el celular solo mientras se arma; al cobrar, el servidor
 * (VentaServicio) vuelve a poner el precio, revisa el stock con la fila bloqueada, registra venta + detalle
 * + pagos + kardex en una transaccion y responde el comprobante.
 */
class VentaViewModel(private val repo: Repositorio) : ViewModel() {
    private val _estado = MutableStateFlow(EstadoVenta())
    val estado: StateFlow<EstadoVenta> = _estado

    init {
        cargarCaja()
    }

    fun cargarCaja() {
        _estado.update { it.copy(cargandoCaja = true) }
        viewModelScope.launch {
            when (val r = repo.caja()) {
                is Resultado.Ok -> _estado.update { it.copy(caja = r.datos, cargandoCaja = false) }
                is Resultado.Error -> _estado.update { it.copy(cargandoCaja = false, aviso = Aviso(r.mensaje, TipoAviso.ERROR)) }
            }
        }
    }

    fun abrirTurno(idCaja: Int, montoInicial: String) {
        if (Validar.monto(montoInicial, puedeSerCero = true) != null) {
            _estado.update { it.copy(aviso = Aviso("Monto inicial: soles con hasta 2 decimales.", TipoAviso.ALERTA)) }
            return
        }
        _estado.update { it.copy(abriendoTurno = true, aviso = null) }
        viewModelScope.launch {
            when (val r = repo.abrirTurno(idCaja, montoInicial.trim().replace(',', '.'))) {
                is Resultado.Ok -> _estado.update { it.copy(abriendoTurno = false, caja = r.datos, aviso = Aviso("Turno abierto. Ya puedes vender.", TipoAviso.OK)) }
                is Resultado.Error -> _estado.update { it.copy(abriendoTurno = false, aviso = Aviso(r.mensaje, TipoAviso.ERROR)) }
            }
        }
    }

    /** Lo que lee el escaner o se escribe a mano: el producto entra al carrito o suma 1. */
    fun leerCodigo(codigo: String, alEncontrar: () -> Unit, alFallar: () -> Unit) {
        val e = _estado.value
        if (e.consultando || e.pendiente || e.cobrando) return
        _estado.update { it.copy(consultando = true, aviso = null) }
        viewModelScope.launch {
            when (val r = repo.productoPorCodigo(codigo)) {
                is Resultado.Ok -> if (agregar(r.datos)) alEncontrar() else alFallar()
                is Resultado.Error -> {
                    // El cajero no registra productos: lo hace el almacenero o el administrador
                    val texto = if (r.http == 404) "${r.mensaje} Avísale al almacenero para que lo registre." else r.mensaje
                    _estado.update { it.copy(aviso = Aviso(texto, if (r.http == 404) TipoAviso.ALERTA else TipoAviso.ERROR)) }
                    alFallar()
                }
            }
            _estado.update { it.copy(consultando = false) }
        }
    }

    /** @return false si el producto no se puede vender */
    fun agregar(producto: Producto): Boolean {
        if (_estado.value.pendiente) return false
        if (!producto.activo) {
            _estado.update { it.copy(aviso = Aviso("${producto.nombre} está descontinuado: no se vende.", TipoAviso.ALERTA)) }
            return false
        }
        val carrito = _estado.value.carrito.agregar(producto)
        val item = carrito.items.first { it.producto.idProducto == producto.idProducto }
        val aviso = when {
            producto.agotado -> Aviso("${producto.nombre}: el sistema dice que está agotado. Revisa antes de cobrar.", TipoAviso.ALERTA)
            item.superaStock -> Aviso("${producto.nombre}: solo hay ${producto.stock.textoCantidad(producto.alPeso)} en stock.", TipoAviso.ALERTA)
            else -> Aviso("${producto.nombre} · ${producto.precioVenta.soles()} · x${item.cantidad.textoCantidad(producto.alPeso)}", TipoAviso.OK)
        }
        _estado.update { it.copy(carrito = carrito, aviso = aviso, claveVenta = null) }
        return true
    }

    fun sumar(idProducto: Int) = cambiarCarrito { it.sumarUno(idProducto) }
    fun restar(idProducto: Int) = cambiarCarrito { it.restarUno(idProducto) }
    fun quitar(idProducto: Int) = cambiarCarrito { it.quitar(idProducto) }

    /** Para productos al peso o para escribir la cantidad. Devuelve el error o null. */
    fun fijarCantidad(idProducto: Int, texto: String): String? {
        val item = _estado.value.carrito.items.firstOrNull { it.producto.idProducto == idProducto } ?: return null
        Validar.cantidad(texto, item.producto.alPeso)?.let { return it }
        cambiarCarrito { it.cambiarCantidad(idProducto, texto.trim().replace(',', '.').toBigDecimal()) }
        return null
    }

    private fun cambiarCarrito(cambio: (Carrito) -> Carrito) = _estado.update {
        // Con una venta pendiente de confirmar no se toca el carrito
        if (it.pendiente || it.cobrando) it else it.copy(carrito = cambio(it.carrito), claveVenta = null)
    }

    fun cobro(cambio: (DatosCobro) -> DatosCobro) = _estado.update { if (it.pendiente) it else it.copy(cobro = cambio(it.cobro), claveVenta = null) }

    fun cerrarAviso() = _estado.update { it.copy(aviso = null) }

    /** Envia la venta. Un segundo toque mientras se envia no hace nada; un reintento usa la misma clave. */
    fun cobrar() {
        val e = _estado.value
        if (e.cobrando || e.carrito.vacio) return
        e.cobro.problema(e.carrito.total)?.let { msg ->
            _estado.update { it.copy(aviso = Aviso(msg, TipoAviso.ALERTA)) }
            return
        }
        val clave = e.claveVenta ?: UUID.randomUUID().toString()
        _estado.update { it.copy(cobrando = true, claveVenta = clave, aviso = null) }
        val pedido = VentaPedido(
            tipoComprobante = e.cobro.tipoComprobante,
            documento = e.cobro.documento.trim(),
            totalEsperado = e.carrito.total.textoMonto(),
            lineas = e.carrito.lineasPedido(),
            pago = e.cobro.pago(),
        )
        viewModelScope.launch {
            when (val r = repo.registrarVenta(clave, pedido)) {
                is Resultado.Ok -> _estado.update { it.copy(cobrando = false, pendiente = false, registrada = r.datos) }
                is Resultado.Error -> when {
                    r.sinRespuesta -> _estado.update {
                        it.copy(cobrando = false, pendiente = true, aviso = Aviso(
                            "${r.mensaje} No sabemos si la venta quedó registrada. Toca «Reintentar»: no se registrará dos veces.",
                            TipoAviso.ERROR,
                        ))
                    }
                    r.codigo == "PRECIO_CAMBIO" -> {
                        _estado.update { it.copy(cobrando = false, claveVenta = null, aviso = Aviso(r.mensaje, TipoAviso.ALERTA)) }
                        actualizarPrecios()
                    }
                    // El servidor la rechazo (stock, turno, datos del pago): no se guardo nada y el carrito sigue igual
                    else -> _estado.update { it.copy(cobrando = false, pendiente = false, claveVenta = null, aviso = Aviso(r.mensaje, TipoAviso.ERROR)) }
                }
            }
        }
    }

    /** Vuelve a pedir precio y stock de cada producto del carrito. */
    fun actualizarPrecios() {
        val ids = _estado.value.carrito.items.map { it.producto.idProducto }
        viewModelScope.launch {
            for (id in ids) {
                val r = repo.producto(id)
                if (r is Resultado.Ok) _estado.update { it.copy(carrito = it.carrito.actualizarProducto(r.datos.copy(movimientos = emptyList()))) }
            }
        }
    }

    /** Despues de "Descartar": la venta pendiente se revisa en el historial. */
    fun descartarPendiente() = _estado.update { it.copy(pendiente = false, claveVenta = null, carrito = Carrito(), aviso = null) }

    fun nuevaVenta() {
        val caja = _estado.value.caja
        _estado.value = EstadoVenta(caja = caja, cargandoCaja = false)
    }
}
