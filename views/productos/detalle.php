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
 * @var bool $puedeEditar       administrador: editar y descontinuar
 * @var bool $puedeFijarMinimo  administrador de esta tienda
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
<?php if ($puedeEditar || $puedeFijarMinimo): ?>
<div class="panel">
    <?php if ($puedeEditar): ?>
        <p>
            <a href="/?ruta=producto_form&amp;id=<?= $producto->getIdProducto() ?>">Editar nombre, código o precio</a>
        </p>
        <form method="post" action="/?ruta=producto_estado">
            <?= campoCsrf($csrf) ?>
            <input type="hidden" name="id" value="<?= $producto->getIdProducto() ?>">
            <?php if ($producto->estaActivo()): ?>
                <input type="hidden" name="estado" value="DESCONTINUADO">
                <button class="peligro" type="submit">Descontinuar (ya no se vende)</button>
            <?php else: ?>
                <input type="hidden" name="estado" value="ACTIVO">
                <button type="submit">Volver a activar</button>
            <?php endif; ?>
        </form>
    <?php endif; ?>
    <?php if ($puedeFijarMinimo): ?>
        <form class="formulario" method="post" action="/?ruta=stock_minimo">
            <?= campoCsrf($csrf) ?>
            <input type="hidden" name="id" value="<?= $producto->getIdProducto() ?>">
            <label for="minimo">Stock mínimo en <?= e($tienda->getNombreTienda()) ?> (<?= $producto->esAlPeso() ? 'kg' : 'unidades' ?>)</label>
            <input id="minimo" name="minimo" inputmode="decimal" size="10" required
                   value="<?= e($stock !== null ? number_format($stock->getStockMinimo(), $producto->esAlPeso() ? 3 : 0, '.', '') : '0') ?>">
            <button type="submit">Guardar mínimo</button>
        </form>
    <?php endif; ?>
</div>
<?php endif; ?>
<p><a href="/?ruta=kardex&amp;tienda=<?= $tienda->getIdTienda() ?>&amp;producto=<?= $producto->getIdProducto() ?>">Ver kardex de este producto en <?= e($tienda->getNombreTienda()) ?></a></p>
<?php require __DIR__ . '/../layout_fin.php'; ?>
