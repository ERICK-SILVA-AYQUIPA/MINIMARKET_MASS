<?php
declare(strict_types=1);
/**
 * V: stock de cada producto en las 3 tiendas.
 * @var array<int, Tienda> $tiendas
 * @var array<int, array{producto: Producto, stocks: array<int, ?Stock>}> $filas
 * @var int $porReponer
 */
$titulo = 'Stock por tienda';
require __DIR__ . '/../layout_inicio.php';
?>
<h1>Stock en las 3 tiendas</h1>
<p><?= $porReponer ?> casos para reponer. REPONER = la cantidad actual llegó al stock mínimo de esa tienda.</p>

<div class="tabla">
<table>
    <thead>
        <tr>
            <th>Producto</th>
            <?php foreach ($tiendas as $t): ?>
                <th><?= e($t->getNombreTienda()) ?></th>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($filas as $fila): ?>
        <?php $p = $fila['producto']; ?>
        <tr>
            <td><?= e($p->getNombreProducto()) ?></td>
            <?php foreach ($fila['stocks'] as $s): ?>
                <td class="num">
                    <?php if ($s === null): ?>
                        Sin registro
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
