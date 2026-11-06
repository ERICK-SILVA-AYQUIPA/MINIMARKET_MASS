<?php
declare(strict_types=1);
/**
 * V: reportes de venta entre dos fechas (solo ventas EMITIDAS), de la tienda o de toda la cadena (consolidado).
 * Cada tabla es una de las consultas 10 a 16 de consultas.sql (mas ventas por hora y por tienda),
 * con su grafico y comparada con el mes anterior. Se descarga en CSV (cada tabla), Excel o PDF (todo el reporte).
 * @var Tienda $tienda
 * @var string $desde
 * @var string $hasta
 * @var string $desdeAnterior
 * @var string $hastaAnterior
 * @var ?string $error
 * @var bool $esCadena
 * @var bool $puedeVerCadena
 * @var string $nombreAlcance
 * @var array<string, array{0: string, 1: string}> $atajos
 * @var array{comprobantes: int, total: float, devuelto: float, neto: float, ticketPromedio: float, anuladas: int} $resumen
 * @var array<int, array{dato: string, formato: string, actual: float, anterior: float, variacion: ?float}> $comparativo
 * @var array<int, array<string, string>> $porDia
 * @var array<int, array{dia: string, comprobantes: int, total: float}> $diasGrafico
 * @var array<int, array{hora: string, comprobantes: int, total: float}> $porHora
 * @var array<int, array<string, string>> $porTipo
 * @var array<int, array<string, string>> $masVendidos
 * @var array<int, array<string, string>> $porCategoria
 * @var array<int, array<string, string>> $porCajero
 * @var array<int, array<string, string>> $porMetodo
 * @var array<int, array<string, string>> $porTienda
 */
$titulo = 'Reportes';
require __DIR__ . '/../layout_inicio.php';

$alcance = $esCadena ? ['alcance' => 'cadena'] : [];
$enlace = static fn(array $extra): string => '/?' . http_build_query([...$extra, ...$alcance]);
$csv = static fn(string $tipo): string => $enlace(['ruta' => 'reporte_csv', 'tipo' => $tipo, 'desde' => $desde, 'hasta' => $hasta]);
$numero = static fn(float $v): string => rtrim(rtrim(number_format($v, 3), '0'), '.');
$porcentaje = static fn(?float $v): string => $v === null ? '—' : ($v > 0 ? '+' : '') . number_format($v, 1) . ' %';
$variacion = array_column($comparativo, 'variacion', 'dato');

// Dibuja una tabla de reporte: [titulo de columna => [clave de la fila, formato]]
$tabla = static function (array $filas, array $columnas) use ($numero): void {
    echo '<div class="tabla"><table><thead><tr>';
    foreach ($columnas as $titulo => [, $formato]) {
        echo '<th scope="col" class="' . e($formato === 'texto' ? '' : 'num') . '">' . e($titulo) . '</th>';
    }
    echo '</tr></thead><tbody>';
    if ($filas === []) {
        echo '<tr><td class="sutil centro" colspan="' . count($columnas) . '">Sin ventas en estas fechas.</td></tr>';
    }
    foreach ($filas as $fila) {
        echo '<tr>';
        foreach ($columnas as [$clave, $formato]) {
            $v = (string) $fila[$clave];
            echo match ($formato) {
                'soles'    => '<td class="num">' . e(soles((float) $v)) . '</td>',
                'numero'   => '<td class="num">' . e($numero((float) $v)) . '</td>',
                default    => '<td>' . e($v) . '</td>',
            };
        }
        echo '</tr>';
    }
    echo '</tbody></table></div>';
};

// Barras horizontales (un solo dato por fila: la etiqueta la nombra, sin leyenda). El valor exacto va escrito al lado.
$barras = static function (array $filas, string $etiqueta, string $valor, string $descripcion): void {
    if ($filas === []) {
        return;
    }
    $maximo = max(array_map(fn(array $f): float => (float) $f[$valor], $filas)) ?: 1.0;
    echo '<ul class="barras-h" aria-label="' . e($descripcion) . '">';
    foreach ($filas as $f) {
        $ancho = max(1, (int) round((float) $f[$valor] / $maximo * 100));
        echo '<li title="' . e($f[$etiqueta] . ': ' . soles((float) $f[$valor])) . '"><span class="nombre">' . e((string) $f[$etiqueta]) . '</span>'
            . '<span class="pista"><span style="width: ' . $ancho . '%"></span></span>'
            . '<span class="valor">' . e(soles((float) $f[$valor])) . '</span></li>';
    }
    echo '</ul>';
};

