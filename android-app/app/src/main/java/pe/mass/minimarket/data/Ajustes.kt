package pe.mass.minimarket.data

import kotlinx.serialization.Serializable
import pe.mass.minimarket.data.api.Producto

enum class ModoTema(val texto: String) {
    SISTEMA("Automático"),
    CLARO("Claro"),
    OSCURO("Oscuro"),
}

/** Un producto visto hace poco (solo lo necesario para mostrarlo en el inicio; precio y stock se piden de nuevo al abrirlo). */
@Serializable
data class ProductoReciente(
    val id: Int,
    val nombre: String,
    val precio: String,
    val codigo: String? = null,
    val alPeso: Boolean = false,
)

data class Ajustes(
    val tema: ModoTema = ModoTema.SISTEMA,
    val sonido: Boolean = true,
    val vibracion: Boolean = true,
    val recientes: List<ProductoReciente> = emptyList(),
)

/** Cuantos productos vistos hace poco se recuerdan. */
const val MAX_RECIENTES = 8

/** Lista de recientes: el ultimo visto va primero, sin repetidos y como maximo [maximo]. */
fun List<ProductoReciente>.conReciente(nuevo: ProductoReciente, maximo: Int = MAX_RECIENTES): List<ProductoReciente> =
    (listOf(nuevo) + filterNot { it.id == nuevo.id }).take(maximo)

fun Producto.comoReciente() = ProductoReciente(idProducto, nombre, precioVenta, codigoBarras, alPeso)
