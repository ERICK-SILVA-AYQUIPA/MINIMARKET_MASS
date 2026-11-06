package pe.mass.minimarket.dominio

import java.text.Normalizer

/**
 * Elige la categoria de NUESTRO catalogo para un producto nuevo, por su nombre y por las categorias que trae
 * Open Food Facts ("Gaseosa Fanta 500ml" + "sodas" -> Bebidas). Solo propone: el trabajador la puede cambiar.
 * Compara sin tildes ni mayusculas, asi "Lácteos" y "Lacteos" son la misma categoria.
 */
object Categorias {

    // Categoria del catalogo (sin tildes, en minusculas) -> palabras que la delatan, en castellano y en ingles (Open Food Facts)
    private val pistas: List<Pair<String, List<String>>> = listOf(
        "lacteos" to listOf("leche", "yogur", "yogurt", "queso", "mantequilla", "manjar", "dairies", "dairy", "milks", "cheeses", "yogurts", "butters"),
        "bebidas" to listOf("gaseosa", "agua", "jugo", "nectar", "refresco", "cerveza", "bebida", "rehidratante", "energizante",
            "beverages", "sodas", "waters", "juices", "nectars", "beers", "soft drinks", "energy drinks"),
        "desayuno" to listOf("cafe", "avena", "cereal", "mermelada", "infusion", "manzanilla", "anis", "cocoa", "chocolate en polvo",
            "coffees", "breakfast", "teas", "herbal teas", "oat", "jams", "cocoa and its products"),
        "snacks y golosinas" to listOf("galleta", "chocolate", "caramelo", "chupetin", "papas fritas", "papitas", "chifle", "snack",
            "chicle", "gomitas", "turron", "biscuits", "cookies", "candies", "confectioneries", "chips", "snacks", "chocolates", "crackers"),
        "cuidado personal" to listOf("jabon", "shampoo", "champu", "acondicionador", "pasta dental", "crema dental", "cepillo dental",
            "desodorante", "papel higienico", "toalla higienica", "panal", "afeitar", "colonia",
            "soaps", "shampoos", "toothpastes", "deodorants", "toilet paper", "hygiene", "body care"),
        "embutidos y huevos" to listOf("jamonada", "jamon", "hot dog", "salchicha", "chorizo", "mortadela", "huevo", "tocino",
            "sausages", "hams", "eggs", "cold cuts", "prepared meats"),
        "limpieza" to listOf("detergente", "lejia", "lavavajilla", "limpiador", "desinfectante", "suavizante", "quitamanchas",
            "esponja", "escoba", "ambientador", "insecticida", "cleaning", "detergents", "dishwashing", "bleach", "household"),
        "frutas y verduras" to listOf("fruta", "verdura", "papa", "platano", "cebolla", "tomate", "limon", "manzana",
            "fresh fruits", "fresh vegetables", "fruits", "vegetables"),
        "abarrotes" to listOf("arroz", "azucar", "aceite", "fideo", "tallarin", "spaghetti", "harina", "sal ", "atun", "conserva",
            "menestra", "lenteja", "frejol", "pallar", "garbanzo", "salsa", "mayonesa", "ketchup", "vinagre", "sillao", "sazonador",
            "condimento", "rices", "pastas", "vegetable oils", "sugars", "flours", "canned", "legumes", "sauces", "condiments", "salts"),
    )

    /**
     * La categoria que mejor calza, o null si ninguna palabra coincide (o el catalogo no tiene esa categoria).
     * Lo que dice el nombre pesa el doble que las categorias de internet (es lo que se ve en el empaque),
     * y su primera palabra aun mas.
     */
    fun <T> adivinar(nombre: String, categoriasPublicas: List<String>, catalogo: List<T>, nombreDe: (T) -> String): T? {
        val enNombre = " ${normalizar(nombre)} "
        val enInternet = categoriasPublicas.map { " ${normalizar(it)} " }
        var mejor: T? = null
        var mejorPuntaje = 0
        for ((categoria, palabras) in pistas) {
            val propia = catalogo.firstOrNull { normalizar(nombreDe(it)) == categoria } ?: continue
            var puntaje = 0
            for (p in palabras) {
                // En castellano lo que es va primero: "Salsa de tomate", "Jabon ... avena"
                if (enNombre.startsWith(" $p")) puntaje += 3
                if (contiene(enNombre, p)) puntaje += 2
                if (enInternet.any { contiene(it, p) }) puntaje += 1
            }
            if (puntaje > mejorPuntaje) {
                mejor = propia
                mejorPuntaje = puntaje
            }
        }
        return mejor
    }

    // Palabra entera o comienzo de palabra: "leche" esta en "leches", pero "sal" no en "salsa" (va como "sal ")
    private fun contiene(texto: String, palabra: String): Boolean = texto.contains(" $palabra")

    fun normalizar(texto: String): String =
        Normalizer.normalize(texto.lowercase(), Normalizer.Form.NFD).replace(Regex("\\p{M}+"), "")
            .replace(Regex("[^a-z0-9]+"), " ").trim()
}
