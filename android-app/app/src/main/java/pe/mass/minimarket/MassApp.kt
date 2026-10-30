package pe.mass.minimarket

import android.app.Application
import pe.mass.minimarket.data.Configuracion
import pe.mass.minimarket.data.Repositorio

/** Crea una sola vez la configuracion y el repositorio que comparten todas las pantallas. */
class MassApp : Application() {
    lateinit var repositorio: Repositorio
        private set

    override fun onCreate() {
        super.onCreate()
        repositorio = Repositorio(Configuracion(this))
    }
}
