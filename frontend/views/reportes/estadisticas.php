<?php
declare(strict_types=1);
/**
 * V: estadisticas de lo que se vende y lo que no, con mapas de calor y opiniones escritas.
 * De la tienda del administrador o, para quien ve la cadena, de todas las tiendas juntas.
 * Los numeros salen de ReporteController::estadisticas; solo ventas EMITIDAS.
 * @var Tienda $tienda
 * @var string $desde
 * @var string $hasta
 * @var ?string $error
 * @var bool $esCadena
 * @var bool $puedeVerCadena
 * @var string $nombreAlcance
 * @var array<string, array{0: string, 1: string}> $atajos
 * @var array<int, array<string, mixed>> $masVendidos
 * @var array<int, array<string, mixed>> $menosVendidos
 * @var array<int, array<string, mixed>> $sinVentas
 * @var array<string, mixed> $resumen
 * @var array{horas: int[], dias: array<int, array{nombre: string, casillas: array<int, array{total: float, comprobantes: int, nivel: float}>, total: float}>, maximo: float, pico: ?array{dia: string, hora: int, total: float}} $calorHoras
 * @var array{filas: array<int, array{producto: string, casillas: array<int, array{importe: float, nivel: float}>}>, maximo: float} $calorProductos
 * @var array<int, array{tipo: string, titulo: string, texto: string}> $opiniones
 */
$titulo = 'Estadísticas';
require __DIR__ . '/../layout_inicio.php';

$alcance = $esCadena ? ['alcance' => 'cadena'] : [];
$enlace = static fn(array $extra): string => '/?' . http_build_query([...$extra, ...$alcance]);
$cantidad = static fn(array $p): string => number_format((float) $p['cantidad'], $p['alPeso'] ? 3 : 0) . ' ' . $p['unidad'];
$diasCortos = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'];
// Una casilla del mapa: el color sube con el nivel (0 a 1); sobre un amarillo fuerte el texto va oscuro
$casilla = static function (float $nivel, string $texto, string $detalle): void {
    $clase = $nivel <= 0 ? 'vacia' : ($nivel >= .55 ? 'fuerte' : '');
    $intensidad = number_format(max(0.0, min(1.0, $nivel)), 3, '.', '');
    echo '<td class="calor-casilla ' . e($clase) . '" style="--nivel: ' . e($intensidad) . '" title="' . e($detalle) . '">';
    if ($nivel <= 0) {
        echo '<span aria-hidden="true">·</span><span class="visualmente-oculto">0</span>';
    } else {
        echo e($texto);
    }
    echo '</td>';
};
$corto = static fn(float $m): string => $m >= 1000 ? number_format($m / 1000, 1) . 'k' : number_format($m, 0);
?>
<div class="cabecera">
    <div>
        <h1>Estadísticas de <?= e($esCadena ? 'toda la cadena' : $tienda->getNombreTienda()) ?></h1>
        <p class="sutil">Qué se vende y qué no, entre <?= e(fecha($desde)) ?> y <?= e(fecha($hasta)) ?><?= e($esCadena ? ', sumando ' . $nombreAlcance : '') ?>. Solo ventas emitidas.</p>
    </div>
    <div class="acciones">
        <a class="boton secundario" href="<?= e($enlace(['ruta' => 'reportes', 'desde' => $desde, 'hasta' => $hasta])) ?>"><?= icono('reportes') ?>Reportes de venta</a>
    </div>
</div>
<?php
$guiaClave = 'estadisticas';
$guiaTitulo = 'Cómo leer las estadísticas';
$guiaPasos = [
    'Arriba están las conclusiones: qué hacer según lo que muestran los números.',
    'En el mapa de calor, cuanto más amarillo la casilla, más se vendió ese día a esa hora. Pasa el cursor para ver el monto exacto.',
    '«No se vendió nada» lista lo que está en el estante y no salió en estas fechas, primero lo que más plata tiene parada.',
];
require __DIR__ . '/../parciales/guia.php';
?>
<?php if ($puedeVerCadena): ?>
<nav class="chips alcance-reporte" aria-label="Qué tiendas ver">
    <a href="/?<?= e(http_build_query(['ruta' => 'estadisticas', 'desde' => $desde, 'hasta' => $hasta])) ?>"<?= $esCadena ? '' : ' aria-current="true"' ?>><?= icono('tiendas') ?><?= e($tienda->getNombreTienda()) ?></a>
    <a href="/?<?= e(http_build_query(['ruta' => 'estadisticas', 'desde' => $desde, 'hasta' => $hasta, 'alcance' => 'cadena'])) ?>"<?= $esCadena ? ' aria-current="true"' : '' ?>><?= icono('reportes') ?>Todas las tiendas</a>
