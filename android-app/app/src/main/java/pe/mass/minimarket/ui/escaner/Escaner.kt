package pe.mass.minimarket.ui.escaner

import android.Manifest
import android.content.Intent
import android.content.pm.PackageManager
import android.media.AudioManager
import android.media.ToneGenerator
import android.net.Uri
import android.os.Build
import android.os.SystemClock
import android.provider.Settings
import android.util.Log
import android.view.HapticFeedbackConstants
import android.view.View
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.annotation.OptIn
import androidx.camera.core.Camera
import androidx.camera.core.CameraSelector
import androidx.camera.core.ExperimentalGetImage
import androidx.camera.core.FocusMeteringAction
import androidx.camera.core.ImageAnalysis
import androidx.camera.core.ImageProxy
import androidx.camera.core.Preview
import androidx.camera.lifecycle.ProcessCameraProvider
import androidx.camera.view.PreviewView
import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.LinearEasing
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.gestures.detectTapGestures
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.Send
import androidx.compose.material.icons.filled.FlashOff
import androidx.compose.material.icons.filled.FlashOn
import androidx.compose.material.icons.filled.NoPhotography
import androidx.compose.material.icons.filled.Search
import androidx.compose.material3.Button
import androidx.compose.material3.FilledTonalButton
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
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
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.CompositingStrategy
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.input.pointer.pointerInput
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
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
import kotlinx.coroutines.delay
import pe.mass.minimarket.data.Ajustes
import pe.mass.minimarket.dominio.CodigoLeido
import pe.mass.minimarket.dominio.Codigos
import pe.mass.minimarket.ui.comun.Aviso
import pe.mass.minimarket.ui.comun.TarjetaAviso
import pe.mass.minimarket.ui.comun.TipoAviso
import pe.mass.minimarket.ui.comun.preferencias
import pe.mass.minimarket.ui.tema.Marca
import java.util.concurrent.ExecutorService
import java.util.concurrent.Executors

/**
 * Lee codigos de barras de cada cuadro de la camara con ML Kit (sin tomar foto).
 * Formatos de productos: EAN-13, EAN-8, UPC-A, UPC-E y Code 128, y QR o Data Matrix con el codigo GS1
 * del producto adentro (los empaques nuevos). Que significa lo leido lo decide [Codigos.interpretar].
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
                Barcode.FORMAT_QR_CODE,
                Barcode.FORMAT_DATA_MATRIX,
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
                val leidos = codigos.mapNotNull { it.rawValue?.trim()?.takeIf { v -> v.isNotEmpty() } }
                // Si en el cuadro hay un QR de publicidad y el codigo de barras, gana el del producto
                val valor = leidos.firstOrNull { Codigos.interpretar(it) is CodigoLeido.Producto } ?: leidos.firstOrNull()
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
 * Arriba a la derecha: linterna (pasillos oscuros, trastienda) y acercar 2× (codigos pequenos).
 * Tocar la imagen enfoca en ese punto.
 * @param activo false mientras se consulta un codigo al servidor: los cuadros se siguen viendo pero no se procesan
 */
