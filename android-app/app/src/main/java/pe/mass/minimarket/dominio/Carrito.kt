package pe.mass.minimarket.dominio

import pe.mass.minimarket.data.api.LineaPedido
import pe.mass.minimarket.data.api.Producto
import java.math.BigDecimal

/** Un producto en el carrito con su cantidad. El precio es el que dio el servidor; al cobrar, el servidor lo vuelve a poner. */
data class ItemCarrito(val producto: Producto, val cantidad: BigDecimal) {
    val precio: BigDecimal get() = producto.precioVenta.aDecimal()
    val importe: BigDecimal get() = importe(cantidad, precio)
    val stock: BigDecimal get() = producto.stock.aDecimal()
    val superaStock: Boolean get() = cantidad > stock
}

/**
 * El carrito de la venta. Es inmutable: cada cambio devuelve un carrito nuevo (asi la pantalla se redibuja).
 * El total es la suma de los importes redondeados, igual que en VentaServicio::registrar().
 */
data class Carrito(val items: List<ItemCarrito> = emptyList()) {

    val total: BigDecimal get() = items.fold(BigDecimal.ZERO) { suma, item -> suma + item.importe }
    val vacio: Boolean get() = items.isEmpty()
    val unidades: Int get() = items.size

    /** Escanear un producto que ya esta suma 1 (o 1 kg); si no esta, entra con 1. */
    fun agregar(producto: Producto): Carrito {
        val existente = items.indexOfFirst { it.producto.idProducto == producto.idProducto }
        if (existente < 0) return copy(items = items + ItemCarrito(producto, BigDecimal.ONE))
        val actualizado = items[existente].let { it.copy(producto = producto, cantidad = it.cantidad + BigDecimal.ONE) }
        return copy(items = items.toMutableList().also { it[existente] = actualizado })
    }

    fun cambiarCantidad(idProducto: Int, cantidad: BigDecimal): Carrito {
        if (cantidad.signum() <= 0) return quitar(idProducto)
        return copy(items = items.map { if (it.producto.idProducto == idProducto) it.copy(cantidad = cantidad) else it })
    }

    /** Boton "-": por unidad resta 1; si queda en 0, el producto sale del carrito. */
    fun restarUno(idProducto: Int): Carrito {
        val item = items.firstOrNull { it.producto.idProducto == idProducto } ?: return this
        return cambiarCantidad(idProducto, item.cantidad - BigDecimal.ONE)
    }

    fun sumarUno(idProducto: Int): Carrito {
        val item = items.firstOrNull { it.producto.idProducto == idProducto } ?: return this
        return cambiarCantidad(idProducto, item.cantidad + BigDecimal.ONE)
    }

    fun quitar(idProducto: Int): Carrito = copy(items = items.filterNot { it.producto.idProducto == idProducto })

    /** Cambia los datos del producto (precio y stock nuevos del servidor) sin tocar la cantidad. */
    fun actualizarProducto(producto: Producto): Carrito =
        copy(items = items.map { if (it.producto.idProducto == producto.idProducto) it.copy(producto = producto) else it })

    /** Productos cuya cantidad pasa el stock que conoce la app (el servidor lo vuelve a revisar al cobrar). */
    val sinStockSuficiente: List<ItemCarrito> get() = items.filter { it.superaStock }

    /** Productos descontinuados: el servidor no los vende. */
    val inactivos: List<ItemCarrito> get() = items.filterNot { it.producto.activo }

    fun lineasPedido(): List<LineaPedido> = items.map {
        LineaPedido(idProducto = it.producto.idProducto.toString(), cantidad = it.cantidad.textoCantidad(it.producto.alPeso))
    }
}
