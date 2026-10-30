package pe.mass.minimarket.ui.escaner

import android.Manifest
import android.content.Intent
import android.content.pm.PackageManager
import android.media.AudioManager
import android.media.ToneGenerator
import android.net.Uri
import android.os.SystemClock
import android.provider.Settings
import android.util.Log
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.annotation.OptIn
import androidx.camera.core.CameraSelector
import androidx.camera.core.ExperimentalGetImage
import androidx.camera.core.ImageAnalysis
import androidx.camera.core.ImageProxy
import androidx.camera.core.Preview
import androidx.camera.lifecycle.ProcessCameraProvider
import androidx.camera.view.PreviewView
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.NoPhotography
import androidx.compose.material.icons.filled.Search
import androidx.compose.material3.Button
import androidx.compose.material3.FilledTonalButton
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberUpdatedState
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.geometry.CornerRadius
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.BlendMode
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.CompositingStrategy
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.hapticfeedback.HapticFeedbackType
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalHapticFeedback
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.viewinterop.AndroidView
import androidx.core.content.ContextCompat
import androidx.lifecycle.compose.LocalLifecycleOwner
import com.google.mlkit.vision.barcode.BarcodeScanner
import com.google.mlkit.vision.barcode.BarcodeScannerOptions
import com.google.mlkit.vision.barcode.BarcodeScanning
import com.google.mlkit.vision.barcode.common.Barcode
import com.google.mlkit.vision.common.InputImage
import pe.mass.minimarket.ui.tema.Marca
import java.util.concurrent.ExecutorService
import java.util.concurrent.Executors

/**
 * Lee codigos de barras de cada cuadro de la camara con ML Kit (sin tomar foto).
 * Formatos de productos: EAN-13, EAN-8, UPC-A, UPC-E y Code 128.
 *
 * La camara ve el mismo codigo muchas veces por segundo: se avisa una sola vez y no se vuelve a avisar
 * del mismo codigo mientras siga a la vista (y hasta [ESPERA_MISMO_CODIGO_MS] despues de que se fue).
 * Un codigo distinto se avisa de inmediato, asi se puede escanear el siguiente producto sin esperar.
 */
class AnalizadorCodigos(private val alLeer: (String) -> Unit) : ImageAnalysis.Analyzer {

    private val lector: BarcodeScanner = BarcodeScanning.getClient(
        BarcodeScannerOptions.Builder()
            .setBarcodeFormats(
                Barcode.FORMAT_EAN_13,
                Barcode.FORMAT_EAN_8,
                Barcode.FORMAT_UPC_A,
                Barcode.FORMAT_UPC_E,
                Barcode.FORMAT_CODE_128,
            )
            .build()
    )

    // Solo se tocan en el hilo principal (los listeners de ML Kit corren alli)
    private var ultimoCodigo: String? = null
    private var vistoPorUltimaVez = 0L

    @OptIn(ExperimentalGetImage::class)
    override fun analyze(cuadro: ImageProxy) {
        val imagen = cuadro.image
        if (imagen == null) {
            cuadro.close()
            return
        }
        lector.process(InputImage.fromMediaImage(imagen, cuadro.imageInfo.rotationDegrees))
            .addOnSuccessListener { codigos ->
                val valor = codigos.firstNotNullOfOrNull { it.rawValue?.trim()?.takeIf { v -> v.isNotEmpty() } }
                    ?: return@addOnSuccessListener
                val ahora = SystemClock.elapsedRealtime()
                val repetido = valor == ultimoCodigo && ahora - vistoPorUltimaVez < ESPERA_MISMO_CODIGO_MS
                ultimoCodigo = valor
                vistoPorUltimaVez = ahora
                if (!repetido) alLeer(valor)
            }
            .addOnFailureListener { Log.w("Escaner", "No se pudo leer el cuadro", it) }
            // Cada cuadro se cierra para que llegue el siguiente (STRATEGY_KEEP_ONLY_LATEST)
            .addOnCompleteListener { cuadro.close() }
    }

    /** Despues de "Escanear otro": el mismo codigo se puede volver a leer de inmediato. */
    fun olvidarUltimo() {
        ultimoCodigo = null
    }

    fun cerrar() = lector.close()

    companion object {
        const val ESPERA_MISMO_CODIGO_MS = 1500L
    }
}

/**
 * Vista de la camara trasera con la zona de lectura marcada.
 * @param activo false mientras se consulta un codigo al servidor: los cuadros se siguen viendo pero no se procesan
 */
