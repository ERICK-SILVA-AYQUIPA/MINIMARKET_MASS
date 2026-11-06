<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/Entorno.php';

/**
 * Manda un correo por SMTP (Gmail u otro), sin librerias: texto + HTML y archivos adjuntos (el PDF del comprobante).
 * Se configura en el .env (CORREO_*). Gmail pide una "contraseña de aplicación", no la clave normal de la cuenta.
 */
final class CorreoSmtp
{
    private const ESPERA_SEGUNDOS = 15;

    /** @var resource|null */
    private $conexion = null;

    public function __construct(
        private string $servidor,
        private int $puerto,
        private string $usuario,
        private string $clave,
        private string $nombreRemitente,
        private string $cifrado = 'tls'   // tls (STARTTLS, puerto 587), ssl (puerto 465) o ninguno (Mailpit de prueba)
    ) {
    }

    // null si falta el servidor, el usuario o la clave: la pagina ofrece abrir Gmail en el navegador
    public static function desdeEntorno(): ?self
    {
        $servidor = trim(Entorno::leer('CORREO_SMTP_HOST'));
        $usuario = trim(Entorno::leer('CORREO_USUARIO'));
        $clave = str_replace(' ', '', Entorno::leer('CORREO_CLAVE'));   // Gmail la muestra en grupos de 4 letras
        if ($servidor === '' || $usuario === '' || $clave === '') {
            return null;
        }
        $puerto = (int) Entorno::leer('CORREO_SMTP_PUERTO', '587');
        $puerto = $puerto > 0 ? $puerto : 587;
        $cifrado = strtolower(Entorno::leer('CORREO_SMTP_CIFRADO', $puerto === 465 ? 'ssl' : 'tls'));
        return new self($servidor, $puerto, $usuario, $clave,
            Entorno::leer('CORREO_REMITENTE', Entorno::leer('EMPRESA_NOMBRE_COMERCIAL', 'TIENDA MASS')),
            in_array($cifrado, ['tls', 'ssl', 'ninguno'], true) ? $cifrado : 'tls');
    }

    /**
     * @param array<string, string> $adjuntos nombre del archivo => contenido (PDF)
     * @throws RuntimeException si el servidor no responde o rechaza el correo (el mensaje no trae la clave)
     */
    public function enviar(string $para, string $asunto, string $texto, string $html, array $adjuntos = []): void
    {
        $mensaje = self::armarMensaje($this->usuario, $this->nombreRemitente, $para, $asunto, $texto, $html, $adjuntos);
        try {
            $this->conectar();
            $this->comando('EHLO minimarket-mass', 250);
            if ($this->cifrado === 'tls') {
                $this->comando('STARTTLS', 220);
                if (stream_socket_enable_crypto($this->conexion, true, STREAM_CRYPTO_METHOD_TLS_CLIENT) !== true) {
                    throw new RuntimeException('No se pudo cifrar la conexión con el servidor de correo.');
                }
                $this->comando('EHLO minimarket-mass', 250);
            }
            $this->comando('AUTH LOGIN', 334);
            $this->comando(base64_encode($this->usuario), 334);
            $this->comando(base64_encode($this->clave), 235, 'El servidor de correo no aceptó el usuario o la clave (en Gmail usa una contraseña de aplicación).');
            $this->comando('MAIL FROM:<' . $this->usuario . '>', 250);
            $this->comando('RCPT TO:<' . $para . '>', [250, 251]);
            $this->comando('DATA', 354);
            // Una linea que empieza con punto se dobla (si no, el servidor creeria que termino el mensaje)
            $this->comando(preg_replace('/^\./m', '..', $mensaje) . "\r\n.", 250);
            $this->comando('QUIT', 221);
        } finally {
            if (is_resource($this->conexion)) {
                fclose($this->conexion);
            }
            $this->conexion = null;
        }
    }

    /**
     * El correo completo (cabeceras + cuerpo MIME), con lineas CRLF.
     * @param array<string, string> $adjuntos
     */
    public static function armarMensaje(string $de, string $nombre, string $para, string $asunto, string $texto, string $html, array $adjuntos = []): string
    {
        $limite = 'mass-' . bin2hex(random_bytes(8));
        $alternativa = 'alt-' . bin2hex(random_bytes(8));
        $b64 = fn(string $contenido): string => rtrim(chunk_split(base64_encode($contenido), 76, "\r\n"));
        $cabeceras = [
            'Date: ' . date('r'),
            'From: ' . mb_encode_mimeheader($nombre, 'UTF-8', 'B') . ' <' . $de . '>',
            'To: <' . $para . '>',
            'Subject: ' . mb_encode_mimeheader($asunto, 'UTF-8', 'B'),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . (explode('@', $de)[1] ?? 'minimarket-mass') . '>',
            'MIME-Version: 1.0',
            'Content-Type: multipart/mixed; boundary="' . $limite . '"',
        ];
        $partes = [
            '--' . $limite,
            'Content-Type: multipart/alternative; boundary="' . $alternativa . '"',
            '',
            '--' . $alternativa,
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            '',
            $b64(str_replace("\n", "\r\n", $texto)),
            '--' . $alternativa,
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            '',
            $b64($html),
            '--' . $alternativa . '--',
        ];
        foreach ($adjuntos as $archivo => $contenido) {
            $archivo = preg_replace('/[^A-Za-z0-9._-]/', '_', $archivo) ?? 'comprobante.pdf';
            array_push($partes,
                '--' . $limite,
                'Content-Type: application/pdf; name="' . $archivo . '"',
                'Content-Transfer-Encoding: base64',
                'Content-Disposition: attachment; filename="' . $archivo . '"',
                '',
                $b64($contenido)
            );
        }
        $partes[] = '--' . $limite . '--';
        return implode("\r\n", $cabeceras) . "\r\n\r\n" . implode("\r\n", $partes);
    }

    private function conectar(): void
    {
        $direccion = ($this->cifrado === 'ssl' ? 'ssl://' : 'tcp://') . $this->servidor . ':' . $this->puerto;
        $conexion = @stream_socket_client($direccion, $codigo, $error, self::ESPERA_SEGUNDOS);
        if ($conexion === false) {
            throw new RuntimeException('No se pudo conectar con el servidor de correo ' . $this->servidor . ':' . $this->puerto . '.');
        }
        stream_set_timeout($conexion, self::ESPERA_SEGUNDOS);
        $this->conexion = $conexion;
        $this->respuesta(220);
    }

    /** @param int|int[] $esperado */
    private function comando(string $linea, int|array $esperado, ?string $siFalla = null): void
    {
        fwrite($this->conexion, $linea . "\r\n");
        $this->respuesta($esperado, $siFalla);
    }

    /** @param int|int[] $esperado */
    private function respuesta(int|array $esperado, ?string $siFalla = null): void
    {
        $texto = '';
        // Respuesta de varias lineas: "250-..." sigue, "250 ..." es la ultima
        while (($linea = fgets($this->conexion, 1024)) !== false) {
            $texto .= $linea;
            if (strlen($linea) < 4 || $linea[3] !== '-') {
                break;
            }
        }
        $codigo = (int) substr($texto, 0, 3);
        if (!in_array($codigo, (array) $esperado, true)) {
            error_log('Minimarket Mass: SMTP respondió ' . trim($texto));
            throw new RuntimeException($siFalla ?? 'El servidor de correo rechazó el envío (' . ($codigo ?: 'sin respuesta') . ').');
        }
    }
}
