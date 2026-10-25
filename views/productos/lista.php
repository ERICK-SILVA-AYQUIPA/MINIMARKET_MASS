<?php
declare(strict_types=1);
/**
 * V: tabla HTML del catalogo. Solo muestra; no calcula reglas ni busca datos.
 * @var array<int, array{producto: Producto, stock: ?Stock}> $filas
 * @var array<int, Tienda> $tiendas
 * @var Tienda $tienda
 * @var string $rutaSelector
 */
$titulo = 'Catálogo';
require __DIR__ . '/../layout_inicio.php';
?>
<h1>Catálogo de productos</h1>

<?php require __DIR__ . '/../parciales/selector_tienda.php'; ?>

<div class="tabla">
<table>
    <thead>
        <tr>
            <th>Código de barras</th>
            <th>Producto</th>
            <th>Precio</th>
            <th>Se vende</th>
            <th>Stock en <?= e($tienda->getNombreTienda()) ?></th>
            <th>Estado</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($filas as $fila): ?>
        <?php
        $p = $fila['producto'];
        $s = $fila['stock'];
        $enlace = $p->getCodigoBarras() !== null
            ? '/?ruta=producto&codigo=' . urlencode($p->getCodigoBarras())
            : '/?ruta=producto&id=' . $p->getIdProducto();
        $enlace .= '&tienda=' . $tienda->getIdTienda();
        ?>
        <tr>
            <td><?= e($p->getCodigoBarras() ?? '(al peso)') ?></td>
            <td><a href="<?= e($enlace) ?>"><?= e($p->getNombreProducto()) ?></a></td>
            <td class="num"><?= e(soles($p->getPrecioVenta())) ?><?= $p->esAlPeso() ? ' /kg' : '' ?></td>
            <td><?= $p->esAlPeso() ? 'Por kg' : 'Por unidad' ?></td>
            <td class="num">
                <?php if ($s === null): ?>
                    Sin registro
                <?php else: ?>
                    <?= e(number_format($s->getCantidadActual(), $p->esAlPeso() ? 3 : 0)) ?>
                    <?php if ($s->necesitaReponer()): ?>
                        <span class="etiqueta reponer">REPONER</span>
                    <?php endif; ?>
                <?php endif; ?>
            </td>
            <td><span class="etiqueta <?= $p->estaActivo() ? 'ok' : 'reponer' ?>"><?= e($p->getEstado()) ?></span></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<p><?= count($filas) ?> productos. REPONER = la cantidad actual llegó al stock mínimo de la tienda.</p>
<?php require __DIR__ . '/../layout_fin.php'; ?>
