package pe.mass.minimarket

import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Test
import pe.mass.minimarket.dominio.Sugerencias

class SugerenciaTest {

    @Test
    fun armaElNombreConMarcaYTamano() {
        val cuerpo = """{"code":"7751271011010","status":1,"product":{"product_name":"Leche evaporada entera","brands":"Gloria, Leche Gloria","quantity":"400 g"}}"""
        val s = Sugerencias.desdeOpenFoodFacts(cuerpo)
        assertEquals("Leche evaporada entera Gloria 400 g", s?.nombre)
        assertEquals("Gloria", s?.marca)
    }

    @Test
    fun prefiereElNombreEnCastellanoYNoRepiteLaMarca() {
        val cuerpo = """{"status":1,"product":{"product_name":"Evaporated milk","product_name_es":"Leche Gloria  azul","brands":"Gloria","quantity":""}}"""
        val s = Sugerencias.desdeOpenFoodFacts(cuerpo)
        assertEquals("Leche Gloria azul", s?.nombre)
    }

    @Test
    fun codigoQueNoConoceONombreVacio() {
        assertNull(Sugerencias.desdeOpenFoodFacts("""{"code":"123","status":0,"status_verbose":"product not found"}"""))
        assertNull(Sugerencias.desdeOpenFoodFacts("""{"status":1,"product":{"product_name":"  ","brands":"X"}}"""))
        assertNull(Sugerencias.desdeOpenFoodFacts("<html>error</html>"))
    }

    @Test
    fun recortaAlLargoDeLaBase() {
        val largo = "a".repeat(150)
        val s = Sugerencias.desdeOpenFoodFacts("""{"status":1,"product":{"product_name":"$largo","brands":"${"b".repeat(80)}"}}""")
        assertEquals(100, s?.nombre?.length)
        assertEquals(50, s?.marca?.length)
    }
}
