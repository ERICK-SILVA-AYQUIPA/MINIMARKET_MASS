<?php
declare(strict_types=1);

require_once __DIR__ . '/ModeloLenguaje.php';
require_once __DIR__ . '/Herramientas.php';

/**
 * El asistente: recibe una pregunta en español ("¿cuánto vendimos ayer?", "¿hay leche Gloria?"),
 * deja que Claude elija que herramientas usar, las ejecuta aqui con los permisos del usuario y devuelve
 * la respuesta en texto, mas los botones a las pantallas que correspondan.
 *
 * Es el ciclo de "tool use" de la API de Claude: Claude pide una herramienta -> PHP la ejecuta ->
 * se le devuelve el resultado -> Claude responde (o pide otra). Maximo VUELTAS_MAXIMO vueltas por pregunta.
 */
final class Asistente
{
    private const VUELTAS_MAXIMO = 6;
    public const TURNOS_GUARDADOS = 8;
    public const LARGO_PREGUNTA = 500;

    // Modelos que aceptan fallbacks "default" (si uno declina por seguridad, la API reintenta con otro)
    private const CON_RESPALDO = ['claude-opus-5-5', 'claude-opus-5', 'claude-fable-5-1', 'claude-sonnet-5-5'];

    private const INSTRUCCIONES = <<<'TXT'
        Eres el asistente del sistema de Minimarket Mass, una cadena de minimarkets en Arequipa, Perú.
        Ayudas a los empleados a sacar datos del sistema y a llegar rápido a la pantalla que necesitan.

        Reglas:
        - Responde siempre en español sencillo, corto y directo (2 a 6 líneas). Montos en soles: S/ 12.50.
        - Para cualquier dato (ventas, precios, stock) usa las herramientas; nunca inventes números.
        - Si una herramienta devuelve "error", explícalo con tus palabras y sugiere qué sí se puede hacer.
        - Tú no registras ni cambias nada. Si piden hacer algo (vender, registrar un ingreso, anular, cambiar un precio),
          usa abrir_pagina para darles el botón a la pantalla donde se hace y se confirma.
        - Si la pregunta no tiene que ver con el minimarket, dilo amablemente en una línea.
        - Interpreta fechas relativas (hoy, ayer, esta semana, este mes, el mes pasado) con la fecha de hoy que se te da.
        - Puedes usar listas con guiones. No uses tablas ni títulos.
        TXT;

    public function __construct(
        private ModeloLenguaje $modelo,
        private Herramientas $herramientas,
        private string $nombreModelo,
        private DateTimeImmutable $hoy = new DateTimeImmutable('now'),
    ) {}

    /**
     * @param array<int, array{rol: string, texto: string}> $historial las preguntas y respuestas anteriores (solo texto)
     * @return array{respuesta: string, enlaces: array<int, array{texto: string, url: string}>, ok: bool}
     */
    public function preguntar(string $pregunta, array $historial = []): array
    {
        $pregunta = trim(mb_substr($pregunta, 0, self::LARGO_PREGUNTA));
        if ($pregunta === '') {
            return ['respuesta' => 'Escribe o dicta tu pregunta.', 'enlaces' => [], 'ok' => false];
        }
        $mensajes = [];
        foreach (array_slice($historial, -self::TURNOS_GUARDADOS * 2) as $turno) {
            $rol = $turno['rol'] === 'assistant' ? 'assistant' : 'user';
            // La API pide que empiece el usuario y que los roles se alternen
            if (($mensajes === [] && $rol !== 'user') || ($mensajes !== [] && end($mensajes)['role'] === $rol)) {
                continue;
            }
            $mensajes[] = ['role' => $rol, 'content' => $turno['texto']];
        }
        if ($mensajes !== [] && end($mensajes)['role'] === 'user') {
            array_pop($mensajes);
        }
        $mensajes[] = ['role' => 'user', 'content' => $pregunta];

        $peticion = [
            'model'         => $this->nombreModelo,
            'max_tokens'    => 4000,
            'system'        => self::INSTRUCCIONES . "\n\n" . $this->herramientas->contexto()
                . ' Hoy es ' . $this->hoy->format('Y-m-d') . ' (' . self::DIAS[(int) $this->hoy->format('w')] . ').',
            'tools'         => $this->herramientas->definiciones(),
            // Preguntas cortas de chat: poco razonamiento = respuesta mas rapida y barata
            'output_config' => ['effort' => 'low'],
        ];
        if (in_array($this->nombreModelo, self::CON_RESPALDO, true)) {
            $peticion['fallbacks'] = 'default';
        }

        for ($vuelta = 0; $vuelta < self::VUELTAS_MAXIMO; $vuelta++) {
            $respuesta = $this->modelo->enviar($peticion + ['messages' => $mensajes]);
            $contenido = is_array($respuesta['content'] ?? null) ? $respuesta['content'] : [];
            $motivo = $respuesta['stop_reason'] ?? null;

            if ($motivo === 'refusal') {
                return $this->fin('No puedo ayudarte con esa pregunta. Prueba con algo del minimarket: ventas, precios o stock.', false);
            }
            if ($motivo !== 'tool_use') {
                $texto = trim(implode("\n", array_map(
                    fn(array $b): string => (string) $b['text'],
                    array_filter($contenido, fn(mixed $b): bool => is_array($b) && ($b['type'] ?? '') === 'text' && is_string($b['text'] ?? null))
                )));
                return $this->fin($texto !== '' ? $texto : 'No encontré una respuesta. ¿Puedes preguntarlo de otra forma?', $texto !== '');
            }

            // Se devuelve el turno del asistente tal cual (con su razonamiento) y, en UN solo mensaje,
            // el resultado de cada herramienta que pidio
            // (un input vacio se decodifico como [] y debe volver como {})
            $mensajes[] = ['role' => 'assistant', 'content' => array_map(
                fn(mixed $b): mixed => is_array($b) && ($b['type'] ?? '') === 'tool_use' && ($b['input'] ?? null) === []
                    ? ['input' => new stdClass()] + $b : $b,
                $contenido
            )];
            $resultados = [];
            foreach ($contenido as $bloque) {
                if (!is_array($bloque) || ($bloque['type'] ?? '') !== 'tool_use') {
                    continue;
                }
                $entrada = is_array($bloque['input'] ?? null) ? $bloque['input'] : [];
                try {
                    $resultado = $this->herramientas->ejecutar((string) ($bloque['name'] ?? ''), $entrada);
                } catch (Throwable $ex) {
                    error_log('Minimarket Mass: asistente, herramienta ' . ($bloque['name'] ?? '?') . ': ' . $ex->getMessage());
                    $resultado = ['error' => 'La consulta falló en el sistema.'];
                }
                $resultados[] = [
                    'type'        => 'tool_result',
                    'tool_use_id' => (string) ($bloque['id'] ?? ''),
                    'content'     => json_encode($resultado, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR) ?: '{}',
                ] + (isset($resultado['error']) ? ['is_error' => true] : []);
            }
            if ($resultados === []) {
                return $this->fin('No encontré una respuesta. ¿Puedes preguntarlo de otra forma?', false);
            }
            $mensajes[] = ['role' => 'user', 'content' => $resultados];
        }
        return $this->fin('La pregunta necesitó demasiados pasos. Prueba a hacerla más concreta.', false);
    }

    private const DIAS = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];

    /** @return array{respuesta: string, enlaces: array<int, array{texto: string, url: string}>, ok: bool} */
    private function fin(string $texto, bool $ok): array
    {
        return ['respuesta' => $texto, 'enlaces' => $this->herramientas->enlaces(), 'ok' => $ok];
    }
}
