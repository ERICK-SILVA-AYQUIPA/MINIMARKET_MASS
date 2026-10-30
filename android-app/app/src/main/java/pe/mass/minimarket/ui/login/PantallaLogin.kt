package pe.mass.minimarket.ui.login

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Dns
import androidx.compose.material.icons.filled.QrCodeScanner
import androidx.compose.material.icons.filled.Visibility
import androidx.compose.material.icons.filled.VisibilityOff
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.input.VisualTransformation
import androidx.compose.ui.unit.dp
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import pe.mass.minimarket.BuildConfig
import pe.mass.minimarket.data.Configuracion
import pe.mass.minimarket.data.Repositorio
import pe.mass.minimarket.data.Resultado
import pe.mass.minimarket.dominio.Validar
import pe.mass.minimarket.ui.comun.Aviso
import pe.mass.minimarket.ui.comun.BotonGrande
import pe.mass.minimarket.ui.comun.TarjetaAviso
import pe.mass.minimarket.ui.comun.TipoAviso
import pe.mass.minimarket.ui.comun.vistaModelo
import pe.mass.minimarket.ui.tema.Marca
import pe.mass.minimarket.ui.tema.Tinta

data class EstadoLogin(
    val dni: String = "",
    val clave: String = "",
    val entrando: Boolean = false,
    val aviso: Aviso? = null,
    val servidor: String = "",
    val probando: Boolean = false,
)

class LoginViewModel(private val repo: Repositorio) : ViewModel() {
    private val _estado = MutableStateFlow(EstadoLogin(servidor = repo.urlServidor))
    val estado: StateFlow<EstadoLogin> = _estado

    fun dni(valor: String) = _estado.update { it.copy(dni = valor.filter(Char::isDigit).take(8), aviso = null) }
    fun clave(valor: String) = _estado.update { it.copy(clave = valor.take(72), aviso = null) }

    fun entrar(alEntrar: () -> Unit) {
        val e = _estado.value
        if (!Validar.dni(e.dni) || e.clave.isEmpty()) {
            _estado.update { it.copy(aviso = Aviso("Escribe tu DNI de 8 dígitos y tu clave.", TipoAviso.ALERTA)) }
            return
        }
        _estado.update { it.copy(entrando = true, aviso = null) }
        viewModelScope.launch {
            when (val r = repo.entrar(e.dni, e.clave)) {
                is Resultado.Ok -> {
                    _estado.update { it.copy(entrando = false, clave = "") }
                    alEntrar()
                }
                is Resultado.Error -> _estado.update { it.copy(entrando = false, aviso = Aviso(r.mensaje, TipoAviso.ERROR)) }
            }
        }
    }

    /** Cambia la direccion del servidor y prueba que responda la API. Devuelve false si la direccion no es valida. */
    fun cambiarServidor(texto: String): Boolean {
        val url = Configuracion.validarUrl(texto) ?: return false
        repo.cambiarServidor(url)
        _estado.update { it.copy(servidor = repo.urlServidor, probando = true, aviso = null) }
        viewModelScope.launch {
            val aviso = when (val r = repo.probarServidor()) {
                is Resultado.Ok -> Aviso("Conectado a ${r.datos.servicio}.", TipoAviso.OK)
                is Resultado.Error -> Aviso(r.mensaje, TipoAviso.ERROR)
            }
            _estado.update { it.copy(probando = false, aviso = aviso) }
        }
        return true
    }
}

