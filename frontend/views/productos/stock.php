<?php
declare(strict_types=1);
/**
 * V: stock de cada producto en cada tienda.
 * @var array<int, Tienda> $tiendas
 * @var array<int, array{producto: Producto, stocks: array<int, ?Stock>}> $filas
 * @var int $porReponer
 */
$titulo = 'Stock por tienda';
require __DIR__ . '/../layout_inicio.php';
?>
<div class="cabecera">
    <div>
        <h1>Stock por tienda</h1>
        <p class="sutil"><?= $porReponer ?> casos para reponer. REPONER = la cantidad actual llegó al stock mínimo de esa tienda.</p>
    </div>
    <div class="acciones"><a class="boton secundario" href="/?ruta=stock_csv"><?= icono('descarga') ?>Descargar CSV</a></div>
</div>

<?php
$buscadorTabla = 'filas-stock';
$buscadorTexto = 'Buscar producto';
require __DIR__ . '/../parciales/buscador.php';
?>
<div class="tabla">
<table>
    <thead>
        <tr>
            <th scope="col">Producto</th>
            <?php foreach ($tiendas as $t): ?>
                <th scope="col" class="num"><?= e($t->getNombreTienda()) ?></th>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tbody id="filas-stock">
    <?php foreach ($filas as $fila): ?>
        <?php $p = $fila['producto']; ?>
        <tr>
            <th scope="row"><a class="fila-enlace" href="/?ruta=productos&amp;ver=<?= $p->getIdProducto() ?>"><?= e($p->getNombreProducto()) ?></a></th>
            <?php foreach ($fila['stocks'] as $s): ?>
                <td class="num">
                    <?php if ($s === null): ?>
                        <span class="sutil">Sin registro</span>
                    <?php else: ?>
                        <?= e(number_format($s->getCantidadActual(), $p->esAlPeso() ? 3 : 0)) ?>
                        <small>(mín. <?= e(number_format($s->getStockMinimo(), $p->esAlPeso() ? 3 : 0)) ?>)</small>
                        <?php if ($s->necesitaReponer()): ?>
                            <span class="etiqueta reponer">REPONER</span>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
            <?php endforeach; ?>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php require __DIR__ . '/../layout_fin.php'; ?>
