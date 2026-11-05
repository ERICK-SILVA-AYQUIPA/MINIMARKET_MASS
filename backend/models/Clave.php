<?php
declare(strict_types=1);

/**
 * Regla de las claves de acceso. En la base solo se guarda el hash de password_hash().
 */
final class Clave
{
    // La clave de prueba de login.sql: sirve para entrar la primera vez, nunca como clave propia
    public const PRUEBA = 'Mass2026!';

    // Claves que se prueban primero al adivinar (en minusculas, sin espacios): no se aceptan como clave propia
    private const COMUNES = [
        'mass2026!', 'mass2026', 'massmass1', 'minimarket1', 'minimarketmass1', 'tiendamass1',
        'password1', 'password123', 'contraseña1', 'contrasena1', 'qwerty123', 'abc12345', 'abcd1234',
        'clave123', 'clave1234', 'admin123', 'admin1234', 'cajero123', 'peru2026', 'arequipa1',
        '12345678a', 'a12345678', '1234567a', 'a1234567', '123456789a', '1q2w3e4r', 'asdf1234',
    ];

    // De 8 a 72 caracteres (bcrypt ignora lo que pasa de 72), con al menos una letra y un numero
    public static function problema(string $clave): ?string
    {
        if (strlen($clave) < 8 || strlen($clave) > 72) {
            return 'La clave debe tener de 8 a 72 caracteres.';
        }
        if (preg_match('/[A-Za-z]/', $clave) !== 1 || preg_match('/[0-9]/', $clave) !== 1) {
            return 'La clave debe tener al menos una letra y un número.';
        }
        return null;
    }

    /**
     * La clave que el empleado elige para si mismo (Mi clave). Ademas de la regla general:
     * no puede ser la de prueba ni una de las mas usadas, y no puede llevar su DNI.
     * La clave temporal que pone el administrador solo sigue problema(): igual se cambia en el primer ingreso.
     */
    public static function problemaPropia(string $clave, string $dni): ?string
    {
        $problema = self::problema($clave);
        if ($problema !== null) {
            return $problema;
        }
        $normal = mb_strtolower(preg_replace('/\s+/', '', $clave) ?? $clave, 'UTF-8');
        if ($normal === mb_strtolower(self::PRUEBA, 'UTF-8')) {
            return 'No puedes usar la clave de prueba. Elige una que solo tú sepas.';
        }
        if (in_array($normal, self::COMUNES, true)) {
            return 'Esa clave es de las primeras que se prueban para adivinar. Elige otra.';
        }
        if ($dni !== '' && str_contains($clave, $dni)) {
            return 'La clave no puede llevar tu DNI.';
        }
        return null;
    }

    public static function hash(string $clave): string
    {
        return password_hash($clave, PASSWORD_DEFAULT);
    }
}
