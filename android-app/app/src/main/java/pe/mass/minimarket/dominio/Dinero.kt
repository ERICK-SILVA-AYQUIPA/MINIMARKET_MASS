package pe.mass.minimarket.dominio

import java.math.BigDecimal
import java.math.RoundingMode

/*
 * Montos y cantidades con BigDecimal, con las mismas reglas del backend (models/Dinero.php y Cantidad.php):
 * soles con 2 decimales, cantidades con 3, importe = ROUND(cantidad * precio, 2) redondeando la mitad hacia arriba.
 */

/** "4.90" -> 4.90. Un texto que no es numero cuenta como 0. */
fun String?.aDecimal(): BigDecimal = this?.trim()?.replace(',', '.')?.toBigDecimalOrNull() ?: BigDecimal.ZERO

/** 4.9 -> "S/ 4.90" */
fun BigDecimal.soles(): String = "S/ " + setScale(2, RoundingMode.HALF_UP).toPlainString()

/** "4.90" -> "S/ 4.90" */
fun String?.soles(): String = aDecimal().soles()

/** El monto como lo pide la API: "12.50" */
fun BigDecimal.textoMonto(): String = setScale(2, RoundingMode.HALF_UP).toPlainString()

/** Cantidad para mostrar: "3" si va por unidad, "1.250" si es al peso. */
fun BigDecimal.textoCantidad(alPeso: Boolean): String =
    if (alPeso) setScale(3, RoundingMode.HALF_UP).toPlainString()
    else setScale(0, RoundingMode.DOWN).toPlainString()

fun String?.textoCantidad(alPeso: Boolean): String = aDecimal().textoCantidad(alPeso)

/** Igual que Cantidad::importe() del backend. */
fun importe(cantidad: BigDecimal, precio: BigDecimal): BigDecimal =
    cantidad.multiply(precio).setScale(2, RoundingMode.HALF_UP)

object Validar {
    private val unidades = Regex("^[0-9]{1,7}$")
    private val kilos = Regex("^[0-9]{1,7}(\\.[0-9]{1,3})?$")
    private val monto = Regex("^[0-9]{1,8}(\\.[0-9]{1,2})?$")

    /**
     * La cantidad que acepta el backend: por unidad solo enteros, al peso hasta 3 decimales; siempre mayor que 0.
     * Devuelve el mensaje de error o null si esta bien.
     */
    fun cantidad(texto: String, alPeso: Boolean): String? {
        val limpio = texto.trim().replace(',', '.')
        val formato = if (alPeso) kilos else unidades
        return when {
            limpio.isEmpty() -> "Escribe la cantidad."
            !formato.matches(limpio) -> if (alPeso) "Kilos con hasta 3 decimales." else "Solo unidades enteras."
            limpio.aDecimal().signum() <= 0 -> "La cantidad debe ser mayor que 0."
            else -> null
        }
    }

    /** Soles con hasta 2 decimales (costo unitario, efectivo recibido, monto inicial). */
    fun monto(texto: String, puedeSerCero: Boolean = false): String? {
        val limpio = texto.trim().replace(',', '.')
        return when {
            limpio.isEmpty() -> "Escribe el monto."
            !monto.matches(limpio) -> "Soles con hasta 2 decimales."
            !puedeSerCero && limpio.aDecimal().signum() <= 0 -> "Debe ser mayor que 0."
            else -> null
        }
    }

    fun dni(texto: String): Boolean = Regex("^[0-9]{8}$").matches(texto.trim())

    fun ruc(texto: String): Boolean = Regex("^[0-9]{11}$").matches(texto.trim())

    /** Documento de la guia, factura o boleta del proveedor: SERIE-NUMERO, por ejemplo F001-00012345 */
    fun numeroDocumento(texto: String): Boolean = Regex("^[A-Z0-9]{1,4}-[0-9]{1,8}$").matches(texto.trim().uppercase())

    /** Fecha AAAA-MM-DD que existe en el calendario. */
    fun fecha(texto: String): Boolean = runCatching { java.time.LocalDate.parse(texto.trim()) }.isSuccess
}
