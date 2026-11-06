<?php
declare(strict_types=1);

require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../models/ReporteRepository.php';
require_once __DIR__ . '/../models/exportar/LibroExcel.php';
require_once __DIR__ . '/../models/exportar/DocumentoPdf.php';

/**
 * C: reportes de venta y de utilidad de la tienda del administrador, en pantalla, CSV, Excel o PDF.
 * El administrador de la cadena tambien ve el consolidado de todas las tiendas (alcance=cadena).
 */
class ReporteController extends Controller
{
    private const DIAS_POR_DEFECTO = 30;
    private const DIAS_MAXIMO = 366;

    public function __construct(TiendaRepository $tiendas, Sesion $sesion, ?Usuario $usuario, private ReporteRepository $reportes)
    {
        parent::__construct($tiendas, $sesion, $usuario);
    }

    // GET /?ruta=reportes&desde=2026-10-01&hasta=2026-10-31[&alcance=cadena]
    public function ventas(?string $desdePedido, ?string $hastaPedido, ?string $alcancePedido = null): void
    {
        if (!$this->puedeVer()) {
            $this->prohibido('Los reportes son para el administrador de la tienda.');
            return;
        }
        [$desde, $hasta, $error] = $this->rango($desdePedido, $hastaPedido);
        $datos = $this->datos($alcancePedido, $desde, $hasta);
        $tienda = $this->tiendas->buscarPorId($this->usuario->getIdTienda());
        $puedeVerCadena = $this->usuario->puedeAbrirTiendas();
        $atajos = $this->atajos();
        $this->vista('reportes/ventas', [...$datos, 'tienda' => $tienda, 'error' => $error, 'puedeVerCadena' => $puedeVerCadena, 'atajos' => $atajos]);
    }

    // GET /?ruta=utilidad&desde=2026-10-01&hasta=2026-10-31: ventas netas menos costo, por producto y por tienda
    public function utilidad(?string $desdePedido, ?string $hastaPedido): void
    {
        if (!$this->puedeVer()) {
            $this->prohibido('Los reportes son para el administrador de la tienda.');
            return;
        }
        $tienda = $this->tiendas->buscarPorId($this->usuario->getIdTienda());
        [$desde, $hasta, $error] = $this->rango($desdePedido, $hastaPedido);
        $productos = $this->reportes->utilidadPorProducto($tienda->getIdTienda(), $desde, $hasta);
        $conCosto = array_filter($productos, fn(array $p): bool => $p['costo'] !== null);
        $ventasConCosto = round(array_sum(array_column($conCosto, 'ventas')), 2);
        $totales = [
            'ventas'   => round(array_sum(array_column($productos, 'ventas')), 2),
            'costo'    => round(array_sum(array_column($conCosto, 'costo')), 2),
            'utilidad' => round(array_sum(array_column($conCosto, 'utilidad')), 2),
            'sinCosto' => count($productos) - count($conCosto),
        ];
        $totales['margen'] = $ventasConCosto > 0 ? round($totales['utilidad'] / $ventasConCosto * 100, 1) : null;

        // Comparacion entre tiendas: el administrador tambien abre tiendas, asi que ve la cadena completa
        $porTienda = [];
        if ($this->usuario->puedeAbrirTiendas()) {
            $nombres = [];
            foreach ($this->tiendas->todas() as $id => $t) {
                $nombres[$id] = $t->getNombreTienda();
            }
            $porTienda = $this->reportes->utilidadPorTienda($nombres, $desde, $hasta);
        }
        unset($conCosto);
        $this->vista('reportes/utilidad', get_defined_vars());
    }