@Composable
fun CamaraEscaner(onCodigo: (String) -> Unit, modifier: Modifier = Modifier, activo: Boolean = true) {
    val contexto = LocalContext.current
    val ciclo = LocalLifecycleOwner.current
    val alLeer by rememberUpdatedState(onCodigo)
    val leyendo by rememberUpdatedState(activo)
    var error by remember { mutableStateOf<String?>(null) }

    val vista = remember {
        PreviewView(contexto).apply {
            scaleType = PreviewView.ScaleType.FILL_CENTER
            implementationMode = PreviewView.ImplementationMode.COMPATIBLE
        }
    }
    val analizador = remember { AnalizadorCodigos { codigo -> if (leyendo) alLeer(codigo) } }

    // Si la pantalla vuelve a activar el lector (despues de consultar un codigo), se puede leer el mismo producto otra vez
    LaunchedEffect(activo) { if (activo) analizador.olvidarUltimo() }

    DisposableEffect(ciclo) {
        val hilo: ExecutorService = Executors.newSingleThreadExecutor()
        val futuro = ProcessCameraProvider.getInstance(contexto)
        val vistaPrevia = Preview.Builder().build()
        val analisis = ImageAnalysis.Builder()
            .setBackpressureStrategy(ImageAnalysis.STRATEGY_KEEP_ONLY_LATEST)
            .build()
        var proveedor: ProcessCameraProvider? = null
        var liberado = false

        futuro.addListener({
            if (liberado) return@addListener
            try {
                val p = futuro.get()
                proveedor = p
                vistaPrevia.setSurfaceProvider(vista.surfaceProvider)
                analisis.setAnalyzer(hilo, analizador)
                p.unbind(vistaPrevia, analisis)
                p.bindToLifecycle(ciclo, CameraSelector.DEFAULT_BACK_CAMERA, vistaPrevia, analisis)
                error = null
            } catch (e: Exception) {
                Log.e("Escaner", "No se pudo abrir la cámara", e)
                error = "No se pudo abrir la cámara trasera. Busca el producto por nombre o escribe el código."
            }
        }, ContextCompat.getMainExecutor(contexto))

        onDispose {
            liberado = true
            // Solo se sueltan los casos de uso de esta pantalla (otra pantalla puede estar abriendo la camara)
            proveedor?.unbind(vistaPrevia, analisis)
            analisis.clearAnalyzer()
            hilo.shutdown()
        }
    }
    DisposableEffect(Unit) { onDispose { analizador.cerrar() } }

    Box(modifier.clip(RoundedCornerShape(16.dp)).background(Color.Black)) {
        if (error != null) {
            Column(
                Modifier.fillMaxSize().padding(16.dp),
                horizontalAlignment = Alignment.CenterHorizontally,
                verticalArrangement = Arrangement.Center,
            ) {
                Icon(Icons.Filled.NoPhotography, contentDescription = null, tint = Color.White)
                Text(error!!, color = Color.White, textAlign = TextAlign.Center)
            }
        } else {
            AndroidView(factory = { vista }, modifier = Modifier.fillMaxSize())
            ZonaDeLectura(activo, Modifier.fillMaxSize())
        }
    }
}

/** Oscurece todo menos un rectangulo central con borde amarillo: ahi se pone el codigo de barras. */
@Composable
private fun ZonaDeLectura(activo: Boolean, modifier: Modifier = Modifier) {
    Box(modifier) {
        Canvas(Modifier.fillMaxSize().graphicsLayer { compositingStrategy = CompositingStrategy.Offscreen }) {
            val ancho = size.width * 0.8f
            val alto = (size.height * 0.45f).coerceAtMost(ancho * 0.6f)
            val esquina = Offset((size.width - ancho) / 2f, (size.height - alto) / 2f)
            drawRect(Color.Black.copy(alpha = 0.45f))
            drawRoundRect(Color.Transparent, esquina, Size(ancho, alto), CornerRadius(24f), blendMode = BlendMode.Clear)
            drawRoundRect(
                if (activo) Marca else Color.White.copy(alpha = 0.5f),
                esquina, Size(ancho, alto), CornerRadius(24f), style = Stroke(width = 6f),
            )
            // Linea guia horizontal
            drawLine(Color.Red.copy(alpha = 0.7f), Offset(esquina.x + 16f, size.height / 2f), Offset(esquina.x + ancho - 16f, size.height / 2f), 3f)
        }
        Text(
            if (activo) "Pon el código de barras dentro del recuadro" else "Consultando…",
            color = Color.White,
            style = MaterialTheme.typography.bodyMedium,
            textAlign = TextAlign.Center,
            modifier = Modifier.align(Alignment.BottomCenter).padding(10.dp)
                .background(Color.Black.copy(alpha = 0.55f), RoundedCornerShape(8.dp)).padding(horizontal = 10.dp, vertical = 4.dp),
        )
    }
}