// Barras verticales (dias u horas), igual que el grafico del panel de inicio
$columnas = static function (array $filas, string $etiqueta, string $descripcion, callable $corta): void {
    if (array_sum(array_column($filas, 'total')) <= 0) {
        return;
    }
    $cada = (int) ceil(count($filas) / 16); // a lo mas unas 16 etiquetas abajo, para que no se encimen
    $maximo = max(array_map(fn(array $f): float => (float) $f['total'], $filas)) ?: 1.0;
    echo '<div class="grafico" role="img" aria-label="' . e($descripcion) . '">';
    foreach (array_values($filas) as $i => $f) {
        $alto = (int) round((float) $f['total'] / $maximo * 100);
        echo '<div class="barra" title="' . e($f[$etiqueta] . ': ' . soles((float) $f['total']) . ' en ' . $f['comprobantes'] . ' comprobantes') . '">'
            . '<span style="height: ' . $alto . '%"></span><small>' . e($i % $cada === 0 ? $corta((string) $f[$etiqueta]) : '') . '</small></div>';
    }
    echo '</div>';
};

$tendencia = static function (?float $v) use ($porcentaje): string {
    if ($v === null) {
        return 'sin ventas el mes anterior';
    }
    return $porcentaje($v) . ' vs. mes anterior';
};
?>
<div class="cabecera">
    <div>
        <h1>Reportes de <?= e($esCadena ? 'toda la cadena' : $tienda->getNombreTienda()) ?></h1>
        <p class="sutil">Solo ventas EMITIDAS entre <?= e(fecha($desde)) ?> y <?= e(fecha($hasta)) ?><?= e($esCadena ? ', sumando ' . $nombreAlcance : '') ?>.
            Se compara con <?= e(fecha($desdeAnterior)) ?> a <?= e(fecha($hastaAnterior)) ?>.</p>
    </div>
    <div class="acciones">
        <a class="boton secundario" href="<?= e($enlace(['ruta' => 'reporte_excel', 'desde' => $desde, 'hasta' => $hasta])) ?>"><?= icono('descarga') ?>Excel</a>
        <a class="boton secundario" href="<?= e($enlace(['ruta' => 'reporte_pdf', 'desde' => $desde, 'hasta' => $hasta])) ?>"><?= icono('imprimir') ?>PDF</a>
        <a class="boton secundario" href="<?= e($enlace(['ruta' => 'estadisticas', 'desde' => $desde, 'hasta' => $hasta])) ?>"><?= icono('calor') ?>Estadísticas</a>
        <a class="boton secundario" href="/?ruta=utilidad&amp;desde=<?= e($desde) ?>&amp;hasta=<?= e($hasta) ?>"><?= icono('utilidad') ?>Ver utilidad</a>
    </div>
</div>
<?php
$guiaClave = 'reportes';
$guiaTitulo = 'Cómo leer los reportes';
$guiaPasos = [
    'Elige las fechas (o un atajo como Este mes) y toca Ver. Solo cuentan las ventas emitidas; las anuladas se muestran aparte.',
    'Cada número se compara con las mismas fechas del mes anterior: verde si subió, rojo si bajó.',
    'Excel y PDF bajan todo el reporte; cada tabla también se baja sola en CSV. En Utilidad ves cuánto se gana, no solo cuánto se vende.',
];
if ($puedeVerCadena) {
    $guiaPasos[] = 'Con «Todas las tiendas» ves la cadena sumada y la comparación entre sucursales.';
}
require __DIR__ . '/../parciales/guia.php';
?>
<?php if ($puedeVerCadena): ?>
<nav class="chips alcance-reporte" aria-label="Qué tiendas ver">
    <a href="/?<?= e(http_build_query(['ruta' => 'reportes', 'desde' => $desde, 'hasta' => $hasta])) ?>"<?= $esCadena ? '' : ' aria-current="true"' ?>><?= icono('tiendas') ?><?= e($tienda->getNombreTienda()) ?></a>
    <a href="/?<?= e(http_build_query(['ruta' => 'reportes', 'desde' => $desde, 'hasta' => $hasta, 'alcance' => 'cadena'])) ?>"<?= $esCadena ? ' aria-current="true"' : '' ?>><?= icono('reportes') ?>Todas las tiendas</a>