    /**
     * GET /?ruta=estadisticas&desde=...&hasta=...[&alcance=cadena]
     * Que se vende y que no: los productos que mas venden, los que casi no salen, los que no se vendieron
     * nada (con su stock parado) y dos mapas de calor: dia de la semana x hora, y producto x dia de la semana.
     */
    public function estadisticas(?string $desdePedido, ?string $hastaPedido, ?string $alcancePedido = null): void
    {
        if (!$this->puedeVer()) {
            $this->prohibido('Las estadísticas son para el administrador de la tienda.');
            return;
        }
        [$desde, $hasta, $error] = $this->rango($desdePedido, $hastaPedido);
        [$ids, $nombreAlcance, $esCadena] = $this->alcance($alcancePedido);
        $tienda = $this->tiendas->buscarPorId($this->usuario->getIdTienda());
        $puedeVerCadena = $this->usuario->puedeAbrirTiendas();
        $atajos = $this->atajos();

        $productos = self::unirVentasConCatalogo(
            $this->reportes->catalogoConStock($ids),
            $this->reportes->ventasPorProducto($ids, $desde, $hasta)
        );
        $vendidos = array_values(array_filter($productos, fn(array $p): bool => $p['importe'] > 0));
        $masVendidos = array_slice($vendidos, 0, 10);
        $menosVendidos = array_slice(array_reverse($vendidos), 0, 10);
        // Lo que no se vendio nada: primero lo que mas plata tiene parada en el estante
        $sinVentas = array_values(array_filter($productos, fn(array $p): bool => $p['importe'] <= 0));
        usort($sinVentas, fn(array $a, array $b): int => [$b['valorStock'], $a['producto']] <=> [$a['valorStock'], $b['producto']]);

        $totalVendido = round(array_sum(array_column($vendidos, 'importe')), 2);
        $top = (int) max(1, ceil(count($vendidos) * 0.2));
        $resumen = [
            'catalogo'     => count($productos),
            'conVentas'    => count($vendidos),
            'sinVentas'    => count($sinVentas),
            'total'        => $totalVendido,
            'valorParado'  => round(array_sum(array_column($sinVentas, 'valorStock')), 2),
            'top'          => $vendidos === [] ? 0 : $top,
            // Regla 80/20: cuanto del total hace el 20 % de productos que mas vende
            'porcentajeTop' => $totalVendido > 0 ? round(array_sum(array_column(array_slice($vendidos, 0, $top), 'importe')) / $totalVendido * 100, 1) : null,
        ];

        $calorHoras = self::mapaCalorHoras($this->reportes->porDiaSemanaYHora($ids, $desde, $hasta));
        $calorProductos = self::mapaCalorProductos($masVendidos, $this->reportes->productoPorDiaSemana($ids, $desde, $hasta));

        $opiniones = self::opiniones($calorHoras, $resumen, $masVendidos, $menosVendidos, $sinVentas, $calorProductos);

        $this->vista('reportes/estadisticas', compact(
            'desde', 'hasta', 'error', 'esCadena', 'nombreAlcance', 'tienda', 'puedeVerCadena', 'atajos',
            'masVendidos', 'menosVendidos', 'sinVentas', 'resumen', 'calorHoras', 'calorProductos', 'opiniones'
        ));
    }

    /**
     * Junta el catalogo de las tiendas con lo vendido: un producto sin ventas queda con 0.
     * Ordenado de lo que mas vende a lo que menos (por importe), y por nombre si empatan.
     * @param array<int, array<string, mixed>> $catalogo filas de ReporteRepository::catalogoConStock
     * @param array<int, array<string, mixed>> $ventas filas de ReporteRepository::ventasPorProducto
     * @return array<int, array{id: int, producto: string, unidad: string, categoria: string, alPeso: bool, cantidad: float, importe: float, comprobantes: int, stock: float, valorStock: float, ultima: ?string}>
     */
    public static function unirVentasConCatalogo(array $catalogo, array $ventas): array
    {
        $vendido = array_column($ventas, null, 'id_producto');
        $filas = [];
        foreach ($catalogo as $c) {
            $v = $vendido[$c['id_producto']] ?? null;
            $stock = (float) $c['stock'];
            $filas[] = [
                'id'           => (int) $c['id_producto'],
                'producto'     => (string) $c['producto'],
                'unidad'       => (string) $c['unidad'],
                'categoria'    => (string) $c['categoria'],
                'alPeso'       => ($c['se_vende_por_peso'] ?? 'N') === 'S',
                'cantidad'     => (float) ($v['cantidad'] ?? 0),
                'importe'      => round((float) ($v['importe'] ?? 0), 2),
                'comprobantes' => (int) ($v['comprobantes'] ?? 0),
                'stock'        => $stock,
                'valorStock'   => round($stock * (float) $c['precio'], 2),
                'ultima'       => $c['ultima'] === null ? null : substr((string) $c['ultima'], 0, 10),
            ];
        }
        usort($filas, fn(array $a, array $b): int => [$b['importe'], $a['producto']] <=> [$a['importe'], $b['producto']]);
        return $filas;
    }