</nav>
<?php endif; ?>
<form class="filtros" method="get" action="/">
    <input type="hidden" name="ruta" value="estadisticas">
    <?php if ($esCadena): ?><input type="hidden" name="alcance" value="cadena"><?php endif; ?>
    <label>Desde <input type="date" name="desde" value="<?= e($desde) ?>" required></label>
    <label>Hasta <input type="date" name="hasta" value="<?= e($hasta) ?>" required></label>
    <button type="submit">Ver</button>
</form>
<nav class="chips atajos-fecha" aria-label="Atajos de fechas">
    <?php foreach ($atajos as $nombre => [$d, $h]): ?>
        <a href="<?= e($enlace(['ruta' => 'estadisticas', 'desde' => $d, 'hasta' => $h])) ?>"<?= $d === $desde && $h === $hasta ? ' aria-current="true"' : '' ?>><?= e($nombre) ?></a>
    <?php endforeach; ?>
</nav>
<?php if ($error !== null): ?><div class="aviso error" role="alert"><?= e($error) ?></div><?php endif; ?>

<section class="tarjetas" aria-label="Resumen del periodo">
    <div><span>Vendido</span><strong><?= e(soles($resumen['total'])) ?></strong><small>de <?= (int) $resumen['conVentas'] ?> productos</small></div>
    <div><span>Productos que se venden</span><strong><?= (int) $resumen['conVentas'] ?> de <?= (int) $resumen['catalogo'] ?></strong><small>del catálogo de la tienda</small></div>
    <div><span>No se vendieron</span><strong><?= (int) $resumen['sinVentas'] ?></strong><small><?= e(soles($resumen['valorParado'])) ?> parados en el estante</small></div>
    <div><span>Regla 80/20</span><strong><?= $resumen['porcentajeTop'] === null ? '—' : e(number_format($resumen['porcentajeTop'], 0) . ' %') ?></strong><small>lo hacen los <?= (int) $resumen['top'] ?> que más venden</small></div>
</section>

<section class="panel" aria-labelledby="titulo-opiniones">
    <div class="titulo-seccion"><h2 id="titulo-opiniones">Qué nos dicen los números</h2></div>
    <ul class="opiniones">
        <?php foreach ($opiniones as $op): ?>
            <li class="<?= e($op['tipo']) ?>"><?php if ($op['tipo'] === 'alerta'): ?><?= icono('alerta') ?><?php elseif ($op['tipo'] === 'ok'): ?><?= icono('ok') ?><?php else: ?><?= icono('info') ?><?php endif; ?>
                <div><strong><?= e($op['titulo']) ?></strong><span><?= e($op['texto']) ?></span></div></li>
        <?php endforeach; ?>
    </ul>
</section>

<section class="panel" aria-labelledby="titulo-calor-horas">
    <div class="titulo-seccion"><h2 id="titulo-calor-horas">Mapa de calor: día y hora</h2></div>
    <p class="sutil">Total vendido en cada hora de cada día de la semana, sumando todas las semanas de las fechas elegidas (montos en soles; «k» = miles).</p>
    <div class="tabla calor"><table>
        <caption class="visualmente-oculto">Ventas por día de la semana y hora</caption>
        <thead><tr><th scope="col">Día</th>
            <?php foreach ($calorHoras['horas'] as $h): ?><th scope="col" class="num"><?= e(sprintf('%02d', $h)) ?></th><?php endforeach; ?>
            <th scope="col" class="num">Total</th></tr></thead>
        <tbody>
        <?php foreach ($calorHoras['dias'] as $n => $d): ?>
            <tr><th scope="row"><?= e($diasCortos[$n]) ?></th>
                <?php foreach ($d['casillas'] as $h => $c): ?>
                    <?php $casilla($c['nivel'], $corto($c['total']), $d['nombre'] . ' ' . sprintf('%02d:00', $h) . ': ' . soles($c['total']) . ' en ' . $c['comprobantes'] . ' comprobantes'); ?>
                <?php endforeach; ?>
                <td class="num"><strong><?= e(soles($d['total'])) ?></strong></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <div class="calor-leyenda" aria-hidden="true"><span>Menos</span><i></i><span>Más</span></div>
</section>

