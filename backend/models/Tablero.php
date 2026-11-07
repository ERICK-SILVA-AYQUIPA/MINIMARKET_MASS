<?php
declare(strict_types=1);

/**
 * Cuentas del panel de inicio (views/panel/inicio.php): variaciones, horas del dia y participacion de cada fila.
 * Sin base de datos: se prueba sola en tests/unitarios.php.
 */
final class Tablero
{
    // Variacion de $actual contra $antes en porcentaje, con 1 decimal (null si antes fue 0: no hay con que comparar)
    public static function variacion(float $actual, float $antes): ?float
    {
        return $antes > 0 ? round(($actual - $antes) / $antes * 100, 1) : null;
    }

    /**
     * Total de cada hora, de $desde a $hasta, con las horas sin ventas en 0 (y las de fuera del horario si tuvieron ventas).
     * @param array<int, array<string, mixed>> $porHora filas de ReporteRepository::porHora (hora, comprobantes, total)
     * @return array<int, float> hora => total, en orden
     */
    public static function horas(array $porHora, int $desde = 7, int $hasta = 22): array
    {
        $serie = array_fill_keys(range($desde, $hasta), 0.0);
        foreach ($porHora as $f) {
            $serie[(int) $f['hora']] = (float) $f['total'];
        }
        ksort($serie);
        return $serie;
    }

    /**
     * Cada fila con 'pct': su parte del total de $campo (0 a 100, sin decimales), en el mismo orden.
     * @param array<int, array<string, mixed>> $filas
     * @return array<int, array<string, mixed>>
     */
    public static function participacion(array $filas, string $campo): array
    {
        $total = array_sum(array_map(fn(array $f): float => (float) $f[$campo], $filas));
        return array_map(fn(array $f): array => $f + ['pct' => $total > 0 ? (int) round((float) $f[$campo] / $total * 100) : 0], $filas);
    }

    // Ancho de una barra (0 a 100) de $valor contra el mayor; lo que tiene algo se ve al menos un poco
    public static function ancho(float $valor, float $mayor): int
    {
        if ($valor <= 0 || $mayor <= 0) {
            return 0;
        }
        return max(3, min(100, (int) round($valor / $mayor * 100)));
    }

    public static function saludo(int $hora): string
    {
        return match (true) {
            $hora < 12 => 'Buenos días',
            $hora < 19 => 'Buenas tardes',
            default    => 'Buenas noches',
        };
    }
}
