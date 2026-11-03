package pe.mass.minimarket.data

import android.content.Context
import android.content.SharedPreferences
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.serialization.encodeToString
import kotlinx.serialization.json.Json
import pe.mass.minimarket.data.api.Producto

/**
 * Preferencias del celular (no son datos de la tienda): tema, sonido, vibracion y productos vistos hace poco.
 * Se guardan sin cifrar porque no tienen nada privado; los recientes se borran al cerrar sesion.
 */
class Preferencias(context: Context) {
    private val prefs: SharedPreferences = context.getSharedPreferences("preferencias", Context.MODE_PRIVATE)
    private val json = Json { ignoreUnknownKeys = true }

    private val _ajustes = MutableStateFlow(leer())
    val ajustes: StateFlow<Ajustes> = _ajustes.asStateFlow()

    private fun leer() = Ajustes(
        tema = runCatching { ModoTema.valueOf(prefs.getString(CLAVE_TEMA, null) ?: "") }.getOrDefault(ModoTema.SISTEMA),
        sonido = prefs.getBoolean(CLAVE_SONIDO, true),
        vibracion = prefs.getBoolean(CLAVE_VIBRACION, true),
        recientes = prefs.getString(CLAVE_RECIENTES, null)
            ?.let { runCatching { json.decodeFromString<List<ProductoReciente>>(it) }.getOrNull() }
            .orEmpty(),
    )

    fun tema(modo: ModoTema) {
        prefs.edit().putString(CLAVE_TEMA, modo.name).apply()
        _ajustes.update { it.copy(tema = modo) }
    }

    fun sonido(activo: Boolean) {
        prefs.edit().putBoolean(CLAVE_SONIDO, activo).apply()
        _ajustes.update { it.copy(sonido = activo) }
    }

    fun vibracion(activa: Boolean) {
        prefs.edit().putBoolean(CLAVE_VIBRACION, activa).apply()
        _ajustes.update { it.copy(vibracion = activa) }
    }

    fun recordar(producto: Producto) {
        val lista = _ajustes.value.recientes.conReciente(producto.comoReciente())
        prefs.edit().putString(CLAVE_RECIENTES, json.encodeToString(lista)).apply()
        _ajustes.update { it.copy(recientes = lista) }
    }

    fun borrarRecientes() {
        prefs.edit().remove(CLAVE_RECIENTES).apply()
        _ajustes.update { it.copy(recientes = emptyList()) }
    }

    companion object {
        private const val CLAVE_TEMA = "tema"
        private const val CLAVE_SONIDO = "sonido"
        private const val CLAVE_VIBRACION = "vibracion"
        private const val CLAVE_RECIENTES = "recientes"
    }
}
