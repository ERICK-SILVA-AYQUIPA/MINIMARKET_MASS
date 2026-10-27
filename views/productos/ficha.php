<?php
declare(strict_types=1);
/**
 * V: datos y acciones de un producto. La usan la pagina del producto y el panel lateral del catalogo.
 * @var Producto $producto
 * @var ?Stock $stock
 * @var Tienda $tienda
 * @var ?Categoria $categoria
 * @var ?Marca $marca
 * @var ?UnidadMedida $unidad
 * @var bool $puedeEditar       administrador: editar y descontinuar
 * @var bool $puedeFijarMinimo  administrador de esta tienda
 * @var ?string $csrf
 */
// Ejemplo de importe: 1 unidad, o 1.250 kg si es al peso
$cantidadEjemplo = $producto->esAlPeso() ? 1.25 : 1.0;
$decimales = $producto->esAlPeso() ? 3 : 0;
?>
<dl class="datos">
    <dt>Stock en <?= e($tienda->getNombreTienda()) ?></dt>
    <dd>
        <?php if ($stock === null): ?>
            Sin registro de stock
        <?php else: ?>
            <?= e(number_format($stock->getCantidadActual(), $decimales)) ?> (mínimo <?= e(number_format($stock->getStockMinimo(), $decimales)) ?>)
            <?php if ($stock->necesitaReponer()): ?>
                <span class="etiqueta reponer">REPONER</span>
            <?php else: ?>
                <span class="etiqueta ok">OK</span>
            <?php endif; ?>
        <?php endif; ?>
    </dd>
    <dt>Precio de venta</dt><dd><?= e(soles($producto->getPrecioVenta())) ?><?= $producto->esAlPeso() ? ' por kg' : '' ?></dd>
    <dt>Código de barras</dt><dd><?= e($producto->getCodigoBarras() ?? '(no tiene: se vende al peso)') ?></dd>
    <dt>Se vende</dt><dd><?= $producto->esAlPeso() ? 'Al peso (kg con hasta 3 decimales)' : 'Por unidad (cantidades enteras)' ?></dd>
    <dt>Importe de ejemplo</dt>
    <dd><?= e(number_format($cantidadEjemplo, $producto->esAlPeso() ? 3 : 0)) ?> × <?= e(soles($producto->getPrecioVenta())) ?>
        = <?= e(soles($producto->importe($cantidadEjemplo))) ?></dd>
    <dt>Categoría</dt><dd><?= e($categoria?->getNombreCategoria() ?? '-') ?></dd>
    <dt>Marca</dt><dd><?= e($marca?->getNombreMarca() ?? '-') ?><?= $marca?->esMarcaPropia() ? ' (marca propia)' : '' ?></dd>
    <dt>Unidad</dt><dd><?= e($unidad !== null ? $unidad->getNombreUnidad() . ' (' . $unidad->getAbreviatura() . ')' : '-') ?></dd>
    <dt>Estado</dt><dd><span class="etiqueta <?= $producto->estaActivo() ? 'ok' : 'neutra' ?>"><?= e($producto->getEstado()) ?></span></dd>
</dl>

<?php if ($puedeFijarMinimo): ?>
    <form class="formulario bloque" method="post" action="/?ruta=stock_minimo">
        <?= campoCsrf($csrf) ?>
        <input type="hidden" name="id" value="<?= $producto->getIdProducto() ?>">
        <label for="minimo-<?= $producto->getIdProducto() ?>">Stock mínimo en <?= e($tienda->getNombreTienda()) ?> (<?= $producto->esAlPeso() ? 'kg' : 'unidades' ?>)</label>
        <p class="ayuda">Cuando el stock llegue a este número, el producto sale como REPONER en el inicio.</p>
        <div class="en-linea">
            <input id="minimo-<?= $producto->getIdProducto() ?>" name="minimo" inputmode="decimal" size="10" required
                   value="<?= e($stock !== null ? number_format($stock->getStockMinimo(), $producto->esAlPeso() ? 3 : 0, '.', '') : '0') ?>">
            <button type="submit">Guardar mínimo</button>
        </div>
    </form>
<?php endif; ?>

<div class="acciones bloque">
    <a class="boton secundario" href="/?ruta=kardex&amp;tienda=<?= $tienda->getIdTienda() ?>&amp;producto=<?= $producto->getIdProducto() ?>"><?= icono('kardex') ?>Ver kardex en <?= e($tienda->getNombreTienda()) ?></a>
    <?php if ($puedeEditar): ?>
        <a class="boton secundario" href="/?ruta=producto_form&amp;id=<?= $producto->getIdProducto() ?>"><?= icono('editar') ?>Editar nombre, código o precio</a>
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
</div>