    /**
     * Mapa de calor dia de la semana x hora. Las horas van de la primera a la ultima con ventas (minimo 8:00 a 20:00).
     * Cada casilla trae su total, sus comprobantes y su intensidad (0 a 1, respecto de la casilla mas alta).
     * @param array<int, array<string, mixed>> $filas filas de ReporteRepository::porDiaSemanaYHora
     * @return array{horas: int[], dias: array<int, array{nombre: string, casillas: array<int, array{total: float, comprobantes: int, nivel: float}>, total: float}>, maximo: float, pico: ?array{dia: string, hora: int, total: float}}
     */
    public static function mapaCalorHoras(array $filas): array
    {
        $valor = [];
        foreach ($filas as $f) {
            $valor[(int) $f['dia']][(int) $f['hora']] = ['total' => (float) $f['total'], 'comprobantes' => (int) $f['comprobantes']];
        }
        $horasConVenta = array_map(fn(array $f): int => (int) $f['hora'], $filas);
        $primera = min([8, ...$horasConVenta]);
        $ultima = max([20, ...$horasConVenta]);
        $maximo = $filas === [] ? 0.0 : max(array_map(fn(array $f): float => (float) $f['total'], $filas));
        $dias = [];
        $pico = null;
        foreach (self::DIAS_SEMANA as $n => $nombre) {
            $casillas = [];
            foreach (range($primera, $ultima) as $h) {
                $c = $valor[$n][$h] ?? ['total' => 0.0, 'comprobantes' => 0];
                $casillas[$h] = [...$c, 'nivel' => $maximo > 0 ? round($c['total'] / $maximo, 3) : 0.0];
                if ($c['total'] > 0 && ($pico === null || $c['total'] > $pico['total'])) {
                    $pico = ['dia' => $nombre, 'hora' => $h, 'total' => $c['total']];
                }
            }
            $dias[$n] = ['nombre' => $nombre, 'casillas' => $casillas, 'total' => round(array_sum(array_column($casillas, 'total')), 2)];
        }
        return ['horas' => range($primera, $ultima), 'dias' => $dias, 'maximo' => $maximo, 'pico' => $pico];
    }

    /**
     * Mapa de calor producto x dia de la semana, para los productos que mas venden.
     * La intensidad es respecto de la casilla mas alta de toda la tabla.
     * @param array<int, array<string, mixed>> $productos los mas vendidos (con 'id' y 'producto')
     * @param array<int, array<string, mixed>> $filas filas de ReporteRepository::productoPorDiaSemana
     * @return array{filas: array<int, array{producto: string, casillas: array<int, array{importe: float, nivel: float}>}>, maximo: float}
     */
    public static function mapaCalorProductos(array $productos, array $filas): array
    {
        $valor = [];
        foreach ($filas as $f) {
            $valor[(int) $f['id_producto']][(int) $f['dia']] = (float) $f['importe'];
        }
        $ids = array_map(fn(array $p): int => (int) $p['id'], $productos);
        $maximo = 0.0;
        foreach ($ids as $id) {
            $maximo = max($maximo, ...array_values($valor[$id] ?? [0.0]));
        }
        $tabla = [];
        foreach ($productos as $p) {
            $casillas = [];
            foreach (array_keys(self::DIAS_SEMANA) as $n) {
                $importe = round($valor[(int) $p['id']][$n] ?? 0.0, 2);
                $casillas[$n] = ['importe' => $importe, 'nivel' => $maximo > 0 ? round($importe / $maximo, 3) : 0.0];
            }
            $tabla[] = ['producto' => (string) $p['producto'], 'casillas' => $casillas];
        }
        return ['filas' => $tabla, 'maximo' => $maximo];
    }

