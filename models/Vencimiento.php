<?php
declare(strict_types=1);

require_once __DIR__ . '/Cantidad.php';

/**
 * Productos por vencer: cuanto queda de cada lote recibido y cuando vence.
 * El kardex no sabe de que lote sale cada venta, asi que se estima como en tienda:
 * primero se vende lo mas antiguo (FIFO), y el stock que queda es de los lotes mas nuevos.
 * Es una clase sin base de datos: se prueba sola en tests/unitarios.php.
 */
final class Vencimiento
{
    public const DIAS_POR_DEFECTO = 30;
    public const DIAS_PERMITIDOS = [7, 15, 30, 60];

    /**
     * Reparte el stock actual entre los lotes, del mas nuevo al mas antiguo.
     * @param array<int, array{milesimas: int}&array<string, mixed>> $lotes del mas antiguo al mas nuevo
     * @return array<int, array<string, mixed>> los mismos lotes con 'restante' (milesimas), solo los que aun tienen algo
     */
    public static function repartirStock(array $lotes, int $stockMilesimas): array
    {
        $resultado = [];
        $porRepartir = max(0, $stockMilesimas);
        foreach (array_reverse($lotes) as $lote) {
            if ($porRepartir <= 0) {
                break;
            }
            $restante = min($lote['milesimas'], $porRepartir);
            $porRepartir -= $restante;
            $resultado[] = $lote + ['restante' => $restante];
        }
        // De vuelta al orden original: el mas antiguo primero
        return array_reverse($resultado);
    }

    /**
     * Lotes con fecha de vencimiento hasta $hoy + $dias (los vencidos tambien), el que vence antes primero.
     * @param array<int, array<string, mixed>> $lotes con 'fecha_vencimiento' (?string AAAA-MM-DD) y 'restante'
     * @return array<int, array<string, mixed>> con 'dias' (negativo = ya vencio) y 'vencido'
     */
    public static function porVencer(array $lotes, string $hoy, int $dias): array
    {
        $inicio = new DateTimeImmutable($hoy);
        $limite = $inicio->modify('+' . $dias . ' days')->format('Y-m-d');
        $resultado = [];
        foreach ($lotes as $lote) {
            $vence = $lote['fecha_vencimiento'] ?? null;
            if ($vence === null || ($lote['restante'] ?? 0) <= 0 || $vence > $limite) {
                continue;
            }
            $faltan = (int) $inicio->diff(new DateTimeImmutable($vence))->format('%r%a');
            $resultado[] = $lote + ['dias' => $faltan, 'vencido' => $faltan < 0];
        }
        usort($resultado, fn(array $a, array $b): int => [$a['fecha_vencimiento'], $a['id_ingreso'] ?? 0]
            <=> [$b['fecha_vencimiento'], $b['id_ingreso'] ?? 0]);
        return $resultado;
    }

    // ?dias=15 de la URL; cualquier otro valor usa 30
    public static function leerDias(?string $texto): int
    {
        $dias = $texto !== null && ctype_digit($texto) ? (int) $texto : 0;
        return in_array($dias, self::DIAS_PERMITIDOS, true) ? $dias : self::DIAS_POR_DEFECTO;
    }

    // Texto para la etiqueta: "Vencido hace 1 día", "Vence hoy", "Vence en 4 días"
    public static function describir(int $dias): string
    {
        return match (true) {
            $dias < 0  => 'Vencido hace ' . -$dias . (-$dias === 1 ? ' día' : ' días'),
            $dias === 0 => 'Vence hoy',
            default    => 'Vence en ' . $dias . ($dias === 1 ? ' día' : ' días'),
        };
    }
}
