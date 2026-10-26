<?php
declare(strict_types=1);
/**
 * V: ficha de un producto.
 * @var Producto $producto
 * @var ?Stock $stock
 * @var Tienda $tienda
 * @var ?Categoria $categoria
 * @var ?Marca $marca
 * @var ?UnidadMedida $unidad
 */
$titulo = $producto->getNombreProducto();
require __DIR__ . '/../layout_inicio.php';

// Ejemplo de importe: 1 unidad, o 1.250 kg si es al peso
$cantidadEjemplo = $producto->esAlPeso() ? 1.25 : 1.0;
?>
<p><a href="/?ruta=productos&amp;tienda=<?= $tienda->getIdTienda() ?>">← Volver al catálogo</a></p>
<h1><?= e($producto->getNombreProducto()) ?></h1>

<table>
    <tr><th>Código de barras</th><td><?= e($producto->getCodigoBarras() ?? '(no tiene: se vende al peso)') ?></td></tr>
    <tr><th>Precio de venta</th><td><?= e(soles($producto->getPrecioVenta())) ?><?= $producto->esAlPeso() ? ' por kg' : '' ?></td></tr>
    <tr><th>Se vende</th><td><?= $producto->esAlPeso() ? 'Al peso (kg con hasta 3 decimales)' : 'Por unidad (cantidades enteras)' ?></td></tr>
    <tr><th>Importe de ejemplo</th>
        <td><?= e(number_format($cantidadEjemplo, $producto->esAlPeso() ? 3 : 0)) ?> × <?= e(soles($producto->getPrecioVenta())) ?>
            = <?= e(soles($producto->importe($cantidadEjemplo))) ?></td></tr>
    <tr><th>Categoría</th><td><?= e($categoria?->getNombreCategoria() ?? '-') ?></td></tr>
    <tr><th>Marca</th><td><?= e($marca?->getNombreMarca() ?? '-') ?><?= $marca?->esMarcaPropia() ? ' (marca propia)' : '' ?></td></tr>
    <tr><th>Unidad</th><td><?= e($unidad !== null ? $unidad->getNombreUnidad() . ' (' . $unidad->getAbreviatura() . ')' : '-') ?></td></tr>
    <tr><th>Estado</th><td><?= e($producto->getEstado()) ?></td></tr>
    <tr><th>Stock en <?= e($tienda->getNombreTienda()) ?></th>
        <td>
            <?php if ($stock === null): ?>
                Sin registro de stock
            <?php else: ?>
                <?= e(number_format($stock->getCantidadActual(), 3)) ?> (mínimo <?= e(number_format($stock->getStockMinimo(), 3)) ?>)
                <?php if ($stock->necesitaReponer()): ?>
                    <span class="etiqueta reponer">REPONER</span>
                <?php else: ?>
                    <span class="etiqueta ok">OK</span>
                <?php endif; ?>
            <?php endif; ?>
        </td></tr>
</table>
<p><a href="/?ruta=kardex&amp;tienda=<?= $tienda->getIdTienda() ?>&amp;producto=<?= $producto->getIdProducto() ?>">Ver kardex de este producto en <?= e($tienda->getNombreTienda()) ?></a></p>
<?php require __DIR__ . '/../layout_fin.php'; ?>