    /**
     * Lo que dicen los numeros, en frases cortas: hora pico, mejor y peor dia, horas muertas,
     * la regla 80/20, lo que no se vende y el mejor dia de cada producto estrella. Cada opinion trae
     * un tipo (ok, alerta, info) para su color y una sugerencia de que hacer.
     * @param array<string, mixed> $calorHoras resultado de mapaCalorHoras
     * @param array<string, mixed> $resumen
     * @param array<int, array<string, mixed>> $masVendidos
     * @param array<int, array<string, mixed>> $menosVendidos
     * @param array<int, array<string, mixed>> $sinVentas
     * @param array<string, mixed> $calorProductos resultado de mapaCalorProductos
     * @return array<int, array{tipo: string, titulo: string, texto: string}>
     */
    public static function opiniones(array $calorHoras, array $resumen, array $masVendidos, array $menosVendidos, array $sinVentas, array $calorProductos): array
    {
        $o = [];
        if ($calorHoras['pico'] === null) {
            return [['tipo' => 'info', 'titulo' => 'Sin ventas en estas fechas', 'texto' => 'Elige otras fechas o «Todas las tiendas» para ver el mapa de calor.']];
        }
        $pico = $calorHoras['pico'];
        $o[] = ['tipo' => 'ok', 'titulo' => 'Hora pico: ' . mb_strtolower($pico['dia']) . ' a las ' . sprintf('%02d:00', $pico['hora']),
            'texto' => 'Es la casilla más caliente del mapa (' . self::soles($pico['total']) . '). Ten las dos cajas abiertas y los estantes llenos antes de esa hora.'];

        // Mejor y peor dia (de los que tuvieron ventas)
        $dias = array_filter($calorHoras['dias'], fn(array $d): bool => $d['total'] > 0);
        uasort($dias, fn(array $a, array $b): int => $b['total'] <=> $a['total']);
        if (count($dias) >= 2) {
            $mejor = reset($dias);
            $peor = end($dias);
            $veces = $peor['total'] > 0 ? round($mejor['total'] / $peor['total'], 1) : null;
            $o[] = ['tipo' => 'info', 'titulo' => 'El ' . mb_strtolower($mejor['nombre']) . ' es el mejor día y el ' . mb_strtolower($peor['nombre']) . ' el más flojo',
                'texto' => self::soles($mejor['total']) . ' contra ' . self::soles($peor['total'])
                    . ($veces !== null && $veces >= 1.5 ? ' (' . number_format($veces, 1) . ' veces más)' : '')
                    . '. Una promoción el ' . mb_strtolower($peor['nombre']) . ' puede atraer clientes ese día.'];
        }
        $sinVentaDias = array_diff_key($calorHoras['dias'], $dias);
        if ($sinVentaDias !== [] && count($sinVentaDias) < 7) {
            $o[] = ['tipo' => 'alerta', 'titulo' => 'Días sin ninguna venta: ' . mb_strtolower(implode(', ', array_column($sinVentaDias, 'nombre'))),
                'texto' => 'Puede que la tienda no abra esos días o que falten datos: revisa si las cajas se abrieron.'];
        }

        // Mañana, tarde o noche: donde se concentra la plata
        $franjas = ['la mañana (antes de las 12)' => 0.0, 'la tarde (12 a 18)' => 0.0, 'la noche (desde las 18)' => 0.0];
        $porHora = array_fill_keys($calorHoras['horas'], 0.0);
        foreach ($calorHoras['dias'] as $d) {
            foreach ($d['casillas'] as $h => $c) {
                $porHora[$h] += $c['total'];
                $franjas[array_keys($franjas)[$h < 12 ? 0 : ($h < 18 ? 1 : 2)]] += $c['total'];
            }
        }
        $totalHoras = array_sum($franjas);
        arsort($franjas);
        $franja = (string) array_key_first($franjas);
        $o[] = ['tipo' => 'info', 'titulo' => 'Se vende más en ' . $franja,
            'texto' => 'Ahí cae el ' . number_format($franjas[$franja] / $totalHoras * 100, 0) . ' % de lo vendido. Pon a tu mejor cajero en ese turno.'];

        // Horas muertas: dentro del horario con ventas, las que menos venden en toda la semana
        $conVenta = array_keys(array_filter($porHora, fn(float $t): bool => $t > 0));
        $dentro = array_filter($porHora, fn(int $h): bool => $h >= min($conVenta) && $h <= max($conVenta), ARRAY_FILTER_USE_KEY);
        asort($dentro);
        $flojas = array_slice($dentro, 0, 2, true);
        if (count($dentro) >= 4 && max($flojas) < max($porHora) * 0.25) {
            ksort($flojas);
            $o[] = ['tipo' => 'info', 'titulo' => 'Horas tranquilas: ' . implode(' y ', array_map(fn(int $h): string => sprintf('%02d:00', $h), array_keys($flojas))),
                'texto' => 'Casi no hay ventas a esa hora: buen momento para reponer estantes, recibir mercadería o hacer el conteo.'];
        }

        // Regla 80/20
        if ($resumen['porcentajeTop'] !== null && $resumen['conVentas'] >= 5) {
            $o[] = ['tipo' => 'ok', 'titulo' => $resumen['top'] . ' productos hacen el ' . number_format($resumen['porcentajeTop'], 0) . ' % de las ventas',
                'texto' => 'Son el 20 % que más vende (arriba, en «Lo que más se vende»). Que nunca les falte stock.'];
        }
        if ($masVendidos !== []) {
            $estrella = $calorProductos['filas'][0] ?? null;
            $mejorDia = null;
            if ($estrella !== null) {
                $casillas = array_column($estrella['casillas'], 'importe');
                $mejorDia = self::DIAS_SEMANA[array_search(max($casillas), $casillas, true) + 1];
            }
            $o[] = ['tipo' => 'ok', 'titulo' => 'El producto estrella es ' . $masVendidos[0]['producto'],
                'texto' => self::soles($masVendidos[0]['importe']) . ' en ' . $masVendidos[0]['comprobantes'] . ' ventas'
                    . ($mejorDia !== null ? ', sobre todo el ' . mb_strtolower($mejorDia) : '') . '. Ponlo a la vista y cerca de la caja.'];
        }

        // Lo que no se vende
        if ($sinVentas !== []) {
            $parado = $sinVentas[0];
            $o[] = ['tipo' => 'alerta', 'titulo' => count($sinVentas) . ' productos no se vendieron nada',
                'texto' => 'Tienen ' . self::soles($resumen['valorParado']) . ' parados en el estante (a precio de venta). El que más: '
                    . $parado['producto'] . ' (' . self::soles($parado['valorStock']) . '). Haz una promoción o deja de pedirlos.'];
        }
        if (count($menosVendidos) >= 3 && $resumen['conVentas'] >= 10) {
            $o[] = ['tipo' => 'alerta', 'titulo' => 'Casi no salen: ' . implode(', ', array_column(array_slice($menosVendidos, 0, 3), 'producto')),
                'texto' => 'Se vendieron muy poco. Revisa su precio, su lugar en el estante o si conviene seguir comprándolos.'];
        }
        return $o;
    }

