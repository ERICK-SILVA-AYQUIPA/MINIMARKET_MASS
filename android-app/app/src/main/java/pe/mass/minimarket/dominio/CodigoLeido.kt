package pe.mass.minimarket.dominio

/**
 * Lo que la camara leyo, ya interpretado. Un codigo de barras trae el numero tal cual;
 * un QR o un Data Matrix puede traer ese numero dentro de un enlace o de un texto GS1.
 */
sealed interface CodigoLeido {
    /** Un numero de producto (EAN-8, EAN-13, UPC-A o GTIN-14) que se busca en el catalogo. */
    data class Producto(val codigo: String) : CodigoLeido

    /** Un QR que no es de un producto: un enlace, una promocion, un pago, un texto. */
    data class NoEsProducto(val motivo: String) : CodigoLeido
}

object Codigos {

    private val soloNumero = Regex("^[0-9]{8,14}$")

    // GS1 Digital Link: https://id.gs1.org/01/07751271011010 o cualquier dominio con /01/{GTIN}
    private val enlaceGs1 = Regex("""^https?://[^/]+(?:/[^?#]*)?/01/([0-9]{8,14})(?:[/?#].*)?$""", RegexOption.IGNORE_CASE)

    // Texto GS1 con parentesis: (01)07751271011010(17)270131...
    private val gs1Parentesis = Regex("""\(01\)([0-9]{14})""")

    // Texto GS1 sin parentesis (Data Matrix, puede empezar con el caracter FNC1 o "]d2"): 0107751271011010...
    private val gs1Plano = Regex("""^(?:\]d2|\]Q3|\u001D)?01([0-9]{14})""")

    fun interpretar(valor: String): CodigoLeido {
        val texto = valor.trim()
        if (soloNumero.matches(texto)) return CodigoLeido.Producto(texto)

        val gtin = enlaceGs1.find(texto)?.groupValues?.get(1)
            ?: gs1Parentesis.find(texto)?.groupValues?.get(1)
            ?: gs1Plano.find(texto)?.groupValues?.get(1)
        if (gtin != null && digitoControlValido(gtin)) return CodigoLeido.Producto(corto(gtin))

        return when {
            texto.startsWith("http://", ignoreCase = true) || texto.startsWith("https://", ignoreCase = true) ->
                CodigoLeido.NoEsProducto("Este QR es un enlace a una página, no el código de un producto. Escanea el código de barras.")
            texto.isEmpty() -> CodigoLeido.NoEsProducto("No se pudo leer el código.")
            else -> CodigoLeido.NoEsProducto("Este código no es de un producto. Escanea el código de barras.")
        }
    }

    /**
     * Un GTIN-14 con ceros adelante se guarda como en el empaque: 07751271011010 -> 7751271011010 (EAN-13).
     * El servidor tambien prueba UPC-A (12) y EAN-13 con 0 adelante, asi que basta con quitar un cero.
     */
    private fun corto(gtin: String): String = if (gtin.length == 14 && gtin.startsWith("0")) gtin.substring(1) else gtin

    /** Digito de control GS1 (modulo 10): asi un QR con un numero cualquiera no se toma por un producto. */
    fun digitoControlValido(gtin: String): Boolean {
        if (!soloNumero.matches(gtin)) return false
        val cuerpo = gtin.dropLast(1)
        val suma = cuerpo.reversed().mapIndexed { i, c -> (c - '0') * if (i % 2 == 0) 3 else 1 }.sum()
        return (10 - suma % 10) % 10 == gtin.last() - '0'
    }
}