@Composable
fun CamaraEscaner(onCodigo: (String) -> Unit, modifier: Modifier = Modifier, activo: Boolean = true) {
    val contexto = LocalContext.current
    val ciclo = LocalLifecycleOwner.current
    val alLeer by rememberUpdatedState(onCodigo)
    val leyendo by rememberUpdatedState(activo)
    var error by remember { mutableStateOf<String?>(null) }
    var camara by remember { mutableStateOf<Camera?>(null) }
    var linterna by remember { mutableStateOf(false) }
    var acercar by remember { mutableStateOf(false) }
    var lecturas by remember { mutableIntStateOf(0) }

    val vista = remember {
        PreviewView(contexto).apply {
            scaleType = PreviewView.ScaleType.FILL_CENTER
            implementationMode = PreviewView.ImplementationMode.COMPATIBLE
        }
    }
    val analizador = remember {
        AnalizadorCodigos { codigo ->
            if (leyendo) {
                lecturas++
                alLeer(codigo)
            }
        }
    }

    // Si la pantalla vuelve a activar el lector (despues de consultar un codigo), se puede leer el mismo producto otra vez
    LaunchedEffect(activo) { if (activo) analizador.olvidarUltimo() }
    LaunchedEffect(camara, linterna) { camara?.cameraControl?.enableTorch(linterna) }
    LaunchedEffect(camara, acercar) { camara?.cameraControl?.setZoomRatio(if (acercar) 2f else 1f) }

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
                camara = p.bindToLifecycle(ciclo, CameraSelector.DEFAULT_BACK_CAMERA, vistaPrevia, analisis)
                error = null
            } catch (e: Exception) {
                Log.e("Escaner", "No se pudo abrir la cámara", e)
                error = "No se pudo abrir la cámara trasera. Busca el producto por nombre o escribe el código."
            }
        }, ContextCompat.getMainExecutor(contexto))

        onDispose {
            liberado = true
            camara = null
            // Solo se sueltan los casos de uso de esta pantalla (otra pantalla puede estar abriendo la camara)
            proveedor?.unbind(vistaPrevia, analisis)
            analisis.clearAnalyzer()
            hilo.shutdown()
        }
    }
    DisposableEffect(Unit) { onDispose { analizador.cerrar() } }

    Box(modifier.clip(MaterialTheme.shapes.large).background(Color.Black)) {
        if (error != null) {
            Column(
                Modifier.fillMaxSize().padding(16.dp),
                horizontalAlignment = Alignment.CenterHorizontally,
                verticalArrangement = Arrangement.Center,
            ) {
                Icon(Icons.Filled.NoPhotography, contentDescription = null, tint = Color.White)
                Spacer(Modifier.height(8.dp))
                Text(error!!, color = Color.White, textAlign = TextAlign.Center)
            }
        } else {
            AndroidView(factory = { vista }, modifier = Modifier.fillMaxSize())
            ZonaDeLectura(
                activo = activo,
                lecturas = lecturas,
                modifier = Modifier.fillMaxSize().pointerInput(camara) {
                    // Tocar para enfocar donde esta el codigo
                    detectTapGestures { punto ->
                        val c = camara ?: return@detectTapGestures
                        val medida = vista.meteringPointFactory.createPoint(punto.x, punto.y)
                        c.cameraControl.startFocusAndMetering(FocusMeteringAction.Builder(medida).build())
                    }
                },
            )
            val tieneFlash = camara?.cameraInfo?.hasFlashUnit() == true
            Row(Modifier.align(Alignment.TopEnd).padding(8.dp), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                BotonSobreCamara(onClick = { acercar = !acercar }, descripcion = if (acercar) "Alejar" else "Acercar", encendido = acercar) {
                    Text(if (acercar) "2×" else "1×", style = MaterialTheme.typography.labelLarge)
                }
                if (tieneFlash) {
                    BotonSobreCamara(
                        onClick = { linterna = !linterna },
                        descripcion = if (linterna) "Apagar linterna" else "Encender linterna",
                        encendido = linterna,
                    ) {
                        Icon(if (linterna) Icons.Filled.FlashOn else Icons.Filled.FlashOff, contentDescription = null)
                    }
                }
            }
        }
    }
}

/** Boton redondo semitransparente sobre la imagen de la camara. */
@Composable
private fun BotonSobreCamara(onClick: () -> Unit, descripcion: String, encendido: Boolean, contenido: @Composable () -> Unit) {
    Surface(
        onClick = onClick,
        shape = CircleShape,
        color = if (encendido) Marca else Color.Black.copy(alpha = 0.5f),
        contentColor = if (encendido) Color(0xFF16202B) else Color.White,
        modifier = Modifier.size(44.dp).semantics { contentDescription = descripcion },
    ) {
        Box(contentAlignment = Alignment.Center) { contenido() }
    }
}

/**
 * Oscurece todo menos un rectangulo central con esquinas amarillas; una linea recorre la zona mientras lee.
 * Cada lectura hace un destello blanco corto: se ve que la camara tomo el codigo aunque no haya sonido.
 */
