<?php
declare(strict_types=1);

require_once __DIR__ . '/../AnalisisVentas.php';

/**
 * Convierte el resultado de una herramienta del asistente en "bloques" para verlo completo, no solo en texto:
 * - tarjetas: cifras grandes (total vendido, comprobantes, ticket promedio...)
 * - tabla:    filas y columnas (productos, stock, clientes...)
 * - barras:   un grafico de barras horizontal (por cajero, por hora, por dia...)
 * Solo datos (texto y numeros): la vista (asistente/bloques.php) y app.js los dibujan escapados.
 * Es una clase sin base de datos: se prueba sola en tests/unitarios.php.
 */
final class Presentacion
{
    public const FILAS_MAXIMO = 10;

    /**
     * @param array<string, mixed> $entrada lo que se le pidio a la herramienta
     * @param array<string, mixed> $r       lo que devolvio (sin 'error')
     * @return array<int, array<string, mixed>> bloques
     */
    public static function de(string $herramienta, array $entrada, array $r): array
    {
        if (isset($r['error'])) {
            return [];
        }
        return match ($herramienta) {
            'resumen_ventas'        => self::resumen($r),
            'detalle_ventas'        => self::detalle($r),
            'buscar_productos'      => self::productos($r),
            'productos_por_reponer' => self::reponer($r),
            'productos_sin_venta'   => self::sinVenta($r),
            'mejores_clientes'      => self::clientes($r),
            'sugerencia_pedido'     => self::pedido($r),
            'productos_por_vencer'  => self::porVencer($r),
            'pronostico_ventas'     => self::pronostico($r),
            'horas_punta'           => self::horasPunta($r),
            'se_compran_juntos'     => self::juntos($r),
            'datos_tienda'          => self::datosTienda($r),
            default                 => [],
        };
    }

    public static function soles(float $monto): string
    {
        return 'S/ ' . number_format($monto, 2);
    }

    public static function cantidad(float $c, string $unidad = ''): string
    {
        return trim(rtrim(rtrim(number_format($c, 3, '.', ''), '0'), '.') . ' ' . $unidad);
    }

