package pe.mass.minimarket.dominio

import kotlinx.serialization.json.Json
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.contentOrNull
import kotlinx.serialization.json.jsonObject
import kotlinx.serialization.json.jsonPrimitive

/**
 * Lo que dice una base publica (Open Food Facts) de un codigo de barras que no esta en nuestro catalogo.
 * Es solo una ayuda para escribir el nombre: el trabajador lo revisa y pone el precio.
 */
data class Sugerencia(val nombre: String, val marca: String)

object Sugerencias {

    private val json = Json { ignoreUnknownKeys = true }

    /**
     * Lee la respuesta de https://world.openfoodfacts.org/api/v2/product/{codigo}.json
     * Devuelve null si el producto no esta o no trae nombre.
     */
    fun desdeOpenFoodFacts(cuerpo: String): Sugerencia? {
        val raiz = runCatching { json.parseToJsonElement(cuerpo).jsonObject }.getOrNull() ?: return null
        if (raiz.texto("status") != "1") return null
        val producto = raiz["product"] as? JsonObject ?: return null

        // Primero el nombre en castellano; luego el general
        val nombreBase = producto.texto("product_name_es") ?: producto.texto("product_name") ?: return null
        // "Gloria, Leche Gloria" -> "Gloria"
        val marca = producto.texto("brands")?.split(',')?.firstOrNull()?.trim().orEmpty()
        val cantidad = producto.texto("quantity").orEmpty()

        var nombre = nombreBase
        if (marca.isNotEmpty() && !nombre.contains(marca, ignoreCase = true)) nombre = "$nombre $marca"
        if (cantidad.isNotEmpty() && !nombre.contains(cantidad, ignoreCase = true)) nombre = "$nombre $cantidad"
        return Sugerencia(nombre.limpio().take(100), marca.limpio().take(50))
    }

    private fun JsonObject.texto(clave: String): String? =
        runCatching { this[clave]?.jsonPrimitive?.contentOrNull }.getOrNull()?.trim()?.takeIf { it.isNotEmpty() }

    private fun String.limpio(): String = replace(Regex("\\s+"), " ").trim()
}
