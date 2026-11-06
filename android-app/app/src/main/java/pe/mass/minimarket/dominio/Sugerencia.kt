package pe.mass.minimarket.dominio

import kotlinx.serialization.json.Json
import kotlinx.serialization.json.JsonArray
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.contentOrNull
import kotlinx.serialization.json.jsonObject
import kotlinx.serialization.json.jsonPrimitive

/**
 * Lo que dicen las tiendas peruanas en linea (Plaza Vea, Wong) o las bases publicas (Open Food Facts,
 * Open Products Facts) de un codigo de barras que no esta en nuestro catalogo. Es una ayuda para llenar el formulario: el trabajador lo revisa y pone el precio.
 * @param categorias las categorias de esa base, en ingles y sin prefijo ("sodas", "milks"): con ellas y el nombre
 *                   se elige la categoria de nuestro catalogo ([Categorias.adivinar])
 */
data class Sugerencia(val nombre: String, val marca: String, val categorias: List<String> = emptyList())

object Sugerencias {

    private val json = Json { ignoreUnknownKeys = true }

    /**
     * Lee la respuesta de https://world.openfoodfacts.org/api/v2/product/{codigo}.json
     * (Open Products Facts, la de limpieza y cuidado personal, responde igual).
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
        // "en:carbonated-drinks" -> "carbonated drinks"
        val categorias = (producto["categories_tags"] as? JsonArray).orEmpty().mapNotNull {
            runCatching { it.jsonPrimitive.contentOrNull }.getOrNull()?.substringAfter(':')?.replace('-', ' ')
        }
        // Algunos nombres vienen en minusculas ("agua san Luis")
        return Sugerencia(nombre.limpio().replaceFirstChar { it.uppercaseChar() }.take(100), marca.limpio().take(50), categorias)
    }

    /**
     * Lee la busqueda por codigo de las tiendas peruanas en linea (Plaza Vea, Wong: VTEX)
     * https://www.plazavea.com.pe/api/catalog_system/pub/products/search?fq=alternateIds_Ean:{codigo}
     * Traen el nombre como en la gondola ("Gaseosa Fanta Kola Inglesa Botella 500ml"), la marca y la ruta de su
     * categoria ("/Bebidas/Gaseosas/"), que se pasa como categorias. null si la tienda no lo vende.
     */
    fun desdeTiendaEnLinea(cuerpo: String): Sugerencia? {
        val lista = runCatching { json.parseToJsonElement(cuerpo) as? JsonArray }.getOrNull() ?: return null
        val producto = lista.firstOrNull() as? JsonObject ?: return null
        val nombre = producto.texto("productName") ?: return null
        val marca = producto.texto("brand").orEmpty()
        val categorias = (producto["categories"] as? JsonArray).orEmpty()
            .mapNotNull { runCatching { it.jsonPrimitive.contentOrNull }.getOrNull() }
            .flatMap { it.split('/') }.map { it.trim() }.filter { it.isNotEmpty() }.distinct()
        return Sugerencia(nombre.limpio().replaceFirstChar { it.uppercaseChar() }.take(100), marca.limpio().take(50), categorias)
    }

    private fun JsonObject.texto(clave: String): String? =
        runCatching { this[clave]?.jsonPrimitive?.contentOrNull }.getOrNull()?.trim()?.takeIf { it.isNotEmpty() }

    private fun String.limpio(): String = replace(Regex("\\s+"), " ").trim()
}