/**
 * Pide el permiso de camara. Si lo niegan, explica que se puede seguir sin camara y ofrece abrir los ajustes.
 */
@Composable
fun ConPermisoDeCamara(sinPermiso: @Composable () -> Unit = {}, contenido: @Composable () -> Unit) {
    val contexto = LocalContext.current
    var concedido by remember {
        mutableStateOf(ContextCompat.checkSelfPermission(contexto, Manifest.permission.CAMERA) == PackageManager.PERMISSION_GRANTED)
    }
    var preguntado by remember { mutableStateOf(false) }
    val pedir = rememberLauncherForActivityResult(ActivityResultContracts.RequestPermission()) {
        concedido = it
        preguntado = true
    }
    LaunchedEffect(Unit) { if (!concedido) pedir.launch(Manifest.permission.CAMERA) }

    if (concedido) {
        contenido()
    } else {
        Column(
            Modifier.fillMaxWidth().padding(16.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.spacedBy(8.dp),
        ) {
            Icon(Icons.Filled.NoPhotography, contentDescription = null)
            Text(
                "Para escanear códigos la app necesita la cámara. También puedes buscar el producto por nombre o escribir el código.",
                textAlign = TextAlign.Center,
            )
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                Button(onClick = { pedir.launch(Manifest.permission.CAMERA) }) { Text("Permitir cámara") }
                if (preguntado) {
                    OutlinedButton(onClick = {
                        contexto.startActivity(
                            Intent(Settings.ACTION_APPLICATION_DETAILS_SETTINGS, Uri.fromParts("package", contexto.packageName, null))
                                .addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
                        )
                    }) { Text("Abrir ajustes") }
                }
            }
            sinPermiso()
        }
    }
}

/** Pitido corto y vibracion al leer un codigo. */
class Confirmacion {
    private val tono: ToneGenerator? = runCatching { ToneGenerator(AudioManager.STREAM_NOTIFICATION, 80) }.getOrNull()

    fun exito() {
        tono?.startTone(ToneGenerator.TONE_PROP_BEEP, 120)
    }

    fun error() {
        tono?.startTone(ToneGenerator.TONE_PROP_NACK, 250)
    }

    fun liberar() {
        tono?.release()
    }
}

@Composable
fun rememberConfirmacion(): Confirmacion {
    val confirmacion = remember { Confirmacion() }
    DisposableEffect(Unit) { onDispose { confirmacion.liberar() } }
    return confirmacion
}

/**
 * Panel reutilizable: camara + escribir el codigo a mano + buscar por nombre.
 * Lo usan Nueva venta, Entrada de stock y Consulta de producto.
 */
@Composable
fun PanelEscaner(
    onCodigo: (String) -> Unit,
    onBuscarPorNombre: () -> Unit,
    modifier: Modifier = Modifier,
    activo: Boolean = true,
    altoCamara: Int = 220,
) {
    var manual by remember { mutableStateOf("") }
    val vibrar = LocalHapticFeedback.current
    val enviarManual = {
        val codigo = manual.trim()
        if (codigo.isNotEmpty()) {
            onCodigo(codigo)
            manual = ""
        }
    }
    Column(modifier, verticalArrangement = Arrangement.spacedBy(8.dp)) {
        ConPermisoDeCamara {
            CamaraEscaner(
                onCodigo = {
                    vibrar.performHapticFeedback(HapticFeedbackType.LongPress)
                    onCodigo(it)
                },
                activo = activo,
                modifier = Modifier.fillMaxWidth().height(altoCamara.dp),
            )
        }
        Row(verticalAlignment = Alignment.CenterVertically) {
            OutlinedTextField(
                value = manual,
                onValueChange = { manual = it.filter { c -> c.isLetterOrDigit() || c == '-' }.take(40) },
                label = { Text("Escribir código") },
                singleLine = true,
                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number, imeAction = ImeAction.Done),
                keyboardActions = KeyboardActions(onDone = { enviarManual() }),
                modifier = Modifier.weight(1f),
            )
            Spacer(Modifier.width(8.dp))
            FilledTonalButton(onClick = enviarManual, enabled = activo && manual.isNotBlank()) { Text("OK") }
        }
        OutlinedButton(onClick = onBuscarPorNombre, modifier = Modifier.fillMaxWidth()) {
            Icon(Icons.Filled.Search, contentDescription = null)
            Spacer(Modifier.width(8.dp))
            Text("Buscar por nombre")
        }
    }
}
