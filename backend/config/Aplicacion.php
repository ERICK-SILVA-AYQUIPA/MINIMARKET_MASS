<?php
declare(strict_types=1);

require_once __DIR__ . '/Database.php';

/**
 * Version del sistema y contacto de soporte, para mostrarlos en el pie de cada pagina y en Ayuda.
 *
 * - La version sale del archivo VERSION de la raiz del proyecto (se cambia en cada entrega, ver CHANGELOG.md).
 * - El soporte sale del .env (SOPORTE_NOMBRE, SOPORTE_TELEFONO, SOPORTE_CORREO, SOPORTE_HORARIO): cada
 *   instalacion pone a quien llamar sin tocar el codigo. Vacios = solo se muestra el nombre por defecto.
 */
final class Aplicacion
{
    public const NOMBRE = 'Minimarket Mass';
    private const SOPORTE_POR_DEFECTO = 'Administrador del sistema';

    private static ?string $version = null;

    public static function version(): string
    {
        if (self::$version === null) {
            $archivo = __DIR__ . '/../../VERSION';
            $texto = is_readable($archivo) ? trim((string) file_get_contents($archivo)) : '';
            self::$version = preg_match('/^[0-9]+\.[0-9]+\.[0-9]+$/', $texto) === 1 ? $texto : 'sin versión';
        }
        return self::$version;
    }

    /**
     * A quien llamar si algo falla. Telefono y correo solo si son validos (lo demas se ignora).
     * @return array{nombre: string, telefono: ?string, correo: ?string, horario: ?string}
     */
    public static function soporte(): array
    {
        Database::cargarEntorno();
        $nombre = self::texto('SOPORTE_NOMBRE', 60);
        $telefono = self::texto('SOPORTE_TELEFONO', 20);
        $correo = self::texto('SOPORTE_CORREO', 80);
        return [
            'nombre'   => $nombre ?? self::SOPORTE_POR_DEFECTO,
            'telefono' => $telefono !== null && preg_match('/^\+?[0-9 ]{6,20}$/', $telefono) === 1 ? $telefono : null,
            'correo'   => $correo !== null && filter_var($correo, FILTER_VALIDATE_EMAIL) !== false ? $correo : null,
            'horario'  => self::texto('SOPORTE_HORARIO', 60),
        ];
    }

    // true si el .env ya dice a quien llamar (lo revisa backend/scripts/verificar_instalacion.php)
    public static function soporteConfigurado(): bool
    {
        $soporte = self::soporte();
        return $soporte['telefono'] !== null || $soporte['correo'] !== null;
    }

    // Enlace tel: con solo digitos (y el + del codigo de pais)
    public static function enlaceTelefono(string $telefono): string
    {
        return 'tel:' . preg_replace('/[^0-9+]/', '', $telefono);
    }

    private static function texto(string $clave, int $largo): ?string
    {
        $valor = getenv($clave);
        if ($valor === false) {
            return null;
        }
        $valor = trim(preg_replace('/\s+/u', ' ', $valor) ?? '');
        return $valor === '' ? null : mb_substr($valor, 0, $largo);
    }
}
