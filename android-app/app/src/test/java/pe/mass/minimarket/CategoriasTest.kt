package pe.mass.minimarket

import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Test
import pe.mass.minimarket.dominio.Categorias

class CategoriasTest {

    private val catalogo = listOf("Abarrotes", "Lácteos", "Bebidas", "Frutas y verduras", "Limpieza",
        "Desayuno", "Snacks y golosinas", "Cuidado personal", "Embutidos y huevos")

    private fun adivinar(nombre: String, internet: List<String> = emptyList()) =
        Categorias.adivinar(nombre, internet, catalogo) { it }

    @Test
    fun porElNombreDelEmpaque() {
        assertEquals("Lácteos", adivinar("Leche evaporada Gloria entera 400 g"))
        assertEquals("Bebidas", adivinar("Gaseosa Fanta Kola Inglesa x 500ml"))
        assertEquals("Abarrotes", adivinar("Aceite Vegetal Primor Premium"))
        assertEquals("Abarrotes", adivinar("Sal Marina EMSAL"))
        assertEquals("Limpieza", adivinar("Detergente Bolívar 750 g"))
        assertEquals("Cuidado personal", adivinar("Jabón Protex avena 110 g"))
        assertEquals("Embutidos y huevos", adivinar("Jamonada Otto Kunz 200 g"))
        assertEquals("Desayuno", adivinar("Café instantáneo Nescafé Kirma 180 g"))
        assertEquals("Snacks y golosinas", adivinar("Galletas Soda Field"))
    }

    @Test
    fun conLasCategoriasDeInternet() {
        assertEquals("Bebidas", adivinar("Sporade Tropical", listOf("beverages", "energy drinks")))
        assertEquals("Lácteos", adivinar("Pura Vida", listOf("dairies", "milks")))
    }

    @Test
    fun noConfundePalabrasParecidas() {
        // "salsa" no es "sal", pero es Abarrotes por la salsa
        assertEquals("Abarrotes", adivinar("Salsa de tomate Alacena"))
        assertEquals("Desayuno", adivinar("Chocolate en polvo Cuisine & Co"))
        assertEquals("Snacks y golosinas", adivinar("Papas fritas Lay's clásicas"))
    }

    @Test
    fun sinPistasOSinEsaCategoria() {
        assertNull(adivinar("Producto XYZ 123"))
        assertNull(Categorias.adivinar("Leche Gloria", emptyList(), listOf("Abarrotes", "Bebidas")) { it })
    }
}
