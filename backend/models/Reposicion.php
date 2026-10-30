<?php
declare(strict_types=1);

/**
 * Cuanto pedir de un producto que llego a su stock minimo, en milesimas.
 * Objetivo: cubrir DIAS_OBJETIVO dias de venta, y nunca menos que el doble del minimo.
 * Es una clase sin base de datos: se prueba sola en tests/unitarios.php.
 */
final class Reposicion
{
    public const DIAS_VENTA = 14;      // dias de venta que se miran para el promedio
    public const DIAS_OBJETIVO = 14;   // dias que deberia durar lo que se pide

    // Venta promedio por dia, en milesimas, a partir de lo vendido en DIAS_VENTA dias
    public static function ventaDiaria(int $vendidoMilesimas): int
    {
        return intdiv(max(0, $vendidoMilesimas) + intdiv(self::DIAS_VENTA, 2), self::DIAS_VENTA);
    }

    // Lo que hay que pedir para llegar al objetivo; por unidad se redondea hacia arriba a unidades enteras
    public static function sugerido(int $actual, int $minimo, int $ventaDiaria, bool $alPeso): int
    {
        $objetivo = max(2 * $minimo, $ventaDiaria * self::DIAS_OBJETIVO);
        $falta = max(0, $objetivo - max(0, $actual));
        if (!$alPeso) {
            $falta = intdiv($falta + 999, 1000) * 1000;
        }
        return $falta;
    }

    // Dias que alcanza el stock al ritmo de venta actual; null si no se vendio nada
    public static function diasCobertura(int $actual, int $ventaDiaria): ?int
    {
        return $ventaDiaria > 0 ? intdiv(max(0, $actual), $ventaDiaria) : null;
    }
}
