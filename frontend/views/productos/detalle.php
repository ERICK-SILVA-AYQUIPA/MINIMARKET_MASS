<?php
declare(strict_types=1);
/**
 * V: pagina de un producto (la misma ficha que se abre en el panel lateral del catalogo).
 * @var Producto $producto
 * @var Tienda $tienda
 */
$titulo = $producto->getNombreProducto();
require __DIR__ . '/../layout_inicio.php';
?>
<a class="volver" href="/?ruta=productos&amp;tienda=<?= $tienda->getIdTienda() ?>"><?= icono('volver') ?>Volver al catálogo</a>
<div class="cabecera">
    <div>
        <h1><?= e($producto->getNombreProducto()) ?></h1>
        <p class="sutil">Producto <?= $producto->getIdProducto() ?> · viendo <?= e($tienda->getNombreTienda()) ?></p>
    </div>
</div>
<div class="panel">
    <?php require __DIR__ . '/ficha.php'; ?>
</div>
<?php require __DIR__ . '/../layout_fin.php'; ?>
