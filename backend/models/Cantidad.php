<?php
declare(strict_types=1);

/**
 * Cantidades y kilos en milesimas enteras, como DECIMAL(10,3): 1.250 kg -> 1250.
 * Asi el stock se suma y se resta sin errores de float.
 */
final class Cantidad
{
    // Lo maximo que cabe en una columna DECIMAL(10,3): 9 999 999.999
    public const MAXIMO_MILESIMAS = 9_999_999_999;

    // Texto del formulario -> milesimas. Al peso: hasta 3 decimales. Por unidad: solo enteros.
    public static function deTexto(string $texto, bool $alPeso): ?int
    {
        $patron = $alPeso ? '/^([0-9]{1,7})(?:\.([0-9]{1,3}))?$/' : '/^([0-9]{1,7})$/';
        if (preg_match($patron, trim($texto), $m) !== 1) {
            return null;
        }
        $milesimas = (int) $m[1] * 1000 + (int) str_pad($m[2] ?? '0', 3, '0');
        return $milesimas > 0 ? $milesimas : null;
    }

    // Valor DECIMAL que devuelve MySQL ("98.000") -> milesimas
    public static function deDecimal(string $valor): int
    {
        [$entero, $decimales] = array_pad(explode('.', $valor, 2), 2, '0');
        return (int) $entero * 1000 + (int) str_pad(substr($decimales, 0, 3), 3, '0');
    }

    // 1250 -> "1.250", listo para una columna DECIMAL(10,3)
    public static function aTexto(int $milesimas): string
    {
        return intdiv($milesimas, 1000) . '.' . str_pad((string) ($milesimas % 1000), 3, '0', STR_PAD_LEFT);
    }

    // importe = ROUND(cantidad * precio, 2), en centimos y redondeando la mitad hacia arriba como MySQL
    public static function importe(int $milesimas, int $precioCentimos): int
    {
        return intdiv($milesimas * $precioCentimos + 500, 1000);
    }
}
