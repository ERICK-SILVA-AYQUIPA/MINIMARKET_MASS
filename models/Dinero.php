<?php
declare(strict_types=1);

/**
 * Comparar montos con float puede fallar (0.1 + 0.2 != 0.3).
 * Por eso los montos se comparan y se suman en centimos enteros, como DECIMAL(10,2).
 */
final class Dinero
{
    public static function centimos(float $monto): int
    {
        return (int) round($monto * 100);
    }

    // "12.5" o "12.50" -> 1250. null si el texto no es un monto valido (hasta 8 enteros y 2 decimales).
    public static function deTexto(string $texto): ?int
    {
        if (preg_match('/^([0-9]{1,8})(?:\.([0-9]{1,2}))?$/', trim($texto), $m) !== 1) {
            return null;
        }
        return (int) $m[1] * 100 + (int) str_pad($m[2] ?? '0', 2, '0');
    }

    // 1250 -> "12.50", listo para mandarlo a una columna DECIMAL(10,2)
    public static function aTexto(int $centimos): string
    {
        return intdiv($centimos, 100) . '.' . str_pad((string) ($centimos % 100), 2, '0', STR_PAD_LEFT);
    }
}
