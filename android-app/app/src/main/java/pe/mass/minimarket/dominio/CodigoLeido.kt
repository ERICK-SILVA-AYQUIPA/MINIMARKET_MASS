package pe.mass.minimarket.dominio

/**
 * Lo que la camara leyo, ya interpretado. Un codigo de barras trae el numero tal cual;
 * un QR o un Data Matrix puede traer ese numero dentro de un enlace o de un texto GS1.
 */
sealed interface CodigoLeido {
    /**
     * Un numero de producto (EAN-8, UPC-A, EAN-13 o GTIN-14 / ITF-14; un UPC-E llega ya como su UPC-A)
     * que se busca en el catalogo. El servidor lo encuentra aunque el catalogo lo tenga con otros ceros
     * adelante, o como uno de los "otros codigos" del producto (CodigoBarras.php).
     */
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

    // Texto GS1 sin parentesis (Data Matrix, GS1-128 de las cajas; puede empezar con FNC1, "]d2" o "]C1"): 0107751271011010...
    private val gs1Plano = Regex("""^(?:\]d2|\]Q3|\]C1|\u001D)?01([0-9]{14})""")

    // Lo que se escribe a mano: "775 1271-011013"
    private val separadores = Regex("""[\s.\-]+""")

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
     * Quita espacios, guiones y puntos de un codigo ESCRITO A MANO ("775 1271-011013"), como CodigoBarras::limpiar()
     * en el servidor. Lo que lee la camara no se limpia: un QR con "987 654 321" no es un producto.
     */
    fun limpiar(texto: String): String = texto.trim().replace(separadores, "")

    /**
     * UPC-E (8 digitos, empaques chicos) a su UPC-A de 12, que es como suele estar en el catalogo.
     * Solo se usa cuando la camara dice que leyo un UPC-E: un EAN-8 tambien tiene 8 digitos y no se toca.
     * null si no es un UPC-E valido (sistema 0 o 1 y su digito de control).
     */
    fun upcEaUpcA(upce: String): String? {
        if (!Regex("^[01][0-9]{7}$").matches(upce)) return null
        val d = upce.toCharArray()
        val cuerpo = when (d[6]) {
            '0', '1', '2' -> "${d[0]}${d[1]}${d[2]}${d[6]}0000${d[3]}${d[4]}${d[5]}"
            '3' -> "${d[0]}${d[1]}${d[2]}${d[3]}00000${d[4]}${d[5]}"
            '4' -> "${d[0]}${d[1]}${d[2]}${d[3]}${d[4]}00000${d[5]}"
            else -> "${d[0]}${d[1]}${d[2]}${d[3]}${d[4]}${d[5]}0000${d[6]}"
        }
        val upca = cuerpo + d[7]
        return if (digitoControlValido(upca)) upca else null
    }

    /**
     * Un GTIN-14 con ceros adelante se guarda como en el empaque: 07751271011010 -> 7751271011010 (EAN-13).
     * El servidor prueba todas las formas con ceros adelante, asi que basta con quitar un cero.
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