</nav>
<?php endif; ?>
<form class="filtros" method="get" action="/">
    <input type="hidden" name="ruta" value="reportes">
    <?php if ($esCadena): ?><input type="hidden" name="alcance" value="cadena"><?php endif; ?>
    <label>Desde <input type="date" name="desde" value="<?= e($desde) ?>" required></label>
    <label>Hasta <input type="date" name="hasta" value="<?= e($hasta) ?>" required></label>
    <button type="submit">Ver</button>
</form>
<nav class="chips atajos-fecha" aria-label="Atajos de fechas">
    <?php foreach ($atajos as $nombre => [$d, $h]): ?>
        <a href="<?= e($enlace(['ruta' => 'reportes', 'desde' => $d, 'hasta' => $h])) ?>"<?= $d === $desde && $h === $hasta ? ' aria-current="true"' : '' ?>><?= e($nombre) ?></a>
    <?php endforeach; ?>
</nav>
<?php if ($error !== null): ?><div class="aviso error" role="alert"><?= e($error) ?></div><?php endif; ?>

<section class="tarjetas" aria-label="Resumen del periodo">
    <?php foreach ([
        ['Vendido', soles($resumen['total']), 'Vendido'],
        ['Neto', soles($resumen['neto']), 'Neto (sin devoluciones)'],
        ['Comprobantes', (string) $resumen['comprobantes'], 'Comprobantes'],
        ['Ticket promedio', soles($resumen['ticketPromedio']), 'Ticket promedio'],
    ] as [$nombre, $valor, $dato]): ?>
        <?php $v = $variacion[$dato] ?? null; ?>
        <div><span><?= e($nombre) ?></span><strong><?= e($valor) ?></strong>
            <small class="<?= e($v === null ? '' : ($v >= 0 ? 'texto-ok' : 'texto-error')) ?>"><?= e($tendencia($v)) ?></small></div>
    <?php endforeach; ?>
    <div><span>Anuladas</span><strong><?= (int) $resumen['anuladas'] ?></strong><small>devuelto <?= e(soles($resumen['devuelto'])) ?></small></div>
</section>

<section class="panel">
    <div class="titulo-seccion"><h2>Comparado con el mes anterior</h2></div>
    <div class="tabla"><table>
        <thead><tr><th scope="col">Dato</th><th scope="col" class="num"><?= e(fecha($desde)) ?> a <?= e(fecha($hasta)) ?></th>
            <th scope="col" class="num"><?= e(fecha($desdeAnterior)) ?> a <?= e(fecha($hastaAnterior)) ?></th><th scope="col" class="num">Variación</th></tr></thead>
        <tbody>
        <?php foreach ($comparativo as $c): ?>
            <?php $ver = static fn(float $x): string => $c['formato'] === 'soles' ? soles($x) : $numero($x); ?>
            <?php // En anuladas, subir es malo
            $bueno = $c['variacion'] === null ? null : ($c['dato'] === 'Anuladas' ? $c['variacion'] <= 0 : $c['variacion'] >= 0); ?>
            <tr><td><?= e($c['dato']) ?></td><td class="num"><?= e($ver($c['actual'])) ?></td><td class="num"><?= e($ver($c['anterior'])) ?></td>
                <td class="num <?= e($bueno === null ? 'sutil' : ($bueno ? 'texto-ok' : 'texto-error')) ?>"><?= e($porcentaje($c['variacion'])) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</section>

