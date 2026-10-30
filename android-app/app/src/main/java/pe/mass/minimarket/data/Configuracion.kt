package pe.mass.minimarket.data

import android.content.Context
import android.content.SharedPreferences
import android.util.Log
import androidx.security.crypto.EncryptedSharedPreferences
import androidx.security.crypto.MasterKey
import kotlinx.serialization.encodeToString
import kotlinx.serialization.json.Json
import pe.mass.minimarket.BuildConfig
import pe.mass.minimarket.data.api.Perfil

/**
 * Lo que la app recuerda entre aperturas:
 * - la direccion del servidor (se cambia desde la pantalla de login, sin recompilar);
 * - el token de sesion y el perfil, cifrados con una llave del Android Keystore.
 * La app NUNCA guarda la clave del empleado ni datos de MySQL.
 */
class Configuracion(context: Context) {

    private val ajustes: SharedPreferences = context.getSharedPreferences("ajustes", Context.MODE_PRIVATE)

    // Si el Keystore del equipo falla, la sesion solo vive en memoria (hay que entrar cada vez)
    private val seguro: SharedPreferences? = try {
        val llave = MasterKey.Builder(context).setKeyScheme(MasterKey.KeyScheme.AES256_GCM).build()
        EncryptedSharedPreferences.create(
            context,
            "sesion_cifrada",
            llave,
            EncryptedSharedPreferences.PrefKeyEncryptionScheme.AES256_SIV,
            EncryptedSharedPreferences.PrefValueEncryptionScheme.AES256_GCM,
        )
    } catch (e: Exception) {
        Log.w("Configuracion", "No se pudo abrir el almacen cifrado", e)
        null
    }

    private val json = Json { ignoreUnknownKeys = true }

    private var tokenEnMemoria: String? = seguro?.getString(CLAVE_TOKEN, null)
    private var perfilEnMemoria: Perfil? = seguro?.getString(CLAVE_PERFIL, null)?.let {
        runCatching { json.decodeFromString<Perfil>(it) }.getOrNull()
    }

    var urlServidor: String
        get() = ajustes.getString(CLAVE_URL, null) ?: BuildConfig.API_URL
        set(valor) = ajustes.edit().putString(CLAVE_URL, normalizarUrl(valor)).apply()

    val token: String? get() = tokenEnMemoria
    val perfil: Perfil? get() = perfilEnMemoria

    fun guardarSesion(token: String, perfil: Perfil) {
        tokenEnMemoria = token
        perfilEnMemoria = perfil
        seguro?.edit()
            ?.putString(CLAVE_TOKEN, token)
            ?.putString(CLAVE_PERFIL, json.encodeToString(perfil))
            ?.apply()
    }

    fun actualizarPerfil(perfil: Perfil) {
        perfilEnMemoria = perfil
        seguro?.edit()?.putString(CLAVE_PERFIL, json.encodeToString(perfil))?.apply()
    }

    fun borrarSesion() {
        tokenEnMemoria = null
        perfilEnMemoria = null
        seguro?.edit()?.clear()?.apply()
    }

    companion object {
        private const val CLAVE_URL = "url_servidor"
        private const val CLAVE_TOKEN = "token"
        private const val CLAVE_PERFIL = "perfil"

        /** "192.168.1.50:8000" -> "http://192.168.1.50:8000/". null si no es una direccion valida. */
        fun validarUrl(texto: String, permiteHttp: Boolean = BuildConfig.PERMITE_HTTP): String? {
            var url = texto.trim()
            if (url.isEmpty()) return null
            if (!url.startsWith("http://") && !url.startsWith("https://")) {
                url = (if (permiteHttp) "http://" else "https://") + url
            }
            if (url.startsWith("http://") && !permiteHttp) return null
            val sinEsquema = url.substringAfter("://")
            if (sinEsquema.isBlank() || sinEsquema.startsWith("/") || sinEsquema.contains(' ')) return null
            return normalizarUrl(url)
        }

        private fun normalizarUrl(url: String): String = if (url.endsWith("/")) url else "$url/"
    }
}
