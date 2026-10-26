<?php
declare(strict_types=1);

/**
 * Regla de las claves de acceso. En la base solo se guarda el hash de password_hash().
 */
final class Clave
{
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

    public static function hash(string $clave): string
    {
        return password_hash($clave, PASSWORD_DEFAULT);
    }
}