@Composable
private fun ZonaDeLectura(activo: Boolean, lecturas: Int, modifier: Modifier = Modifier) {
    val barrido = rememberInfiniteTransition(label = "barrido")
    val posicion by barrido.animateFloat(
        initialValue = 0.08f,
        targetValue = 0.92f,
        animationSpec = infiniteRepeatable(tween(1400, easing = LinearEasing), RepeatMode.Reverse),
        label = "posicion",
    )
    val destello = remember { Animatable(0f) }
    LaunchedEffect(lecturas) {
        if (lecturas > 0) {
            destello.snapTo(1f)
            destello.animateTo(0f, tween(450))
        }
    }

    Box(modifier) {
        Canvas(Modifier.fillMaxSize().graphicsLayer { compositingStrategy = CompositingStrategy.Offscreen }) {
            val ancho = size.width * 0.8f
            val alto = (size.height * 0.5f).coerceAtMost(ancho * 0.62f)
            val esquina = Offset((size.width - ancho) / 2f, (size.height - alto) / 2f)
            val radio = CornerRadius(28f)
            drawRect(Color.Black.copy(alpha = 0.5f))
            drawRoundRect(Color.Transparent, esquina, Size(ancho, alto), radio, blendMode = BlendMode.Clear)
            // Destello al leer
            if (destello.value > 0f) {
                drawRoundRect(Color.White.copy(alpha = 0.35f * destello.value), esquina, Size(ancho, alto), radio)
            }
            val color = if (activo) Marca else Color.White.copy(alpha = 0.5f)
            // Solo las cuatro esquinas del recuadro (mas limpio que el borde completo)
            val largo = minOf(ancho, alto) * 0.18f
            val grosor = 8f
            val x0 = esquina.x
            val y0 = esquina.y
            val x1 = esquina.x + ancho
            val y1 = esquina.y + alto
            listOf(
                Offset(x0, y0) to Offset(x0 + largo, y0), Offset(x0, y0) to Offset(x0, y0 + largo),
                Offset(x1, y0) to Offset(x1 - largo, y0), Offset(x1, y0) to Offset(x1, y0 + largo),
                Offset(x0, y1) to Offset(x0 + largo, y1), Offset(x0, y1) to Offset(x0, y1 - largo),
                Offset(x1, y1) to Offset(x1 - largo, y1), Offset(x1, y1) to Offset(x1, y1 - largo),
            ).forEach { (a, b) -> drawLine(color, a, b, grosor, cap = StrokeCap.Round) }
            drawRoundRect(color.copy(alpha = 0.35f), esquina, Size(ancho, alto), radio, style = Stroke(width = 2f))
            // Linea de lectura que sube y baja
            if (activo) {
                val y = esquina.y + alto * posicion
                drawLine(
                    Brush.horizontalGradient(
                        listOf(Color.Transparent, Marca, Color.Transparent),
                        startX = x0 + 12f,
                        endX = x1 - 12f,
                    ),
                    Offset(x0 + 12f, y),
                    Offset(x1 - 12f, y),
                    strokeWidth = 4f,
                )
            }
        }
        Text(
            if (activo) "Apunta al código de barras o al QR" else "Consultando…",
            color = Color.White,
            style = MaterialTheme.typography.labelLarge,
            textAlign = TextAlign.Center,
            modifier = Modifier.align(Alignment.BottomCenter).padding(12.dp)
                .background(Color.Black.copy(alpha = 0.55f), RoundedCornerShape(50)).padding(horizontal = 14.dp, vertical = 6.dp),
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
        Surface(color = MaterialTheme.colorScheme.surfaceContainerHigh, shape = MaterialTheme.shapes.large, modifier = Modifier.fillMaxWidth()) {
            Column(
                Modifier.fillMaxWidth().padding(20.dp),
                horizontalAlignment = Alignment.CenterHorizontally,
                verticalArrangement = Arrangement.spacedBy(10.dp),
            ) {
                Icon(Icons.Filled.NoPhotography, contentDescription = null, modifier = Modifier.size(36.dp))
                Text(
                    "Para escanear códigos la app necesita la cámara. También puedes buscar el producto por nombre o escribir el código.",
                    textAlign = TextAlign.Center,
                    style = MaterialTheme.typography.bodyMedium,
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
}

/**
 * Pitido corto y vibracion al leer un codigo: uno para "encontrado" y otro distinto para "no se pudo".
 * Respeta lo elegido en Mi perfil → Ajustes (sonido y vibracion).
 */
class Confirmacion(private val vista: View, private val ajustes: () -> Ajustes) {
    private val tono: ToneGenerator? = runCatching { ToneGenerator(AudioManager.STREAM_NOTIFICATION, 80) }.getOrNull()

    fun exito() {
        val a = ajustes()
        if (a.sonido) tono?.startTone(ToneGenerator.TONE_PROP_BEEP, 120)
        if (a.vibracion) vista.performHapticFeedback(if (Build.VERSION.SDK_INT >= 30) HapticFeedbackConstants.CONFIRM else HapticFeedbackConstants.VIRTUAL_KEY)
    }

    fun error() {
        val a = ajustes()
        if (a.sonido) tono?.startTone(ToneGenerator.TONE_PROP_NACK, 250)
        if (a.vibracion) vista.performHapticFeedback(if (Build.VERSION.SDK_INT >= 30) HapticFeedbackConstants.REJECT else HapticFeedbackConstants.LONG_PRESS)
    }

    /** Toque suave (botones de sumar o restar, quitar del carrito). */
    fun toque() {
        if (ajustes().vibracion) vista.performHapticFeedback(HapticFeedbackConstants.KEYBOARD_TAP)
    }

    fun liberar() {
        tono?.release()
    }
}

@Composable
fun rememberConfirmacion(): Confirmacion {
    val vista = LocalView.current
    val prefs = preferencias()
    val confirmacion = remember(vista) { Confirmacion(vista) { prefs.ajustes.value } }
    DisposableEffect(confirmacion) { onDispose { confirmacion.liberar() } }
    return confirmacion
}

/**
 * Panel reutilizable: camara + escribir el codigo a mano + buscar por nombre.
 * Lo usan Nueva venta, Entrada de stock, Consulta de producto y Agregar producto.
 * [onCodigo] recibe solo numeros de producto: un QR que no es de un producto se avisa aqui y no llega a la pantalla.
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
    var noEsProducto by remember { mutableStateOf<String?>(null) }
    val confirmacion = rememberConfirmacion()
    val leer: (String) -> Unit = { valor ->
        when (val leido = Codigos.interpretar(valor)) {
            is CodigoLeido.Producto -> {
                noEsProducto = null
                onCodigo(leido.codigo)
            }
            is CodigoLeido.NoEsProducto -> {
                noEsProducto = leido.motivo
                confirmacion.error()
            }
        }
    }
    val enviarManual = {
        val codigo = manual.trim()
        if (codigo.isNotEmpty()) {
            leer(codigo)
            manual = ""
        }
    }
    // El aviso del QR se borra solo, para no tapar el siguiente producto
    LaunchedEffect(noEsProducto) {
        if (noEsProducto != null) {
            delay(4_000)
            noEsProducto = null
        }
    }
    Column(modifier, verticalArrangement = Arrangement.spacedBy(10.dp)) {
        ConPermisoDeCamara {
            CamaraEscaner(
                onCodigo = leer,
                activo = activo,
                modifier = Modifier.fillMaxWidth().height(altoCamara.dp),
            )
        }
        TarjetaAviso(noEsProducto?.let { Aviso(it, TipoAviso.ALERTA) }, onCerrar = { noEsProducto = null })
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            OutlinedTextField(
                value = manual,
                onValueChange = { manual = it.filter { c -> c.isLetterOrDigit() || c == '-' }.take(40) },
                placeholder = { Text("Escribir código") },
                singleLine = true,
                shape = RoundedCornerShape(50),
                trailingIcon = {
                    IconButton(onClick = enviarManual, enabled = activo && manual.isNotBlank()) {
                        Icon(Icons.AutoMirrored.Filled.Send, contentDescription = "Buscar este código")
                    }
                },
                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number, imeAction = ImeAction.Done),
                keyboardActions = KeyboardActions(onDone = { enviarManual() }),
                modifier = Modifier.weight(1f),
            )
            FilledTonalButton(onClick = onBuscarPorNombre, modifier = Modifier.heightIn(min = 56.dp), shape = RoundedCornerShape(50)) {
                Icon(Icons.Filled.Search, contentDescription = null)
                Spacer(Modifier.width(6.dp))
                Text("Nombre")
            }
        }
    }
}