<?php if ($calorProductos['filas'] !== []): ?>
<section class="panel" aria-labelledby="titulo-calor-productos">
    <div class="titulo-seccion"><h2 id="titulo-calor-productos">Mapa de calor: productos estrella por día</h2></div>
    <p class="sutil">Qué día de la semana se vende más cada uno de los 10 productos que más venden (en soles).</p>
    <div class="tabla calor calor-productos"><table>
        <caption class="visualmente-oculto">Ventas de los productos más vendidos por día de la semana</caption>
        <thead><tr><th scope="col">Producto</th><?php foreach ($diasCortos as $dc): ?><th scope="col" class="num"><?= e($dc) ?></th><?php endforeach; ?></tr></thead>
        <tbody>
        <?php foreach ($calorProductos['filas'] as $f): ?>
            <tr><th scope="row"><?= e($f['producto']) ?></th>
                <?php foreach ($f['casillas'] as $n => $c): ?>
                    <?php $casilla($c['nivel'], $corto($c['importe']), $f['producto'] . ', ' . $diasCortos[$n] . ': ' . soles($c['importe'])); ?>
                <?php endforeach; ?></tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</section>
<?php endif; ?>

<div class="rejilla rejilla-reportes">
<section class="panel" aria-labelledby="titulo-mas">
    <div class="titulo-seccion"><h2 id="titulo-mas">Lo que más se vende</h2></div>
    <div class="tabla"><table>
        <thead><tr><th scope="col">#</th><th scope="col">Producto</th><th scope="col" class="num">Cantidad</th><th scope="col" class="num">Importe</th></tr></thead>
        <tbody>
        <?php if ($masVendidos === []): ?><tr><td class="sutil centro" colspan="4">Sin ventas en estas fechas.</td></tr><?php endif; ?>
        <?php foreach ($masVendidos as $i => $p): ?>
            <tr><td><?= $i + 1 ?></td><td><?= e($p['producto']) ?><br><small class="sutil"><?= e($p['categoria']) ?></small></td>
                <td class="num"><?= e($cantidad($p)) ?></td><td class="num"><strong><?= e(soles($p['importe'])) ?></strong></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</section>
<section class="panel" aria-labelledby="titulo-menos">
    <div class="titulo-seccion"><h2 id="titulo-menos">Lo que casi no sale</h2></div>
    <p class="sutil">Se vendieron, pero muy poco.</p>
    <div class="tabla"><table>
        <thead><tr><th scope="col">Producto</th><th scope="col" class="num">Cantidad</th><th scope="col" class="num">Importe</th><th scope="col" class="num">Stock</th></tr></thead>
        <tbody>
        <?php if ($menosVendidos === []): ?><tr><td class="sutil centro" colspan="4">Sin ventas en estas fechas.</td></tr><?php endif; ?>
        <?php foreach ($menosVendidos as $p): ?>
            <tr><td><?= e($p['producto']) ?></td><td class="num"><?= e($cantidad($p)) ?></td>
                <td class="num"><?= e(soles($p['importe'])) ?></td><td class="num"><?= e(number_format($p['stock'], $p['alPeso'] ? 3 : 0)) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</section>
</div>

<section class="panel" aria-labelledby="titulo-sin">
    <div class="titulo-seccion"><h2 id="titulo-sin">No se vendió nada</h2><span class="etiqueta alerta"><?= count($sinVentas) ?> productos</span></div>
    <p class="sutil">Productos activos del catálogo que no tuvieron ninguna venta en estas fechas. «Valor parado» es su stock por el precio de venta.</p>
    <div class="tabla"><table>
        <thead><tr><th scope="col">Producto</th><th scope="col">Categoría</th><th scope="col" class="num">Stock</th><th scope="col" class="num">Valor parado</th><th scope="col">Última venta</th></tr></thead>
        <tbody>
        <?php if ($sinVentas === []): ?><tr><td class="sutil centro" colspan="5">Todo el catálogo tuvo al menos una venta. ¡Bien!</td></tr><?php endif; ?>
        <?php foreach (array_slice($sinVentas, 0, 50) as $p): ?>
            <tr><td><?= e($p['producto']) ?></td><td><?= e($p['categoria']) ?></td>
                <td class="num"><?= e(number_format($p['stock'], $p['alPeso'] ? 3 : 0) . ' ' . $p['unidad']) ?></td>
                <td class="num"><?= e(soles($p['valorStock'])) ?></td>
                <td><?= $p['ultima'] === null ? '<span class="etiqueta neutra">Nunca</span>' : e(fecha($p['ultima'])) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php if (count($sinVentas) > 50): ?><p class="sutil">Se muestran los 50 con más valor parado de <?= count($sinVentas) ?>.</p><?php endif; ?>
</section>
<?php require __DIR__ . '/../layout_fin.php'; ?>
