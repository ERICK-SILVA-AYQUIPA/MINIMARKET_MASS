<?php
declare(strict_types=1);

require_once __DIR__ . '/ModeloLenguaje.php';
require_once __DIR__ . '/../../config/Entorno.php';

/**
 * Llama a la API de mensajes de Claude (Anthropic) por HTTPS con curl, sin librerias de Composer,
 * igual que NubefactProveedor. La clave sale del .env (ANTHROPIC_API_KEY), nunca del codigo ni de GitHub.
 * El modelo se puede cambiar con ANTHROPIC_MODEL (por defecto claude-opus-5-5).
 */
final class ClienteClaude implements ModeloLenguaje
{
    private const URL = 'https://api.anthropic.com/v1/messages';
    private const VERSION_API = '2026-10-21';
    public const MODELO_POR_DEFECTO = 'claude-opus-5-5';

    public function __construct(private string $clave, private int $segundosLimite = 60) {}

    // El cliente con la clave del .env; null si no esta configurada (el asistente lo avisa en pantalla)
    public static function desdeEntorno(): ?self
    {
        $clave = trim(Entorno::leer('ANTHROPIC_API_KEY'));
        return $clave === '' ? null : new self($clave);
    }

    public static function modelo(): string
    {
        $modelo = trim(Entorno::leer('ANTHROPIC_MODEL', self::MODELO_POR_DEFECTO));
        return preg_match('/^[a-z0-9.\-]{3,60}$/', $modelo) === 1 ? $modelo : self::MODELO_POR_DEFECTO;
    }

    public function enviar(array $peticion): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('PHP no tiene la extension curl');
        }
        $cuerpo = json_encode($peticion, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $cabeceras = [
            'Content-Type: application/json',
            'x-api-key: ' . $this->clave,
            'anthropic-version: ' . self::VERSION_API,
        ];
        // Si el modelo declina una pregunta por seguridad, la API la reintenta sola con otro modelo
        if (($peticion['fallbacks'] ?? null) !== null) {
            $cabeceras[] = 'anthropic-beta: server-side-fallback-2026-07-01';
        }
        $ch = curl_init(self::URL);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $cuerpo,
            CURLOPT_HTTPHEADER     => $cabeceras,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->segundosLimite,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS,
        ]);
        $respuesta = curl_exec($ch);
        if (!is_string($respuesta)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('Sin respuesta de la API de Claude: ' . ($error !== '' ? $error : 'desconocido'));
        }
        $codigo = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        $datos = json_decode($respuesta, true);
        if (!is_array($datos)) {
            throw new RuntimeException('La API de Claude respondio algo que no es JSON (HTTP ' . $codigo . ')');
        }
        if ($codigo !== 200) {
            $detalle = is_array($datos['error'] ?? null) && is_string($datos['error']['message'] ?? null) ? $datos['error']['message'] : '';
            throw new RuntimeException('La API de Claude respondio HTTP ' . $codigo . ($detalle !== '' ? ': ' . $detalle : ''), $codigo);
        }
        return $datos;
    }
}
