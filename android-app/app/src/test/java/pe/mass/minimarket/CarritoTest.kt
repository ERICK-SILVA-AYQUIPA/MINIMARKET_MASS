package pe.mass.minimarket

import org.junit.Assert.assertEquals
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test
import pe.mass.minimarket.data.Configuracion
import pe.mass.minimarket.data.api.Producto
import pe.mass.minimarket.dominio.Carrito
import pe.mass.minimarket.dominio.Validar
import pe.mass.minimarket.dominio.importe
import pe.mass.minimarket.dominio.soles
import pe.mass.minimarket.dominio.textoCantidad
import java.math.BigDecimal

class CarritoTest {

    private val leche = Producto(idProducto = 1, nombre = "Leche", codigoBarras = "7750000000011", precioVenta = "4.90", stock = "3.000")
    private val platano = Producto(idProducto = 2, nombre = "Plátano", precioVenta = "2.35", alPeso = true, stock = "10.000")

    @Test
    fun volverAEscanearSumaUno() {
        val carrito = Carrito().agregar(leche).agregar(leche)
        assertEquals(1, carrito.items.size)
        assertEquals(BigDecimal("2"), carrito.items[0].cantidad)
        assertEquals(BigDecimal("9.80"), carrito.total)
    }

    @Test
    fun restarHastaCeroQuitaElProducto() {
        val carrito = Carrito().agregar(leche).restarUno(1)
        assertTrue(carrito.vacio)
    }

    @Test
    fun importeRedondeaComoElBackend() {
        // 1.235 kg * 2.35 = 2.90225 -> 2.90 ; 0.5 * 0.05 = 0.025 -> 0.03 (mitad hacia arriba)
        assertEquals(BigDecimal("2.90"), importe(BigDecimal("1.235"), BigDecimal("2.35")))
        assertEquals(BigDecimal("0.03"), importe(BigDecimal("0.5"), BigDecimal("0.05")))
        val carrito = Carrito().agregar(platano).cambiarCantidad(2, BigDecimal("1.235")).agregar(leche)
        assertEquals(BigDecimal("7.80"), carrito.total)
    }

    @Test
    fun avisaCuandoPasaElStockConocido() {
        val carrito = Carrito().agregar(leche).cambiarCantidad(1, BigDecimal("4"))
        assertEquals(1, carrito.sinStockSuficiente.size)
    }

    @Test
    fun lineasDelPedidoConElFormatoDeLaApi() {
        val lineas = Carrito().agregar(leche).agregar(platano).cambiarCantidad(2, BigDecimal("0.5")).lineasPedido()
        assertEquals("1", lineas[0].idProducto)
        assertEquals("1", lineas[0].cantidad)
        assertEquals("0.500", lineas[1].cantidad)
    }

    @Test
    fun actualizarPrecioConservaLaCantidad() {
        val carrito = Carrito().agregar(leche).agregar(leche).actualizarProducto(leche.copy(precioVenta = "5.00"))
        assertEquals(BigDecimal("10.00"), carrito.total)
    }

    @Test
    fun validaCantidades() {
        assertNull(Validar.cantidad("3", alPeso = false))
        assertNotNull(Validar.cantidad("1.5", alPeso = false))
        assertNotNull(Validar.cantidad("0", alPeso = false))
        assertNotNull(Validar.cantidad("-2", alPeso = false))
        assertNull(Validar.cantidad("1,250", alPeso = true))
        assertNotNull(Validar.cantidad("1.2345", alPeso = true))
    }

    @Test
    fun validaMontosYDocumentos() {
        assertNull(Validar.monto("20"))
        assertNull(Validar.monto("3.20"))
        assertNotNull(Validar.monto("3.205"))
        assertNotNull(Validar.monto("0"))
        assertNull(Validar.monto("0", puedeSerCero = true))
        assertTrue(Validar.numeroDocumento("f001-00012345"))
        assertTrue(Validar.ruc("20100070970"))
        assertTrue(Validar.fecha("2026-10-30"))
        assertTrue(!Validar.fecha("2026-02-30"))
    }

    @Test
    fun formatos() {
        assertEquals("S/ 4.90", BigDecimal("4.9").soles())
        assertEquals("3", "3.000".textoCantidad(alPeso = false))
        assertEquals("1.250", "1.25".textoCantidad(alPeso = true))
    }

    @Test
    fun direccionDelServidor() {
        assertEquals("http://192.168.1.50:8000/", Configuracion.validarUrl("192.168.1.50:8000", permiteHttp = true))
        assertEquals("https://mass.ejemplo.pe/", Configuracion.validarUrl("mass.ejemplo.pe", permiteHttp = false))
        assertNull(Configuracion.validarUrl("http://192.168.1.50:8000", permiteHttp = false))
        assertNull(Configuracion.validarUrl("", permiteHttp = true))
    }
}
