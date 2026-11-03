package pe.mass.minimarket

import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Test
import pe.mass.minimarket.data.ProductoReciente
import pe.mass.minimarket.data.conReciente
import pe.mass.minimarket.dominio.montosSugeridos
import pe.mass.minimarket.dominio.saludo
import java.math.BigDecimal

class AtajosTest {

    private fun textos(total: String) = montosSugeridos(BigDecimal(total)).map { it.toPlainString() }

    @Test
    fun montosParaUnTotalConCentimos() {
        assertEquals(listOf("13.40", "14.00", "15.00", "20.00", "50.00"), textos("13.40"))
    }

    @Test
    fun montoRedondoNoSeRepite() {
        assertEquals(listOf("20.00", "50.00", "100.00", "200.00"), textos("20"))
    }

    @Test
    fun totalGrandeSoloTieneLosBilletesQueAlcanzan() {
        assertEquals(listOf("187.50", "188.00", "190.00", "200.00"), textos("187.5"))
    }

    @Test
    fun sinTotalNoHaySugerencias() {
        assertTrue(montosSugeridos(BigDecimal.ZERO).isEmpty())
    }

    @Test
    fun saludoSegunLaHora() {
        assertEquals("Buenos días", saludo(7))
        assertEquals("Buenas tardes", saludo(15))
        assertEquals("Buenas noches", saludo(21))
        assertEquals("Buenas noches", saludo(2))
    }

    @Test
    fun recientesSinRepetidosYConLimite() {
        val p = { id: Int -> ProductoReciente(id, "Producto $id", "1.00") }
        var lista = emptyList<ProductoReciente>()
        (1..10).forEach { lista = lista.conReciente(p(it), maximo = 8) }
        assertEquals(8, lista.size)
        assertEquals(10, lista.first().id)
        // Volver a ver uno lo sube al primer lugar sin duplicarlo
        lista = lista.conReciente(p(5), maximo = 8)
        assertEquals(listOf(5, 10, 9, 8, 7, 6, 4, 3), lista.map { it.id })
    }
}
