package pe.mass.minimarket

import android.app.Application
import pe.mass.minimarket.data.Configuracion
import pe.mass.minimarket.data.Preferencias
import pe.mass.minimarket.data.Repositorio

/** Crea una sola vez la configuracion, el repositorio y las preferencias que comparten todas las pantallas. */
class MassApp : Application() {
    lateinit var repositorio: Repositorio
        private set
    lateinit var preferencias: Preferencias
        private set

    override fun onCreate() {
        super.onCreate()
        repositorio = Repositorio(Configuracion(this))
        preferencias = Preferencias(this)
    }
}
