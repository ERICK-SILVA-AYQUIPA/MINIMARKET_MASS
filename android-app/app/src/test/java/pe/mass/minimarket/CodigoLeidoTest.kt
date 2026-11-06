package pe.mass.minimarket

import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test
import pe.mass.minimarket.dominio.CodigoLeido
import pe.mass.minimarket.dominio.Codigos

class CodigoLeidoTest {

    private fun producto(valor: String) = Codigos.interpretar(valor) as? CodigoLeido.Producto

    @Test
    fun codigoDeBarrasPasaTalCual() {
        assertEquals("7751271011013", producto("7751271011013")?.codigo)
        assertEquals("12345670", producto(" 12345670 ")?.codigo)
    }

    @Test
    fun escritoAManoConEspaciosOGuionesSeBuscaLimpio() {
        assertEquals("7751271011013", Codigos.limpiar(" 775 1271-011013 "))
        assertEquals("7751271011013", producto(Codigos.limpiar("7751-2710-11013"))?.codigo)
        // Lo que lee la camara no se limpia: un QR con un telefono separado no es un producto
        assertTrue(Codigos.interpretar("987 654 321") is CodigoLeido.NoEsProducto)
    }

    @Test
    fun upcEDeLaCamaraVaComoSuUpcA() {
        assertEquals("042100005264", Codigos.upcEaUpcA("04252614"))
        assertEquals("012000003455", Codigos.upcEaUpcA("01234505"))
        assertEquals("012300000451", Codigos.upcEaUpcA("01234531"))
        assertEquals("012340000053", Codigos.upcEaUpcA("01234543"))
        assertEquals("012345000065", Codigos.upcEaUpcA("01234565"))
        // Con el digito de control errado, o un EAN-8 de otro sistema, no se expande
        assertNull(Codigos.upcEaUpcA("04252615"))
        assertNull(Codigos.upcEaUpcA("96385074"))
    }

    @Test
    fun cajaItf14PasaTalCual() {
        assertEquals("17751271011010", producto("17751271011010")?.codigo)
        assertTrue(Codigos.digitoControlValido("17751271011010"))
    }

    @Test
    fun gs1128DeLaCajaConPrefijo() {
        assertEquals("7751271011013", producto("]C10107751271011013")?.codigo)
    }

    @Test
    fun qrGs1DigitalLinkTraeElCodigoDelProducto() {
        assertEquals("7751271011013", producto("https://id.gs1.org/01/07751271011013")?.codigo)
        assertEquals("7751271011013", producto("https://marca.pe/p/01/07751271011013/10/LOTE5?17=270131")?.codigo)
        assertEquals("012345678905", producto("https://id.gs1.org/01/012345678905")?.codigo)
    }

    @Test
    fun textoGs1ConYSinParentesis() {
        assertEquals("7751271011013", producto("(01)07751271011013(17)270131(10)L5")?.codigo)
        assertEquals("7751271011013", producto("01077512710110131727013110L5")?.codigo)
        assertEquals("7751271011013", producto("\u001D0107751271011013")?.codigo)
    }

    @Test
    fun unQrQueNoEsDeProducto() {
        assertTrue(Codigos.interpretar("https://www.gloria.com.pe/promo") is CodigoLeido.NoEsProducto)
        assertTrue(Codigos.interpretar("Yape: 987654321") is CodigoLeido.NoEsProducto)
        // Un enlace con /01/ pero con el digito de control errado no se toma por un producto
        assertTrue(Codigos.interpretar("https://id.gs1.org/01/07751271011019") is CodigoLeido.NoEsProducto)
        assertTrue(Codigos.interpretar("") is CodigoLeido.NoEsProducto)
    }

    @Test
    fun digitoDeControl() {
        assertTrue(Codigos.digitoControlValido("7751271011013"))
        assertTrue(Codigos.digitoControlValido("40063813339"))
        assertFalse(Codigos.digitoControlValido("7751271011014"))
        assertFalse(Codigos.digitoControlValido("abc"))
    }
}
