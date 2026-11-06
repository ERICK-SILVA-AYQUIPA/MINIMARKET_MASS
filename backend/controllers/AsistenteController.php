<?php
declare(strict_types=1);

require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../models/asistente/Asistente.php';
require_once __DIR__ . '/../models/asistente/ClienteClaude.php';
require_once __DIR__ . '/../models/asistente/HerramientasAsistente.php';

/**
 * C: el asistente inteligente. Cualquier empleado le pregunta en español (escribiendo o con el microfono)
 * y responde con datos del sistema: ventas, precios, stock, lo que falta reponer. Lo que cada uno puede
 * ver lo deciden sus permisos (HerramientasAsistente), igual que en el resto de la pagina.
 *
 * Funciona sin JavaScript (el formulario recarga la pagina) y con JavaScript (app.js lo envia con fetch
 * y recibe JSON, sin recargar). Necesita ANTHROPIC_API_KEY en el .env; si no esta, lo dice en pantalla.
 */
class AsistenteController extends Controller
{
    // Tope de preguntas por sesion: cada una se paga a la API de Claude
    private const PREGUNTAS_MAXIMO = 20;
    private const PREGUNTAS_SEGUNDOS = 600;

    public const SUGERENCIAS = [
        '¿Cuánto vendimos hoy?',
        '¿Qué productos tienen stock bajo?',
        '¿Cuál es el precio de la leche Gloria?',
        '¿Cuáles fueron los 5 productos más vendidos esta semana?',
        'Quiero hacer una venta',
    ];

    /** @param Closure(Usuario): Herramientas $herramientas arma las herramientas con los permisos de quien pregunta */
    public function __construct(
        TiendaRepository $tiendas,
        Sesion $sesion,
        ?Usuario $usuario,
        private ?ModeloLenguaje $modelo,
        private Closure $herramientas,
    ) {
        parent::__construct($tiendas, $sesion, $usuario);
    }

    // GET /?ruta=asistente
    public function chat(): void
    {
        $this->vista('asistente/chat', [
            'titulo'       => 'Asistente',
            'conversacion' => $this->sesion->conversacionAsistente(),
            'configurado'  => $this->modelo !== null,
            'sugerencias'  => $this->sugerencias(),
        ]);
    }

    // POST /?ruta=asistente_preguntar (pregunta). Con $comoJson responde JSON a app.js; si no, vuelve al chat.
    public function preguntar(array $post, bool $comoJson): void
    {
        $pregunta = trim(mb_substr($this->campo($post, 'pregunta'), 0, Asistente::LARGO_PREGUNTA));
        $resultado = $this->responder($pregunta);
        if ($resultado['guardar']) {
            $conversacion = $this->sesion->conversacionAsistente();
            $conversacion[] = ['rol' => 'user', 'texto' => $pregunta, 'enlaces' => []];
            $conversacion[] = ['rol' => 'assistant', 'texto' => $resultado['respuesta'], 'enlaces' => $resultado['enlaces']];
            $this->sesion->guardarConversacionAsistente($conversacion, Asistente::TURNOS_GUARDADOS * 2);
        }
        if ($comoJson) {
            header('Content-Type: application/json; charset=UTF-8');
            header('Cache-Control: no-store');
            echo json_encode(['ok' => $resultado['ok'], 'respuesta' => $resultado['respuesta'], 'enlaces' => $resultado['enlaces']],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }
        if (!$resultado['guardar']) {
            $this->sesion->avisar('error', $resultado['respuesta']);
        }
        header('Location: /?ruta=asistente#ultima', true, 303);
    }

    // POST /?ruta=asistente_borrar: empezar una conversacion nueva
    public function borrar(): void
    {
        $this->sesion->guardarConversacionAsistente([], 0);
        $this->redirigir('/?ruta=asistente', 'Conversación borrada.');
    }

    /** @return array{ok: bool, respuesta: string, enlaces: array<int, array{texto: string, url: string}>, guardar: bool} */
    private function responder(string $pregunta): array
    {
        if ($this->modelo === null) {
            return $this->aviso('El asistente aún no está configurado: falta ANTHROPIC_API_KEY en el archivo .env del servidor.');
        }
        if ($pregunta === '') {
            return $this->aviso('Escribe o dicta tu pregunta.');
        }
        if ($this->sesion->asistenteSaturado(self::PREGUNTAS_MAXIMO, self::PREGUNTAS_SEGUNDOS)) {
            return $this->aviso('Hiciste muchas preguntas seguidas. Espera unos minutos y vuelve a intentar.');
        }
        $historial = array_map(fn(array $t): array => ['rol' => $t['rol'], 'texto' => $t['texto']],
            $this->sesion->conversacionAsistente());
        $asistente = new Asistente($this->modelo, ($this->herramientas)($this->usuario), ClienteClaude::modelo());
        try {
            $r = $asistente->preguntar($pregunta, $historial);
        } catch (RuntimeException $ex) {
            error_log('Minimarket Mass: asistente: ' . $ex->getMessage());
            return $this->aviso(match ($ex->getCode()) {
                401, 403 => 'La clave del asistente (ANTHROPIC_API_KEY) no es válida. Avísale al administrador.',
                429      => 'El asistente está ocupado. Espera un minuto y vuelve a preguntar.',
                default  => 'No pude conectarme con el asistente. Revisa la conexión a internet e inténtalo otra vez.',
            });
        }
        return $r + ['guardar' => true];
    }

    /** @return array{ok: bool, respuesta: string, enlaces: array<int, array{texto: string, url: string}>, guardar: bool} */
    private function aviso(string $texto): array
    {
        return ['ok' => false, 'respuesta' => $texto, 'enlaces' => [], 'guardar' => false];
    }

    /** @return string[] las sugerencias que tienen sentido para este rol */
    private function sugerencias(): array
    {
        $puedeReportes = $this->usuario?->puedeAdministrarTienda($this->usuario->getIdTienda()) === true;
        return array_values(array_filter(self::SUGERENCIAS, fn(string $s): bool =>
            ($puedeReportes || !preg_match('/vendimos|más vendidos/u', $s))
            && ($this->usuario?->puedeVender() === true || $s !== 'Quiero hacer una venta')));
    }
}
