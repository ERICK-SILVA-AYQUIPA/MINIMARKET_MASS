<?php
declare(strict_types=1);

require_once __DIR__ . '/Entorno.php';

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
            Entorno::cargar();

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
        return Entorno::leer($clave, $porDefecto);
    }
}
