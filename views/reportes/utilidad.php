<?php
declare(strict_types=1);
/**
 * V: utilidad de la tienda entre dos fechas: ventas netas (menos devoluciones) menos el costo de lo vendido.
 * El costo sale del promedio de los ingresos de mercaderia (ver ReporteRepository::utilidadPorProducto).
 * @var Tienda $tienda
 * @var string $desde
 * @var string $hasta
 * @var ?string $error
 * @var array<int, array{producto: string, unidad: string, al_peso: bool, cantidad: float, ventas: float, costo_unitario: ?float, costo: ?float, utilidad: ?float, margen: ?float}> $productos
 * @var array{ventas: float, costo: float, utilidad: float, sinCosto: int, margen: ?float} $totales
 * @var array<int, array{tienda: string, ventas: float, conCosto: float, costo: float, utilidad: float, margen: ?float}> $porTienda
 */
$titulo = 'Utilidad';
require __DIR__ . '/../layout_inicio.php';

$csv = '/?' . http_build_query(['ruta' => 'reporte_csv', 'tipo' => 'utilidad', 'desde' => $desde, 'hasta' => $hasta]);
$porcentaje = static fn(?float $m): string => $m === null ? '—' : number_format($m, 1) . ' %';
$margenTotal = $porcentaje($totales['margen']);
?>
<div class="cabecera">
    <div>
        <h1>Utilidad de <?= e($tienda->getNombreTienda()) ?></h1>
        <p class="sutil">Ventas EMITIDAS entre <?= e($desde) ?> y <?= e($hasta) ?>, menos sus devoluciones y menos el costo promedio de compra de cada producto.</p>
    </div>
    <div class="acciones">
        <a class="boton secundario" href="/?ruta=reportes&amp;desde=<?= e($desde) ?>&amp;hasta=<?= e($hasta) ?>"><?= icono('reportes') ?>Reportes de venta</a>
        <?php if ($productos !== []): ?><a class="boton secundario" href="<?= e($csv) ?>"><?= icono('descarga') ?>CSV</a><?php endif; ?>
    </div>
</div>
<form class="filtros" method="get" action="/">
    <input type="hidden" name="ruta" value="utilidad">
    <label>Desde <input type="date" name="desde" value="<?= e($desde) ?>" required></label>
    <label>Hasta <input type="date" name="hasta" value="<?= e($hasta) ?>" required></label>
    <button type="submit">Ver</button>
</form>
<?php if ($error !== null): ?><div class="aviso error" role="alert"><?= e($error) ?></div><?php endif; ?>

<section class="tarjetas" aria-label="Resumen del periodo">
    <div><span>Ventas netas</span><strong><?= e(soles($totales['ventas'])) ?></strong><small>sin devoluciones</small></div>
    <div><span>Costo de lo vendido</span><strong><?= e(soles($totales['costo'])) ?></strong><small>productos con costo</small></div>
    <div><span>Utilidad</span><strong><?= e(soles($totales['utilidad'])) ?></strong><small>margen <?= e($margenTotal) ?> de lo que tiene costo</small></div>
    <div><span>Sin costo</span><strong><?= (int) $totales['sinCosto'] ?></strong><small>productos sin ingresos</small></div>
</section>
<?php if ($totales['sinCosto'] > 0): ?>
    <div class="aviso alerta"><span>Hay productos vendidos que nunca entraron con un ingreso de mercadería: no se conoce su costo y no suman a la utilidad. Registra su próxima compra en <a href="/?ruta=ingresos">Ingresos</a>.</span></div>
<?php endif; ?>

<?php if ($porTienda !== []): ?>
<section class="panel">
    <h2>Por tienda</h2>
    <div class="tabla"><table>
        <thead><tr><th scope="col">Tienda</th><th scope="col" class="num">Ventas netas</th><th scope="col" class="num">Costo</th><th scope="col" class="num">Utilidad</th><th scope="col" class="num">Margen</th></tr></thead>
        <tbody>
        <?php foreach ($porTienda as $t): ?>
            <?php $margenTienda = $porcentaje($t['margen']); ?>
            <tr>
                <td><?= e($t['tienda']) ?></td>
                <td class="num"><?= e(soles($t['ventas'])) ?></td>
                <td class="num"><?= e(soles($t['costo'])) ?></td>
                <td class="num"><strong><?= e(soles($t['utilidad'])) ?></strong></td>
                <td class="num"><?= e($margenTienda) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <p class="sutil">El margen de cada tienda se calcula solo con los productos que tienen costo.</p>
</section>
<?php endif; ?>

<section class="panel">
    <h2>Por producto</h2>
    <?php if ($productos === []): ?>
        <p class="sutil">Sin ventas en estas fechas.</p>
    <?php else: ?>
    <div class="tabla"><table>
        <thead><tr><th scope="col">Producto</th><th scope="col" class="num">Cantidad</th><th scope="col" class="num">Ventas netas</th><th scope="col" class="num">Costo unit.</th><th scope="col" class="num">Costo</th><th scope="col" class="num">Utilidad</th><th scope="col" class="num">Margen</th></tr></thead>
        <tbody>
        <?php foreach ($productos as $p): ?>
            <?php
            $cantidadTexto = number_format($p['cantidad'], $p['al_peso'] ? 3 : 0) . ' ' . $p['unidad'];
            $costoUnitario = $p['costo_unitario'] === null ? 'sin costo' : soles($p['costo_unitario']);
            $costo = $p['costo'] === null ? '—' : soles($p['costo']);
            $utilidad = $p['utilidad'] === null ? '—' : soles($p['utilidad']);
            $margen = $porcentaje($p['margen']);
            $claseMargen = $p['margen'] === null ? 'neutra' : ($p['margen'] < 0 ? 'error' : ($p['margen'] < 15 ? 'alerta' : 'ok'));
            ?>
            <tr>
                <td><?= e($p['producto']) ?></td>
                <td class="num"><?= e($cantidadTexto) ?></td>
                <td class="num"><?= e(soles($p['ventas'])) ?></td>
                <td class="num"><?= e($costoUnitario) ?></td>
                <td class="num"><?= e($costo) ?></td>
                <td class="num"><?= e($utilidad) ?></td>
                <td class="num"><span class="etiqueta <?= e($claseMargen) ?>"><?= e($margen) ?></span></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <p class="sutil">Margen menor a 15 % en amarillo y negativo en rojo: revisa el precio de venta o el costo de compra.</p>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../layout_fin.php'; ?>
