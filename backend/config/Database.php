<?php
declare(strict_types=1);

/**
 * Conexion unica a MySQL con PDO.
 * Los datos de conexion salen de variables de entorno (o del archivo .env),
 * nunca escritos en el codigo.
 */
final class Database
{
    private static ?PDO $conexion = null;

    public static function conexion(): PDO
    {
        if (self::$conexion === null) {
            self::cargarEnv(__DIR__ . '/../../.env');

            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                self::leer('DB_HOST', '127.0.0.1'),
                self::leer('DB_PORT', '3307'),
                self::leer('DB_NAME', 'minimarket_mass')
            );

            self::$conexion = new PDO($dsn, self::leer('DB_USER', 'mass_app'), self::leer('DB_PASSWORD', ''), [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // un error de SQL lanza excepcion
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // filas como arreglo columna => valor
                PDO::ATTR_EMULATE_PREPARES   => false,                  // consultas preparadas reales en MySQL
            ]);
        }
        return self::$conexion;
    }

    private static function leer(string $clave, string $porDefecto): string
    {
        $valor = getenv($clave);
        return $valor === false || $valor === '' ? $porDefecto : $valor;
    }

    // Lee lineas CLAVE=valor del .env. Una variable que ya existe en el sistema no se pisa.
    private static function cargarEnv(string $ruta): void
    {
        if (!is_readable($ruta)) {
            return;
        }
        foreach (file($ruta, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linea) {
            $linea = trim($linea);
            if ($linea === '' || $linea[0] === '#' || !str_contains($linea, '=')) {
                continue;
            }
            [$clave, $valor] = array_map('trim', explode('=', $linea, 2));
            if (getenv($clave) === false) {
                putenv($clave . '=' . $valor);
            }
        }
    }
}