@Composable
fun PantallaLogin(onEntrar: () -> Unit) {
    val vm = vistaModelo { LoginViewModel(it) }
    val estado by vm.estado.collectAsState()
    var verClave by remember { mutableStateOf(false) }
    var editarServidor by remember { mutableStateOf(false) }

    Box(Modifier.fillMaxSize().imePadding()) {
        Column(
            Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(24.dp),
            verticalArrangement = Arrangement.spacedBy(16.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
        ) {
            Spacer(Modifier.height(24.dp))
            Surface(color = Marca, shape = RoundedCornerShape(20.dp), modifier = Modifier.size(84.dp)) {
                Box(contentAlignment = Alignment.Center) {
                    Icon(Icons.Filled.QrCodeScanner, contentDescription = null, tint = Tinta, modifier = Modifier.size(48.dp))
                }
            }
            Text("Minimarket Mass", style = MaterialTheme.typography.headlineSmall)
            Text(
                "Entra con el mismo DNI y clave de la página web.",
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
            OutlinedTextField(
                value = estado.dni,
                onValueChange = vm::dni,
                label = { Text("DNI") },
                singleLine = true,
                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.NumberPassword, imeAction = ImeAction.Next),
                modifier = Modifier.fillMaxWidth(),
            )
            OutlinedTextField(
                value = estado.clave,
                onValueChange = vm::clave,
                label = { Text("Clave") },
                singleLine = true,
                visualTransformation = if (verClave) VisualTransformation.None else PasswordVisualTransformation(),
                trailingIcon = {
                    IconButton(onClick = { verClave = !verClave }) {
                        Icon(
                            if (verClave) Icons.Filled.VisibilityOff else Icons.Filled.Visibility,
                            contentDescription = if (verClave) "Ocultar clave" else "Ver clave",
                        )
                    }
                },
                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Password, imeAction = ImeAction.Done),
                keyboardActions = KeyboardActions(onDone = { vm.entrar(onEntrar) }),
                modifier = Modifier.fillMaxWidth(),
            )
            TarjetaAviso(estado.aviso)
            BotonGrande("Entrar", onClick = { vm.entrar(onEntrar) }, ocupado = estado.entrando)

            Row(verticalAlignment = Alignment.CenterVertically) {
                Icon(Icons.Filled.Dns, contentDescription = null, tint = MaterialTheme.colorScheme.onSurfaceVariant)
                Spacer(Modifier.size(6.dp))
                Text(
                    if (estado.probando) "Probando ${estado.servidor}…" else "Servidor: ${estado.servidor}",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    modifier = Modifier.weight(1f, fill = false),
                )
                TextButton(onClick = { editarServidor = true }) { Text("Cambiar") }
            }
            Text("Versión ${BuildConfig.VERSION_NAME}", style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
        }
    }

    if (editarServidor) {
        DialogoServidor(
            actual = estado.servidor,
            onGuardar = { texto -> vm.cambiarServidor(texto).also { valida -> if (valida) editarServidor = false } },
            onCerrar = { editarServidor = false },
        )
    }
}

@Composable
private fun DialogoServidor(actual: String, onGuardar: (String) -> Boolean, onCerrar: () -> Unit) {
    var texto by remember { mutableStateOf(actual) }
    var error by remember { mutableStateOf<String?>(null) }
    AlertDialog(
        onDismissRequest = onCerrar,
        title = { Text("Dirección del servidor") },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                Text(
                    if (BuildConfig.PERMITE_HTTP)
                        "Emulador: http://10.0.2.2:8000/ · Celular en el mismo Wi-Fi: http://IP-de-la-PC:8000/ · Servidor publicado: https://tu-dominio/"
                    else "La dirección HTTPS donde está publicada la página web, por ejemplo https://tu-dominio/",
                    style = MaterialTheme.typography.bodySmall,
                )
                OutlinedTextField(
                    value = texto,
                    onValueChange = { texto = it.trim(); error = null },
                    singleLine = true,
                    isError = error != null,
                    supportingText = { error?.let { Text(it) } },
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Uri),
                    modifier = Modifier.fillMaxWidth(),
                )
            }
        },
        confirmButton = {
            TextButton(onClick = {
                if (!onGuardar(texto)) {
                    error = if (BuildConfig.PERMITE_HTTP) "Dirección no válida." else "Debe ser una dirección https:// válida."
                }
            }) { Text("Guardar y probar") }
        },
        dismissButton = { TextButton(onClick = onCerrar) { Text("Cancelar") } },
    )
}
