package pe.mass.minimarket.dominio

import java.math.BigDecimal
import java.math.RoundingMode

/** Saludo segun la hora del dia (0..23), para el inicio de la app. */
fun saludo(hora: Int): String = when (hora) {
    in 5..11 -> "Buenos días"
    in 12..18 -> "Buenas tardes"
    else -> "Buenas noches"
}

/**
 * Montos que el cliente suele entregar para pagar [total] en efectivo: el monto exacto y los siguientes
 * redondeos a 1, 5, 10, 20, 50, 100 y 200 soles (los billetes y monedas del Peru). Como maximo [cuantos].
 * Ejemplo: 13.40 -> 13.40, 14, 15, 20, 50.
 */
fun montosSugeridos(total: BigDecimal, cuantos: Int = 5): List<BigDecimal> {
    if (total.signum() <= 0) return emptyList()
    val exacto = total.setScale(2, RoundingMode.UP)
    val redondeos = listOf(1, 5, 10, 20, 50, 100, 200).map { paso ->
        val p = BigDecimal(paso)
        exacto.divide(p, 0, RoundingMode.CEILING).multiply(p).setScale(2)
    }
    return (listOf(exacto) + redondeos)
        .distinctBy { it.stripTrailingZeros() }
        .sorted()
        .take(cuantos)
}
