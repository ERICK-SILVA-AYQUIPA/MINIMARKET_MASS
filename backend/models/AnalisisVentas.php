<?php
declare(strict_types=1);

/**
 * Cuentas "inteligentes" sobre las ventas que ya guarda el sistema, sin IA ni internet:
 * - pronostico de los proximos 7 dias: lo que se vendio el mismo dia de la semana en las ultimas semanas
 *   (los sabados se parecen a los sabados), con la tendencia de las 2 ultimas semanas frente a las anteriores;
 * - horas punta: las horas y el dia con mas comprobantes, y las horas mas tranquilas, para armar los turnos.
 * Es una clase sin base de datos: se prueba sola en tests/unitarios.php.
 */
final class AnalisisVentas
{
    public const SEMANAS_PRONOSTICO = 8;   // semanas de historia que se miran para el pronostico
    public const DIAS_HORAS_PUNTA = 28;    // dias que se miran para las horas punta

    public const DIAS = [1 => 'lunes', 2 => 'martes', 3 => 'miércoles', 4 => 'jueves', 5 => 'viernes', 6 => 'sábado', 7 => 'domingo'];

    /**
     * Pronostico de los 7 dias que empiezan en $desde (incluido).
     * @param array<int, array{dia: string, total: string|float, comprobantes?: string|int}> $porDia ventas de cada dia
     *        (AAAA-MM-DD) de las SEMANAS_PRONOSTICO semanas antes de $desde; los dias sin ventas pueden faltar (cuentan 0)
     * @return array{dias: array<int, array{fecha: string, dia: string, estimado: float}>, total: float,
     *         ultimos7: float, tendencia: ?float, semanas: int}
     *         tendencia: % de cambio de las 2 ultimas semanas frente a las anteriores (null si no hay con que comparar)
     */
    public static function pronostico(array $porDia, DateTimeImmutable $desde, int $semanas = self::SEMANAS_PRONOSTICO): array
    {
        $semanas = max(1, $semanas);
        $totales = [];
        foreach ($porDia as $f) {
            $totales[substr((string) $f['dia'], 0, 10)] = (float) $f['total'];
        }
        // Cuantas semanas de historia hay de verdad (desde la primera venta): una tienda nueva no promedia con ceros
        $primera = $totales === [] ? null : min(array_keys($totales));
        $inicio = $desde->modify('-' . (7 * $semanas) . ' days');
        if ($primera !== null && $primera > $inicio->format('Y-m-d')) {
            $dias = (int) (new DateTimeImmutable($primera))->diff($desde)->days;
            $semanas = max(1, min($semanas, intdiv($dias, 7)));
        }

        $dias = [];
        $total = 0.0;
        for ($i = 0; $i < 7; $i++) {
            $fecha = $desde->modify('+' . $i . ' days');
            $suma = 0.0;
            for ($s = 1; $s <= $semanas; $s++) {
                $suma += $totales[$fecha->modify('-' . (7 * $s) . ' days')->format('Y-m-d')] ?? 0.0;
            }
            $estimado = round($suma / $semanas, 2);
            $total += $estimado;
            $dias[] = ['fecha' => $fecha->format('Y-m-d'), 'dia' => self::DIAS[(int) $fecha->format('N')], 'estimado' => $estimado];
        }

        // Tendencia: promedio semanal de las 2 ultimas semanas frente al de las anteriores
        $semana = static function (int $atras) use ($totales, $desde): float {
            $suma = 0.0;
            for ($d = 1; $d <= 7; $d++) {
                $suma += $totales[$desde->modify('-' . (7 * ($atras - 1) + $d) . ' days')->format('Y-m-d')] ?? 0.0;
            }
            return $suma;
        };
        $tendencia = null;
        if ($semanas >= 4) {
            $recientes = ($semana(1) + $semana(2)) / 2;
            $antes = 0.0;
            for ($s = 3; $s <= $semanas; $s++) {
                $antes += $semana($s);
            }
            $antes /= $semanas - 2;
            $tendencia = $antes > 0 ? round(($recientes - $antes) / $antes * 100, 1) : null;
        }
        return ['dias' => $dias, 'total' => round($total, 2), 'ultimos7' => round($semana(1), 2), 'tendencia' => $tendencia,
            'semanas' => $semanas];
    }

    /**
     * Horas punta a partir del mapa de dia de la semana (1 lunes ... 7 domingo) y hora (0 a 23).
     * @param array<int, array{dia: string|int, hora: string|int, comprobantes: string|int, total: string|float}> $filas
     * @return array{horas_punta: array<int, array{hora: string, comprobantes: int, total: float}>,
     *         horas_tranquilas: array<int, array{hora: string, comprobantes: int, total: float}>,
     *         dia_mas_movido: ?string, dia_mas_tranquilo: ?string, comprobantes: int}
     */
    public static function horasPunta(array $filas, int $cuantas = 3): array
    {
        $porHora = [];
        $porDia = [];
        foreach ($filas as $f) {
            $hora = (int) $f['hora'];
            $dia = (int) $f['dia'];
            $porHora[$hora] ??= ['comprobantes' => 0, 'total' => 0.0];
            $porHora[$hora]['comprobantes'] += (int) $f['comprobantes'];
            $porHora[$hora]['total'] += (float) $f['total'];
            $porDia[$dia] = ($porDia[$dia] ?? 0) + (int) $f['comprobantes'];
        }
        $lista = [];
        foreach ($porHora as $hora => $h) {
            $lista[] = ['hora' => self::franja($hora), 'comprobantes' => $h['comprobantes'], 'total' => round($h['total'], 2), 'h' => $hora];
        }
        $quitar = static fn(array $filas): array => array_map(static function (array $f): array {
            unset($f['h']);
            return $f;
        }, $filas);

        $mas = $lista;
        usort($mas, static fn(array $a, array $b): int => [$b['comprobantes'], $a['h']] <=> [$a['comprobantes'], $b['h']]);
        $menos = $lista;
        usort($menos, static fn(array $a, array $b): int => [$a['comprobantes'], $a['h']] <=> [$b['comprobantes'], $b['h']]);
        $punta = array_slice($mas, 0, $cuantas);
        // Las tranquilas no repiten las de punta y tienen menos comprobantes que todas ellas (con pocos datos no hay)
        $horasPunta = array_column($punta, 'h');
        $minimoPunta = $punta === [] ? 0 : min(array_column($punta, 'comprobantes'));
        $tranquilas = array_slice(array_values(array_filter($menos, static fn(array $f): bool => !in_array($f['h'], $horasPunta, true)
            && $f['comprobantes'] < $minimoPunta)), 0, $cuantas);
        usort($punta, static fn(array $a, array $b): int => $a['h'] <=> $b['h']);
        usort($tranquilas, static fn(array $a, array $b): int => $a['h'] <=> $b['h']);

        $diaMas = null;
        $diaMenos = null;
        if ($porDia !== []) {
            arsort($porDia);
            $diaMas = self::DIAS[array_key_first($porDia)] ?? null;
            $diaMenos = self::DIAS[array_key_last($porDia)] ?? null;
        }
        return ['horas_punta' => $quitar($punta), 'horas_tranquilas' => $quitar($tranquilas), 'dia_mas_movido' => $diaMas,
            'dia_mas_tranquilo' => $diaMenos === $diaMas ? null : $diaMenos, 'comprobantes' => array_sum($porDia)];
    }

    // 9 -> "9:00 a 10:00"
    public static function franja(int $hora): string
    {
        return $hora . ':00 a ' . (($hora + 1) % 24) . ':00';
    }
}