    private const DIAS_SEMANA = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];

    // GET /?ruta=reporte_csv&tipo=productos&desde=...&hasta=...[&alcance=cadena]
    public function csv(?string $tipo, ?string $desdePedido, ?string $hastaPedido, ?string $alcancePedido = null): void
    {
        if (!$this->puedeVer()) {
            $this->prohibido('Los reportes son para el administrador de la tienda.');
            return;
        }
        [$desde, $hasta] = $this->rango($desdePedido, $hastaPedido);
        [$ids] = $this->alcance($alcancePedido);
        [$encabezado, $filas] = match ($tipo) {
            'productos' => [['producto', 'unidad', 'cantidad', 'importe'], $this->reportes->masVendidos($ids, $desde, $hasta, 100)],
            'cajeros'   => [['cajero', 'comprobantes', 'total'], $this->reportes->porCajero($ids, $desde, $hasta)],
            'metodos'   => [['metodo', 'pagos', 'monto'], $this->reportes->porMetodoPago($ids, $desde, $hasta)],
            'horas'     => [['hora', 'comprobantes', 'total'], $this->reportes->porHora($ids, $desde, $hasta)],
            'tiendas'   => [['tienda', 'comprobantes', 'total', 'ticket_promedio'], $this->reportes->porTienda($ids, $desde, $hasta)],
            'utilidad'  => [['producto', 'unidad', 'cantidad_neta', 'ventas_netas', 'costo_unitario', 'costo', 'utilidad', 'margen_%'],
                array_map(fn(array $p): array => [$p['producto'], $p['unidad'], $p['cantidad'], $p['ventas'], $p['costo_unitario'] ?? '',
                    $p['costo'] ?? '', $p['utilidad'] ?? '', $p['margen'] ?? ''], $this->reportes->utilidadPorProducto($this->usuario->getIdTienda(), $desde, $hasta))],
            default     => [['dia', 'comprobantes', 'total'], $this->reportes->porDia($ids, $desde, $hasta)],
        };
        $nombre = sprintf('ventas_%s_%s_%s.csv', preg_replace('/[^a-z]/', '', (string) $tipo) ?: 'dias', $desde, $hasta);
        $this->enviarCsv($nombre, $encabezado, $filas);
    }

    // GET /?ruta=reporte_excel&desde=...&hasta=...[&alcance=cadena]: todo el reporte en un libro de Excel, una hoja por tabla
    public function excel(?string $desdePedido, ?string $hastaPedido, ?string $alcancePedido = null): void
    {
        if (!$this->puedeVer()) {
            $this->prohibido('Los reportes son para el administrador de la tienda.');
            return;
        }
        [$desde, $hasta] = $this->rango($desdePedido, $hastaPedido);
        $datos = $this->datos($alcancePedido, $desde, $hasta);
        $libro = new LibroExcel();
        $libro->agregarHoja('Resumen', ['Dato', 'Valor'], [
            ['Reporte de ventas', $datos['nombreAlcance']],
            ['Desde', $desde],
            ['Hasta', $hasta],
            ['Generado', date('Y-m-d H:i')],
        ]);
        foreach ($this->tablas($datos) as $t) {
            $libro->agregarHoja(
                $t['titulo'],
                array_column($t['columnas'], 0),
                array_map(fn(array $f): array => array_map(fn(array $c) => $f[$c[1]] ?? null, $t['columnas']), $t['filas']),
                array_map(fn(string $f): string => $f === 'fila' ? 'numero' : $f, array_column($t['columnas'], 2))
            );
        }
        $this->descargar($libro->contenido(), 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $this->nombreArchivo($datos, 'xlsx'));
    }

    // GET /?ruta=reporte_pdf&desde=...&hasta=...[&alcance=cadena]: el reporte para imprimir o mandar por correo
    public function pdf(?string $desdePedido, ?string $hastaPedido, ?string $alcancePedido = null): void
    {
        if (!$this->puedeVer()) {
            $this->prohibido('Los reportes son para el administrador de la tienda.');
            return;
        }
        [$desde, $hasta] = $this->rango($desdePedido, $hastaPedido);
        $datos = $this->datos($alcancePedido, $desde, $hasta);
        $pdf = new DocumentoPdf('Reporte de ventas - ' . $datos['nombreAlcance'],
            'Minimarket Mass · generado el ' . date('d/m/Y H:i') . ' por ' . $this->usuario->nombreCompleto());
        $pdf->titulo('Reporte de ventas: ' . $datos['nombreAlcance']);
        $pdf->texto('Del ' . $desde . ' al ' . $hasta . '. Solo ventas emitidas; las anuladas se cuentan aparte. Comparado con '
            . $datos['desdeAnterior'] . ' a ' . $datos['hastaAnterior'] . '.');
        foreach ($this->tablas($datos) as $t) {
            $pdf->subtitulo($t['titulo']);
            if ($t['grafico'] !== null && $t['filas'] !== []) {
                [$etiqueta, $valor] = $t['grafico'];
                $filas = array_slice($t['filas'], 0, 24);
                $pdf->barras(
                    array_map(fn(array $f): string => (string) $f[$etiqueta], $filas),
                    array_map(fn(array $f): float => (float) $f[$valor], $filas),
                    array_map(fn(array $f): string => self::soles((float) $f[$valor]), $filas)
                );
            }
            $pdf->tabla(
                array_map(fn(array $c): array => [$c[0], $c[2] === 'texto' ? 3.0 : 1.4, $c[2] === 'texto' ? 'izq' : 'der'], $t['columnas']),
                array_map(fn(array $f): array => array_map(fn(array $c): string => self::formatear($f[$c[1]] ?? null, $c[2] === 'fila' ? $f['formato'] : $c[2]), $t['columnas']), $t['filas'])
            );
        }
        $this->descargar($pdf->contenido(), 'application/pdf', $this->nombreArchivo($datos, 'pdf'));
    }

    /**
     * Todo lo que muestra el reporte, para la pantalla y para los archivos.
     * @return array<string, mixed>
     */
    private function datos(?string $alcancePedido, string $desde, string $hasta): array
    {
        [$ids, $nombreAlcance, $esCadena] = $this->alcance($alcancePedido);
        [$desdeAnterior, $hastaAnterior] = [self::mesAnterior($desde), self::mesAnterior($hasta)];
        $resumen = $this->reportes->resumen($ids, $desde, $hasta);
        $resumenAnterior = $this->reportes->resumen($ids, $desdeAnterior, $hastaAnterior);
        $comparativo = [];
        foreach ([
            'Vendido' => ['total', 'soles'], 'Neto (sin devoluciones)' => ['neto', 'soles'], 'Comprobantes' => ['comprobantes', 'numero'],
            'Ticket promedio' => ['ticketPromedio', 'soles'], 'Anuladas' => ['anuladas', 'numero'],
        ] as $dato => [$clave, $formato]) {
            $actual = (float) $resumen[$clave];
            $anterior = (float) $resumenAnterior[$clave];
            $comparativo[] = [
                'dato' => $dato, 'formato' => $formato, 'actual' => $actual, 'anterior' => $anterior,
                'variacion' => $anterior > 0 ? round(($actual - $anterior) / $anterior * 100, 1) : null,
            ];
        }
        // Las 24 horas, con 0 en las que no hubo ventas, recortadas a la primera y la ultima hora con ventas
        $horas = array_column($this->reportes->porHora($ids, $desde, $hasta), null, 'hora');
        $porHora = [];
        if ($horas !== []) {
            for ($h = (int) min(array_keys($horas)); $h <= (int) max(array_keys($horas)); $h++) {
                $porHora[] = ['hora' => sprintf('%02d:00', $h), 'comprobantes' => (int) ($horas[$h]['comprobantes'] ?? 0),
                    'total' => (float) ($horas[$h]['total'] ?? 0)];
            }
        }
        // Para el grafico: todos los dias del rango, con 0 en los que no se vendio (si no, un hueco no se nota)
        $porDia = $this->reportes->porDia($ids, $desde, $hasta);
        $totalDe = array_column($porDia, null, 'dia');
        $diasGrafico = [];
        for ($d = new DateTimeImmutable($desde); $d->format('Y-m-d') <= $hasta; $d = $d->modify('+1 day')) {
            $dia = $d->format('Y-m-d');
            $diasGrafico[] = ['dia' => $dia, 'comprobantes' => (int) ($totalDe[$dia]['comprobantes'] ?? 0), 'total' => (float) ($totalDe[$dia]['total'] ?? 0)];
        }
        return [
            'desde' => $desde, 'hasta' => $hasta, 'esCadena' => $esCadena, 'nombreAlcance' => $nombreAlcance,
            'desdeAnterior' => $desdeAnterior, 'hastaAnterior' => $hastaAnterior,
            'resumen' => $resumen, 'resumenAnterior' => $resumenAnterior, 'comparativo' => $comparativo,
            'porDia' => $porDia,
            'diasGrafico' => $diasGrafico,
            'porHora' => $porHora,
            'porTipo' => $this->reportes->porTipoComprobante($ids, $desde, $hasta),
            'masVendidos' => $this->reportes->masVendidos($ids, $desde, $hasta),
            'porCategoria' => $this->reportes->porCategoria($ids, $desde, $hasta),
            'porCajero' => $this->reportes->porCajero($ids, $desde, $hasta),
            'porMetodo' => $this->reportes->porMetodoPago($ids, $desde, $hasta),
            'porTienda' => $esCadena ? $this->reportes->porTienda($ids, $desde, $hasta) : [],
        ];
    }

    /**
     * Las tablas del reporte, en el orden en que salen en Excel y PDF.
     * columnas: [titulo, clave de la fila, formato]; grafico: [clave de la etiqueta, clave del valor] o null
     * @param array<string, mixed> $d
     * @return array<int, array{titulo: string, columnas: array<int, array{0: string, 1: string, 2: string}>, filas: array<int, array<string, mixed>>, grafico: ?array{0: string, 1: string}}>
     */
    private function tablas(array $d): array
    {
        $tablas = [[
            'titulo' => 'Comparado con el mes anterior',
            'columnas' => [['Dato', 'dato', 'texto'], ['Este periodo', 'actual', 'fila'], ['Mes anterior', 'anterior', 'fila'], ['Variación %', 'variacion', 'porcentaje']],
            'filas' => $d['comparativo'], 'grafico' => null,
        ]];
        if ($d['esCadena']) {
            $tablas[] = ['titulo' => 'Por tienda', 'columnas' => [['Tienda', 'tienda', 'texto'], ['Comprobantes', 'comprobantes', 'numero'],
                ['Total', 'total', 'soles'], ['Ticket promedio', 'ticket', 'soles']], 'filas' => $d['porTienda'], 'grafico' => ['tienda', 'total']];
        }
        return [...$tablas,
            ['titulo' => 'Ventas por día', 'columnas' => [['Día', 'dia', 'texto'], ['Comprobantes', 'comprobantes', 'numero'], ['Total', 'total', 'soles']],
                'filas' => $d['porDia'], 'grafico' => null],
            ['titulo' => 'Ventas por hora', 'columnas' => [['Hora', 'hora', 'texto'], ['Comprobantes', 'comprobantes', 'numero'], ['Total', 'total', 'soles']],
                'filas' => $d['porHora'], 'grafico' => ['hora', 'total']],
            ['titulo' => 'Productos más vendidos', 'columnas' => [['Producto', 'producto', 'texto'], ['Unidad', 'unidad', 'texto'],
                ['Cantidad', 'cantidad', 'numero'], ['Importe', 'importe', 'soles']], 'filas' => $d['masVendidos'], 'grafico' => ['producto', 'importe']],
            ['titulo' => 'Por cajero', 'columnas' => [['Cajero', 'cajero', 'texto'], ['Comprobantes', 'comprobantes', 'numero'], ['Total', 'total', 'soles']],
                'filas' => $d['porCajero'], 'grafico' => ['cajero', 'total']],
            ['titulo' => 'Por método de pago', 'columnas' => [['Método', 'metodo', 'texto'], ['Pagos', 'pagos', 'numero'], ['Monto', 'monto', 'soles']],
                'filas' => $d['porMetodo'], 'grafico' => null],
            ['titulo' => 'Por categoría', 'columnas' => [['Categoría', 'categoria', 'texto'], ['Importe', 'importe', 'soles']],
                'filas' => $d['porCategoria'], 'grafico' => null],
            ['titulo' => 'Boletas y facturas', 'columnas' => [['Tipo', 'tipo', 'texto'], ['Comprobantes', 'comprobantes', 'numero'], ['Total', 'total', 'soles']],
                'filas' => $d['porTipo'], 'grafico' => null],
        ];
    }

    /**
     * La tienda del usuario o, si lo pide quien ve la cadena completa, todas las tiendas juntas.
     * El cajero y el almacenero ni llegan aqui; un jefe de tienda que no ve la cadena se queda con la suya.
     * @return array{0: int[], 1: string, 2: bool} ids de las tiendas, nombre para el titulo y si es el consolidado
     */
    private function alcance(?string $pedido): array
    {
        if ($pedido === 'cadena' && $this->usuario->puedeAbrirTiendas()) {
            $todas = $this->tiendas->todas();
            return [array_keys($todas), 'toda la cadena (' . count($todas) . ' tiendas)', true];
        }
        $tienda = $this->tiendas->buscarPorId($this->usuario->getIdTienda());
        return [[$this->usuario->getIdTienda()], $tienda?->getNombreTienda() ?? '', false];
    }

    /**
     * El mismo dia del mes anterior; si no existe (31 de marzo -> febrero) o es fin de mes, el ultimo dia de ese mes.
     * Asi "octubre completo" se compara con "septiembre completo".
     */
    public static function mesAnterior(string $fecha): string
    {
        $d = new DateTimeImmutable($fecha);
        $mesAnterior = $d->modify('first day of previous month');
        $diasDelMes = (int) $mesAnterior->format('t');
        $dia = (int) $d->format('j') === (int) $d->format('t') ? $diasDelMes : min((int) $d->format('j'), $diasDelMes);
        return $mesAnterior->setDate((int) $mesAnterior->format('Y'), (int) $mesAnterior->format('n'), $dia)->format('Y-m-d');
    }

    /** @return array<string, array{0: string, 1: string}> nombre del atajo => [desde, hasta] */
    private function atajos(): array
    {
        $hoy = new DateTimeImmutable('today');
        return [
            'Este mes'          => [$hoy->modify('first day of this month')->format('Y-m-d'), $hoy->format('Y-m-d')],
            'Mes anterior'      => [$hoy->modify('first day of previous month')->format('Y-m-d'), $hoy->modify('last day of previous month')->format('Y-m-d')],
            'Últimos 30 días'   => [$hoy->modify('-' . self::DIAS_POR_DEFECTO . ' days')->format('Y-m-d'), $hoy->format('Y-m-d')],
        ];
    }

    // Igual que soles() de las vistas: "S/ 1,234.50"
    private static function soles(float $monto): string
    {
        return 'S/ ' . number_format($monto, 2);
    }

    private static function formatear(mixed $valor, string $formato): string
    {
        if ($valor === null || $valor === '') {
            return '-';
        }
        return match ($formato) {
            'soles'      => self::soles((float) $valor),
            'numero'     => rtrim(rtrim(number_format((float) $valor, 3), '0'), '.'),
            'porcentaje' => ((float) $valor > 0 ? '+' : '') . number_format((float) $valor, 1) . ' %',
            default      => (string) $valor,
        };
    }

    /** @param array<string, mixed> $datos */
    private function nombreArchivo(array $datos, string $extension): string
    {
        return sprintf('reporte_ventas_%s_%s_%s.%s', $datos['esCadena'] ? 'cadena' : 'tienda' . $this->usuario->getIdTienda(),
            $datos['desde'], $datos['hasta'], $extension);
    }

    private function descargar(string $contenido, string $tipo, string $nombreArchivo): void
    {
        header('Content-Type: ' . $tipo);
        header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9_.-]/', '_', $nombreArchivo) . '"');
        header('Content-Length: ' . strlen($contenido));
        echo $contenido;
    }

    /**
     * Rango de fechas pedido, o los ultimos 30 dias. Maximo un año, para no pedir consultas enormes.
     * @return array{0: string, 1: string, 2: ?string} desde, hasta y un aviso si se corrigio algo
     */
    private function rango(?string $desdePedido, ?string $hastaPedido): array
    {
        $hoy = new DateTimeImmutable('today');
        $hasta = $this->leerFecha($hastaPedido) ?? $hoy->format('Y-m-d');
        $desde = $this->leerFecha($desdePedido)
            ?? (new DateTimeImmutable($hasta))->modify('-' . self::DIAS_POR_DEFECTO . ' days')->format('Y-m-d');
        $error = null;
        if ($desde > $hasta) {
            [$desde, $hasta] = [$hasta, $desde];
            $error = 'La fecha inicial era posterior a la final: se invirtieron.';
        }
        $limite = (new DateTimeImmutable($hasta))->modify('-' . self::DIAS_MAXIMO . ' days')->format('Y-m-d');
        if ($desde < $limite) {
            $desde = $limite;
            $error = 'El rango máximo es de un año.';
        }
        return [$desde, $hasta, $error];
    }

    private function puedeVer(): bool
    {
        return $this->usuario !== null && $this->usuario->puedeAdministrarTienda($this->usuario->getIdTienda());
    }
}