    public static function fecha(?string $iso): string
    {
        return $iso !== null && preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $iso, $m) === 1 ? $m[3] . '/' . $m[2] . '/' . $m[1] : '—';
    }

    /**
     * @param array<int, array{etiqueta: string, valor: string, nota?: string, tono?: string}> $items
     * @return array<string, mixed>
     */
    public static function tarjetas(array $items): array
    {
        return ['tipo' => 'tarjetas', 'items' => array_values($items)];
    }

    /**
     * @param array<int, array{0: string, 1: bool}> $columnas [texto, es numero (se alinea a la derecha)]
     * @param array<int, array<int, string>> $filas
     * @return array<string, mixed>
     */
    public static function tabla(string $titulo, array $columnas, array $filas, int $total = 0): array
    {
        $mostradas = array_slice(array_values($filas), 0, self::FILAS_MAXIMO);
        return ['tipo' => 'tabla', 'titulo' => $titulo,
            'columnas' => array_map(fn(array $c): array => ['texto' => $c[0], 'numero' => $c[1]], $columnas),
            'filas' => array_map(fn(array $f): array => array_map('strval', array_values($f)), $mostradas),
            'mas' => max(0, max($total, count($filas)) - count($mostradas))];
    }

    /**
     * Grafico de barras: el ancho (0 a 100) se calcula aqui contra el valor mas alto.
     * @param array<int, array{0: string, 1: float, 2: string, 3?: bool}> $items [etiqueta, valor, texto del valor, resaltar]
     * @return array<string, mixed>
     */
    public static function barras(string $titulo, array $items): array
    {
        $items = array_slice(array_values($items), 0, 24);
        $maximo = max(array_map(fn(array $i): float => (float) $i[1], $items) ?: [0.0]);
        return ['tipo' => 'barras', 'titulo' => $titulo, 'items' => array_map(fn(array $i): array => [
            'etiqueta' => $i[0],
            'texto'    => $i[2],
            'ancho'    => $maximo > 0 ? (int) max($i[1] > 0 ? 2 : 0, round(100 * $i[1] / $maximo)) : 0,
            'resaltar' => (bool) ($i[3] ?? false),
        ], $items)];
    }

    private static function resumen(array $r): array
    {
        $bloques = [self::tarjetas(array_values(array_filter([
            ['etiqueta' => 'Total vendido', 'valor' => self::soles((float) ($r['total'] ?? 0)), 'tono' => 'marca'],
            ['etiqueta' => 'Comprobantes', 'valor' => (string) (int) ($r['comprobantes'] ?? 0)],
            ['etiqueta' => 'Ticket promedio', 'valor' => self::soles((float) ($r['ticketPromedio'] ?? 0))],
            (float) ($r['devuelto'] ?? 0) > 0 ? ['etiqueta' => 'Neto (con devoluciones)', 'valor' => self::soles((float) $r['neto'])] : null,
            (int) ($r['anuladas'] ?? 0) > 0 ? ['etiqueta' => 'Anuladas', 'valor' => (string) (int) $r['anuladas'], 'tono' => 'alerta'] : null,
        ])))];
        if (is_array($r['por_tienda'] ?? null) && $r['por_tienda'] !== []) {
            $bloques[] = self::barras('Por tienda', array_map(fn(array $t): array => [(string) $t['tienda'], (float) $t['total'],
                self::soles((float) $t['total'])], $r['por_tienda']));
        }
        return $bloques;
    }

    private static function detalle(array $r): array
    {
        $filas = is_array($r['filas'] ?? null) ? $r['filas'] : [];
        if ($filas === []) {
            return [];
        }
        return match ($r['tipo'] ?? '') {
            'mas_vendidos' => [self::tabla('Más vendidos', [['Producto', false], ['Cantidad', true], ['Importe', true]],
                array_map(fn(array $f): array => [(string) $f['producto'], self::cantidad((float) $f['cantidad'], (string) $f['unidad']),
                    self::soles((float) $f['importe'])], $filas))],
            'por_cajero' => [self::barras('Por cajero', array_map(fn(array $f): array => [(string) $f['cajero'], (float) $f['total'],
                self::soles((float) $f['total']) . ' · ' . (int) $f['comprobantes']], $filas))],
            'por_metodo_pago' => [self::barras('Por medio de pago', array_map(fn(array $f): array => [(string) $f['metodo'], (float) $f['monto'],
                self::soles((float) $f['monto'])], $filas))],
            'por_categoria' => [self::barras('Por categoría', array_map(fn(array $f): array => [(string) $f['categoria'], (float) $f['importe'],
                self::soles((float) $f['importe'])], $filas))],
            'por_hora' => [self::barras('Por hora', array_map(fn(array $f): array => [AnalisisVentas::franja((int) $f['hora']), (float) $f['total'],
                self::soles((float) $f['total'])], $filas))],
            'por_dia' => [self::barras('Por día', array_map(fn(array $f): array => [self::fecha((string) $f['dia']), (float) $f['total'],
                self::soles((float) $f['total'])], $filas))],
            default => [],
        };
    }

    private static function productos(array $r): array
    {
        $productos = is_array($r['productos'] ?? null) ? $r['productos'] : [];
        if ($productos === []) {
            return [];
        }
        return [self::tabla('Productos', [['Producto', false], ['Precio', true], ['Stock', false]], array_map(fn(array $p): array => [
            (string) $p['producto'],
            self::soles((float) $p['precio']),
            implode(' · ', array_map(fn(array $s): string => $s['tienda'] . ' ' . self::cantidad((float) $s['cantidad'], (string) $p['unidad'])
                . ($s['bajo'] ? ' (bajo)' : ''), $p['stock'] ?? [])) ?: '—',
        ], $productos), (int) ($r['encontrados'] ?? 0))];
    }

    private static function reponer(array $r): array
    {
        $filas = is_array($r['mostrados'] ?? null) ? $r['mostrados'] : [];
        if ($filas === []) {
            return [];
        }
        return [self::tabla('Stock bajo', [['Producto', false], ['Queda', true], ['Mínimo', true]], array_map(fn(array $f): array => [
            (string) $f['producto'], self::cantidad((float) $f['cantidad'], (string) $f['unidad']), self::cantidad((float) $f['minimo'], (string) $f['unidad']),
        ], $filas), (int) ($r['total'] ?? 0))];
    }

    private static function sinVenta(array $r): array
    {
        $filas = is_array($r['mostrados'] ?? null) ? $r['mostrados'] : [];
        if ($filas === []) {
            return [];
        }
        return [self::tabla('Sin venderse hace ' . (int) ($r['dias'] ?? 30) . ' días', [['Producto', false], ['Stock', true], ['Plata parada', true],
            ['Última venta', false]], array_map(fn(array $f): array => [
            (string) $f['producto'], self::cantidad((float) $f['stock'], (string) $f['unidad']), self::soles((float) $f['stock'] * (float) $f['precio']),
            $f['ultima_venta'] === null ? 'Nunca' : self::fecha((string) $f['ultima_venta']),
        ], $filas), (int) ($r['total'] ?? 0))];
    }

    private static function clientes(array $r): array
    {
        $filas = is_array($r['clientes'] ?? null) ? $r['clientes'] : [];
        if ($filas === []) {
            return [];
        }
        return [self::barras('Mejores clientes', array_map(fn(array $c): array => [(string) $c['cliente'], (float) $c['total'],
            self::soles((float) $c['total']) . ' · ' . (int) $c['compras'] . ((int) $c['compras'] === 1 ? ' compra' : ' compras')], $filas))];
    }

    private static function pedido(array $r): array
    {
        $filas = is_array($r['mostrados'] ?? null) ? $r['mostrados'] : [];
        if ($filas === []) {
            return [];
        }
        return [self::tabla('Qué pedir (para ' . (int) ($r['dias_que_debe_durar'] ?? 14) . ' días)', [['Producto', false], ['Queda', true],
            ['Alcanza', true], ['Pedir', true]], array_map(fn(array $f): array => [
            (string) $f['producto'], self::cantidad((float) $f['queda'], (string) $f['unidad']),
            $f['alcanza_dias'] === null ? 'Sin ventas' : (int) $f['alcanza_dias'] . ((int) $f['alcanza_dias'] === 1 ? ' día' : ' días'),
            self::cantidad((float) $f['pedir'], (string) $f['unidad']),
        ], $filas), (int) ($r['total'] ?? 0))];
    }

    private static function porVencer(array $r): array
    {
        $filas = is_array($r['mostrados'] ?? null) ? $r['mostrados'] : [];
        if ($filas === []) {
            return [];
        }
        return [
            self::tarjetas([
                ['etiqueta' => 'Lotes por vencer', 'valor' => (string) (int) ($r['total'] ?? 0)],
                ['etiqueta' => 'Ya vencidos', 'valor' => (string) (int) ($r['vencidos'] ?? 0), 'tono' => (int) ($r['vencidos'] ?? 0) > 0 ? 'alerta' : ''],
                ['etiqueta' => 'Valor al costo', 'valor' => self::soles((float) ($r['valor_en_riesgo'] ?? 0)), 'tono' => 'marca'],
            ]),
            self::tabla('Por vencer', [['Producto', false], ['Queda', true], ['Vence', false], ['Estado', false]], array_map(fn(array $f): array => [
                (string) $f['producto'], self::cantidad((float) $f['queda'], (string) $f['unidad']), self::fecha((string) $f['vence']), (string) $f['estado'],
            ], $filas), (int) ($r['total'] ?? 0)),
        ];
    }

    private static function pronostico(array $r): array
    {
        $dias = is_array($r['dias'] ?? null) ? $r['dias'] : [];
        if ($dias === []) {
            return [];
        }
        $tendencia = $r['tendencia'] ?? null;
        $mejor = max(array_map(fn(array $d): float => (float) $d['estimado'], $dias));
        return [
            self::tarjetas([
                ['etiqueta' => 'Próximos 7 días', 'valor' => self::soles((float) ($r['total'] ?? 0)), 'tono' => 'marca'],
                ['etiqueta' => 'Últimos 7 días', 'valor' => self::soles((float) ($r['ultimos7'] ?? 0))],
                ['etiqueta' => 'Tendencia', 'valor' => $tendencia === null ? '—' : (($tendencia >= 0 ? '+' : '') . number_format((float) $tendencia, 1) . '%'),
                    'tono' => $tendencia === null ? '' : ($tendencia >= 0 ? 'bien' : 'alerta')],
            ]),
            self::barras('Pronóstico por día', array_map(fn(array $d): array => [ucfirst((string) $d['dia']) . ' ' . substr(self::fecha((string) $d['fecha']), 0, 5),
                (float) $d['estimado'], self::soles((float) $d['estimado']), (float) $d['estimado'] === $mejor && $mejor > 0], $dias)),
        ];
    }

    private static function horasPunta(array $r): array
    {
        $horas = is_array($r['por_hora'] ?? null) ? $r['por_hora'] : [];
        if ($horas === []) {
            return [];
        }
        $punta = array_column(is_array($r['horas_punta'] ?? null) ? $r['horas_punta'] : [], 'hora');
        return [
            self::tarjetas(array_filter([
                ['etiqueta' => 'Hora más movida', 'valor' => (string) ($r['hora_mas_movida'] ?? '—'), 'tono' => 'marca'],
                ['etiqueta' => 'Día más movido', 'valor' => ucfirst((string) ($r['dia_mas_movido'] ?? '—'))],
                ($r['dia_mas_tranquilo'] ?? null) !== null ? ['etiqueta' => 'Día más tranquilo', 'valor' => ucfirst((string) $r['dia_mas_tranquilo'])] : null,
            ])),
            self::barras('Comprobantes por hora (las punta resaltadas)', array_map(fn(array $h): array => [(string) $h['hora'], (float) $h['comprobantes'],
                (string) $h['comprobantes'], in_array($h['hora'], $punta, true)], $horas)),
        ];
    }

    private static function juntos(array $r): array
    {
        $pares = is_array($r['pares'] ?? null) ? $r['pares'] : [];
        if ($pares === []) {
            return [];
        }
        return [self::tabla('Se compran juntos', [['Producto', false], ['Con', false], ['Veces', true], ['Del primero, llevan el segundo', true]],
            array_map(fn(array $p): array => [(string) $p['producto_a'], (string) $p['producto_b'], (string) $p['veces_juntos'], (string) $p['con_a_llevan_b']],
                $pares))];
    }

    private static function datosTienda(array $r): array
    {
        $bloques = [];
        $sedes = is_array($r['sedes'] ?? null) ? $r['sedes'] : [];
        if ($sedes !== []) {
            $bloques[] = self::tabla('Nuestras tiendas', [['Tienda', false], ['Dirección', false], ['Teléfono', false]], array_map(fn(array $s): array => [
                $s['tienda'] . ($s['es_la_tuya'] ? ' (aquí)' : ''), (string) $s['direccion'], (string) ($s['telefono'] ?? '') ?: '—',
            ], $sedes));
        }
        $categorias = is_array($r['categorias'] ?? null) ? $r['categorias'] : [];
        if ($categorias !== []) {
            $bloques[] = self::barras('Productos por categoría en ' . ($r['tienda_actual'] ?? 'tu tienda'), array_map(fn(array $c): array => [
                (string) $c['categoria'], (float) $c['productos'], (string) $c['productos']], $categorias));
        }
        return $bloques;
    }
}

