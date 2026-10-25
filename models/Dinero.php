<?php
declare(strict_types=1);

/**
 * Comparar montos con float puede fallar (0.1 + 0.2 != 0.3).
 * Por eso los montos se comparan en centimos enteros, redondeados como DECIMAL(10,2).
 */
final class Dinero
{
    public static function centimos(float $monto): int
    {
        return (int) round($monto * 100);
    }
}