<?php if ($esCadena): ?>
<section class="panel">
    <div class="titulo-seccion"><h2>Por tienda</h2><a class="boton secundario chico" href="<?= e($csv('tiendas')) ?>"><?= icono('descarga') ?>CSV</a></div>
    <?php $barras($porTienda, 'tienda', 'total', 'Total vendido por tienda'); ?>
    <?php $tabla($porTienda, ['Tienda' => ['tienda', 'texto'], 'Comprobantes' => ['comprobantes', 'numero'], 'Total' => ['total', 'soles'], 'Ticket promedio' => ['ticket', 'soles']]); ?>
</section>
<?php endif; ?>

<div class="rejilla rejilla-reportes">
<section class="panel">
    <div class="titulo-seccion"><h2>Ventas por día</h2><a class="boton secundario chico" href="<?= e($csv('dias')) ?>"><?= icono('descarga') ?>CSV</a></div>
    <?php $columnas($diasGrafico, 'dia', 'Total vendido por día', fn(string $d): string => substr($d, 8, 2)); ?>
    <?php $tabla($porDia, ['Día' => ['dia', 'texto'], 'Comprobantes' => ['comprobantes', 'numero'], 'Total' => ['total', 'soles']]); ?>
</section>
<section class="panel">
    <div class="titulo-seccion"><h2>Ventas por hora</h2><a class="boton secundario chico" href="<?= e($csv('horas')) ?>"><?= icono('descarga') ?>CSV</a></div>
    <?php $columnas($porHora, 'hora', 'Total vendido por hora del día', fn(string $h): string => substr($h, 0, 2)); ?>
    <?php $tabla(array_filter($porHora, fn(array $h): bool => $h['comprobantes'] > 0), ['Hora' => ['hora', 'texto'], 'Comprobantes' => ['comprobantes', 'numero'], 'Total' => ['total', 'soles']]); ?>
</section>
<section class="panel">
    <div class="titulo-seccion"><h2>Productos más vendidos</h2><a class="boton secundario chico" href="<?= e($csv('productos')) ?>"><?= icono('descarga') ?>CSV</a></div>
    <?php $barras($masVendidos, 'producto', 'importe', 'Importe de los productos más vendidos'); ?>
    <?php $tabla($masVendidos, ['Producto' => ['producto', 'texto'], 'Unidad' => ['unidad', 'texto'], 'Cantidad' => ['cantidad', 'numero'], 'Importe' => ['importe', 'soles']]); ?>
</section>
<section class="panel">
    <div class="titulo-seccion"><h2>Por cajero</h2><a class="boton secundario chico" href="<?= e($csv('cajeros')) ?>"><?= icono('descarga') ?>CSV</a></div>
    <?php $barras($porCajero, 'cajero', 'total', 'Total vendido por cajero'); ?>
    <?php $tabla($porCajero, ['Cajero' => ['cajero', 'texto'], 'Comprobantes' => ['comprobantes', 'numero'], 'Total' => ['total', 'soles']]); ?>
</section>
<section class="panel">
    <div class="titulo-seccion"><h2>Por método de pago</h2><a class="boton secundario chico" href="<?= e($csv('metodos')) ?>"><?= icono('descarga') ?>CSV</a></div>
    <?php $tabla($porMetodo, ['Método' => ['metodo', 'texto'], 'Pagos' => ['pagos', 'numero'], 'Monto' => ['monto', 'soles']]); ?>
</section>
<section class="panel">
    <div class="titulo-seccion"><h2>Por categoría</h2></div>
    <?php $tabla($porCategoria, ['Categoría' => ['categoria', 'texto'], 'Importe' => ['importe', 'soles']]); ?>
</section>
<section class="panel">
    <div class="titulo-seccion"><h2>Boletas y facturas</h2></div>
    <?php $tabla($porTipo, ['Tipo' => ['tipo', 'texto'], 'Comprobantes' => ['comprobantes', 'numero'], 'Total' => ['total', 'soles']]); ?>
</section>
</div>
<?php require __DIR__ . '/../layout_fin.php'; ?>
