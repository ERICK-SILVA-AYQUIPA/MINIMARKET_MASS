<?php
declare(strict_types=1);

require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../models/asistente/Asistente.php';
require_once __DIR__ . '/../models/asistente/ClienteClaude.php';
require_once __DIR__ . '/../models/asistente/HerramientasAsistente.php';
require_once __DIR__ . '/../models/asistente/RespuestasRapidas.php';

/**
 * C: el asistente inteligente. Cualquier empleado le pregunta en español (escribiendo o con el microfono)
 * y responde con datos del sistema: ventas, precios, stock, lo que falta reponer. Lo que cada uno puede
 * ver lo deciden sus permisos (HerramientasAsistente), igual que en el resto de la pagina.
 *
 * Las preguntas predeterminadas (botones) se responden al instante desde la base, sin IA ni internet
 * (RespuestasRapidas). Lo escrito libre lo responde Claude si el .env tiene ANTHROPIC_API_KEY; sin clave (o si
 * Claude no responde) se entiende por palabras clave o se busca como producto.
 *
 * Funciona sin JavaScript (el formulario recarga la pagina) y con JavaScript (app.js lo envia con fetch
 * y recibe JSON, sin recargar).
 */
class AsistenteController extends Controller
{
    // Tope de preguntas a Claude por sesion: cada una se paga a la API (las predeterminadas no cuentan)
    private const PREGUNTAS_MAXIMO = 20;
    private const PREGUNTAS_SEGUNDOS = 600;

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
            'conIa'        => $this->modelo !== null,
            'preguntas'    => $this->rapidas()->disponibles(),
        ]);
    }

    // POST /?ruta=asistente_preguntar (rapida = clave de una pregunta predeterminada, o pregunta = texto libre).
    // Con $comoJson responde JSON a app.js; si no, vuelve al chat.
    public function preguntar(array $post, bool $comoJson): void
    {
        $rapidas = $this->rapidas();
        $clave = $this->campo($post, 'rapida');
        $pregunta = $clave !== '' ? ($rapidas->texto($clave) ?? '') : trim(mb_substr($this->campo($post, 'pregunta'), 0, Asistente::LARGO_PREGUNTA));
        $resultado = $clave !== ''
            ? ($pregunta === '' ? $this->aviso('Esa pregunta no está disponible para tu usuario.') : $rapidas->responder($clave) + ['guardar' => true])
            : $this->responder($pregunta, $rapidas);
        if ($resultado['guardar']) {
            $conversacion = $this->sesion->conversacionAsistente();
            $conversacion[] = ['rol' => 'user', 'texto' => $pregunta, 'enlaces' => []];
            $conversacion[] = ['rol' => 'assistant', 'texto' => $resultado['respuesta'], 'enlaces' => $resultado['enlaces']];
            $this->sesion->guardarConversacionAsistente($conversacion, Asistente::TURNOS_GUARDADOS * 2);
        }
        if ($comoJson) {
            header('Content-Type: application/json; charset=UTF-8');
            header('Cache-Control: no-store');
            echo json_encode(['ok' => $resultado['ok'], 'pregunta' => $pregunta, 'respuesta' => $resultado['respuesta'],
                'enlaces' => $resultado['enlaces']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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

    private function rapidas(): RespuestasRapidas
    {
        return new RespuestasRapidas(($this->herramientas)($this->usuario));
    }

    /** @return array{ok: bool, respuesta: string, enlaces: array<int, array{texto: string, url: string}>, guardar: bool} */
    private function responder(string $pregunta, RespuestasRapidas $rapidas): array
    {
        if ($pregunta === '') {
            return $this->aviso('Escribe o dicta tu pregunta.');
        }
        // Sin clave de IA: palabras clave y busqueda de productos, desde la base
        if ($this->modelo === null) {
            return $rapidas->interpretar($pregunta) + ['guardar' => true];
        }
        if ($this->sesion->asistenteSaturado(self::PREGUNTAS_MAXIMO, self::PREGUNTAS_SEGUNDOS)) {
            return $this->aviso('Hiciste muchas preguntas seguidas. Usa los botones de preguntas rápidas o espera unos minutos.');
        }
        $historial = array_map(fn(array $t): array => ['rol' => $t['rol'], 'texto' => $t['texto']],
            $this->sesion->conversacionAsistente());
        $asistente = new Asistente($this->modelo, ($this->herramientas)($this->usuario), ClienteClaude::modelo());
        try {
            return $asistente->preguntar($pregunta, $historial) + ['guardar' => true];
        } catch (RuntimeException $ex) {
            error_log('Minimarket Mass: asistente: ' . $ex->getMessage());
            // Sin internet, clave mala o API ocupada: igual se responde lo que se pueda desde la base
            $local = $rapidas->interpretar($pregunta);
            if ($local['ok']) {
                return $local + ['guardar' => true];
            }
            return $this->aviso(match ($ex->getCode()) {
                401, 403 => 'La clave del asistente (ANTHROPIC_API_KEY) no es válida. Usa las preguntas rápidas o avísale al administrador.',
                429      => 'El asistente está ocupado. Usa las preguntas rápidas o vuelve a preguntar en un minuto.',
                default  => 'No pude conectarme con la inteligencia artificial. Usa las preguntas rápidas, que funcionan sin internet.',
            });
        }
    }

    /** @return array{ok: bool, respuesta: string, enlaces: array<int, array{texto: string, url: string}>, guardar: bool} */
    private function aviso(string $texto): array
    {
        return ['ok' => false, 'respuesta' => $texto, 'enlaces' => [], 'guardar' => false];
    }
}
