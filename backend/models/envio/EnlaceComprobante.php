<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/Entorno.php';

/**
 * Enlace publico al PDF de un comprobante, para el mensaje de WhatsApp: el cliente lo abre sin usuario ni clave.
 * Lleva una firma (HMAC) de la serie y el numero: cambiar el numero en la URL no abre la boleta de otra persona.
 * Solo se arma si PAGINA_PUBLICA_URL esta en el .env (una direccion de localhost no le sirve al cliente).
 */
final class EnlaceComprobante
{
    public static function url(string $serie, int $numero): ?string
    {
        $base = rtrim(trim(Entorno::leer('PAGINA_PUBLICA_URL')), '/');
        if (preg_match('#^https?://[^\s/?\#]+(/[^\s?\#]*)?$#', $base) !== 1) {
            return null;
        }
        return $base . '/?' . http_build_query(['ruta' => 'boleta', 'serie' => $serie, 'numero' => $numero, 'f' => self::firma($serie, $numero)]);
    }

    public static function firma(string $serie, int $numero): string
    {
        return substr(hash_hmac('sha256', 'comprobante|' . $serie . '|' . $numero, self::clave()), 0, 20);
    }

    public static function esValida(string $serie, int $numero, ?string $firma): bool
    {
        return $firma !== null && hash_equals(self::firma($serie, $numero), $firma);
    }

    // ENLACES_CLAVE del .env; si no esta, una derivada de la clave de la base (secreta y fija en cada instalacion)
    private static function clave(): string
    {
        $clave = Entorno::leer('ENLACES_CLAVE');
        return $clave !== '' ? $clave : hash('sha256', 'minimarket-mass-enlaces|' . Entorno::leer('DB_PASSWORD') . '|' . Entorno::leer('DB_NAME'));
    }
}
